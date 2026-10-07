<?php
/**
 * REST API Controller for TRS Leads Generator.
 *
 * @package    TRS_Leads_Generator
 * @subpackage TRS_Leads_Generator/includes
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Handles custom REST API endpoints for IA Agents and Form submission.
 */
class TRS_REST_Controller extends WP_REST_Controller {

	/**
	 * Namespace for the REST API routes.
	 *
	 * @var string
	 */
	protected $namespace = 'trs/v1';

	/**
	 * Register routes for TRS Leads Generator.
	 */
	public function register_routes() {
		// Route: POST /trs/v1/generate-form (Exclusive for AI Agent / Admin).
		register_rest_route(
			$this->namespace,
			'/generate-form',
			array(
				array(
					'methods'             => WP_REST_Server::CREATABLE,
					'callback'            => array( $this, 'generate_form_from_ai' ),
					'permission_callback' => array( $this, 'check_ai_agent_permissions' ),
					'args'                => $this->get_generate_form_schema(),
				),
			)
		);

		// Route: POST /trs/v1/generate-landing (Generates full Landing Page for a Form).
		register_rest_route(
			$this->namespace,
			'/generate-landing',
			array(
				array(
					'methods'             => WP_REST_Server::CREATABLE,
					'callback'            => array( $this, 'generate_landing_page' ),
					'permission_callback' => array( $this, 'check_landing_permissions' ),
					'args'                => $this->get_generate_landing_schema(),
				),
			)
		);
	}

	/**
	 * Permission check for landing page generation endpoint.
	 *
	 * @param WP_REST_Request $request Current request.
	 * @return bool|WP_Error
	 */
	public function check_landing_permissions( $request ) {
		if ( ! current_user_can( 'edit_pages' ) ) {
			return new WP_Error(
				'rest_forbidden',
				__( 'No tienes permisos para crear o editar páginas.', 'trs-leads-generator' ),
				array( 'status' => rest_authorization_required_code() )
			);
		}
		return true;
	}

	/**
	 * Permission check for AI agent endpoint.
	 *
	 * Requires valid user session (e.g. via Application Passwords or logged-in user).
	 *
	 * @param WP_REST_Request $request Current request.
	 * @return bool|WP_Error
	 */
	public function check_ai_agent_permissions( $request ) {
		if ( ! current_user_can( 'edit_posts' ) ) {
			return new WP_Error(
				'rest_forbidden',
				__( 'No tienes permisos suficientes para generar formularios.', 'trs-leads-generator' ),
				array( 'status' => rest_authorization_required_code() )
			);
		}
		return true;
	}

	/**
	 * Schema validation for /generate-form endpoint.
	 *
	 * @return array
	 */
	public function get_generate_form_schema() {
		return array(
			'title' => array(
				'description'       => __( 'Título del formulario.', 'trs-leads-generator' ),
				'type'              => 'string',
				'required'          => true,
				'sanitize_callback' => 'sanitize_text_field',
			),
			'type' => array(
				'description'       => __( 'Tipo de formulario (performance, lead_magnet, event).', 'trs-leads-generator' ),
				'type'              => 'string',
				'enum'              => array( 'performance', 'lead_magnet', 'event' ),
				'default'           => 'performance',
				'sanitize_callback' => 'sanitize_text_field',
			),
			'description' => array(
				'description'       => __( 'Descripción o contenido textual introductorio.', 'trs-leads-generator' ),
				'type'              => 'string',
				'required'          => false,
				'default'           => '',
			),
			'captcha' => array(
				'description' => __( 'Activar captcha/antispam.', 'trs-leads-generator' ),
				'type'        => 'boolean',
				'default'     => true,
			),
			'terms' => array(
				'description' => __( 'Requerir aceptación de términos y condiciones.', 'trs-leads-generator' ),
				'type'        => 'boolean',
				'default'     => true,
			),
			'associated_page' => array(
				'description'       => __( 'URL o identificador de página asociada.', 'trs-leads-generator' ),
				'type'              => 'string',
				'required'          => false,
				'default'           => '',
				'sanitize_callback' => 'sanitize_text_field',
			),
			'confirmation_url' => array(
				'description'       => __( 'URL de redirección post-conversión.', 'trs-leads-generator' ),
				'type'              => 'string',
				'required'          => false,
				'default'           => '',
				'sanitize_callback' => 'esc_url_raw',
			),
			'cover_image_id' => array(
				'description' => __( 'ID del attachment en Media Library para portada.', 'trs-leads-generator' ),
				'type'        => 'integer',
				'default'     => 0,
			),
			'pdf_resource_id' => array(
				'description' => __( 'ID del attachment en Media Library para PDF descargable.', 'trs-leads-generator' ),
				'type'        => 'integer',
				'default'     => 0,
			),
			'fields' => array(
				'description' => __( 'Lista de campos opcionales a activar (phone, company, job_title, message). Si se activan, serán obligatorios.', 'trs-leads-generator' ),
				'type'        => 'array',
				'items'       => array( 'type' => 'string' ),
				'required'    => false,
				'default'     => array(),
			),
		);
	}

	/**
	 * Validate against executable code or dangerous injection payloads.
	 *
	 * Zero-Trust verification: explicitly rejects any JS/PHP injection or dangerous tags.
	 *
	 * @param array $payload Payload data to check.
	 * @return true|WP_Error
	 */
	protected function validate_zero_trust_safety( $payload ) {
		$dangerous_patterns = array(
			'/<script\b[^>]*>(.*?)<\/script>/is',
			'/javascript\s*:/i',
			'/eval\s*\(/i',
			'/base64_decode\s*\(/i',
			'/<\?php/i',
			'/<\?=/',
			'/<iframe\b[^>]*>/i',
			'/onload\s*=/i',
			'/onerror\s*=/i',
			'/onclick\s*=/i',
			'/onmouseover\s*=/i',
			'/system\s*\(/i',
			'/passthru\s*\(/i',
			'/exec\s*\(/i',
		);

		foreach ( $payload as $key => $value ) {
			if ( is_string( $value ) ) {
				foreach ( $dangerous_patterns as $pattern ) {
					if ( preg_match( $pattern, $value ) ) {
						return new WP_Error(
							'trs_security_violation',
							sprintf(
								/* translators: %s: parameter key */
								__( 'Violación de Seguridad (Zero Trust): Se detectó código ejecutable o no autorizado en el parámetro "%s".', 'trs-leads-generator' ),
								esc_html( $key )
							),
							array( 'status' => 400 )
						);
					}
				}
			}
		}

		return true;
	}

	/**
	 * Endpoint callback: Generates a new trs_form draft from an AI payload.
	 *
	 * @param WP_REST_Request $request Incoming REST request.
	 * @return WP_REST_Response|WP_Error
	 */
	public function generate_form_from_ai( $request ) {
		$params = $request->get_params();

		// 1. Zero Trust Security Validation.
		$safety_check = $this->validate_zero_trust_safety( $params );
		if ( is_wp_error( $safety_check ) ) {
			return $safety_check;
		}

		// 2. Sanitize and prepare post fields.
		$title       = sanitize_text_field( $params['title'] ?? __( 'Nuevo Formulario TRS', 'trs-leads-generator' ) );
		$description = wp_kses_post( $params['description'] ?? '' );
		$type        = in_array( $params['type'] ?? '', array( 'performance', 'lead_magnet', 'event' ), true ) ? $params['type'] : 'performance';
		$captcha     = ! empty( $params['captcha'] ) ? 1 : 0;
		$terms       = ! empty( $params['terms'] ) ? 1 : 0;
		$associated  = sanitize_text_field( $params['associated_page'] ?? '' );
		$confirm_url = esc_url_raw( $params['confirmation_url'] ?? '' );
		$cover_id    = absint( $params['cover_image_id'] ?? 0 );
		$pdf_id      = absint( $params['pdf_resource_id'] ?? 0 );

		// 3. Create Draft Post in trs_form.
		$post_id = wp_insert_post(
			array(
				'post_title'   => $title,
				'post_content' => $description,
				'post_status'  => 'draft',
				'post_type'    => 'trs_form',
			),
			true
		);

		if ( is_wp_error( $post_id ) ) {
			return new WP_Error(
				'trs_creation_failed',
				$post_id->get_error_message(),
				array( 'status' => 500 )
			);
		}

		// 4. Update post meta safely.
		update_post_meta( $post_id, '_trs_form_type', $type );
		update_post_meta( $post_id, '_trs_form_captcha', $captcha );
		update_post_meta( $post_id, '_trs_form_terms', $terms );
		update_post_meta( $post_id, '_trs_form_associated_page', $associated );
		update_post_meta( $post_id, '_trs_form_confirmation_url', $confirm_url );
		update_post_meta( $post_id, '_trs_form_cover_image', $cover_id );
		update_post_meta( $post_id, '_trs_form_pdf_resource', $pdf_id );

		// Optional fields (phone, company, job_title, message).
		$fields = is_array( $params['fields'] ?? null ) ? $params['fields'] : array();
		update_post_meta( $post_id, '_trs_field_phone', in_array( 'phone', $fields, true ) ? 1 : 0 );
		update_post_meta( $post_id, '_trs_field_company', in_array( 'company', $fields, true ) ? 1 : 0 );
		update_post_meta( $post_id, '_trs_field_job_title', in_array( 'job_title', $fields, true ) ? 1 : 0 );
		update_post_meta( $post_id, '_trs_field_message', in_array( 'message', $fields, true ) ? 1 : 0 );

		// 5. Build and return structured response.
		$response_data = array(
			'success'   => true,
			'message'   => __( 'Formulario creado exitosamente en estado borrador.', 'trs-leads-generator' ),
			'data'      => array(
				'id'                   => $post_id,
				'title'                => $title,
				'status'               => 'draft',
				'type'                 => $type,
				'shortcode'            => sprintf( '[trs_form id="%d"]', $post_id ),
				'shortcode_form'       => sprintf( '[trs_leads_generator_form id="%d"]', $post_id ),
				'shortcode_image'      => sprintf( '[trs_leads_generator_image id="%d"]', $post_id ),
				'edit_url'             => admin_url( sprintf( 'post.php?post=%d&action=edit', $post_id ) ),
				'created_at'           => current_time( 'mysql' ),
			),
		);

		return new WP_REST_Response( $response_data, 201 );
	}

	/**
	 * Schema validation for /generate-landing endpoint.
	 *
	 * @return array
	 */
	public function get_generate_landing_schema() {
		return array(
			'form_id' => array(
				'description'       => __( 'ID del formulario TRS a incrustar.', 'trs-leads-generator' ),
				'type'              => 'integer',
				'required'          => true,
				'sanitize_callback' => 'absint',
			),
			'page_title' => array(
				'description'       => __( 'Título de la Landing Page.', 'trs-leads-generator' ),
				'type'              => 'string',
				'required'          => false,
				'sanitize_callback' => 'sanitize_text_field',
			),
			'headline' => array(
				'description'       => __( 'Titular principal (Hero Headline).', 'trs-leads-generator' ),
				'type'              => 'string',
				'required'          => false,
				'sanitize_callback' => 'sanitize_text_field',
			),
			'subheadline' => array(
				'description'       => __( 'Subtítulo o propuesta de valor.', 'trs-leads-generator' ),
				'type'              => 'string',
				'required'          => false,
				'sanitize_callback' => 'sanitize_text_field',
			),
			'bullet_points' => array(
				'description' => __( 'Puntos destacados o beneficios.', 'trs-leads-generator' ),
				'type'        => 'array',
				'items'       => array( 'type' => 'string' ),
				'required'    => false,
				'default'     => array(),
			),
			'status' => array(
				'description'       => __( 'Estado de la página creada (publish o draft).', 'trs-leads-generator' ),
				'type'              => 'string',
				'enum'              => array( 'publish', 'draft' ),
				'default'           => 'publish',
				'sanitize_callback' => 'sanitize_text_field',
			),
		);
	}

	/**
	 * Endpoint callback: Generates a complete WordPress Page with native Gutenberg layout and embedded form.
	 *
	 * @param WP_REST_Request $request Incoming REST request.
	 * @return WP_REST_Response|WP_Error
	 */
	public function generate_landing_page( $request ) {
		$params  = $request->get_params();
		$form_id = absint( $params['form_id'] ?? 0 );

		$form_post = get_post( $form_id );
		if ( ! $form_post || 'trs_form' !== $form_post->post_type ) {
			return new WP_Error(
				'trs_invalid_form',
				__( 'El formulario especificado no existe o no es válido.', 'trs-leads-generator' ),
				array( 'status' => 404 )
			);
		}

		$page_title  = ! empty( $params['page_title'] ) ? sanitize_text_field( $params['page_title'] ) : sprintf( __( 'Landing - %s', 'trs-leads-generator' ), $form_post->post_title );
		$headline    = ! empty( $params['headline'] ) ? sanitize_text_field( $params['headline'] ) : $form_post->post_title;
		$subheadline = ! empty( $params['subheadline'] ) ? sanitize_text_field( $params['subheadline'] ) : get_post_field( 'post_excerpt', $form_id );
		$status      = in_array( $params['status'] ?? '', array( 'publish', 'draft' ), true ) ? $params['status'] : 'publish';
		$bullets     = is_array( $params['bullet_points'] ?? null ) ? $params['bullet_points'] : array();

		// Retrieve form cover image if exists.
		$cover_id  = (int) get_post_meta( $form_id, '_trs_form_cover_image', true );
		$cover_url = $cover_id ? wp_get_attachment_image_url( $cover_id, 'large' ) : '';

		// Build structured layout using native Gutenberg block markup.
		$content = '<!-- wp:group {"style":{"spacing":{"padding":{"top":"50px","bottom":"60px","left":"20px","right":"20px"}}},"layout":{"type":"constrained","contentSize":"850px"}} -->' . "\n";
		$content .= '<div class="wp-block-group" style="padding-top:50px;padding-right:20px;padding-bottom:60px;padding-left:20px">' . "\n";

		// Headline
		$content .= '<!-- wp:heading {"textAlign":"center","level":1} -->' . "\n";
		$content .= '<h1 class="wp-block-heading has-text-align-center">' . esc_html( $headline ) . '</h1>' . "\n";
		$content .= '<!-- /wp:heading -->' . "\n";

		// Subheadline
		if ( ! empty( $subheadline ) ) {
			$content .= '<!-- wp:paragraph {"align":"center","fontSize":"medium"} -->' . "\n";
			$content .= '<p class="has-text-align-center has-medium-font-size">' . esc_html( $subheadline ) . '</p>' . "\n";
			$content .= '<!-- /wp:paragraph -->' . "\n";
		}

		// Cover Image (Modular Shortcode)
		if ( ! empty( $cover_id ) ) {
			$content .= '<!-- wp:shortcode -->' . "\n";
			$content .= sprintf( '[trs_leads_generator_image id="%d"]', $form_id ) . "\n";
			$content .= '<!-- /wp:shortcode -->' . "\n";
		} elseif ( ! empty( $cover_url ) ) {
			$content .= '<!-- wp:image {"align":"center","sizeSlug":"large","linkDestination":"none"} -->' . "\n";
			$content .= '<figure class="wp-block-image aligncenter size-large"><img src="' . esc_url( $cover_url ) . '" alt="' . esc_attr( $headline ) . '" style="border-radius:10px;box-shadow:0 10px 25px rgba(0,0,0,0.1);max-height:420px;object-fit:cover;"/></figure>' . "\n";
			$content .= '<!-- /wp:image -->' . "\n";
		}

		// Bullet points list
		if ( ! empty( $bullets ) ) {
			$content .= '<!-- wp:list -->' . "\n";
			$content .= '<ul class="wp-block-list">' . "\n";
			foreach ( $bullets as $bullet ) {
				$content .= '<li>' . esc_html( sanitize_text_field( $bullet ) ) . '</li>' . "\n";
			}
			$content .= '</ul>' . "\n";
			$content .= '<!-- /wp:list -->' . "\n";
		}

		// Embedded Form Shortcode (Modular Shortcode)
		$content .= '<!-- wp:shortcode -->' . "\n";
		$content .= sprintf( '[trs_leads_generator_form id="%d"]', $form_id ) . "\n";
		$content .= '<!-- /wp:shortcode -->' . "\n";

		$content .= '</div>' . "\n";
		$content .= '<!-- /wp:group -->';

		// Create the page.
		$page_id = wp_insert_post(
			array(
				'post_title'   => $page_title,
				'post_content' => $content,
				'post_status'  => $status,
				'post_type'    => 'page',
			),
			true
		);

		if ( is_wp_error( $page_id ) ) {
			return new WP_Error(
				'trs_landing_creation_failed',
				$page_id->get_error_message(),
				array( 'status' => 500 )
			);
		}

		// Associate page URL with the form.
		$permalink = get_permalink( $page_id );
		update_post_meta( $form_id, '_trs_form_associated_page', $permalink );

		return new WP_REST_Response(
			array(
				'success' => true,
				'message' => __( 'Landing page creada e integrada exitosamente con el formulario.', 'trs-leads-generator' ),
				'data'    => array(
					'page_id'         => $page_id,
					'title'           => $page_title,
					'status'          => $status,
					'permalink'       => $permalink,
					'edit_url'        => admin_url( sprintf( 'post.php?post=%d&action=edit', $page_id ) ),
					'form_id'         => $form_id,
					'shortcode_form'  => sprintf( '[trs_leads_generator_form id="%d"]', $form_id ),
					'shortcode_image' => sprintf( '[trs_leads_generator_image id="%d"]', $form_id ),
					'shortcode'       => sprintf( '[trs_form id="%d"]', $form_id ),
				),
			),
			201
		);
	}
}
