<?php
/**
 * The admin-specific functionality of the plugin.
 *
 * @package    TRS_Leads_Generator
 * @subpackage TRS_Leads_Generator/admin
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Handles admin menu, Settings API, and admin assets.
 */
class TRS_Admin {

	/**
	 * Register the stylesheets for the admin area.
	 *
	 * @since 1.0.0
	 * @param string $hook_suffix The current admin page.
	 */
	public function enqueue_styles( $hook_suffix ) {
		// Only load assets on TRS Leads Generator screens.
		if ( false === strpos( $hook_suffix, 'trs' ) ) {
			return;
		}

		wp_enqueue_style(
			'trs-admin-css',
			TRS_PLUGIN_URL . 'admin/css/trs-admin.css',
			array(),
			TRS_VERSION,
			'all'
		);
	}

	/**
	 * Register admin menu and submenus.
	 *
	 * @since 1.0.0
	 */
	public function register_admin_menu() {
		// Top level menu: Lead Generation.
		add_menu_page(
			__( 'Lead Generation', 'trs-leads-generator' ),
			__( 'Lead Generation', 'trs-leads-generator' ),
			'manage_options',
			'trs-leads-generator',
			array( $this, 'render_dashboard_page' ),
			'dashicons-funnel',
			30
		);

		// Submenu: Dashboard (renaming top-level default sub-item).
		add_submenu_page(
			'trs-leads-generator',
			__( 'Dashboard Lead Generation', 'trs-leads-generator' ),
			__( 'Dashboard', 'trs-leads-generator' ),
			'manage_options',
			'trs-leads-generator',
			array( $this, 'render_dashboard_page' )
		);

		// Submenu: Ajustes.
		add_submenu_page(
			'trs-leads-generator',
			__( 'Ajustes de Lead Generation', 'trs-leads-generator' ),
			__( 'Ajustes', 'trs-leads-generator' ),
			'manage_options',
			'trs-settings',
			array( $this, 'render_settings_page' )
		);
	}

	/**
	 * Register Settings, Sections, and Fields using the WordPress Settings API.
	 *
	 * @since 1.0.0
	 */
	public function register_settings() {
		// 1. Register Setting for Google Sheets JSON Credentials.
		register_setting(
			'trs_settings_group',
			'trs_google_sheets_credentials',
			array(
				'type'              => 'string',
				'description'       => __( 'Credenciales JSON de Google Service Account', 'trs-leads-generator' ),
				'sanitize_callback' => array( $this, 'sanitize_google_credentials' ),
				'show_in_rest'      => false,
				'default'           => '',
			)
		);

		// 2. Register Setting for Spreadsheet ID.
		register_setting(
			'trs_settings_group',
			'trs_google_sheet_id',
			array(
				'type'              => 'string',
				'description'       => __( 'ID de la Hoja de Google Sheets', 'trs-leads-generator' ),
				'sanitize_callback' => 'sanitize_text_field',
				'show_in_rest'      => false,
				'default'           => '',
			)
		);

		// 3. Register Setting for Tab/Sheet Name.
		register_setting(
			'trs_settings_group',
			'trs_google_sheet_tab_name',
			array(
				'type'              => 'string',
				'description'       => __( 'Nombre de la pestaña en Google Sheets', 'trs-leads-generator' ),
				'sanitize_callback' => 'sanitize_text_field',
				'show_in_rest'      => false,
				'default'           => 'Leads',
			)
		);

		// Settings Section.
		add_settings_section(
			'trs_google_sheets_section',
			__( 'Configuración de Google Sheets (Service Account)', 'trs-leads-generator' ),
			array( $this, 'render_section_description' ),
			'trs-settings'
		);

		// Fields.
		add_settings_field(
			'trs_google_sheets_credentials',
			__( 'Credenciales JSON (Service Account)', 'trs-leads-generator' ),
			array( $this, 'render_credentials_field' ),
			'trs-settings',
			'trs_google_sheets_section'
		);

		add_settings_field(
			'trs_google_sheet_id',
			__( 'ID de la Hoja (Spreadsheet ID)', 'trs-leads-generator' ),
			array( $this, 'render_sheet_id_field' ),
			'trs-settings',
			'trs_google_sheets_section'
		);

		add_settings_field(
			'trs_google_sheet_tab_name',
			__( 'Nombre de la Pestaña / Hoja', 'trs-leads-generator' ),
			array( $this, 'render_tab_name_field' ),
			'trs-settings',
			'trs_google_sheets_section'
		);
	}

	/**
	 * Sanitize and validate Google Sheets JSON Service Account credentials.
	 *
	 * @since 1.0.0
	 * @param string $input Raw JSON input from textarea.
	 * @return string Sanitized JSON string or previous valid option on error.
	 */
	public function sanitize_google_credentials( $input ) {
		$trimmed = trim( (string) $input );

		// If user cleared the input, allow saving empty string.
		if ( empty( $trimmed ) ) {
			return '';
		}

		$decoded = json_decode( $trimmed, true );

		if ( json_last_error() !== JSON_ERROR_NONE || ! is_array( $decoded ) ) {
			add_settings_error(
				'trs_google_sheets_credentials',
				'invalid_json_format',
				__( 'El texto ingresado no es un JSON válido. Revisa la sintaxis e inténtalo de nuevo.', 'trs-leads-generator' ),
				'error'
			);
			return get_option( 'trs_google_sheets_credentials', '' );
		}

		// Validate essential Service Account JSON keys.
		$required_keys = array( 'type', 'project_id', 'private_key', 'client_email' );
		foreach ( $required_keys as $key ) {
			if ( empty( $decoded[ $key ] ) ) {
				add_settings_error(
					'trs_google_sheets_credentials',
					'missing_credentials_key',
					sprintf(
						/* translators: %s: key name */
						__( 'El JSON no parece ser una cuenta de servicio válida. Falta la clave requerida: "%s".', 'trs-leads-generator' ),
						esc_html( $key )
					),
					'error'
				);
				return get_option( 'trs_google_sheets_credentials', '' );
			}
		}

		if ( 'service_account' !== $decoded['type'] ) {
			add_settings_error(
				'trs_google_sheets_credentials',
				'invalid_account_type',
				__( 'El tipo de cuenta en el JSON debe ser "service_account".', 'trs-leads-generator' ),
				'error'
			);
			return get_option( 'trs_google_sheets_credentials', '' );
		}

		add_settings_error(
			'trs_google_sheets_credentials',
			'valid_json_credentials',
			__( 'Credenciales de Google Service Account validadas correctamente.', 'trs-leads-generator' ),
			'updated'
		);

		return (string) wp_json_encode( $decoded, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES );
	}

	/**
	 * Section description callback.
	 *
	 * @since 1.0.0
	 */
	public function render_section_description() {
		echo '<p>' . esc_html__( 'Configura la integración de Google Sheets mediante una Service Account para sincronizar en tiempo real los leads capturados en la Fase 3.', 'trs-leads-generator' ) . '</p>';
	}

	/**
	 * Render JSON credentials textarea field.
	 *
	 * @since 1.0.0
	 */
	public function render_credentials_field() {
		$value = get_option( 'trs_google_sheets_credentials', '' );
		$decoded = ! empty( $value ) ? json_decode( $value, true ) : null;
		?>
		<textarea
			name="trs_google_sheets_credentials"
			id="trs_google_sheets_credentials"
			rows="10"
			cols="70"
			class="large-text code"
			placeholder='{
  "type": "service_account",
  "project_id": "mi-proyecto",
  "private_key": "-----BEGIN PRIVATE KEY-----\n...\n-----END PRIVATE KEY-----\n",
  "client_email": "leads-service@mi-proyecto.iam.gserviceaccount.com"
}'><?php echo esc_textarea( $value ); ?></textarea>

		<?php if ( is_array( $decoded ) && ! empty( $decoded['client_email'] ) ) : ?>
			<div class="trs-info-box">
				<p>
					<strong><?php esc_html_e( 'Estado:', 'trs-leads-generator' ); ?></strong>
					<span class="trs-badge trs-badge-success"><?php esc_html_e( 'Credenciales cargadas', 'trs-leads-generator' ); ?></span>
				</p>
				<p>
					<strong><?php esc_html_e( 'Email de la Service Account:', 'trs-leads-generator' ); ?></strong>
					<code><?php echo esc_html( $decoded['client_email'] ); ?></code>
				</p>
				<p>
					<strong><?php esc_html_e( 'Proyecto GCP:', 'trs-leads-generator' ); ?></strong>
					<code><?php echo esc_html( $decoded['project_id'] ?? 'N/A' ); ?></code>
				</p>
				<p>
					<em><?php esc_html_e( '⚠️ Importante: Asegúrate de compartir tu Google Sheet con este correo con permiso de "Editor".', 'trs-leads-generator' ); ?></em>
				</p>
			</div>
		<?php else : ?>
			<p class="description">
				<?php esc_html_e( 'Pega aquí el contenido completo del archivo JSON descargado desde Google Cloud Console (IAM & Admin > Service Accounts > Keys).', 'trs-leads-generator' ); ?>
			</p>
		<?php endif; ?>
		<?php
	}

	/**
	 * Render Spreadsheet ID text input.
	 *
	 * @since 1.0.0
	 */
	public function render_sheet_id_field() {
		$value = get_option( 'trs_google_sheet_id', '' );
		?>
		<input
			type="text"
			name="trs_google_sheet_id"
			id="trs_google_sheet_id"
			value="<?php echo esc_attr( $value ); ?>"
			class="regular-text"
			placeholder="1BxiMVs0XRA5nFMdKvBdBZjgmUUqptlbs74OgvE2upms"
		/>
		<p class="description">
			<?php esc_html_e( 'El ID se encuentra en la URL de Google Sheets: https://docs.google.com/spreadsheets/d/[ID_AQUI]/edit', 'trs-leads-generator' ); ?>
		</p>
		<?php
	}

	/**
	 * Render Tab Name text input.
	 *
	 * @since 1.0.0
	 */
	public function render_tab_name_field() {
		$value = get_option( 'trs_google_sheet_tab_name', 'Leads' );
		?>
		<input
			type="text"
			name="trs_google_sheet_tab_name"
			id="trs_google_sheet_tab_name"
			value="<?php echo esc_attr( $value ); ?>"
			class="regular-text"
			placeholder="Leads"
		/>
		<p class="description">
			<?php esc_html_e( 'Nombre de la pestaña/hoja dentro del documento donde se insertarán las filas (por defecto: Leads).', 'trs-leads-generator' ); ?>
		</p>
		<?php
	}

	/**
	 * Render plugin dashboard page.
	 *
	 * @since 1.0.0
	 */
	public function render_dashboard_page() {
		if ( ! current_user_can( 'manage_options' ) ) {
			return;
		}
		require_once TRS_PLUGIN_DIR . 'admin/partials/trs-admin-dashboard-display.php';
	}

	/**
	 * Render plugin settings page.
	 *
	 * @since 1.0.0
	 */
	public function render_settings_page() {
		if ( ! current_user_can( 'manage_options' ) ) {
			return;
		}
		require_once TRS_PLUGIN_DIR . 'admin/partials/trs-admin-settings-display.php';
	}
}
