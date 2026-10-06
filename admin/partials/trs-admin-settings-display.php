<?php
/**
 * View template for the plugin settings page.
 *
 * @package    TRS_Leads_Generator
 * @subpackage TRS_Leads_Generator/admin/partials
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}
?>
<div class="wrap trs-admin-wrap">
	<h1><?php echo esc_html( get_admin_page_title() ); ?></h1>

	<?php settings_errors(); ?>

	<div class="trs-admin-card">
		<form method="post" action="options.php">
			<?php
			settings_fields( 'trs_settings_group' );
			do_settings_sections( 'trs-settings' );
			submit_button( __( 'Guardar Ajustes', 'trs-leads-generator' ) );
			?>
		</form>
	</div>
</div>

