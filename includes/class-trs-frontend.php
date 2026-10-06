<?php
/**
 * Frontend Form Shortcode and Submission Handler.
 *
 * @package    TRS_Leads_Generator
 * @subpackage TRS_Leads_Generator/includes
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Handles rendering the [trs_form] shortcode and processing AJAX submissions.
 */
class TRS_Frontend {

	/**
	 * Initialize frontend hooks.
	 */
	public function init() {
		add_shortcode( 'trs_form', array( $this, 'render_form_shortcode' ) );

		// AJAX endpoints.
		add_action( 'wp_ajax_nopriv_trs_submit_lead', array( $this, 'handle_lead_submission' ) );
		add_action( 'wp_ajax_trs_submit_lead', array( $this, 'handle_lead_submission' ) );

		// Enqueue scripts.
		add_action( 'wp_enqueue_scripts', array( $this, 'register_frontend_assets' ) );
	}

	/**
	 * Register frontend scripts and styles so they can be enqueued when shortcode runs.
	 */
	public function register_frontend_assets() {
		wp_register_style(
			'trs-frontend-css',
			TRS_PLUGIN_URL . 'public/css/trs-frontend.css',
			array(),
			TRS_VERSION,
			'all'
		);

		wp_register_script(
			'trs-tracking-js',
			TRS_PLUGIN_URL . 'public/js/trs-tracking.js',
			array(),
			TRS_VERSION,
			true
		);

		wp_register_script(
			'trs-frontend-js',
			TRS_PLUGIN_URL . 'public/js/trs-frontend.js',
			array(),
			TRS_VERSION,
			true
		);

		wp_localize_script(
			'trs-frontend-js',
			'trs_frontend_obj',
			array(
				'ajax_url' => admin_url( 'admin-ajax.php' ),
			)
		);

		// Always load tracking script on frontend to persist UTMs in cookies.
		if ( ! is_admin() ) {
			wp_enqueue_script( 'trs-tracking-js' );
		}
	}

	/**
	 * Render [trs_form id="X"] shortcode.
	 *
	 * @param array $atts Shortcode attributes.
	 * @return string HTML output of the form.
	 */
	public function render_form_shortcode( $atts ) {
		$atts = shortcode_atts(
			array(
				'id' => 0,
			),
			$atts,
			'trs_form'
		);

		$form_id = absint( $atts['id'] );
		if ( ! $form_id ) {
			return '<!-- [TRS Leads Generator] Error: Atributo id no especificado -->';
		}

		$post = get_post( $form_id );
		if ( ! $post || 'trs_form' !== $post->post_type ) {
			return '<!-- [TRS Leads Generator] Error: Formulario no encontrado -->';
		}

		// Enqueue frontend assets.
		wp_enqueue_style( 'trs-frontend-css' );
		wp_enqueue_script( 'trs-tracking-js' );
		wp_enqueue_script( 'trs-frontend-js' );

		// Retrieve post metadata.
		$type             = get_post_meta( $form_id, '_trs_form_type', true ) ?: 'performance';
		$captcha_enabled  = (bool) get_post_meta( $form_id, '_trs_form_captcha', true );
		$terms_enabled    = (bool) get_post_meta( $form_id, '_trs_form_terms', true );
		$cover_image_id   = (int) get_post_meta( $form_id, '_trs_form_cover_image', true );
		$cover_image_url  = $cover_image_id ? wp_get_attachment_image_url( $cover_image_id, 'large' ) : '';

		// Dynamic button text according to form type.
		$button_text = __( 'Enviar Información', 'trs-leads-generator' );
		if ( 'lead_magnet' === $type ) {
			$button_text = __( 'Descargar Recurso Gratis', 'trs-leads-generator' );
		} elseif ( 'event' === $type ) {
			$button_text = __( 'Registrarme al Evento', 'trs-leads-generator' );
		}

		// Math challenge for simple zero-dependency spam protection if captcha enabled.
		$num1 = wp_rand( 2, 7 );
		$num2 = wp_rand( 1, 5 );
		$expected_sum = $num1 + $num2;
		$captcha_token = wp_create_nonce( 'trs_captcha_' . $expected_sum );

		ob_start();
		?>
		<div class="trs-form-container" id="trs-form-<?php echo esc_attr( (string) $form_id ); ?>">
			<?php if ( ! empty( $cover_image_url ) ) : ?>
				<div class="trs-form-cover-wrap" style="text-align: center; margin-bottom: 20px;">
					<img src="<?php echo esc_url( $cover_image_url ); ?>" alt="<?php echo esc_attr( $post->post_title ); ?>" style="max-width: 100%; height: auto; border-radius: 8px;" />
				</div>
			<?php endif; ?>

			<div class="trs-form-header">
				<h3 class="trs-form-title"><?php echo esc_html( $post->post_title ); ?></h3>
				<?php if ( ! empty( $post->post_content ) ) : ?>
					<div class="trs-form-desc"><?php echo wp_kses_post( wpautop( $post->post_content ) ); ?></div>
				<?php endif; ?>
			</div>

			<form class="trs-lead-form" method="POST" data-form-id="<?php echo esc_attr( (string) $form_id ); ?>" data-form-type="<?php echo esc_attr( $type ); ?>">
				<!-- Security Nonce -->
				<?php wp_nonce_field( 'trs_lead_submission_nonce', 'trs_nonce' ); ?>

				<!-- Form Identification & Honeypot -->
				<input type="hidden" name="form_id" value="<?php echo esc_attr( (string) $form_id ); ?>" />
				<input type="text" name="trs_hp" value="" style="display: none !important; visibility: hidden !important;" tabindex="-1" autocomplete="off" />

				<!-- Hidden UTM tracking fields (Populated via trs-tracking.js) -->
				<input type="hidden" name="utm_source" value="" />
				<input type="hidden" name="utm_medium" value="" />
				<input type="hidden" name="utm_campaign" value="" />
				<input type="hidden" name="utm_content" value="" />
				<input type="hidden" name="utm_term" value="" />

				<!-- User Information Inputs -->
				<div class="trs-form-row">
					<div class="trs-form-col">
						<div class="trs-form-group">
							<label class="trs-form-label" for="trs_first_name_<?php echo esc_attr( (string) $form_id ); ?>">
								<?php esc_html_e( 'Nombre', 'trs-leads-generator' ); ?> <span class="required">*</span>
							</label>
							<input type="text" class="trs-form-control" id="trs_first_name_<?php echo esc_attr( (string) $form_id ); ?>" name="first_name" required="required" placeholder="Ej. Juan" />
						</div>
					</div>
					<div class="trs-form-col">
						<div class="trs-form-group">
							<label class="trs-form-label" for="trs_last_name_<?php echo esc_attr( (string) $form_id ); ?>">
								<?php esc_html_e( 'Apellido', 'trs-leads-generator' ); ?>
							</label>
							<input type="text" class="trs-form-control" id="trs_last_name_<?php echo esc_attr( (string) $form_id ); ?>" name="last_name" placeholder="Ej. Pérez" />
						</div>
					</div>
				</div>

				<div class="trs-form-group">
					<label class="trs-form-label" for="trs_email_<?php echo esc_attr( (string) $form_id ); ?>">
						<?php esc_html_e( 'Correo Electrónico', 'trs-leads-generator' ); ?> <span class="required">*</span>
					</label>
					<input type="email" class="trs-form-control" id="trs_email_<?php echo esc_attr( (string) $form_id ); ?>" name="email" required="required" placeholder="juan.perez@empresa.com" />
				</div>

				<!-- Antispam Captcha if active -->
				<?php if ( $captcha_enabled ) : ?>
					<div class="trs-form-group">
						<label class="trs-form-label" for="trs_captcha_ans_<?php echo esc_attr( (string) $form_id ); ?>">
							<?php
							printf(
								/* translators: 1: number 1, 2: number 2 */
								esc_html__( 'Pregunta de seguridad: ¿Cuánto es %1$d + %2$d?', 'trs-leads-generator' ),
								(int) $num1,
								(int) $num2
							);
							?> <span class="required">*</span>
						</label>
						<input type="number" class="trs-form-control" id="trs_captcha_ans_<?php echo esc_attr( (string) $form_id ); ?>" name="captcha_answer" required="required" placeholder="?" style="max-width: 140px;" />
						<input type="hidden" name="captcha_token" value="<?php echo esc_attr( $captcha_token ); ?>" />
					</div>
				<?php endif; ?>

				<!-- Terms & Conditions if active -->
				<?php if ( $terms_enabled ) : ?>
					<div class="trs-form-checkbox-group">
						<label class="trs-form-checkbox-label">
							<input type="checkbox" name="trs_terms" value="1" required="required" />
							<span><?php esc_html_e( 'Acepto las políticas de privacidad y los términos de tratamiento de datos personales.', 'trs-leads-generator' ); ?> <span class="required">*</span></span>
						</label>
					</div>
				<?php endif; ?>

				<!-- Submit Button -->
				<button type="submit" class="trs-form-submit-btn" data-loading-text="<?php esc_attr_e( 'Enviando...', 'trs-leads-generator' ); ?>">
					<span><?php echo esc_html( $button_text ); ?></span>
				</button>

				<!-- Response Notification Box -->
				<div class="trs-form-message"></div>
			</form>
		</div>
		<?php
		return ob_get_clean();
	}

	/**
	 * Process lead submission via AJAX.
	 */
	public function handle_lead_submission() {
		// 1. Verify Nonce.
		if ( ! isset( $_POST['trs_nonce'] ) || ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['trs_nonce'] ) ), 'trs_lead_submission_nonce' ) ) {
			wp_send_json_error( array( 'message' => __( 'Sesión caducada o verificación de seguridad no válida. Por favor recarga la página.', 'trs-leads-generator' ) ), 403 );
		}

		// 2. Honeypot check (anti-bot trap).
		if ( ! empty( $_POST['trs_hp'] ) ) {
			// Silently approve bot to waste bot resources without recording spam lead.
			wp_send_json_success( array( 'message' => __( '¡Solicitud recibida!', 'trs-leads-generator' ) ) );
		}

		$form_id = isset( $_POST['form_id'] ) ? absint( $_POST['form_id'] ) : 0;
		$post    = get_post( $form_id );
		if ( ! $post || 'trs_form' !== $post->post_type ) {
			wp_send_json_error( array( 'message' => __( 'El formulario especificado no es válido.', 'trs-leads-generator' ) ), 400 );
		}

		// 3. Captcha check if enabled.
		$captcha_enabled = (bool) get_post_meta( $form_id, '_trs_form_captcha', true );
		if ( $captcha_enabled ) {
			$answer = isset( $_POST['captcha_answer'] ) ? (int) $_POST['captcha_answer'] : null;
			$token  = isset( $_POST['captcha_token'] ) ? sanitize_text_field( wp_unslash( $_POST['captcha_token'] ) ) : '';

			if ( null === $answer || ! wp_verify_nonce( $token, 'trs_captcha_' . $answer ) ) {
				wp_send_json_error( array( 'message' => __( 'Respuesta de seguridad incorrecta. Inténtalo nuevamente.', 'trs-leads-generator' ) ), 400 );
			}
		}

		// 4. Terms check if enabled.
		$terms_enabled = (bool) get_post_meta( $form_id, '_trs_form_terms', true );
		if ( $terms_enabled && empty( $_POST['trs_terms'] ) ) {
			wp_send_json_error( array( 'message' => __( 'Debes aceptar los términos y condiciones para continuar.', 'trs-leads-generator' ) ), 400 );
		}

		// 5. Sanitize and Validate Inputs.
		$email = isset( $_POST['email'] ) ? sanitize_email( wp_unslash( $_POST['email'] ) ) : '';
		if ( ! is_email( $email ) ) {
			wp_send_json_error( array( 'message' => __( 'Por favor ingresa un correo electrónico válido.', 'trs-leads-generator' ) ), 400 );
		}

		$first_name   = isset( $_POST['first_name'] ) ? sanitize_text_field( wp_unslash( $_POST['first_name'] ) ) : '';
		$last_name    = isset( $_POST['last_name'] ) ? sanitize_text_field( wp_unslash( $_POST['last_name'] ) ) : '';
		$utm_source   = isset( $_POST['utm_source'] ) ? sanitize_text_field( wp_unslash( $_POST['utm_source'] ) ) : '';
		$utm_medium   = isset( $_POST['utm_medium'] ) ? sanitize_text_field( wp_unslash( $_POST['utm_medium'] ) ) : '';
		$utm_campaign = isset( $_POST['utm_campaign'] ) ? sanitize_text_field( wp_unslash( $_POST['utm_campaign'] ) ) : '';
		$utm_content  = isset( $_POST['utm_content'] ) ? sanitize_text_field( wp_unslash( $_POST['utm_content'] ) ) : '';
		$utm_term     = isset( $_POST['utm_term'] ) ? sanitize_text_field( wp_unslash( $_POST['utm_term'] ) ) : '';
		$created_at   = current_time( 'mysql' );

		// 6. Insert lead into custom SQL table {$wpdb->prefix}trs_leads.
		global $wpdb;
		$table_name = $wpdb->prefix . 'trs_leads';

		$inserted = $wpdb->insert(
			$table_name,
			array(
				'form_id'      => $form_id,
				'email'        => $email,
				'first_name'   => $first_name,
				'last_name'    => $last_name,
				'utm_source'   => $utm_source,
				'utm_medium'   => $utm_medium,
				'utm_campaign' => $utm_campaign,
				'utm_content'  => $utm_content,
				'utm_term'     => $utm_term,
				'created_at'   => $created_at,
			),
			array( '%d', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s' )
		);

		if ( false === $inserted ) {
			error_log( '[TRS Leads Generator] Error al insertar lead: ' . $wpdb->last_error );
			wp_send_json_error( array( 'message' => __( 'Error interno al registrar el lead. Inténtalo de nuevo.', 'trs-leads-generator' ) ), 500 );
		}

		$lead_id = (int) $wpdb->insert_id;

		// 7. Push to Google Sheets (Non-blocking integration).
		$lead_data = array(
			'id'           => $lead_id,
			'form_id'      => $form_id,
			'email'        => $email,
			'first_name'   => $first_name,
			'last_name'    => $last_name,
			'utm_source'   => $utm_source,
			'utm_medium'   => $utm_medium,
			'utm_campaign' => $utm_campaign,
			'utm_content'  => $utm_content,
			'utm_term'     => $utm_term,
			'created_at'   => $created_at,
		);

		require_once TRS_PLUGIN_DIR . 'includes/class-trs-google-sheets.php';
		TRS_Google_Sheets::sync_lead( $lead_data );

		// 8. Determine post-conversion actions (Redirection or PDF Download).
		$type             = get_post_meta( $form_id, '_trs_form_type', true ) ?: 'performance';
		$confirmation_url = get_post_meta( $form_id, '_trs_form_confirmation_url', true );
		$pdf_resource_id  = (int) get_post_meta( $form_id, '_trs_form_pdf_resource', true );

		$response = array(
			'message' => __( '¡Registro completado con éxito! Gracias por tu interés.', 'trs-leads-generator' ),
			'lead_id' => $lead_id,
		);

		if ( 'lead_magnet' === $type ) {
			$pdf_template = get_post_meta( $form_id, '_trs_form_pdf_template', true );

			// 8.1 Generación dinámica con Dompdf si hay plantilla seleccionada.
			if ( ! empty( $pdf_template ) ) {
				require_once TRS_PLUGIN_DIR . 'includes/class-trs-pdf-generator.php';
				$templates = TRS_PDF_Generator::get_templates();
				if ( isset( $templates[ $pdf_template ] ) ) {
					$lead_data['form_title'] = $post->post_title;
					$pdf_result = TRS_PDF_Generator::generate_pdf(
						$templates[ $pdf_template ]['html'],
						$lead_data,
						sanitize_title( $post->post_title )
					);
					if ( ! is_wp_error( $pdf_result ) && ! empty( $pdf_result['file_url'] ) ) {
						$response['download_url'] = $pdf_result['file_url'];
						$response['message']      = __( '¡Registro exitoso! Tu recurso PDF personalizado ha sido compilado y está listo para descargar.', 'trs-leads-generator' );
					}
				}
			}

			// 8.2 Fallback a recurso PDF estático si no se usó o falló la plantilla.
			if ( empty( $response['download_url'] ) && $pdf_resource_id ) {
				$download_url = wp_get_attachment_url( $pdf_resource_id );
				if ( $download_url ) {
					$response['download_url'] = $download_url;
					$response['message']      = __( '¡Registro exitoso! Tu recurso PDF está listo para descargar.', 'trs-leads-generator' );
				}
			}
		} elseif ( ( 'performance' === $type || 'event' === $type ) && ! empty( $confirmation_url ) ) {
			$response['redirect_url'] = esc_url_raw( $confirmation_url );
		}

		wp_send_json_success( $response );
	}
}
