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
 * Coordinates internationalization, admin-specific hooks,
 * REST API routes, frontend handlers and ads automation.
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
	 * The meta boxes handler instance.
	 *
	 * @var TRS_Meta_Boxes
	 */
	private $meta_boxes;

	/**
	 * The frontend handler instance.
	 *
	 * @var TRS_Frontend
	 */
	private $frontend;

	/**
	 * The REST controller instance.
	 *
	 * @var TRS_REST_Controller
	 */
	private $rest_controller;

	/**
	 * The Ads automation instance.
	 *
	 * @var TRS_Ads_Automation
	 */
	private $ads_automation;

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
		$this->define_rest_hooks();
	}

	/**
	 * Load required dependencies.
	 */
	private function load_dependencies() {
		// Autoload Composer dependencies (e.g. Dompdf).
		if ( file_exists( TRS_PLUGIN_DIR . 'vendor/autoload.php' ) ) {
			require_once TRS_PLUGIN_DIR . 'vendor/autoload.php';
		}

		require_once TRS_PLUGIN_DIR . 'includes/class-trs-post-types.php';
		require_once TRS_PLUGIN_DIR . 'includes/class-trs-meta-boxes.php';
		require_once TRS_PLUGIN_DIR . 'includes/class-trs-frontend.php';
		require_once TRS_PLUGIN_DIR . 'includes/class-trs-google-sheets.php';
		require_once TRS_PLUGIN_DIR . 'includes/class-trs-pdf-generator.php';
		require_once TRS_PLUGIN_DIR . 'includes/class-trs-ads-automation.php';
		require_once TRS_PLUGIN_DIR . 'includes/class-trs-rest-controller.php';
		require_once TRS_PLUGIN_DIR . 'admin/class-trs-admin.php';

		$this->admin           = new TRS_Admin();
		$this->meta_boxes      = new TRS_Meta_Boxes();
		$this->frontend        = new TRS_Frontend();
		$this->rest_controller = new TRS_REST_Controller();
		$this->ads_automation  = new TRS_Ads_Automation();
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

		// Initialize Meta Boxes & Ads Automation handlers.
		$this->meta_boxes->init();
		$this->ads_automation->init();
	}

	/**
	 * Register all hooks related to public-facing and core data functionality.
	 */
	private function define_public_hooks() {
		add_action( 'init', array( 'TRS_Post_Types', 'register_cpt' ) );

		// Initialize Frontend shortcode and submission handler.
		$this->frontend->init();
	}

	/**
	 * Register REST API hooks.
	 */
	private function define_rest_hooks() {
		add_action( 'rest_api_init', array( $this->rest_controller, 'register_routes' ) );
	}

	/**
	 * Run the loader to execute all registered hooks with WordPress.
	 */
	public function run() {
		// Executed during plugin bootstrap.
	}
}
