<?php
/**
 * Fired during plugin activation.
 *
 * @package    TRS_Leads_Generator
 * @subpackage TRS_Leads_Generator/includes
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Fired during plugin activation.
 *
 * This class defines all code necessary to run during the plugin's activation.
 */
class TRS_Activator {

	/**
	 * Short Description. (use period)
	 *
	 * Long Description.
	 *
	 * @since 1.0.0
	 */
	public static function activate() {
		self::create_tables();
		self::register_post_types();
		flush_rewrite_rules();

		// Save current DB version for future schema migrations.
		update_option( 'trs_db_version', TRS_VERSION );
	}

	/**
	 * Create custom database tables.
	 *
	 * Creates table {$wpdb->prefix}trs_leads for relational lead storage.
	 *
	 * @since 1.0.0
	 */
	public static function create_tables() {
		global $wpdb;

		$table_name      = $wpdb->prefix . 'trs_leads';
		$charset_collate = $wpdb->get_charset_collate();

		// dbDelta requires two spaces after PRIMARY KEY and specific formatting.
		$sql = "CREATE TABLE {$table_name} (
			id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
			form_id bigint(20) unsigned NOT NULL DEFAULT 0,
			email varchar(100) NOT NULL,
			first_name varchar(100) DEFAULT '' NOT NULL,
			last_name varchar(100) DEFAULT '' NOT NULL,
			phone varchar(50) DEFAULT '' NOT NULL,
			company varchar(150) DEFAULT '' NOT NULL,
			job_title varchar(150) DEFAULT '' NOT NULL,
			message text NOT NULL,
			utm_source varchar(100) DEFAULT '' NOT NULL,
			utm_medium varchar(100) DEFAULT '' NOT NULL,
			utm_campaign varchar(150) DEFAULT '' NOT NULL,
			utm_content varchar(150) DEFAULT '' NOT NULL,
			utm_term varchar(150) DEFAULT '' NOT NULL,
			created_at datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
			PRIMARY KEY  (id),
			KEY form_id (form_id),
			KEY email (email),
			KEY created_at (created_at)
		) {$charset_collate};";

		require_once ABSPATH . 'wp-admin/includes/upgrade.php';
		dbDelta( $sql );

		// Ensure columns exist on existing installations where dbDelta did not alter.
		$existing_cols = $wpdb->get_col( "DESC {$table_name}", 0 );
		if ( is_array( $existing_cols ) && ! empty( $existing_cols ) ) {
			if ( ! in_array( 'phone', $existing_cols, true ) ) {
				$wpdb->query( "ALTER TABLE {$table_name} ADD COLUMN phone varchar(50) DEFAULT '' NOT NULL AFTER last_name" );
			}
			if ( ! in_array( 'company', $existing_cols, true ) ) {
				$wpdb->query( "ALTER TABLE {$table_name} ADD COLUMN company varchar(150) DEFAULT '' NOT NULL AFTER phone" );
			}
			if ( ! in_array( 'job_title', $existing_cols, true ) ) {
				$wpdb->query( "ALTER TABLE {$table_name} ADD COLUMN job_title varchar(150) DEFAULT '' NOT NULL AFTER company" );
			}
			if ( ! in_array( 'message', $existing_cols, true ) ) {
				$wpdb->query( "ALTER TABLE {$table_name} ADD COLUMN message text NOT NULL AFTER job_title" );
			}
		}
	}

	/**
	 * Trigger post type registration so rewrite rules flush properly during activation.
	 *
	 * @since 1.0.0
	 */
	private static function register_post_types() {
		require_once TRS_PLUGIN_DIR . 'includes/class-trs-post-types.php';
		TRS_Post_Types::register_cpt();
	}
}

