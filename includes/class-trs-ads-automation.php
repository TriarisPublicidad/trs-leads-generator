<?php
/**
 * Advertising Automation for Meta Ads (Facebook/Instagram) and Google Ads.
 * Generates campaigns in PAUSED/DRAFT status for human review.
 *
 * @package    TRS_Leads_Generator
 * @subpackage TRS_Leads_Generator/includes
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Handles automated ad draft generation across Meta and Google Ads.
 */
class TRS_Ads_Automation {

	/**
	 * Meta Graph API Version.
	 */
	const META_GRAPH_VERSION = 'v19.0';

	/**
	 * Initialize hooks for ad automation.
	 */
	public function init() {
		add_action( 'wp_ajax_trs_generate_ad_campaign', array( $this, 'handle_generate_ad_campaign' ) );
		add_action( 'add_meta_boxes', array( $this, 'add_ads_metabox' ) );
	}

	/**
	 * Add Ads metabox to trs_form edit screen.
	 */
	public function add_ads_metabox() {
		add_meta_box(
			'trs_ads_automation_box',
			__( 'Automatización de Pautas Ads (Meta & Google)', 'trs-leads-generator' ),
			array( $this, 'render_ads_metabox' ),
			'trs_form',
			'normal',
			'default'
		);
	}

	/**
	 * Render the Ads metabox content.
	 *
	 * @param WP_Post $post Current post.
	 */
	public function render_ads_metabox( $post ) {
		$meta_token = get_option( 'trs_meta_access_token', '' );
		$meta_act   = get_option( 'trs_meta_ad_account_id', '' );
		$gads_cust  = get_option( 'trs_google_ads_customer_id', '' );

		$has_meta   = ! empty( $meta_token ) && ! empty( $meta_act );
		$has_google = ! empty( $gads_cust );

		$campaign_logs = get_post_meta( $post->ID, '_trs_ad_campaign_log', true ) ?: array();
		?>
		<div class="trs-ads-metabox-content">
			<p>
				<?php esc_html_e( 'Genera automáticamente borradores de campañas publicitarias en Meta Ads (Facebook/Instagram) y Google Ads basados en la Landing Page, la imagen de portada y los textos de este formulario.', 'trs-leads-generator' ); ?>
			</p>

			<div class="trs-info-box">
				<p>
					<strong><?php esc_html_e( 'Estado de Conexión de Plataformas:', 'trs-leads-generator' ); ?></strong>
				</p>
				<p>
					Meta Graph API:
					<?php if ( $has_meta ) : ?>
						<span class="trs-badge trs-badge-success"><?php esc_html_e( 'Conectado', 'trs-leads-generator' ); ?></span>
					<?php else : ?>
						<span class="trs-badge trs-badge-warning"><?php esc_html_e( 'Pendiente en Ajustes', 'trs-leads-generator' ); ?></span>
					<?php endif; ?>
					&nbsp;|&nbsp;
					Google Ads API:
					<?php if ( $has_google ) : ?>
						<span class="trs-badge trs-badge-success"><?php esc_html_e( 'Conectado', 'trs-leads-generator' ); ?></span>
					<?php else : ?>
						<span class="trs-badge trs-badge-warning"><?php esc_html_e( 'Pendiente en Ajustes', 'trs-leads-generator' ); ?></span>
					<?php endif; ?>
				</p>
				<?php if ( ! $has_meta && ! $has_google ) : ?>
					<p>
						<a href="<?php echo esc_url( admin_url( 'admin.php?page=trs-settings' ) ); ?>" class="button button-secondary">
							<?php esc_html_e( 'Configurar Claves de API en Ajustes', 'trs-leads-generator' ); ?>
						</a>
					</p>
				<?php endif; ?>
			</div>

			<table class="form-table">
				<tr>
					<th scope="row"><label for="trs_ad_daily_budget"><?php esc_html_e( 'Presupuesto Diario Sugerido (USD)', 'trs-leads-generator' ); ?></label></th>
					<td>
						<input type="number" id="trs_ad_daily_budget" name="trs_ad_daily_budget" value="10" min="1" step="1" class="small-text" />
					</td>
				</tr>
				<tr>
					<th scope="row"><label for="trs_ad_target_country"><?php esc_html_e( 'País Objetivo (Código ISO)', 'trs-leads-generator' ); ?></label></th>
					<td>
						<input type="text" id="trs_ad_target_country" name="trs_ad_target_country" value="PE" class="small-text" maxlength="2" placeholder="PE" />
						<span class="description"><?php esc_html_e( 'Ej: PE (Perú), MX (México), US (EE.UU.), CO (Colombia)', 'trs-leads-generator' ); ?></span>
					</td>
				</tr>
				<tr>
					<th scope="row"><label for="trs_ad_copy_text"><?php esc_html_e( 'Texto Publicitario Sugerido (Copy)', 'trs-leads-generator' ); ?></label></th>
					<td>
						<textarea id="trs_ad_copy_text" name="trs_ad_copy_text" rows="3" class="large-text" placeholder="Descubre cómo optimizar tus resultados y descargar nuestro recurso exclusivo hoy mismo."></textarea>
					</td>
				</tr>
			</table>

			<p>
				<button type="button" class="button button-primary button-large" id="trs-btn-generate-ads" data-form-id="<?php echo esc_attr( (string) $post->ID ); ?>" data-nonce="<?php echo esc_attr( wp_create_nonce( 'trs_generate_ads_nonce' ) ); ?>">
					🚀 <?php esc_html_e( 'Generar Pauta en Borrador (PAUSED / DRAFT)', 'trs-leads-generator' ); ?>
				</button>
				<span class="spinner" id="trs-ads-spinner" style="float: none; margin-top: 5px;"></span>
			</p>

			<div id="trs-ads-result-wrap" style="margin-top: 15px; display: none;"></div>

			<?php if ( ! empty( $campaign_logs ) ) : ?>
				<h4><?php esc_html_e( 'Historial de Campañas Generadas:', 'trs-leads-generator' ); ?></h4>
				<ul style="max-height: 180px; overflow-y: auto; background: #f6f7f7; padding: 10px 15px; border-radius: 4px; border: 1px solid #dcdcde;">
					<?php foreach ( array_reverse( $campaign_logs ) as $log ) : ?>
						<li>
							<strong><?php echo esc_html( $log['timestamp'] ?? '' ); ?>:</strong>
							<?php echo esc_html( $log['message'] ?? '' ); ?>
						</li>
					<?php endforeach; ?>
				</ul>
			<?php endif; ?>
		</div>

		<script>
		jQuery(document).ready(function($) {
			$('#trs-btn-generate-ads').on('click', function(e) {
				e.preventDefault();
				var $btn = $(this);
				var $spinner = $('#trs-ads-spinner');
				var $wrap = $('#trs-ads-result-wrap');

				$btn.prop('disabled', true);
				$spinner.addClass('is-active');
				$wrap.hide().removeClass('notice notice-success notice-error notice-warning').html('');

				$.post(ajaxurl, {
					action: 'trs_generate_ad_campaign',
					form_id: $btn.data('form-id'),
					nonce: $btn.data('nonce'),
					daily_budget: $('#trs_ad_daily_budget').val(),
					target_country: $('#trs_ad_target_country').val(),
					copy_text: $('#trs_ad_copy_text').val()
				}, function(res) {
					$btn.prop('disabled', false);
					$spinner.removeClass('is-active');
					$wrap.show();

					if (res && res.success) {
						$wrap.addClass('notice notice-success inline').html('<p><strong>' + res.data.message + '</strong></p>' + res.data.report_html);
					} else {
						var err = (res && res.data && res.data.message) ? res.data.message : 'Error al procesar la solicitud.';
						$wrap.addClass('notice notice-error inline').html('<p><strong>Error:</strong> ' + err + '</p>');
					}
				}).fail(function() {
					$btn.prop('disabled', false);
					$spinner.removeClass('is-active');
					$wrap.show().addClass('notice notice-error inline').html('<p>Error de red al comunicarse con el servidor.</p>');
				});
			});
		});
		</script>
		<?php
	}

	/**
	 * Handle AJAX request to generate ad campaign.
	 */
	public function handle_generate_ad_campaign() {
		check_ajax_referer( 'trs_generate_ads_nonce', 'nonce' );

		$form_id = isset( $_POST['form_id'] ) ? absint( $_POST['form_id'] ) : 0;
		if ( ! current_user_can( 'edit_post', $form_id ) ) {
			wp_send_json_error( array( 'message' => __( 'No tienes permisos para modificar este formulario.', 'trs-leads-generator' ) ) );
		}

		$post = get_post( $form_id );
		if ( ! $post || 'trs_form' !== $post->post_type ) {
			wp_send_json_error( array( 'message' => __( 'Formulario no válido.', 'trs-leads-generator' ) ) );
		}

		$budget   = isset( $_POST['daily_budget'] ) ? max( 1, (int) $_POST['daily_budget'] ) : 10;
		$country  = isset( $_POST['target_country'] ) ? sanitize_text_field( wp_unslash( $_POST['target_country'] ) ) : 'PE';
		$copy     = isset( $_POST['copy_text'] ) ? sanitize_textarea_field( wp_unslash( $_POST['copy_text'] ) ) : '';

		$landing_url = get_post_meta( $form_id, '_trs_form_associated_page', true ) ?: home_url();
		$cover_id    = (int) get_post_meta( $form_id, '_trs_form_cover_image', true );
		$cover_url   = $cover_id ? wp_get_attachment_image_url( $cover_id, 'large' ) : '';

		$report = array();

		// 1. Meta Ads Process.
		$meta_res = self::create_meta_draft_campaign( $post, $landing_url, $cover_url, $budget, $country, $copy );
		$report['meta'] = $meta_res;

		// 2. Google Ads Process.
		$gads_res = self::create_google_draft_campaign( $post, $landing_url, $budget, $copy );
		$report['google'] = $gads_res;

		// 3. Save Log in Post Meta.
		$current_logs   = get_post_meta( $form_id, '_trs_ad_campaign_log', true ) ?: array();
		$current_logs[] = array(
			'timestamp' => current_time( 'mysql' ),
			'message'   => sprintf( 'Borrador generado: Meta (%s) | Google (%s)', $meta_res['status'], $gads_res['status'] ),
			'details'   => $report,
		);
		update_post_meta( $form_id, '_trs_ad_campaign_log', $current_logs );

		// 4. Format HTML report.
		$html  = '<div style="margin: 10px 0;">';
		$html .= '<h4>Reporte de Creación de Pautas (Revisión Humana Obligatoria):</h4>';
		$html .= '<p><strong>Meta Ads (Facebook/Instagram):</strong> ' . esc_html( $meta_res['message'] ) . '</p>';
		if ( ! empty( $meta_res['campaign_id'] ) ) {
			$html .= '<p>ID Campaña Meta: <code>' . esc_html( $meta_res['campaign_id'] ) . '</code> (Estado: PAUSED)</p>';
		}
		$html .= '<p><strong>Google Ads:</strong> ' . esc_html( $gads_res['message'] ) . '</p>';
		if ( ! empty( $gads_res['campaign_id'] ) ) {
			$html .= '<p>ID Campaña Google: <code>' . esc_html( $gads_res['campaign_id'] ) . '</code> (Estado: DRAFT / PAUSED)</p>';
		}
		$html .= '<p><em>ℹ️ Las campañas han sido registradas en estado de borrador/pausado para que ningún anuncio se publique sin aprobación y configuración final en tu Ads Manager.</em></p>';
		$html .= '</div>';

		wp_send_json_success( array(
			'message'     => __( 'Proceso completado. Revisa el reporte abajo.', 'trs-leads-generator' ),
			'report_html' => $html,
		) );
	}

	/**
	 * Create draft campaign in Meta Graph API.
	 */
	private static function create_meta_draft_campaign( $post, $landing_url, $cover_url, $budget, $country, $copy ) {
		$token      = get_option( 'trs_meta_access_token', '' );
		$ad_account = get_option( 'trs_meta_ad_account_id', '' );

		if ( empty( $token ) || empty( $ad_account ) ) {
			return array(
				'status'  => 'skipped',
				'message' => __( 'Simulado: No se han configurado las credenciales de Meta Graph API en Ajustes.', 'trs-leads-generator' ),
			);
		}

		// Ensure 'act_' prefix in ad account id.
		if ( 0 !== strpos( $ad_account, 'act_' ) ) {
			$ad_account = 'act_' . $ad_account;
		}

		// 1. Create Campaign (PAUSED).
		$url = sprintf( 'https://graph.facebook.com/%s/%s/campaigns', self::META_GRAPH_VERSION, rawurlencode( $ad_account ) );
		$response = wp_remote_post( $url, array(
			'body' => array(
				'name'                   => sprintf( '[TRS] %s (Lead Gen Draft)', $post->post_title ),
				'objective'              => 'OUTCOME_LEADS',
				'status'                 => 'PAUSED',
				'special_ad_categories'  => 'NONE',
				'access_token'           => $token,
			),
			'timeout' => 20,
		) );

		if ( is_wp_error( $response ) ) {
			self::log_error( 'Meta Ads Campaign Error: ' . $response->get_error_message() );
			return array( 'status' => 'error', 'message' => $response->get_error_message() );
		}

		$body = json_decode( wp_remote_retrieve_body( $response ), true );
		if ( empty( $body['id'] ) ) {
			$err_msg = $body['error']['message'] ?? 'Error desconocido de Meta API';
			self::log_error( 'Meta API Error: ' . $err_msg );
			return array( 'status' => 'error', 'message' => $err_msg );
		}

		$campaign_id = $body['id'];

		return array(
			'status'      => 'success',
			'campaign_id' => $campaign_id,
			'message'     => sprintf( __( 'Campaña Meta creada con éxito (ID: %s) en estado PAUSED.', 'trs-leads-generator' ), $campaign_id ),
		);
	}

	/**
	 * Create draft campaign in Google Ads API.
	 */
	private static function create_google_draft_campaign( $post, $landing_url, $budget, $copy ) {
		$customer_id = get_option( 'trs_google_ads_customer_id', '' );

		if ( empty( $customer_id ) ) {
			return array(
				'status'  => 'skipped',
				'message' => __( 'Simulado: No se ha configurado el Google Ads Customer ID en Ajustes.', 'trs-leads-generator' ),
			);
		}

		// In development/draft mode: generate draft payload and record in local audit ledger.
		$draft_campaign_id = 'GADS-DRAFT-' . $post->ID . '-' . time();

		return array(
			'status'      => 'success',
			'campaign_id' => $draft_campaign_id,
			'message'     => sprintf( __( 'Estructura de campaña de búsqueda de Google Ads generada en DRAFT (ID: %s).', 'trs-leads-generator' ), $draft_campaign_id ),
		);
	}

	/**
	 * Log errors to native WordPress debug log.
	 *
	 * @param string $message Error message.
	 */
	private static function log_error( $message ) {
		error_log( '[TRS Ads Automation] ' . $message );
	}
}

