<?php
/**
 * Register and manage Meta Boxes for trs_form post type.
 *
 * @package    TRS_Leads_Generator
 * @subpackage TRS_Leads_Generator/includes
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Handles custom meta boxes for trs_form.
 */
class TRS_Meta_Boxes {

	/**
	 * Hook into WordPress to add meta boxes.
	 */
	public function init() {
		add_action( 'add_meta_boxes', array( $this, 'add_meta_boxes' ) );
		add_action( 'save_post_trs_form', array( $this, 'save_meta_boxes' ), 10, 2 );
		add_action( 'admin_enqueue_scripts', array( $this, 'enqueue_admin_assets' ) );
	}

	/**
	 * Enqueue scripts and styles needed by metaboxes.
	 *
	 * @param string $hook The current admin page.
	 */
	public function enqueue_admin_assets( $hook ) {
		global $post_type;

		if ( 'trs_form' !== $post_type ) {
			return;
		}

		wp_enqueue_media();

		wp_enqueue_script(
			'trs-metaboxes-js',
			TRS_PLUGIN_URL . 'admin/js/trs-metaboxes.js',
			array( 'jquery' ),
			TRS_VERSION,
			true
		);
	}

	/**
	 * Register meta boxes.
	 */
	public function add_meta_boxes() {
		add_meta_box(
			'trs_form_settings_metabox',
			__( 'Configuración del Formulario (TRS Leads)', 'trs-leads-generator' ),
			array( $this, 'render_settings_metabox' ),
			'trs_form',
			'normal',
			'high'
		);

		add_meta_box(
			'trs_form_shortcode_metabox',
			__( 'Shortcode para Incrustar', 'trs-leads-generator' ),
			array( $this, 'render_shortcode_metabox' ),
			'trs_form',
			'side',
			'high'
		);
	}

	/**
	 * Render the main settings meta box.
	 *
	 * @param WP_Post $post Current post object.
	 */
	public function render_settings_metabox( $post ) {
		wp_nonce_field( 'trs_save_form_metabox', 'trs_form_metabox_nonce' );

		$type             = get_post_meta( $post->ID, '_trs_form_type', true ) ?: 'performance';
		$captcha          = (bool) get_post_meta( $post->ID, '_trs_form_captcha', true );
		$terms            = (bool) get_post_meta( $post->ID, '_trs_form_terms', true );
		$associated_page  = get_post_meta( $post->ID, '_trs_form_associated_page', true );
		$cover_image_id   = (int) get_post_meta( $post->ID, '_trs_form_cover_image', true );
		$pdf_resource_id  = (int) get_post_meta( $post->ID, '_trs_form_pdf_resource', true );
		$confirmation_url = get_post_meta( $post->ID, '_trs_form_confirmation_url', true );

		$cover_image_url  = $cover_image_id ? wp_get_attachment_image_url( $cover_image_id, 'medium' ) : '';
		$pdf_file_title   = $pdf_resource_id ? get_the_title( $pdf_resource_id ) : '';
		?>
		<table class="form-table trs-metabox-table">
			<tbody>
				<!-- 1. Tipo de Formulario -->
				<tr>
					<th scope="row">
						<label for="trs_form_type"><?php esc_html_e( 'Tipo de Formulario', 'trs-leads-generator' ); ?></label>
					</th>
					<td>
						<select name="trs_form_type" id="trs_form_type" class="regular-text">
							<option value="performance" <?php selected( $type, 'performance' ); ?>>
								<?php esc_html_e( 'Performance (Conversión Directa)', 'trs-leads-generator' ); ?>
							</option>
							<option value="lead_magnet" <?php selected( $type, 'lead_magnet' ); ?>>
								<?php esc_html_e( 'Lead Magnet (Descarga de Recurso PDF)', 'trs-leads-generator' ); ?>
							</option>
							<option value="event" <?php selected( $type, 'event' ); ?>>
								<?php esc_html_e( 'Evento (Registro a Webinar / Conferencia)', 'trs-leads-generator' ); ?>
							</option>
						</select>
						<p class="description">
							<?php esc_html_e( 'Determina la lógica de entrega (redirección o descarga de PDF).', 'trs-leads-generator' ); ?>
						</p>
					</td>
				</tr>

				<!-- 2. Captcha -->
				<tr>
					<th scope="row">
						<label for="trs_form_captcha"><?php esc_html_e( 'Protección Antispam / Captcha', 'trs-leads-generator' ); ?></label>
					</th>
					<td>
						<label>
							<input type="checkbox" name="trs_form_captcha" id="trs_form_captcha" value="1" <?php checked( $captcha, true ); ?> />
							<?php esc_html_e( 'Activar validación antispam en este formulario', 'trs-leads-generator' ); ?>
						</label>
					</td>
				</tr>

				<!-- 3. Términos y Condiciones -->
				<tr>
					<th scope="row">
						<label for="trs_form_terms"><?php esc_html_e( 'Términos y Condiciones', 'trs-leads-generator' ); ?></label>
					</th>
					<td>
						<label>
							<input type="checkbox" name="trs_form_terms" id="trs_form_terms" value="1" <?php checked( $terms, true ); ?> />
							<?php esc_html_e( 'Exigir casilla obligatoria de aceptación de términos y privacidad', 'trs-leads-generator' ); ?>
						</label>
					</td>
				</tr>

				<!-- 3.1 Campos del Formulario (Configuración dinámica) -->
				<?php
				$field_phone     = (bool) get_post_meta( $post->ID, '_trs_field_phone', true );
				$field_company   = (bool) get_post_meta( $post->ID, '_trs_field_company', true );
				$field_job_title = (bool) get_post_meta( $post->ID, '_trs_field_job_title', true );
				$field_message   = (bool) get_post_meta( $post->ID, '_trs_field_message', true );
				?>
				<tr>
					<th scope="row">
						<label><?php esc_html_e( 'Campos del Formulario', 'trs-leads-generator' ); ?></label>
					</th>
					<td>
						<fieldset style="background: #f8fafc; border: 1px solid #e2e8f0; border-radius: 8px; padding: 14px 18px;">
							<legend style="font-weight: 600; color: #1e293b; padding: 0 6px;">
								<?php esc_html_e( 'Selecciona los campos a solicitar:', 'trs-leads-generator' ); ?>
							</legend>

							<p style="margin-top: 4px; color: #64748b; font-size: 13px;">
								<em><?php esc_html_e( '📌 Nota: Si un campo se activa, es obligatorio por defecto para el lead.', 'trs-leads-generator' ); ?></em>
							</p>

							<div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(220px, 1fr)); gap: 10px; margin-top: 10px;">
								<!-- Fijos e Inalterables -->
								<label style="cursor: not-allowed; color: #334155;">
									<input type="checkbox" checked="checked" disabled="disabled" />
									<strong><?php esc_html_e( 'Nombre', 'trs-leads-generator' ); ?></strong>
									<span style="color: #ef4444;">*</span> <small style="color: #94a3b8;">(Requerido)</small>
								</label>

								<label style="cursor: not-allowed; color: #334155;">
									<input type="checkbox" checked="checked" disabled="disabled" />
									<strong><?php esc_html_e( 'Apellido', 'trs-leads-generator' ); ?></strong>
									<span style="color: #ef4444;">*</span> <small style="color: #94a3b8;">(Requerido)</small>
								</label>

								<label style="cursor: not-allowed; color: #334155;">
									<input type="checkbox" checked="checked" disabled="disabled" />
									<strong><?php esc_html_e( 'Correo Electrónico', 'trs-leads-generator' ); ?></strong>
									<span style="color: #ef4444;">*</span> <small style="color: #94a3b8;">(Requerido)</small>
								</label>

								<!-- Opcionales a activar -->
								<label style="cursor: pointer; color: #1e293b;">
									<input type="checkbox" name="trs_field_phone" value="1" <?php checked( $field_phone, true ); ?> />
									<strong><?php esc_html_e( 'Teléfono / WhatsApp', 'trs-leads-generator' ); ?></strong>
									<span style="color: #ef4444;">*</span>
								</label>

								<label style="cursor: pointer; color: #1e293b;">
									<input type="checkbox" name="trs_field_company" value="1" <?php checked( $field_company, true ); ?> />
									<strong><?php esc_html_e( 'Institución', 'trs-leads-generator' ); ?></strong>
									<span style="color: #ef4444;">*</span>
								</label>

								<label style="cursor: pointer; color: #1e293b;">
									<input type="checkbox" name="trs_field_job_title" value="1" <?php checked( $field_job_title, true ); ?> />
									<strong><?php esc_html_e( 'Cargo', 'trs-leads-generator' ); ?></strong>
									<span style="color: #ef4444;">*</span>
								</label>

								<label style="cursor: pointer; color: #1e293b;">
									<input type="checkbox" name="trs_field_message" value="1" <?php checked( $field_message, true ); ?> />
									<strong><?php esc_html_e( 'Mensaje / Comentarios', 'trs-leads-generator' ); ?></strong>
									<span style="color: #ef4444;">*</span>
								</label>
							</div>
						</fieldset>
					</td>
				</tr>

				<!-- 4. Página Web Asociada -->
				<tr>
					<th scope="row">
						<label for="trs_form_associated_page"><?php esc_html_e( 'Página Web Asociada', 'trs-leads-generator' ); ?></label>
					</th>
					<td>
						<input type="text" name="trs_form_associated_page" id="trs_form_associated_page" value="<?php echo esc_attr( $associated_page ); ?>" class="large-text" placeholder="https://misitio.com/landing o ID de página interna" />
						<p class="description">
							<?php esc_html_e( 'URL externa o ID de página interna donde se desplegará principalmente este formulario.', 'trs-leads-generator' ); ?>
						</p>
					</td>
				</tr>

				<!-- 5. Imagen de Portada -->
				<tr>
					<th scope="row">
						<label><?php esc_html_e( 'Imagen de Portada', 'trs-leads-generator' ); ?></label>
					</th>
					<td>
						<input type="hidden" name="trs_form_cover_image" id="trs_form_cover_image" value="<?php echo esc_attr( $cover_image_id ?: '' ); ?>" />
						<div style="margin-bottom: 10px;">
							<img id="trs-cover-preview" src="<?php echo esc_url( $cover_image_url ); ?>" style="max-width: 200px; height: auto; border: 1px solid #ddd; padding: 4px; border-radius: 4px; display: <?php echo $cover_image_url ? 'block' : 'none'; ?>;" alt="<?php esc_attr_e( 'Portada', 'trs-leads-generator' ); ?>" />
						</div>
						<button type="button" class="button" id="trs-select-cover-btn"><?php esc_html_e( 'Seleccionar Imagen', 'trs-leads-generator' ); ?></button>
						<button type="button" class="button button-link-delete" id="trs-remove-cover-btn" style="display: <?php echo $cover_image_id ? 'inline-block' : 'none'; ?>;"><?php esc_html_e( 'Quitar Imagen', 'trs-leads-generator' ); ?></button>
						<p class="description"><?php esc_html_e( 'Imagen representativa para landings automáticas y anuncios de pauta.', 'trs-leads-generator' ); ?></p>
					</td>
				</tr>

				<!-- 6. Archivo PDF (Lead Magnet) -->
				<tr>
					<th scope="row">
						<label><?php esc_html_e( 'Recurso PDF (Lead Magnet)', 'trs-leads-generator' ); ?></label>
					</th>
					<td>
						<input type="hidden" name="trs_form_pdf_resource" id="trs_form_pdf_resource" value="<?php echo esc_attr( $pdf_resource_id ?: '' ); ?>" />
						<p id="trs-pdf-filename" style="font-weight: 600; display: <?php echo $pdf_resource_id ? 'block' : 'none'; ?>;">
							📄 <?php echo esc_html( $pdf_file_title ); ?>
						</p>
						<button type="button" class="button" id="trs-select-pdf-btn"><?php esc_html_e( 'Seleccionar PDF Estático', 'trs-leads-generator' ); ?></button>
						<button type="button" class="button button-link-delete" id="trs-remove-pdf-btn" style="display: <?php echo $pdf_resource_id ? 'inline-block' : 'none'; ?>;"><?php esc_html_e( 'Quitar PDF', 'trs-leads-generator' ); ?></button>
						<p class="description"><?php esc_html_e( 'Archivo PDF estático desde la Biblioteca de Medios.', 'trs-leads-generator' ); ?></p>
					</td>
				</tr>

				<!-- 6.1 Plantilla HTML Dinámica para PDF (Dompdf) -->
				<?php
				$pdf_template = get_post_meta( $post->ID, '_trs_form_pdf_template', true );
				require_once TRS_PLUGIN_DIR . 'includes/class-trs-pdf-generator.php';
				$available_templates = TRS_PDF_Generator::get_templates();
				?>
				<tr>
					<th scope="row">
						<label for="trs_form_pdf_template"><?php esc_html_e( 'Generar PDF desde Plantilla HTML (Dompdf)', 'trs-leads-generator' ); ?></label>
					</th>
					<td>
						<select name="trs_form_pdf_template" id="trs_form_pdf_template" class="regular-text">
							<option value=""><?php esc_html_e( '-- Usar archivo estático de arriba --', 'trs-leads-generator' ); ?></option>
							<?php foreach ( $available_templates as $tpl_key => $tpl_data ) : ?>
								<option value="<?php echo esc_attr( $tpl_key ); ?>" <?php selected( $pdf_template, $tpl_key ); ?>>
									<?php echo esc_html( $tpl_data['title'] ); ?>
								</option>
							<?php endforeach; ?>
						</select>
						<p class="description">
							<?php esc_html_e( 'Si seleccionas una plantilla HTML, el plugin compilará un PDF personalizado en tiempo real con los datos de cada lead (nombre, fecha, etc.) usando Dompdf.', 'trs-leads-generator' ); ?>
						</p>
					</td>
				</tr>

				<!-- 7. URL de Confirmación (Condicional para Performance y Event) -->
				<tr id="trs-confirmation-url-wrap">
					<th scope="row">
						<label for="trs_form_confirmation_url"><?php esc_html_e( 'URL de Confirmación (Redirección)', 'trs-leads-generator' ); ?></label>
					</th>
					<td>
						<input type="url" name="trs_form_confirmation_url" id="trs_form_confirmation_url" value="<?php echo esc_url( $confirmation_url ); ?>" class="large-text" placeholder="https://misitio.com/gracias" />
						<p class="description">
							<?php esc_html_e( 'URL a la que se redirigirá al usuario tras enviar el formulario (Activa para tipos Performance y Evento).', 'trs-leads-generator' ); ?>
						</p>
					</td>
				</tr>
			</tbody>
		</table>
		<?php
	}

	/**
	 * Render the side meta box displaying modular shortcodes.
	 *
	 * @param WP_Post $post Current post object.
	 */
	public function render_shortcode_metabox( $post ) {
		$form_id = esc_attr( (string) $post->ID );
		?>
		<div style="margin-bottom: 14px;">
			<label style="font-weight: 600; display: block; margin-bottom: 4px;">
				📋 <?php esc_html_e( '1. Solo Formulario:', 'trs-leads-generator' ); ?>
			</label>
			<input type="text" readonly="readonly" value="[trs_leads_generator_form id=&quot;<?php echo $form_id; ?>&quot;]" class="large-text code" onclick="this.select();" />
			<p class="description" style="margin-top: 2px;">
				<?php esc_html_e( 'Renderiza solo los inputs y botón de envío.', 'trs-leads-generator' ); ?>
			</p>
		</div>

		<div style="margin-bottom: 14px;">
			<label style="font-weight: 600; display: block; margin-bottom: 4px;">
				🖼️ <?php esc_html_e( '2. Solo Imagen de Portada:', 'trs-leads-generator' ); ?>
			</label>
			<input type="text" readonly="readonly" value="[trs_leads_generator_image id=&quot;<?php echo $form_id; ?>&quot;]" class="large-text code" onclick="this.select();" />
			<p class="description" style="margin-top: 2px;">
				<?php esc_html_e( 'Renderiza solo la imagen configurada.', 'trs-leads-generator' ); ?>
			</p>
		</div>

		<div>
			<label style="font-weight: 600; display: block; margin-bottom: 4px;">
				📦 <?php esc_html_e( '3. Componente Completo:', 'trs-leads-generator' ); ?>
			</label>
			<input type="text" readonly="readonly" value="[trs_form id=&quot;<?php echo $form_id; ?>&quot;]" class="large-text code" onclick="this.select();" />
			<p class="description" style="margin-top: 2px;">
				<?php esc_html_e( 'Renderiza imagen + formulario juntos.', 'trs-leads-generator' ); ?>
			</p>
		</div>

		<hr style="margin: 14px 0; border: 0; border-top: 1px solid #e2e8f0;" />
		<p class="description">
			💡 <em><?php esc_html_e( 'Puedes colocar los shortcodes en diferentes columnas o bloques según tu diseño.', 'trs-leads-generator' ); ?></em>
		</p>
		<?php
	}

	/**
	 * Save meta box data.
	 *
	 * @param int     $post_id Post ID.
	 * @param WP_Post $post    Post object.
	 */
	public function save_meta_boxes( $post_id, $post ) {
		// Nonce check.
		if ( ! isset( $_POST['trs_form_metabox_nonce'] ) || ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['trs_form_metabox_nonce'] ) ), 'trs_save_form_metabox' ) ) {
			return;
		}

		// Prevent autosave overrides.
		if ( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE ) {
			return;
		}

		// Check permissions.
		if ( ! current_user_can( 'edit_post', $post_id ) ) {
			return;
		}

		// 1. Tipo
		$allowed_types = array( 'performance', 'lead_magnet', 'event' );
		$type = isset( $_POST['trs_form_type'] ) ? sanitize_text_field( wp_unslash( $_POST['trs_form_type'] ) ) : 'performance';
		if ( ! in_array( $type, $allowed_types, true ) ) {
			$type = 'performance';
		}
		update_post_meta( $post_id, '_trs_form_type', $type );

		// 2. Captcha
		$captcha = ! empty( $_POST['trs_form_captcha'] ) ? 1 : 0;
		update_post_meta( $post_id, '_trs_form_captcha', $captcha );

		// 3. Términos
		$terms = ! empty( $_POST['trs_form_terms'] ) ? 1 : 0;
		update_post_meta( $post_id, '_trs_form_terms', $terms );

		// 3.1 Campos Activos del Formulario (Obligatorios por defecto si se marcan)
		$phone     = ! empty( $_POST['trs_field_phone'] ) ? 1 : 0;
		$company   = ! empty( $_POST['trs_field_company'] ) ? 1 : 0;
		$job_title = ! empty( $_POST['trs_field_job_title'] ) ? 1 : 0;
		$message   = ! empty( $_POST['trs_field_message'] ) ? 1 : 0;

		update_post_meta( $post_id, '_trs_field_phone', $phone );
		update_post_meta( $post_id, '_trs_field_company', $company );
		update_post_meta( $post_id, '_trs_field_job_title', $job_title );
		update_post_meta( $post_id, '_trs_field_message', $message );

		// 4. Página Asociada
		$associated = isset( $_POST['trs_form_associated_page'] ) ? sanitize_text_field( wp_unslash( $_POST['trs_form_associated_page'] ) ) : '';
		update_post_meta( $post_id, '_trs_form_associated_page', $associated );

		// 5. Imagen de Portada
		$cover_id = isset( $_POST['trs_form_cover_image'] ) ? absint( $_POST['trs_form_cover_image'] ) : 0;
		update_post_meta( $post_id, '_trs_form_cover_image', $cover_id );

		// 6. Recurso PDF
		$pdf_id = isset( $_POST['trs_form_pdf_resource'] ) ? absint( $_POST['trs_form_pdf_resource'] ) : 0;
		update_post_meta( $post_id, '_trs_form_pdf_resource', $pdf_id );

		// 6.1 Plantilla HTML Dinámica de PDF
		$pdf_tpl = isset( $_POST['trs_form_pdf_template'] ) ? sanitize_key( wp_unslash( $_POST['trs_form_pdf_template'] ) ) : '';
		update_post_meta( $post_id, '_trs_form_pdf_template', $pdf_tpl );

		// 7. URL de Confirmación
		$confirmation_url = isset( $_POST['trs_form_confirmation_url'] ) ? esc_url_raw( wp_unslash( $_POST['trs_form_confirmation_url'] ) ) : '';
		update_post_meta( $post_id, '_trs_form_confirmation_url', $confirmation_url );
	}
}
