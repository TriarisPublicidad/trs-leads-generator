<?php
/**
 * View template for the plugin main dashboard page.
 *
 * @package    TRS_Leads_Generator
 * @subpackage TRS_Leads_Generator/admin/partials
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

global $wpdb;

// Calculate basic metrics for the admin.
$forms_count = wp_count_posts( 'trs_form' );
$total_forms = isset( $forms_count->publish ) ? (int) $forms_count->publish : 0;
$total_forms += isset( $forms_count->draft ) ? (int) $forms_count->draft : 0;

$leads_table = $wpdb->prefix . 'trs_leads';
$total_leads = 0;
$table_exists = $wpdb->get_var( $wpdb->prepare( 'SHOW TABLES LIKE %s', $leads_table ) );

if ( $table_exists === $leads_table ) {
	$total_leads = (int) $wpdb->get_var( "SELECT COUNT(id) FROM {$leads_table}" );
}

$sheets_creds = get_option( 'trs_google_sheets_credentials', '' );
$sheets_configured = ! empty( $sheets_creds );
?>
<div class="wrap trs-admin-wrap">
	<h1><?php echo esc_html( get_admin_page_title() ); ?></h1>

	<div class="trs-stat-grid">
		<div class="trs-stat-card">
			<div class="trs-stat-number"><?php echo esc_html( (string) $total_forms ); ?></div>
			<div class="trs-stat-label"><?php esc_html_e( 'Formularios Totales', 'trs-leads-generator' ); ?></div>
		</div>
		<div class="trs-stat-card">
			<div class="trs-stat-number"><?php echo esc_html( (string) $total_leads ); ?></div>
			<div class="trs-stat-label"><?php esc_html_e( 'Leads Capturados', 'trs-leads-generator' ); ?></div>
		</div>
		<div class="trs-stat-card">
			<div class="trs-stat-number">
				<?php if ( $sheets_configured ) : ?>
					<span class="trs-badge trs-badge-success"><?php esc_html_e( 'Activo', 'trs-leads-generator' ); ?></span>
				<?php else : ?>
					<span class="trs-badge trs-badge-warning"><?php esc_html_e( 'Pendiente', 'trs-leads-generator' ); ?></span>
				<?php endif; ?>
			</div>
			<div class="trs-stat-label"><?php esc_html_e( 'Google Sheets API', 'trs-leads-generator' ); ?></div>
		</div>
	</div>

	<div class="trs-admin-card">
		<h2><?php esc_html_e( 'Bienvenido a TRS Leads Generator', 'trs-leads-generator' ); ?></h2>
		<p><?php esc_html_e( 'Sistema nativo de captación, gestión y distribución de leads de alto rendimiento para WordPress.', 'trs-leads-generator' ); ?></p>
		
		<div class="trs-info-box">
			<p><strong><?php esc_html_e( 'Acciones Rápidas:', 'trs-leads-generator' ); ?></strong></p>
			<p>
				<a href="<?php echo esc_url( admin_url( 'post-new.php?post_type=trs_form' ) ); ?>" class="button button-primary">
					<?php esc_html_e( 'Crear Nuevo Formulario', 'trs-leads-generator' ); ?>
				</a>
				<a href="<?php echo esc_url( admin_url( 'edit.php?post_type=trs_form' ) ); ?>" class="button button-secondary">
					<?php esc_html_e( 'Ver Formularios Creados', 'trs-leads-generator' ); ?>
				</a>
				<a href="<?php echo esc_url( admin_url( 'admin.php?page=trs-settings' ) ); ?>" class="button button-secondary">
					<?php esc_html_e( 'Configurar Google Sheets', 'trs-leads-generator' ); ?>
				</a>
			</p>
		</div>
	</div>
</div>

