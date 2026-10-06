<?php
/**
 * The core plugin class.
 *
 * @package    TRS_Leads_Generator
 * @subpackage TRS_Leads_Generator/includes
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * The core plugin class.
 *
 * This is used to define internationalization, admin-specific hooks,
 * and public-facing site hooks.
 */
class TRS_Leads_Generator {

	/**
	 * Single instance of the class.
	 *
	 * @var TRS_Leads_Generator|null
	 */
	private static $instance = null;

	/**
	 * The admin handler instance.
	 *
	 * @var TRS_Admin
	 */
	private $admin;

	/**
	 * Get the single instance of the class.
	 *
	 * @return TRS_Leads_Generator
	 */
	public static function get_instance() {
		if ( null === self::$instance ) {
			self::$instance = new self();
		}
		return self::$instance;
	}

	/**
	 * Constructor.
	 */
	private function __construct() {
		$this->load_dependencies();
		$this->set_locale();
		$this->define_admin_hooks();
		$this->define_public_hooks();
	}

	/**
	 * Load required dependencies.
	 */
	private function load_dependencies() {
		require_once TRS_PLUGIN_DIR . 'includes/class-trs-post-types.php';
		require_once TRS_PLUGIN_DIR . 'admin/class-trs-admin.php';

		$this->admin = new TRS_Admin();
	}

	/**
	 * Define the locale for internationalization.
	 */
	private function set_locale() {
		add_action( 'plugins_loaded', array( $this, 'load_plugin_textdomain' ) );
	}

	/**
	 * Load text domain for translations.
	 */
	public function load_plugin_textdomain() {
		load_plugin_textdomain(
			'trs-leads-generator',
			false,
			dirname( TRS_PLUGIN_BASENAME ) . '/languages/'
		);
	}

	/**
	 * Register all hooks related to the admin area functionality.
	 */
	private function define_admin_hooks() {
		add_action( 'admin_menu', array( $this->admin, 'register_admin_menu' ) );
		add_action( 'admin_init', array( $this->admin, 'register_settings' ) );
		add_action( 'admin_enqueue_scripts', array( $this->admin, 'enqueue_styles' ) );
	}

	/**
	 * Register all hooks related to public-facing and core data functionality.
	 */
	private function define_public_hooks() {
		add_action( 'init', array( 'TRS_Post_Types', 'register_cpt' ) );
	}

	/**
	 * Run the loader to execute all registered hooks with WordPress.
	 */
	public function run() {
		// Executed during plugin bootstrap.
	}
}
