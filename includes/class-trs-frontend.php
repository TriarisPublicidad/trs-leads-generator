<?php
/**
 * Frontend Form Shortcodes and Lead Submission Handler.
 *
 * @package    TRS_Leads_Generator
 * @subpackage TRS_Leads_Generator/includes
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Handles modular shortcodes:
 * - [trs_leads_generator_form id="X"]   (Solo formulario)
 * - [trs_leads_generator_image id="X"]  (Solo imagen de portada)
 * - [trs_form id="X"]                   (Combinado imagen + formulario)
 */
class TRS_Frontend {

	/**
	 * Initialize frontend hooks.
	 */
	public function init() {
		// Modular Shortcodes.
		add_shortcode( 'trs_leads_generator_form', array( $this, 'render_form_component_shortcode' ) );
		add_shortcode( 'trs_leads_generator_image', array( $this, 'render_image_component_shortcode' ) );

		// Legacy / Combined Shortcodes.
		add_shortcode( 'trs_form', array( $this, 'render_combined_shortcode' ) );
		add_shortcode( 'trs_leads_generator', array( $this, 'render_combined_shortcode' ) );

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
	 * Retrieve cover image URL based on selected source (media, external URL, or page/post).
	 *
	 * @param int $form_id Form post ID.
	 * @return string
	 */
	public static function get_form_cover_url( $form_id ) {
		$cover_source = get_post_meta( $form_id, '_trs_form_cover_source', true ) ?: 'media';

		if ( 'external' === $cover_source ) {
			$url = get_post_meta( $form_id, '_trs_form_cover_external_url', true );
			if ( ! empty( $url ) ) {
				return esc_url( $url );
			}
		} elseif ( 'post' === $cover_source ) {
			$target_id = (int) get_post_meta( $form_id, '_trs_form_cover_post_id', true );
			if ( $target_id && has_post_thumbnail( $target_id ) ) {
				$thumb_url = get_the_post_thumbnail_url( $target_id, 'large' );
				if ( $thumb_url ) {
					return $thumb_url;
				}
			}
		}

		$cover_image_id = (int) get_post_meta( $form_id, '_trs_form_cover_image', true );
		if ( $cover_image_id ) {
			$img = wp_get_attachment_image_url( $cover_image_id, 'large' );
			if ( $img ) {
				return $img;
			}
		}

		// Fallback check if external URL was entered without switching source.
		$ext_url = get_post_meta( $form_id, '_trs_form_cover_external_url', true );
		if ( ! empty( $ext_url ) ) {
			return esc_url( $ext_url );
		}

		return '';
	}

	/**
	 * Shortcode: [trs_leads_generator_image id="X"]
	 * Renders only the cover image component.
	 *
	 * @param array $atts Shortcode attributes.
	 * @return string HTML output.
	 */
	public function render_image_component_shortcode( $atts ) {
		$atts = shortcode_atts(
			array(
				'id'    => 0,
				'class' => '',
			),
			$atts,
			'trs_leads_generator_image'
		);

		$form_id = absint( $atts['id'] );
		if ( ! $form_id ) {
			return '<!-- [TRS Leads Generator Image] Error: ID no especificado -->';
		}

		$post = get_post( $form_id );
		if ( ! $post || 'trs_form' !== $post->post_type ) {
			return '<!-- [TRS Leads Generator Image] Error: Formulario no válido -->';
		}

		$cover_image_url = self::get_form_cover_url( $form_id );
		if ( ! $cover_image_url ) {
			return '';
		}

		wp_enqueue_style( 'trs-frontend-css' );

		ob_start();
		?>
		<div class="trs-image-container <?php echo esc_attr( $atts['class'] ); ?>" id="trs-image-<?php echo esc_attr( (string) $form_id ); ?>">
			<img src="<?php echo esc_url( $cover_image_url ); ?>" alt="<?php echo esc_attr( $post->post_title ); ?>" class="trs-cover-image" style="max-width: 100%; height: auto; border-radius: 10px; display: block; margin: 0 auto;" />
		</div>
		<?php
		return ob_get_clean();
	}

	/**
	 * Shortcode: [trs_leads_generator_form id="X"]
	 * Renders strictly the form without the top cover image.
	 *
	 * @param array $atts Shortcode attributes.
	 * @return string HTML output.
	 */
	public function render_form_component_shortcode( $atts ) {
		return $this->render_form_html( $atts, false );
	}

	/**
	 * Shortcode: [trs_form id="X"] or [trs_leads_generator id="X"]
	 * Renders image + form combined.
	 *
	 * @param array $atts Shortcode attributes.
	 * @return string HTML output.
	 */
	public function render_combined_shortcode( $atts ) {
		return $this->render_form_html( $atts, true );
	}

	/**
	 * Core HTML generator for forms.
	 *
	 * @param array $atts       Shortcode attributes.
	 * @param bool  $show_image Whether to render top cover image inside form container.
	 * @return string HTML output.
	 */
	private function render_form_html( $atts, $show_image = false ) {
		$atts = shortcode_atts(
			array(
				'id' => 0,
			),
			$atts,
			'trs_leads_generator_form'
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
		$cover_image_url  = self::get_form_cover_url( $form_id );

		// Dynamic active fields.
		$field_phone     = (bool) get_post_meta( $form_id, '_trs_field_phone', true );
		$field_company   = (bool) get_post_meta( $form_id, '_trs_field_company', true );
		$field_job_title = (bool) get_post_meta( $form_id, '_trs_field_job_title', true );
		$field_message   = (bool) get_post_meta( $form_id, '_trs_field_message', true );

		// Dynamic button text.
		$button_text = __( 'Enviar Información', 'trs-leads-generator' );
		if ( 'lead_magnet' === $type ) {
			$button_text = __( 'Descargar Recurso Gratis', 'trs-leads-generator' );
		} elseif ( 'event' === $type ) {
			$button_text = __( 'Registrarme al Evento', 'trs-leads-generator' );
		}

		// Math challenge for zero-dependency captcha if active.
		$num1 = wp_rand( 2, 7 );
		$num2 = wp_rand( 1, 5 );
		$expected_sum = $num1 + $num2;
		$captcha_token = wp_create_nonce( 'trs_captcha_' . $expected_sum );

		ob_start();
		?>
		<div class="trs-form-container" id="trs-form-<?php echo esc_attr( (string) $form_id ); ?>">
			<?php if ( $show_image && ! empty( $cover_image_url ) ) : ?>
				<div class="trs-form-cover-wrap" style="text-align: center; margin-bottom: 20px;">
					<img src="<?php echo esc_url( $cover_image_url ); ?>" alt="<?php echo esc_attr( $post->post_title ); ?>" style="max-width: 100%; height: auto; border-radius: 8px;" />
				</div>
			<?php endif; ?>

			<div class="trs-form-header">
				<h3 class="trs-form-title" style="display: none;">  <?php echo esc_html( $post->post_title ); ?></h3>
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

				<!-- 1. Nombre y Apellido (Siempre obligatorios y requeridos) -->
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
								<?php esc_html_e( 'Apellido', 'trs-leads-generator' ); ?> <span class="required">*</span>
							</label>
							<input type="text" class="trs-form-control" id="trs_last_name_<?php echo esc_attr( (string) $form_id ); ?>" name="last_name" required="required" placeholder="Ej. Pérez" />
						</div>
					</div>
				</div>

				<!-- 2. Correo Electrónico (Siempre obligatorio y requerido) -->
				<div class="trs-form-group">
					<label class="trs-form-label" for="trs_email_<?php echo esc_attr( (string) $form_id ); ?>">
						<?php esc_html_e( 'Correo Electrónico', 'trs-leads-generator' ); ?> <span class="required">*</span>
					</label>
					<input type="email" class="trs-form-control" id="trs_email_<?php echo esc_attr( (string) $form_id ); ?>" name="email" required="required" placeholder="juan.perez@empresa.com" />
				</div>

				<!-- 3. Teléfono / WhatsApp (Opcional según metabox, obligatorio si se activa) -->
				<?php if ( $field_phone ) : ?>
					<div class="trs-form-group">
						<label class="trs-form-label" for="trs_phone_<?php echo esc_attr( (string) $form_id ); ?>">
							<?php esc_html_e( 'Teléfono / WhatsApp', 'trs-leads-generator' ); ?> <span class="required">*</span>
						</label>
						<input type="tel" class="trs-form-control" id="trs_phone_<?php echo esc_attr( (string) $form_id ); ?>" name="phone" required="required" placeholder="+51 987 654 321" />
					</div>
				<?php endif; ?>

				<!-- 4. Institución y/o Cargo (Opcionales según metabox, obligatorios si se activan) -->
				<?php if ( $field_company || $field_job_title ) : ?>
					<div class="trs-form-row">
						<?php if ( $field_company ) : ?>
							<div class="trs-form-col">
								<div class="trs-form-group">
									<label class="trs-form-label" for="trs_company_<?php echo esc_attr( (string) $form_id ); ?>">
										<?php esc_html_e( 'Institución / Empresa', 'trs-leads-generator' ); ?> <span class="required">*</span>
									</label>
									<input type="text" class="trs-form-control" id="trs_company_<?php echo esc_attr( (string) $form_id ); ?>" name="company" required="required" placeholder="Ej. Corporación ABC" />
								</div>
							</div>
						<?php endif; ?>

						<?php if ( $field_job_title ) : ?>
							<div class="trs-form-col">
								<div class="trs-form-group">
									<label class="trs-form-label" for="trs_job_title_<?php echo esc_attr( (string) $form_id ); ?>">
										<?php esc_html_e( 'Cargo / Puesto', 'trs-leads-generator' ); ?> <span class="required">*</span>
									</label>
									<input type="text" class="trs-form-control" id="trs_job_title_<?php echo esc_attr( (string) $form_id ); ?>" name="job_title" required="required" placeholder="Ej. Gerente de Marketing" />
								</div>
							</div>
						<?php endif; ?>
					</div>
				<?php endif; ?>

				<!-- 5. Mensaje / Comentarios (Opcional según metabox, obligatorio si se activa) -->
				<?php if ( $field_message ) : ?>
					<div class="trs-form-group">
						<label class="trs-form-label" for="trs_message_<?php echo esc_attr( (string) $form_id ); ?>">
							<?php esc_html_e( 'Mensaje / Comentarios', 'trs-leads-generator' ); ?> <span class="required">*</span>
						</label>
						<textarea class="trs-form-control" id="trs_message_<?php echo esc_attr( (string) $form_id ); ?>" name="message" rows="3" required="required" placeholder="<?php esc_attr_e( 'Escribe aquí tu consulta o comentario...', 'trs-leads-generator' ); ?>"></textarea>
					</div>
				<?php endif; ?>

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

		// 5. Sanitize and Validate Mandatory Base Fields (Nombre, Apellido, Email).
		$first_name = isset( $_POST['first_name'] ) ? sanitize_text_field( wp_unslash( $_POST['first_name'] ) ) : '';
		if ( empty( $first_name ) ) {
			wp_send_json_error( array( 'message' => __( 'El campo Nombre es obligatorio.', 'trs-leads-generator' ) ), 400 );
		}

		$last_name = isset( $_POST['last_name'] ) ? sanitize_text_field( wp_unslash( $_POST['last_name'] ) ) : '';
		if ( empty( $last_name ) ) {
			wp_send_json_error( array( 'message' => __( 'El campo Apellido es obligatorio.', 'trs-leads-generator' ) ), 400 );
		}

		$email = isset( $_POST['email'] ) ? sanitize_email( wp_unslash( $_POST['email'] ) ) : '';
		if ( ! is_email( $email ) ) {
			wp_send_json_error( array( 'message' => __( 'Por favor ingresa un correo electrónico válido.', 'trs-leads-generator' ) ), 400 );
		}

		// 6. Validate Optional Active Fields (Obligatorios por defecto si se activaron).
		$field_phone_active     = (bool) get_post_meta( $form_id, '_trs_field_phone', true );
		$field_company_active   = (bool) get_post_meta( $form_id, '_trs_field_company', true );
		$field_job_title_active = (bool) get_post_meta( $form_id, '_trs_field_job_title', true );
		$field_message_active   = (bool) get_post_meta( $form_id, '_trs_field_message', true );

		$phone = isset( $_POST['phone'] ) ? sanitize_text_field( wp_unslash( $_POST['phone'] ) ) : '';
		if ( $field_phone_active && empty( $phone ) ) {
			wp_send_json_error( array( 'message' => __( 'El campo Teléfono / WhatsApp es obligatorio.', 'trs-leads-generator' ) ), 400 );
		}

		$company = isset( $_POST['company'] ) ? sanitize_text_field( wp_unslash( $_POST['company'] ) ) : '';
		if ( $field_company_active && empty( $company ) ) {
			wp_send_json_error( array( 'message' => __( 'El campo Institución / Empresa es obligatorio.', 'trs-leads-generator' ) ), 400 );
		}

		$job_title = isset( $_POST['job_title'] ) ? sanitize_text_field( wp_unslash( $_POST['job_title'] ) ) : '';
		if ( $field_job_title_active && empty( $job_title ) ) {
			wp_send_json_error( array( 'message' => __( 'El campo Cargo / Puesto es obligatorio.', 'trs-leads-generator' ) ), 400 );
		}

		$message = isset( $_POST['message'] ) ? sanitize_textarea_field( wp_unslash( $_POST['message'] ) ) : '';
		if ( $field_message_active && empty( $message ) ) {
			wp_send_json_error( array( 'message' => __( 'El campo Mensaje / Comentarios es obligatorio.', 'trs-leads-generator' ) ), 400 );
		}

		// UTMs.
		$utm_source   = isset( $_POST['utm_source'] ) ? sanitize_text_field( wp_unslash( $_POST['utm_source'] ) ) : '';
		$utm_medium   = isset( $_POST['utm_medium'] ) ? sanitize_text_field( wp_unslash( $_POST['utm_medium'] ) ) : '';
		$utm_campaign = isset( $_POST['utm_campaign'] ) ? sanitize_text_field( wp_unslash( $_POST['utm_campaign'] ) ) : '';
		$utm_content  = isset( $_POST['utm_content'] ) ? sanitize_text_field( wp_unslash( $_POST['utm_content'] ) ) : '';
		$utm_term     = isset( $_POST['utm_term'] ) ? sanitize_text_field( wp_unslash( $_POST['utm_term'] ) ) : '';
		$created_at   = current_time( 'mysql' );

		// 7. Insert lead into custom SQL table {$wpdb->prefix}trs_leads.
		global $wpdb;
		$table_name = $wpdb->prefix . 'trs_leads';

		$inserted = $wpdb->insert(
			$table_name,
			array(
				'form_id'      => $form_id,
				'email'        => $email,
				'first_name'   => $first_name,
				'last_name'    => $last_name,
				'phone'        => $phone,
				'company'      => $company,
				'job_title'    => $job_title,
				'message'      => $message,
				'utm_source'   => $utm_source,
				'utm_medium'   => $utm_medium,
				'utm_campaign' => $utm_campaign,
				'utm_content'  => $utm_content,
				'utm_term'     => $utm_term,
				'created_at'   => $created_at,
			),
			array( '%d', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s' )
		);

		if ( false === $inserted ) {
			// Auto-repair missing table/columns and retry insertion once.
			require_once TRS_PLUGIN_DIR . 'includes/class-trs-activator.php';
			TRS_Activator::create_tables();

			$inserted = $wpdb->insert(
				$table_name,
				array(
					'form_id'      => $form_id,
					'email'        => $email,
					'first_name'   => $first_name,
					'last_name'    => $last_name,
					'phone'        => $phone,
					'company'      => $company,
					'job_title'    => $job_title,
					'message'      => $message,
					'utm_source'   => $utm_source,
					'utm_medium'   => $utm_medium,
					'utm_campaign' => $utm_campaign,
					'utm_content'  => $utm_content,
					'utm_term'     => $utm_term,
					'created_at'   => $created_at,
				),
				array( '%d', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s' )
			);
		}

		if ( false === $inserted ) {
			error_log( '[TRS Leads Generator] Error al insertar lead: ' . $wpdb->last_error );
			wp_send_json_error( array( 'message' => __( 'Error interno al registrar el lead. Inténtalo de nuevo.', 'trs-leads-generator' ) ), 500 );
		}

		$lead_id = (int) $wpdb->insert_id;

		// 8. Push to Google Sheets (Non-blocking integration).
		$lead_data = array(
			'id'           => $lead_id,
			'form_id'      => $form_id,
			'email'        => $email,
			'first_name'   => $first_name,
			'last_name'    => $last_name,
			'phone'        => $phone,
			'company'      => $company,
			'job_title'    => $job_title,
			'message'      => $message,
			'utm_source'   => $utm_source,
			'utm_medium'   => $utm_medium,
			'utm_campaign' => $utm_campaign,
			'utm_content'  => $utm_content,
			'utm_term'     => $utm_term,
			'created_at'   => $created_at,
		);

		require_once TRS_PLUGIN_DIR . 'includes/class-trs-google-sheets.php';
		TRS_Google_Sheets::sync_lead( $lead_data );

		// 9. Determine post-conversion actions (Redirection or PDF Download).
		$type             = get_post_meta( $form_id, '_trs_form_type', true ) ?: 'performance';
		$confirmation_url = get_post_meta( $form_id, '_trs_form_confirmation_url', true );
		$pdf_resource_id  = (int) get_post_meta( $form_id, '_trs_form_pdf_resource', true );

		$response = array(
			'message' => __( '¡Registro completado con éxito! Gracias por tu interés.', 'trs-leads-generator' ),
			'lead_id' => $lead_id,
		);

		if ( 'lead_magnet' === $type ) {
			$pdf_source = get_post_meta( $form_id, '_trs_form_pdf_source', true ) ?: 'media';

			if ( 'dynamic' === $pdf_source ) {
				$pdf_content = get_post_meta( $form_id, '_trs_form_pdf_content', true );
				if ( empty( $pdf_content ) ) {
					require_once TRS_PLUGIN_DIR . 'includes/class-trs-pdf-generator.php';
					$pdf_content = TRS_PDF_Generator::get_default_html();
				}

				require_once TRS_PLUGIN_DIR . 'includes/class-trs-pdf-generator.php';
				$lead_data['form_title'] = $post->post_title;
				$pdf_result = TRS_PDF_Generator::generate_pdf(
					$pdf_content,
					$lead_data,
					sanitize_title( $post->post_title )
				);

				if ( ! is_wp_error( $pdf_result ) && ! empty( $pdf_result['file_url'] ) ) {
					$response['download_url'] = $pdf_result['file_url'];
					$response['message']      = __( '¡Registro exitoso! Tu recurso PDF personalizado ha sido compilado y está listo para descargar.', 'trs-leads-generator' );
				}
			} elseif ( 'external' === $pdf_source ) {
				$ext_pdf = get_post_meta( $form_id, '_trs_form_pdf_external_url', true );
				if ( ! empty( $ext_pdf ) ) {
					$response['download_url'] = esc_url_raw( $ext_pdf );
					$response['message']      = __( '¡Registro exitoso! Tu recurso PDF está listo para descargar.', 'trs-leads-generator' );
				}
			} elseif ( 'page' === $pdf_source ) {
				$page_id = (int) get_post_meta( $form_id, '_trs_form_pdf_page_id', true );
				if ( $page_id ) {
					$perm = get_permalink( $page_id );
					if ( $perm ) {
						$response['download_url'] = esc_url_raw( $perm );
						$response['message']      = __( '¡Registro exitoso! Accede a tu recurso a continuación.', 'trs-leads-generator' );
					}
				}
			} else {
				// Media Library or default static resource
				if ( $pdf_resource_id ) {
					$download_url = wp_get_attachment_url( $pdf_resource_id );
					if ( $download_url ) {
						$response['download_url'] = $download_url;
						$response['message']      = __( '¡Registro exitoso! Tu recurso PDF está listo para descargar.', 'trs-leads-generator' );
					}
				}
			}

			// Fallback: if download_url is still empty, check if static or external was provided.
			if ( empty( $response['download_url'] ) ) {
				$fallback_pdf_id = (int) get_post_meta( $form_id, '_trs_form_pdf_resource', true );
				if ( $fallback_pdf_id ) {
					$response['download_url'] = wp_get_attachment_url( $fallback_pdf_id );
				} else {
					$fallback_ext = get_post_meta( $form_id, '_trs_form_pdf_external_url', true );
					if ( ! empty( $fallback_ext ) ) {
						$response['download_url'] = esc_url_raw( $fallback_ext );
					}
				}
			}
		} elseif ( ( 'performance' === $type || 'event' === $type ) && ! empty( $confirmation_url ) ) {
			$response['redirect_url'] = esc_url_raw( $confirmation_url );
		}

		wp_send_json_success( $response );
	}
}
