<?php
/**
 * Plugin Name:       TRS Leads Generator
 * Plugin URI:        https://github.com/TriarisPublicidad/trs-leads-generator
 * Description:       Plugin nativo de WordPress para captación, gestión y distribución inteligente de leads con CPT, almacenamiento SQL relacional dedicado y panel de administración.
 * Version:           1.0.0
 * Author:            Triaris Publicidad
 * Author URI:        https://triaris.pe
 * License:           GPL-2.0+
 * License URI:       http://www.gnu.org/licenses/gpl-2.0.txt
 * Text Domain:       trs-leads-generator
 * Domain Path:       /languages
 * Requires at least: 6.0
 * Requires PHP:      8.0
 *
 * @package TRS_Leads_Generator
 */

// If this file is called directly, abort.
if ( ! defined( 'WPINC' ) ) {
	die;
}

// Current plugin version.
define( 'TRS_VERSION', '1.0.0' );

// Plugin directory and URL paths.
define( 'TRS_PLUGIN_DIR', plugin_dir_path( __FILE__ ) );
define( 'TRS_PLUGIN_URL', plugin_dir_url( __FILE__ ) );
define( 'TRS_PLUGIN_BASENAME', plugin_basename( __FILE__ ) );

/**
 * The code that runs during plugin activation.
 */
function trs_activate_leads_generator() {
	require_once TRS_PLUGIN_DIR . 'includes/class-trs-activator.php';
	TRS_Activator::activate();
}

/**
 * The code that runs during plugin deactivation.
 */
function trs_deactivate_leads_generator() {
	require_once TRS_PLUGIN_DIR . 'includes/class-trs-deactivator.php';
	TRS_Deactivator::deactivate();
}

register_activation_hook( __FILE__, 'trs_activate_leads_generator' );
register_deactivation_hook( __FILE__, 'trs_deactivate_leads_generator' );

/**
 * The core plugin class that is used to define internationalization,
 * admin-specific hooks, and public-facing site hooks.
 */
require_once TRS_PLUGIN_DIR . 'includes/class-trs-leads-generator.php';

/**
 * Begins execution of the plugin.
 */
function trs_run_leads_generator() {
	$plugin = TRS_Leads_Generator::get_instance();
	$plugin->run();
}
trs_run_leads_generator();

