<?php
/**
 * Register Custom Post Types for the plugin.
 *
 * @package    TRS_Leads_Generator
 * @subpackage TRS_Leads_Generator/includes
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Handles custom post types registration.
 */
class TRS_Post_Types {

	/**
	 * Register the trs_form custom post type.
	 *
	 * Note: Post type is non-public on frontend (accessible only via shortcode or block)
	 * but displays in the admin dashboard under the Lead Generation menu.
	 *
	 * @since 1.0.0
	 */
	public static function register_cpt() {
		$labels = array(
			'name'                  => _x( 'Formularios TRS', 'Post type general name', 'trs-leads-generator' ),
			'singular_name'         => _x( 'Formulario TRS', 'Post type singular name', 'trs-leads-generator' ),
			'menu_name'             => _x( 'Formularios', 'Admin Menu text', 'trs-leads-generator' ),
			'name_admin_bar'        => _x( 'Formulario TRS', 'Add New on Toolbar', 'trs-leads-generator' ),
			'add_new'               => __( 'Añadir Formulario', 'trs-leads-generator' ),
			'add_new_item'          => __( 'Añadir Nuevo Formulario', 'trs-leads-generator' ),
			'new_item'              => __( 'Nuevo Formulario', 'trs-leads-generator' ),
			'edit_item'             => __( 'Editar Formulario', 'trs-leads-generator' ),
			'view_item'             => __( 'Ver Formulario', 'trs-leads-generator' ),
			'all_items'             => __( 'Todos los Formularios', 'trs-leads-generator' ),
			'search_items'          => __( 'Buscar Formularios', 'trs-leads-generator' ),
			'not_found'             => __( 'No se encontraron formularios.', 'trs-leads-generator' ),
			'not_found_in_trash'    => __( 'No se encontraron formularios en la papelera.', 'trs-leads-generator' ),
		);

		$args = array(
			'labels'             => $labels,
			'description'        => __( 'Formularios de captación de leads para TRS Leads Generator.', 'trs-leads-generator' ),
			'public'             => false,
			'publicly_queryable' => false,
			'show_ui'            => true,
			'show_in_menu'       => 'trs-leads-generator',
			'query_var'          => false,
			'rewrite'            => false,
			'capability_type'    => 'post',
			'map_meta_cap'       => true,
			'has_archive'        => false,
			'hierarchical'       => false,
			'menu_position'      => null,
			'supports'           => array( 'title', 'editor', 'custom-fields' ),
			'show_in_rest'       => true, // Enables Gutenberg editor & REST API access.
		);

		register_post_type( 'trs_form', $args );
	}
}

