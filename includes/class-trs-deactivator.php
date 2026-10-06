<?php
/**
 * Fired during plugin deactivation.
 *
 * @package    TRS_Leads_Generator
 * @subpackage TRS_Leads_Generator/includes
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Fired during plugin deactivation.
 *
 * This class defines all code necessary to run during the plugin's deactivation.
 */
class TRS_Deactivator {

	/**
	 * Deactivation tasks.
	 *
	 * Note: We do NOT drop custom tables or delete CPT data to preserve customer leads.
	 *
	 * @since 1.0.0
	 */
	public static function deactivate() {
		flush_rewrite_rules();
	}
}
