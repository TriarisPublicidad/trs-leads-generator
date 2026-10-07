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
	 * Hook into WordPress to add meta boxes and list columns.
	 */
	public function init() {
		add_action( 'add_meta_boxes', array( $this, 'add_meta_boxes' ) );
		add_action( 'save_post_trs_form', array( $this, 'save_meta_boxes' ), 10, 2 );
		add_action( 'admin_enqueue_scripts', array( $this, 'enqueue_admin_assets' ) );

		// Custom columns on CPT trs_form list screen (edit.php?post_type=trs_form).
		add_filter( 'manage_trs_form_posts_columns', array( $this, 'set_custom_columns' ) );
		add_action( 'manage_trs_form_posts_custom_column', array( $this, 'render_custom_column' ), 10, 2 );
	}

	/**
	 * Define custom columns for trs_form list screen.
	 *
	 * @param array $columns Existing columns.
	 * @return array
	 */
	public function set_custom_columns( $columns ) {
		$new_columns = array();
		foreach ( $columns as $key => $title ) {
			$new_columns[ $key ] = $title;
			if ( 'title' === $key ) {
				$new_columns['shortcode']   = __( 'Shortcode', 'trs-leads-generator' );
				$new_columns['form_type']   = __( 'Tipo', 'trs-leads-generator' );
				$new_columns['leads_count'] = __( 'Leads Registrados', 'trs-leads-generator' );
			}
		}
		return $new_columns;
	}

	/**
	 * Render content for custom columns in trs_form list screen.
	 *
	 * @param string $column  Column name.
	 * @param int    $post_id Current post ID.
	 */
	public function render_custom_column( $column, $post_id ) {
		global $wpdb;

		if ( 'shortcode' === $column ) {
			echo '<input type="text" readonly="readonly" value="[trs_form id=&quot;' . esc_attr( (string) $post_id ) . '&quot;]" class="small-text code" style="width: 175px;" onclick="this.select();" />';
		} elseif ( 'form_type' === $column ) {
			$type = get_post_meta( $post_id, '_trs_form_type', true ) ?: 'performance';
			$labels = array(
				'performance' => __( 'Performance', 'trs-leads-generator' ),
				'lead_magnet' => __( 'Lead Magnet', 'trs-leads-generator' ),
				'event'       => __( 'Evento', 'trs-leads-generator' ),
			);
			$badge_class = 'lead_magnet' === $type ? 'trs-badge-warning' : ( 'event' === $type ? 'trs-badge' : 'trs-badge-success' );
			echo '<span class="trs-badge ' . esc_attr( $badge_class ) . '">' . esc_html( $labels[ $type ] ?? $type ) . '</span>';
		} elseif ( 'leads_count' === $column ) {
			$leads_table = $wpdb->prefix . 'trs_leads';
			$count       = (int) $wpdb->get_var( $wpdb->prepare( "SELECT COUNT(id) FROM {$leads_table} WHERE form_id = %d", $post_id ) );
			$view_url    = add_query_arg( array( 'page' => 'trs-leads', 'form_id' => $post_id ), admin_url( 'admin.php' ) );
			$csv_url     = add_query_arg(
				array(
					'action'           => 'trs_export_leads_csv',
					'form_id'          => $post_id,
					'trs_export_nonce' => wp_create_nonce( 'trs_export_leads_action' ),
				),
				admin_url( 'admin-post.php' )
			);

			if ( $count > 0 ) {
				echo '<a href="' . esc_url( $view_url ) . '" class="button button-small" style="font-weight: 600; margin-right: 5px;">' . sprintf( esc_html__( '%d Registrados', 'trs-leads-generator' ), $count ) . '</a>';
				echo '<a href="' . esc_url( $csv_url ) . '" title="' . esc_attr__( 'Descargar CSV', 'trs-leads-generator' ) . '" style="text-decoration: none; font-size: 14px;">📥</a>';
			} else {
				echo '<span style="color: #94a3b8; font-size: 12px;">' . esc_html__( '0 registrados', 'trs-leads-generator' ) . '</span>';
			}
		}
	}

	/**
	 * Enqueue scripts and styles needed by metaboxes.
	 *
	 * @param string $hook The current admin page.
	 */
	public function enqueue_admin_assets( $hook ) {
		global $post_type, $post;

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

		require_once TRS_PLUGIN_DIR . 'includes/class-trs-pdf-generator.php';
		$raw_tpls  = TRS_PDF_Generator::get_templates();
		$tpl_htmls = array(
			'executive_whitepaper' => $raw_tpls['executive_whitepaper']['html'] ?? '',
			'strategy_checklist'   => $raw_tpls['strategy_checklist']['html'] ?? '',
			'event_ticket'         => $raw_tpls['event_ticket']['html'] ?? '',
			'default'              => TRS_PDF_Generator::get_default_html(),
		);

		wp_localize_script(
			'trs-metaboxes-js',
			'trsMetaboxData',
			array(
				'ajaxUrl'   => admin_url( 'admin-ajax.php' ),
				'postId'    => $post ? $post->ID : 0,
				'nonce'     => wp_create_nonce( 'trs_preview_draft_pdf_nonce' ),
				'templates' => $tpl_htmls,
			)
		);
	}

	/**
	 * Register meta boxes.
	 */
	public function add_meta_boxes() {
		// 1. Leads registrados en este formulario.
		add_meta_box(
			'trs_form_leads_metabox',
			__( 'Registrados de este Formulario', 'trs-leads-generator' ),
			array( $this, 'render_leads_metabox' ),
			'trs_form',
			'normal',
			'high'
		);

		// 2. Configuración general del formulario.
		add_meta_box(
			'trs_form_settings_metabox',
			__( 'Configuración del Formulario (TRS Leads)', 'trs-leads-generator' ),
			array( $this, 'render_settings_metabox' ),
			'trs_form',
			'normal',
			'high'
		);

		// 3. Shortcodes laterales.
		add_meta_box(
			'trs_form_shortcode_metabox',
			__( 'Shortcodes para Incrustar', 'trs-leads-generator' ),
			array( $this, 'render_shortcode_metabox' ),
			'trs_form',
			'side',
			'high'
		);
	}

	/**
	 * Render leads list for THIS specific form.
	 *
	 * @param WP_Post $post Current post object.
	 */
	public function render_leads_metabox( $post ) {
		global $wpdb;
		$leads_table = $wpdb->prefix . 'trs_leads';
		$form_id     = $post->ID;

		$total_leads  = (int) $wpdb->get_var( $wpdb->prepare( "SELECT COUNT(id) FROM {$leads_table} WHERE form_id = %d", $form_id ) );
		$recent_leads = $wpdb->get_results( $wpdb->prepare( "SELECT * FROM {$leads_table} WHERE form_id = %d ORDER BY id DESC LIMIT 5", $form_id ) );

		$view_all_url = add_query_arg( array( 'page' => 'trs-leads', 'form_id' => $form_id ), admin_url( 'admin.php' ) );
		$export_url   = add_query_arg(
			array(
				'action'           => 'trs_export_leads_csv',
				'form_id'          => $form_id,
				'trs_export_nonce' => wp_create_nonce( 'trs_export_leads_action' ),
			),
			admin_url( 'admin-post.php' )
		);
		?>
		<div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 14px; flex-wrap: wrap; gap: 10px;">
			<div>
				<span style="font-size: 15px; font-weight: 600; color: #1e293b;">
					👥 <?php printf( esc_html__( 'Total de personas registradas en este formulario: %d', 'trs-leads-generator' ), $total_leads ); ?>
				</span>
			</div>
			<div style="display: flex; gap: 8px;">
				<a href="<?php echo esc_url( $view_all_url ); ?>" class="button button-primary">
					👉 <?php esc_html_e( 'Ver Todos los Registrados', 'trs-leads-generator' ); ?>
				</a>
				<?php if ( $total_leads > 0 ) : ?>
					<a href="<?php echo esc_url( $export_url ); ?>" class="button button-secondary">
						📥 <?php esc_html_e( 'Descargar CSV', 'trs-leads-generator' ); ?>
					</a>
				<?php endif; ?>
			</div>
		</div>

		<?php if ( $total_leads > 0 ) : ?>
			<table class="wp-list-table widefat fixed striped" style="margin-top: 10px;">
				<thead>
					<tr>
						<th style="width: 130px;"><?php esc_html_e( 'Fecha', 'trs-leads-generator' ); ?></th>
						<th><?php esc_html_e( 'Nombre', 'trs-leads-generator' ); ?></th>
						<th><?php esc_html_e( 'Correo Electrónico', 'trs-leads-generator' ); ?></th>
						<th><?php esc_html_e( 'Teléfono', 'trs-leads-generator' ); ?></th>
						<th><?php esc_html_e( 'Institución / Cargo', 'trs-leads-generator' ); ?></th>
						<th><?php esc_html_e( 'Origen (UTM)', 'trs-leads-generator' ); ?></th>
					</tr>
				</thead>
				<tbody>
					<?php foreach ( $recent_leads as $lead ) : ?>
						<tr>
							<td><small><?php echo esc_html( gmdate( 'd/m/Y H:i', strtotime( $lead->created_at ) ) ); ?></small></td>
							<td><strong><?php echo esc_html( trim( $lead->first_name . ' ' . $lead->last_name ) ); ?></strong></td>
							<td><a href="mailto:<?php echo esc_attr( $lead->email ); ?>"><?php echo esc_html( $lead->email ); ?></a></td>
							<td><?php echo esc_html( ! empty( $lead->phone ) ? $lead->phone : '—' ); ?></td>
							<td><?php echo esc_html( ! empty( $lead->company ) ? $lead->company : ( ! empty( $lead->job_title ) ? $lead->job_title : '—' ) ); ?></td>
							<td><?php echo esc_html( ! empty( $lead->utm_source ) ? $lead->utm_source : 'Directo' ); ?></td>
						</tr>
					<?php endforeach; ?>
				</tbody>
			</table>
			<p style="margin-top: 8px; font-size: 12px; color: #64748b;">
				<?php printf( esc_html__( 'Mostrando los últimos %d registrados de este formulario.', 'trs-leads-generator' ), count( $recent_leads ) ); ?>
				<a href="<?php echo esc_url( $view_all_url ); ?>" style="font-weight: 600;"><?php esc_html_e( 'Abrir lista completa con búsqueda y filtros &rarr;', 'trs-leads-generator' ); ?></a>
			</p>
		<?php else : ?>
			<div style="background: #f8fafc; border: 1px dashed #cbd5e1; border-radius: 6px; padding: 25px; text-align: center; color: #64748b;">
				<p style="font-size: 24px; margin: 0 0 6px 0;">📭</p>
				<p style="margin: 0; font-size: 14px; font-weight: 500;">
					<?php esc_html_e( 'Aún no hay registros en este formulario.', 'trs-leads-generator' ); ?>
				</p>
				<p style="margin: 4px 0 0 0; font-size: 12px;">
					<?php esc_html_e( 'En cuanto los visitantes completen el formulario en tu web, aparecerán reflejados aquí automáticamente.', 'trs-leads-generator' ); ?>
				</p>
			</div>
		<?php endif; ?>
		<?php
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
		$confirmation_url = get_post_meta( $post->ID, '_trs_form_confirmation_url', true );

		// Imagen de Portada settings.
		$cover_source       = get_post_meta( $post->ID, '_trs_form_cover_source', true ) ?: 'media';
		$cover_image_id     = (int) get_post_meta( $post->ID, '_trs_form_cover_image', true );
		$cover_external_url = get_post_meta( $post->ID, '_trs_form_cover_external_url', true );
		$cover_post_id      = (int) get_post_meta( $post->ID, '_trs_form_cover_post_id', true );
		$cover_image_url    = $cover_image_id ? wp_get_attachment_image_url( $cover_image_id, 'medium' ) : '';

		// PDF settings.
		$pdf_source       = get_post_meta( $post->ID, '_trs_form_pdf_source', true ) ?: 'media';
		$pdf_resource_id  = (int) get_post_meta( $post->ID, '_trs_form_pdf_resource', true );
		$pdf_external_url = get_post_meta( $post->ID, '_trs_form_pdf_external_url', true );
		$pdf_page_id      = (int) get_post_meta( $post->ID, '_trs_form_pdf_page_id', true );
		$pdf_content      = get_post_meta( $post->ID, '_trs_form_pdf_content', true );
		$pdf_file_title   = $pdf_resource_id ? get_the_title( $pdf_resource_id ) : '';

		if ( empty( $pdf_content ) ) {
			require_once TRS_PLUGIN_DIR . 'includes/class-trs-pdf-generator.php';
			$pdf_content = TRS_PDF_Generator::get_default_html();
		}

		// Fetch published pages and posts for dropdown selectors.
		$site_pages = get_posts(
			array(
				'post_type'      => 'page',
				'posts_per_page' => 200,
				'post_status'    => 'publish',
				'orderby'        => 'title',
				'order'          => 'ASC',
			)
		);
		$site_posts = get_posts(
			array(
				'post_type'      => 'post',
				'posts_per_page' => 100,
				'post_status'    => 'publish',
				'orderby'        => 'title',
				'order'          => 'ASC',
			)
		);
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
							<?php esc_html_e( 'Determina la lógica de entrega (redirección a URL o entrega de PDF).', 'trs-leads-generator' ); ?>
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
						<label for="trs_form_cover_source"><?php esc_html_e( 'Imagen de Portada', 'trs-leads-generator' ); ?></label>
					</th>
					<td>
						<div style="margin-bottom: 10px;">
							<label style="font-weight: 600; margin-right: 8px;"><?php esc_html_e( 'Origen de la Imagen:', 'trs-leads-generator' ); ?></label>
							<select name="trs_form_cover_source" id="trs_form_cover_source" class="regular-text">
								<option value="media" <?php selected( $cover_source, 'media' ); ?>>
									<?php esc_html_e( 'Biblioteca de Medios (Subir o seleccionar)', 'trs-leads-generator' ); ?>
								</option>
								<option value="external" <?php selected( $cover_source, 'external' ); ?>>
									<?php esc_html_e( 'URL Externa (Enlace directo a imagen)', 'trs-leads-generator' ); ?>
								</option>
								<option value="post" <?php selected( $cover_source, 'post' ); ?>>
									<?php esc_html_e( 'Página o Entrada (Usar su imagen destacada)', 'trs-leads-generator' ); ?>
								</option>
							</select>
						</div>

						<!-- Opción A: Biblioteca de Medios -->
						<div id="trs-cover-source-media" class="trs-cover-source-box" style="display: <?php echo 'media' === $cover_source ? 'block' : 'none'; ?>;">
							<input type="hidden" name="trs_form_cover_image" id="trs_form_cover_image" value="<?php echo esc_attr( $cover_image_id ?: '' ); ?>" />
							<div style="margin-bottom: 10px;">
								<img id="trs-cover-preview" src="<?php echo esc_url( $cover_image_url ); ?>" style="max-width: 220px; height: auto; border: 1px solid #ddd; padding: 4px; border-radius: 4px; display: <?php echo $cover_image_url ? 'block' : 'none'; ?>;" alt="<?php esc_attr_e( 'Portada', 'trs-leads-generator' ); ?>" />
							</div>
							<button type="button" class="button" id="trs-select-cover-btn"><?php esc_html_e( 'Seleccionar Imagen', 'trs-leads-generator' ); ?></button>
							<button type="button" class="button button-link-delete" id="trs-remove-cover-btn" style="display: <?php echo $cover_image_id ? 'inline-block' : 'none'; ?>;"><?php esc_html_e( 'Quitar Imagen', 'trs-leads-generator' ); ?></button>
						</div>

						<!-- Opción B: URL Externa -->
						<div id="trs-cover-source-external" class="trs-cover-source-box" style="display: <?php echo 'external' === $cover_source ? 'block' : 'none'; ?>;">
							<input type="url" name="trs_form_cover_external_url" id="trs_form_cover_external_url" value="<?php echo esc_url( $cover_external_url ); ?>" class="large-text" placeholder="https://ejemplo.com/imagenes/mi-portada.jpg" />
							<p class="description"><?php esc_html_e( 'Pega aquí la URL completa de la imagen externa alojada en cualquier servidor o CDN.', 'trs-leads-generator' ); ?></p>
							<div style="margin-top: 10px;">
								<img id="trs-cover-ext-preview" src="<?php echo esc_url( $cover_external_url ); ?>" style="max-width: 220px; height: auto; border: 1px solid #ddd; padding: 4px; border-radius: 4px; display: <?php echo ! empty( $cover_external_url ) ? 'block' : 'none'; ?>;" alt="" />
							</div>
						</div>

						<!-- Opción C: Página o Entrada -->
						<div id="trs-cover-source-post" class="trs-cover-source-box" style="display: <?php echo 'post' === $cover_source ? 'block' : 'none'; ?>;">
							<select name="trs_form_cover_post_id" id="trs_form_cover_post_id" class="large-text">
								<option value=""><?php esc_html_e( '-- Selecciona una Página o Entrada --', 'trs-leads-generator' ); ?></option>
								<optgroup label="<?php esc_attr_e( 'Páginas', 'trs-leads-generator' ); ?>">
									<?php foreach ( $site_pages as $p ) : ?>
										<option value="<?php echo esc_attr( (string) $p->ID ); ?>" <?php selected( $cover_post_id, $p->ID ); ?>>
											<?php echo esc_html( $p->post_title ); ?> (ID: <?php echo esc_html( (string) $p->ID ); ?>)
										</option>
									<?php endforeach; ?>
								</optgroup>
								<optgroup label="<?php esc_attr_e( 'Entradas (Posts)', 'trs-leads-generator' ); ?>">
									<?php foreach ( $site_posts as $p ) : ?>
										<option value="<?php echo esc_attr( (string) $p->ID ); ?>" <?php selected( $cover_post_id, $p->ID ); ?>>
											<?php echo esc_html( $p->post_title ); ?> (ID: <?php echo esc_html( (string) $p->ID ); ?>)
										</option>
									<?php endforeach; ?>
								</optgroup>
							</select>
							<p class="description"><?php esc_html_e( 'Se utilizará automáticamente la imagen destacada de la página o entrada seleccionada.', 'trs-leads-generator' ); ?></p>
						</div>
					</td>
				</tr>

				<!-- 6. PDF -->
				<tr>
					<th scope="row">
						<label for="trs_form_pdf_source"><?php esc_html_e( 'PDF', 'trs-leads-generator' ); ?></label>
					</th>
					<td>
						<div style="margin-bottom: 12px;">
							<label style="font-weight: 600; margin-right: 8px;"><?php esc_html_e( 'Origen del PDF:', 'trs-leads-generator' ); ?></label>
							<select name="trs_form_pdf_source" id="trs_form_pdf_source" class="regular-text">
								<option value="media" <?php selected( $pdf_source, 'media' ); ?>>
									<?php esc_html_e( 'Biblioteca de Medios (Subir o seleccionar PDF)', 'trs-leads-generator' ); ?>
								</option>
								<option value="external" <?php selected( $pdf_source, 'external' ); ?>>
									<?php esc_html_e( 'URL Externa (Google Drive, Dropbox, Enlace externo)', 'trs-leads-generator' ); ?>
								</option>
								<option value="page" <?php selected( $pdf_source, 'page' ); ?>>
									<?php esc_html_e( 'Página o Entrada Interna (Página de agradecimiento / descarga)', 'trs-leads-generator' ); ?>
								</option>
								<option value="dynamic" <?php selected( $pdf_source, 'dynamic' ); ?>>
									<?php esc_html_e( 'Generar PDF Personalizado con Editor (Dompdf)', 'trs-leads-generator' ); ?>
								</option>
							</select>
						</div>

						<!-- Opción 1: Biblioteca de Medios -->
						<div id="trs-pdf-source-media" class="trs-pdf-source-box" style="display: <?php echo 'media' === $pdf_source ? 'block' : 'none'; ?>;">
							<input type="hidden" name="trs_form_pdf_resource" id="trs_form_pdf_resource" value="<?php echo esc_attr( $pdf_resource_id ?: '' ); ?>" />
							<p id="trs-pdf-filename" style="font-weight: 600; display: <?php echo $pdf_resource_id ? 'block' : 'none'; ?>;">
								📄 <?php echo esc_html( $pdf_file_title ); ?>
								<?php if ( $pdf_resource_id ) : ?>
									<a href="<?php echo esc_url( wp_get_attachment_url( $pdf_resource_id ) ); ?>" target="_blank" style="margin-left: 8px; font-weight: normal;"><?php esc_html_e( '👁️ Ver archivo', 'trs-leads-generator' ); ?></a>
								<?php endif; ?>
							</p>
							<button type="button" class="button" id="trs-select-pdf-btn"><?php esc_html_e( 'Seleccionar PDF de Medios', 'trs-leads-generator' ); ?></button>
							<button type="button" class="button button-link-delete" id="trs-remove-pdf-btn" style="display: <?php echo $pdf_resource_id ? 'inline-block' : 'none'; ?>;"><?php esc_html_e( 'Quitar PDF', 'trs-leads-generator' ); ?></button>
							<p class="description"><?php esc_html_e( 'Selecciona un archivo PDF previamente cargado a la biblioteca de medios.', 'trs-leads-generator' ); ?></p>
						</div>

						<!-- Opción 2: URL Externa -->
						<div id="trs-pdf-source-external" class="trs-pdf-source-box" style="display: <?php echo 'external' === $pdf_source ? 'block' : 'none'; ?>;">
							<input type="url" name="trs_form_pdf_external_url" id="trs_form_pdf_external_url" value="<?php echo esc_url( $pdf_external_url ); ?>" class="large-text" placeholder="https://drive.google.com/file/d/..." />
							<p class="description">
								<?php esc_html_e( 'Enlace directo o compartido hacia el PDF alojado en Google Drive, Dropbox, Amazon S3 o cualquier servidor externo.', 'trs-leads-generator' ); ?>
								<?php if ( ! empty( $pdf_external_url ) ) : ?>
									<a href="<?php echo esc_url( $pdf_external_url ); ?>" target="_blank" style="margin-left: 8px;"><?php esc_html_e( '🔗 Probar enlace', 'trs-leads-generator' ); ?></a>
								<?php endif; ?>
							</p>
						</div>

						<!-- Opción 3: Página Interna -->
						<div id="trs-pdf-source-page" class="trs-pdf-source-box" style="display: <?php echo 'page' === $pdf_source ? 'block' : 'none'; ?>;">
							<select name="trs_form_pdf_page_id" id="trs_form_pdf_page_id" class="large-text">
								<option value=""><?php esc_html_e( '-- Selecciona una Página o Entrada --', 'trs-leads-generator' ); ?></option>
								<optgroup label="<?php esc_attr_e( 'Páginas', 'trs-leads-generator' ); ?>">
									<?php foreach ( $site_pages as $p ) : ?>
										<option value="<?php echo esc_attr( (string) $p->ID ); ?>" <?php selected( $pdf_page_id, $p->ID ); ?>>
											<?php echo esc_html( $p->post_title ); ?> (ID: <?php echo esc_html( (string) $p->ID ); ?>)
										</option>
									<?php endforeach; ?>
								</optgroup>
								<optgroup label="<?php esc_attr_e( 'Entradas (Posts)', 'trs-leads-generator' ); ?>">
									<?php foreach ( $site_posts as $p ) : ?>
										<option value="<?php echo esc_attr( (string) $p->ID ); ?>" <?php selected( $pdf_page_id, $p->ID ); ?>>
											<?php echo esc_html( $p->post_title ); ?> (ID: <?php echo esc_html( (string) $p->ID ); ?>)
										</option>
									<?php endforeach; ?>
								</optgroup>
							</select>
							<p class="description">
								<?php esc_html_e( 'El lead será dirigido o recibirá acceso a la página o entrada seleccionada tras completar el registro.', 'trs-leads-generator' ); ?>
								<?php if ( $pdf_page_id ) : ?>
									<a href="<?php echo esc_url( get_permalink( $pdf_page_id ) ); ?>" target="_blank" style="margin-left: 8px;"><?php esc_html_e( '🔗 Ver página', 'trs-leads-generator' ); ?></a>
								<?php endif; ?>
							</p>
						</div>

						<!-- Opción 4: Generador Dinámico con Editor Dompdf -->
						<div id="trs-pdf-source-dynamic" class="trs-pdf-source-box" style="display: <?php echo 'dynamic' === $pdf_source ? 'block' : 'none'; ?>;">
							<div style="background: #f1f5f9; border: 1px solid #cbd5e1; border-radius: 6px; padding: 12px; margin-bottom: 12px;">
								<div style="display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 8px;">
									<div>
										<strong><?php esc_html_e( 'Plantillas Rápidas:', 'trs-leads-generator' ); ?></strong>
										<button type="button" class="button button-small trs-load-tpl-btn" data-tpl="executive_whitepaper"><?php esc_html_e( 'Guía Ejecutiva', 'trs-leads-generator' ); ?></button>
										<button type="button" class="button button-small trs-load-tpl-btn" data-tpl="strategy_checklist"><?php esc_html_e( 'Checklist', 'trs-leads-generator' ); ?></button>
										<button type="button" class="button button-small trs-load-tpl-btn" data-tpl="event_ticket"><?php esc_html_e( 'Pase de Evento', 'trs-leads-generator' ); ?></button>
										<button type="button" class="button button-small trs-load-tpl-btn" data-tpl="default"><?php esc_html_e( 'Plantilla Base', 'trs-leads-generator' ); ?></button>
									</div>
									<div>
										<button type="button" id="trs-preview-live-pdf-btn" class="button button-primary" style="display: inline-flex; align-items: center; gap: 4px;">
											👁️ <?php esc_html_e( 'Previsualizar PDF en Nueva Pestaña', 'trs-leads-generator' ); ?>
										</button>
									</div>
								</div>
							</div>

							<label for="trs_form_pdf_content" style="font-weight: 600; display: block; margin-bottom: 6px;">
								<?php esc_html_e( 'Contenido del PDF (Código HTML / Texto con variables):', 'trs-leads-generator' ); ?>
							</label>
							<textarea name="trs_form_pdf_content" id="trs_form_pdf_content" rows="14" class="large-text code" style="width: 100%; font-family: monospace; font-size: 13px;"><?php echo esc_textarea( $pdf_content ); ?></textarea>

							<div style="background: #eef2ff; border-left: 4px solid #6366f1; padding: 10px 14px; margin-top: 10px; border-radius: 4px; font-size: 12px; color: #3730a3;">
								<strong><?php esc_html_e( 'Variables dinámicas que se reemplazan automáticamente para cada lead:', 'trs-leads-generator' ); ?></strong><br />
								<code>{first_name}</code> (Nombre), <code>{last_name}</code> (Apellido), <code>{email}</code> (Correo), <code>{phone}</code> (Teléfono), <code>{company}</code> (Institución), <code>{job_title}</code> (Cargo), <code>{message}</code> (Mensaje), <code>{form_title}</code> (Nombre del Formulario), <code>{date}</code> (Fecha actual).
							</div>
						</div>
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
		$cover_source = isset( $_POST['trs_form_cover_source'] ) ? sanitize_key( wp_unslash( $_POST['trs_form_cover_source'] ) ) : 'media';
		update_post_meta( $post_id, '_trs_form_cover_source', $cover_source );

		$cover_id = isset( $_POST['trs_form_cover_image'] ) ? absint( $_POST['trs_form_cover_image'] ) : 0;
		update_post_meta( $post_id, '_trs_form_cover_image', $cover_id );

		$cover_ext = isset( $_POST['trs_form_cover_external_url'] ) ? esc_url_raw( wp_unslash( $_POST['trs_form_cover_external_url'] ) ) : '';
		update_post_meta( $post_id, '_trs_form_cover_external_url', $cover_ext );

		$cover_post_id = isset( $_POST['trs_form_cover_post_id'] ) ? absint( $_POST['trs_form_cover_post_id'] ) : 0;
		update_post_meta( $post_id, '_trs_form_cover_post_id', $cover_post_id );

		// 6. PDF
		$pdf_source = isset( $_POST['trs_form_pdf_source'] ) ? sanitize_key( wp_unslash( $_POST['trs_form_pdf_source'] ) ) : 'media';
		update_post_meta( $post_id, '_trs_form_pdf_source', $pdf_source );

		$pdf_id = isset( $_POST['trs_form_pdf_resource'] ) ? absint( $_POST['trs_form_pdf_resource'] ) : 0;
		update_post_meta( $post_id, '_trs_form_pdf_resource', $pdf_id );

		$pdf_ext = isset( $_POST['trs_form_pdf_external_url'] ) ? esc_url_raw( wp_unslash( $_POST['trs_form_pdf_external_url'] ) ) : '';
		update_post_meta( $post_id, '_trs_form_pdf_external_url', $pdf_ext );

		$pdf_page_id = isset( $_POST['trs_form_pdf_page_id'] ) ? absint( $_POST['trs_form_pdf_page_id'] ) : 0;
		update_post_meta( $post_id, '_trs_form_pdf_page_id', $pdf_page_id );

		if ( isset( $_POST['trs_form_pdf_content'] ) ) {
			$pdf_content = wp_unslash( $_POST['trs_form_pdf_content'] );
			update_post_meta( $post_id, '_trs_form_pdf_content', $pdf_content );
		}

		// 7. URL de Confirmación
		$confirmation_url = isset( $_POST['trs_form_confirmation_url'] ) ? esc_url_raw( wp_unslash( $_POST['trs_form_confirmation_url'] ) ) : '';
		update_post_meta( $post_id, '_trs_form_confirmation_url', $confirmation_url );
	}
}
