<?php
/**
 * Google Sheets Service Account Integration.
 *
 * @package    TRS_Leads_Generator
 * @subpackage TRS_Leads_Generator/includes
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Handles communication with Google Sheets API v4 using Service Account JWT.
 */
class TRS_Google_Sheets {

	/**
	 * Transient key for caching Google OAuth access token.
	 */
	const TOKEN_TRANSIENT_KEY = 'trs_google_sheets_access_token';

	/**
	 * Send lead data to configured Google Sheet.
	 *
	 * @param array $lead_data Associative array of lead properties.
	 * @return bool|WP_Error True on success or WP_Error on failure.
	 */
	public static function sync_lead( array $lead_data ) {
		$credentials_json = get_option( 'trs_google_sheets_credentials', '' );
		$spreadsheet_id   = get_option( 'trs_google_sheet_id', '' );
		$tab_name         = get_option( 'trs_google_sheet_tab_name', 'Leads' );

		if ( empty( $credentials_json ) || empty( $spreadsheet_id ) ) {
			// Integration not configured yet, skip silently or log info.
			return true;
		}

		$credentials = json_decode( $credentials_json, true );
		if ( ! is_array( $credentials ) || empty( $credentials['client_email'] ) || empty( $credentials['private_key'] ) ) {
			return new WP_Error( 'trs_invalid_creds', __( 'Credenciales de Google Sheets inválidas o incompletas.', 'trs-leads-generator' ) );
		}

		// 1. Get OAuth Access Token.
		$access_token = self::get_access_token( $credentials );
		if ( is_wp_error( $access_token ) ) {
			error_log( '[TRS Leads Generator] Error al obtener token de Google Sheets: ' . $access_token->get_error_message() );
			return $access_token;
		}

		// 2. Prepare Row Data to append.
		$row = array(
			$lead_data['id'] ?? '',
			$lead_data['form_id'] ?? '',
			$lead_data['email'] ?? '',
			$lead_data['first_name'] ?? '',
			$lead_data['last_name'] ?? '',
			$lead_data['utm_source'] ?? '',
			$lead_data['utm_medium'] ?? '',
			$lead_data['utm_campaign'] ?? '',
			$lead_data['utm_content'] ?? '',
			$lead_data['utm_term'] ?? '',
			$lead_data['created_at'] ?? current_time( 'mysql' ),
		);

		// 3. Send append request to Google Sheets API v4.
		$range = rawurlencode( $tab_name . '!A:K' );
		$api_url = sprintf(
			'https://sheets.googleapis.com/v4/spreadsheets/%s/values/%s:append?valueInputOption=USER_ENTERED&insertDataOption=INSERT_ROWS',
			rawurlencode( $spreadsheet_id ),
			$range
		);

		$payload = array(
			'range'  => $tab_name . '!A:K',
			'values' => array( $row ),
		);

		$response = wp_remote_post(
			$api_url,
			array(
				'headers' => array(
					'Authorization' => 'Bearer ' . $access_token,
					'Content-Type'  => 'application/json',
				),
				'body'    => (string) wp_json_encode( $payload ),
				'timeout' => 15,
			)
		);

		if ( is_wp_error( $response ) ) {
			error_log( '[TRS Leads Generator] Error al sincronizar fila con Google Sheets: ' . $response->get_error_message() );
			return $response;
		}

		$response_code = wp_remote_retrieve_response_code( $response );
		$response_body = wp_remote_retrieve_body( $response );

		if ( $response_code < 200 || $response_code >= 300 ) {
			error_log( '[TRS Leads Generator] Google Sheets API devolvió código ' . $response_code . ': ' . $response_body );
			return new WP_Error( 'trs_sheets_api_error', 'Google Sheets API error: ' . $response_body );
		}

		return true;
	}

	/**
	 * Retrieve OAuth2 access token using Service Account RS256 JWT.
	 *
	 * Uses cached transient if available.
	 *
	 * @param array $credentials Service Account JSON parsed array.
	 * @return string|WP_Error Access token or WP_Error.
	 */
	private static function get_access_token( array $credentials ) {
		$cached_token = get_transient( self::TOKEN_TRANSIENT_KEY );
		if ( ! empty( $cached_token ) ) {
			return $cached_token;
		}

		$now = time();
		$header = array(
			'alg' => 'RS256',
			'typ' => 'JWT',
		);

		$claims = array(
			'iss'   => $credentials['client_email'],
			'scope' => 'https://www.googleapis.com/auth/spreadsheets',
			'aud'   => 'https://oauth2.googleapis.com/token',
			'exp'   => $now + 3600,
			'iat'   => $now,
		);

		$base64_header = self::base64url_encode( (string) wp_json_encode( $header ) );
		$base64_claims = self::base64url_encode( (string) wp_json_encode( $claims ) );
		$payload       = $base64_header . '.' . $base64_claims;

		$private_key = $credentials['private_key'];
		$signature   = '';

		$signed = openssl_sign( $payload, $signature, $private_key, OPENSSL_ALGO_SHA256 );
		if ( ! $signed ) {
			return new WP_Error( 'trs_jwt_signing_error', __( 'Fallo al firmar JWT con la clave privada de la Service Account.', 'trs-leads-generator' ) );
		}

		$jwt = $payload . '.' . self::base64url_encode( $signature );

		$token_endpoint = $credentials['token_uri'] ?? 'https://oauth2.googleapis.com/token';

		$response = wp_remote_post(
			$token_endpoint,
			array(
				'headers' => array(
					'Content-Type' => 'application/x-www-form-urlencoded',
				),
				'body'    => array(
					'grant_type' => 'urn:ietf:params:oauth:grant-type:jwt-bearer',
					'assertion'  => $jwt,
				),
				'timeout' => 15,
			)
		);

		if ( is_wp_error( $response ) ) {
			return $response;
		}

		$body = json_decode( wp_remote_retrieve_body( $response ), true );
		if ( empty( $body['access_token'] ) ) {
			return new WP_Error(
				'trs_token_request_failed',
				sprintf(
					/* translators: %s: error description */
					__( 'No se pudo obtener el token de acceso de Google: %s', 'trs-leads-generator' ),
					$body['error_description'] ?? 'Error desconocido'
				)
			);
		}

		$token      = $body['access_token'];
		$expires_in = isset( $body['expires_in'] ) ? (int) $body['expires_in'] - 120 : 3400;

		set_transient( self::TOKEN_TRANSIENT_KEY, $token, max( 60, $expires_in ) );

		return $token;
	}

	/**
	 * Base64 URL safe encoding.
	 *
	 * @param string $data Raw string data.
	 * @return string Encoded string.
	 */
	private static function base64url_encode( $data ) {
		return rtrim( strtr( base64_encode( $data ), '+/', '-_' ), '=' );
	}
}
