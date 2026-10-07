<?php
/**
 * View template for captured leads list.
 *
 * @package    TRS_Leads_Generator
 * @subpackage TRS_Leads_Generator/admin/partials
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

global $wpdb;

$table_name = $wpdb->prefix . 'trs_leads';

// Query parameters.
$filter_form_id = isset( $_GET['form_id'] ) ? absint( $_GET['form_id'] ) : 0;
$search_query   = isset( $_GET['s'] ) ? sanitize_text_field( wp_unslash( $_GET['s'] ) ) : '';
$current_page   = isset( $_GET['paged'] ) ? max( 1, absint( $_GET['paged'] ) ) : 1;
$per_page       = 20;
$offset         = ( $current_page - 1 ) * $per_page;

// Build WHERE clause safely.
$where_clauses = array();
$where_values  = array();

if ( $filter_form_id ) {
	$where_clauses[] = 'form_id = %d';
	$where_values[]  = $filter_form_id;
}

if ( ! empty( $search_query ) ) {
	$like_search     = '%' . $wpdb->esc_like( $search_query ) . '%';
	$where_clauses[] = '(email LIKE %s OR first_name LIKE %s OR last_name LIKE %s OR phone LIKE %s OR company LIKE %s)';
	$where_values[]  = $like_search;
	$where_values[]  = $like_search;
	$where_values[]  = $like_search;
	$where_values[]  = $like_search;
	$where_values[]  = $like_search;
}

$where_sql = '';
if ( ! empty( $where_clauses ) ) {
	$where_sql = 'WHERE ' . implode( ' AND ', $where_clauses );
}

// Total items count.
$count_query = "SELECT COUNT(id) FROM {$table_name} {$where_sql}";
if ( ! empty( $where_values ) ) {
	$total_items = (int) $wpdb->get_var( $wpdb->prepare( $count_query, $where_values ) );
} else {
	$total_items = (int) $wpdb->get_var( $count_query );
}

$total_pages = ceil( $total_items / $per_page );

// Fetch leads page.
$data_query = "SELECT * FROM {$table_name} {$where_sql} ORDER BY id DESC LIMIT %d OFFSET %d";
$query_args = array_merge( $where_values, array( $per_page, $offset ) );
$leads      = $wpdb->get_results( $wpdb->prepare( $data_query, $query_args ), ARRAY_A );

// Fetch all forms for dropdown filter.
$forms = get_posts(
	array(
		'post_type'      => 'trs_form',
		'posts_per_page' => -1,
		'post_status'    => 'any',
	)
);
?>
<div class="wrap trs-admin-wrap" style="max-width: 1300px;">
	<h1 class="wp-heading-inline"><?php esc_html_e( 'Leads Capturados', 'trs-leads-generator' ); ?></h1>

	<?php if ( isset( $_GET['deleted'] ) ) : ?>
		<div class="notice notice-success is-dismissible" style="margin-top: 15px;">
			<p><?php esc_html_e( 'Lead eliminado correctamente de la base de datos.', 'trs-leads-generator' ); ?></p>
		</div>
	<?php endif; ?>

	<?php if ( $filter_form_id ) : ?>
		<?php $filtered_form_title = get_the_title( $filter_form_id ) ?: ( 'ID #' . $filter_form_id ); ?>
		<div class="notice notice-info" style="margin-top: 15px; padding: 12px 16px; display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 10px;">
			<p style="margin: 0; font-size: 14px;">
				📄 <strong><?php printf( esc_html__( 'Mostrando registrados exclusivamente del formulario: "%s"', 'trs-leads-generator' ), esc_html( $filtered_form_title ) ); ?></strong>
			</p>
			<div>
				<a href="<?php echo esc_url( admin_url( 'post.php?post=' . $filter_form_id . '&action=edit' ) ); ?>" class="button button-small" style="margin-right: 6px;">
					✏️ <?php esc_html_e( 'Editar Formulario', 'trs-leads-generator' ); ?>
				</a>
				<a href="<?php echo esc_url( admin_url( 'admin.php?page=trs-leads' ) ); ?>" class="button button-small">
					✕ <?php esc_html_e( 'Ver todos los formularios', 'trs-leads-generator' ); ?>
				</a>
			</div>
		</div>
	<?php endif; ?>

	<!-- Actions & Filters Bar -->
	<div class="trs-admin-card" style="margin-top: 20px; padding: 16px 20px;">
		<form method="GET" action="<?php echo esc_url( admin_url( 'admin.php' ) ); ?>" style="display: flex; flex-wrap: wrap; gap: 12px; align-items: center; justify-content: space-between;">
			<input type="hidden" name="page" value="trs-leads" />

			<div style="display: flex; gap: 10px; align-items: center; flex-wrap: wrap;">
				<!-- Filter by Form -->
				<select name="form_id" id="filter_form_id">
					<option value=""><?php esc_html_e( '-- Todos los Formularios --', 'trs-leads-generator' ); ?></option>
					<?php foreach ( $forms as $f ) : ?>
						<option value="<?php echo esc_attr( (string) $f->ID ); ?>" <?php selected( $filter_form_id, $f->ID ); ?>>
							<?php echo esc_html( $f->post_title ); ?> (ID: <?php echo esc_html( (string) $f->ID ); ?>)
						</option>
					<?php endforeach; ?>
				</select>

				<!-- Search Input -->
				<input type="search" name="s" value="<?php echo esc_attr( $search_query ); ?>" placeholder="<?php esc_attr_e( 'Buscar por nombre, email, teléfono...', 'trs-leads-generator' ); ?>" style="min-width: 250px;" />

				<button type="submit" class="button button-secondary">
					🔍 <?php esc_html_e( 'Filtrar', 'trs-leads-generator' ); ?>
				</button>

				<?php if ( $filter_form_id || ! empty( $search_query ) ) : ?>
					<a href="<?php echo esc_url( admin_url( 'admin.php?page=trs-leads' ) ); ?>" class="button button-link-delete" style="text-decoration: none; padding-top: 4px;">
						✕ <?php esc_html_e( 'Limpiar filtros', 'trs-leads-generator' ); ?>
					</a>
				<?php endif; ?>
			</div>

			<!-- Export Button -->
			<div>
				<?php
				$export_url = add_query_arg(
					array(
						'action'           => 'trs_export_leads_csv',
						'form_id'          => $filter_form_id,
						'trs_export_nonce' => wp_create_nonce( 'trs_export_leads_action' ),
					),
					admin_url( 'admin-post.php' )
				);
				?>
				<a href="<?php echo esc_url( $export_url ); ?>" class="button button-primary" style="display: flex; align-items: center; gap: 6px;">
					📥 <?php esc_html_e( 'Exportar a CSV (Excel)', 'trs-leads-generator' ); ?>
				</a>
			</div>
		</form>
	</div>

	<!-- Results Count -->
	<p style="color: #64748b; font-size: 14px; margin-bottom: 12px;">
		<?php
		printf(
			/* translators: %d: total items */
			esc_html__( 'Total de registros encontrados: %d', 'trs-leads-generator' ),
			(int) $total_items
		);
		?>
	</p>

	<!-- Leads Table -->
	<div class="trs-admin-card" style="padding: 0; overflow-x: auto;">
		<table class="wp-list-table widefat fixed striped table-view-list" style="border: none;">
			<thead>
				<tr>
					<th style="width: 50px;">ID</th>
					<th style="width: 140px;"><?php esc_html_e( 'Fecha', 'trs-leads-generator' ); ?></th>
					<th style="width: 180px;"><?php esc_html_e( 'Formulario', 'trs-leads-generator' ); ?></th>
					<th><?php esc_html_e( 'Contacto', 'trs-leads-generator' ); ?></th>
					<th style="width: 130px;"><?php esc_html_e( 'Teléfono', 'trs-leads-generator' ); ?></th>
					<th><?php esc_html_e( 'Institución / Cargo', 'trs-leads-generator' ); ?></th>
					<th><?php esc_html_e( 'Mensaje', 'trs-leads-generator' ); ?></th>
					<th style="width: 170px;"><?php esc_html_e( 'Atribución (UTMs)', 'trs-leads-generator' ); ?></th>
					<th style="width: 70px; text-align: center;"><?php esc_html_e( 'Acción', 'trs-leads-generator' ); ?></th>
				</tr>
			</thead>
			<tbody>
				<?php if ( empty( $leads ) ) : ?>
					<tr>
						<td colspan="9" style="text-align: center; padding: 40px 20px; color: #64748b;">
							<p style="font-size: 28px; margin: 0 0 10px 0;">📭</p>
							<p style="font-size: 16px; font-weight: 600; margin: 0 0 6px 0; color: #1e293b;">
								<?php esc_html_e( 'No se encontraron leads registrados', 'trs-leads-generator' ); ?>
							</p>
							<p style="margin: 0; font-size: 13px;">
								<?php esc_html_e( 'Cuando los usuarios completen tus formularios en el frontend, aparecerán listados aquí.', 'trs-leads-generator' ); ?>
							</p>
						</td>
					</tr>
				<?php else : ?>
					<?php foreach ( $leads as $lead ) : ?>
						<?php
						$lead_form_title = $lead['form_id'] ? get_the_title( $lead['form_id'] ) : '';
						$lead_form_url   = $lead['form_id'] ? admin_url( 'post.php?post=' . $lead['form_id'] . '&action=edit' ) : '#';
						$phone_clean     = preg_replace( '/[^0-9+]/', '', $lead['phone'] ?? '' );
						$delete_url      = wp_nonce_url(
							admin_url( 'admin-post.php?action=trs_delete_lead&lead_id=' . $lead['id'] ),
							'trs_delete_lead_' . $lead['id']
						);
						?>
						<tr>
							<td><strong>#<?php echo esc_html( (string) $lead['id'] ); ?></strong></td>
							<td>
								<span style="font-size: 12px; color: #475569;">
									<?php echo esc_html( gmdate( 'd/m/Y H:i', strtotime( $lead['created_at'] ) ) ); ?>
								</span>
							</td>
							<td>
								<?php if ( $lead_form_title ) : ?>
									<a href="<?php echo esc_url( $lead_form_url ); ?>" style="font-weight: 600; text-decoration: none;">
										<?php echo esc_html( $lead_form_title ); ?>
									</a>
									<span style="color: #94a3b8; font-size: 11px; display: block;">ID: <?php echo esc_html( (string) $lead['form_id'] ); ?></span>
								<?php else : ?>
									<span style="color: #94a3b8;">N/A</span>
								<?php endif; ?>
							</td>
							<td>
								<strong><?php echo esc_html( trim( ( $lead['first_name'] ?? '' ) . ' ' . ( $lead['last_name'] ?? '' ) ) ); ?></strong>
								<br />
								<a href="mailto:<?php echo esc_attr( $lead['email'] ); ?>" style="font-size: 12px; color: #2563eb;">
									<?php echo esc_html( $lead['email'] ); ?>
								</a>
							</td>
							<td>
								<?php if ( ! empty( $lead['phone'] ) ) : ?>
									<a href="tel:<?php echo esc_attr( $phone_clean ); ?>" style="font-size: 12px; color: #0284c7; text-decoration: none;">
										📞 <?php echo esc_html( $lead['phone'] ); ?>
									</a>
									<br />
									<a href="https://wa.me/<?php echo esc_attr( ltrim( $phone_clean, '+' ) ); ?>" target="_blank" rel="noopener noreferrer" style="font-size: 11px; color: #16a34a; text-decoration: none;">
										💬 WhatsApp
									</a>
								<?php else : ?>
									<span style="color: #cbd5e1;">-</span>
								<?php endif; ?>
							</td>
							<td>
								<?php if ( ! empty( $lead['company'] ) || ! empty( $lead['job_title'] ) ) : ?>
									<strong><?php echo esc_html( $lead['company'] ?? '' ); ?></strong>
									<?php if ( ! empty( $lead['job_title'] ) ) : ?>
										<span style="color: #64748b; font-size: 12px; display: block;">
											<?php echo esc_html( $lead['job_title'] ); ?>
										</span>
									<?php endif; ?>
								<?php else : ?>
									<span style="color: #cbd5e1;">-</span>
								<?php endif; ?>
							</td>
							<td>
								<?php if ( ! empty( $lead['message'] ) ) : ?>
									<span title="<?php echo esc_attr( $lead['message'] ); ?>" style="font-size: 12px; color: #334155;">
										<?php echo esc_html( wp_trim_words( $lead['message'], 10, '...' ) ); ?>
									</span>
								<?php else : ?>
									<span style="color: #cbd5e1;">-</span>
								<?php endif; ?>
							</td>
							<td>
								<?php if ( ! empty( $lead['utm_source'] ) ) : ?>
									<span class="trs-badge trs-badge-success" style="font-size: 10px; margin-bottom: 2px;">
										<?php echo esc_html( $lead['utm_source'] ); ?>
									</span>
								<?php endif; ?>
								<?php if ( ! empty( $lead['utm_campaign'] ) ) : ?>
									<span class="trs-badge" style="font-size: 10px; background: #e0f2fe; color: #0369a1; border: 1px solid #bae6fd; margin-bottom: 2px;">
										<?php echo esc_html( $lead['utm_campaign'] ); ?>
									</span>
								<?php endif; ?>
								<?php if ( empty( $lead['utm_source'] ) && empty( $lead['utm_campaign'] ) ) : ?>
									<span style="color: #94a3b8; font-size: 11px;">Directo / Orgánico</span>
								<?php endif; ?>
							</td>
							<td style="text-align: center;">
								<a href="<?php echo esc_url( $delete_url ); ?>" onclick="return confirm('¿Seguro que deseas eliminar permanentemente este lead?');" style="color: #ef4444; font-size: 14px; text-decoration: none;" title="<?php esc_attr_e( 'Eliminar lead', 'trs-leads-generator' ); ?>">
									🗑️
								</a>
							</td>
						</tr>
					<?php endforeach; ?>
				<?php endif; ?>
			</tbody>
		</table>
	</div>

	<!-- Pagination -->
	<?php if ( $total_pages > 1 ) : ?>
		<div class="tablenav" style="margin-top: 15px;">
			<div class="tablenav-pages">
				<?php
				echo paginate_links(
					array(
						'base'      => add_query_arg( 'paged', '%#%' ),
						'format'    => '',
						'prev_text' => '&laquo; ' . __( 'Anterior', 'trs-leads-generator' ),
						'next_text' => __( 'Siguiente', 'trs-leads-generator' ) . ' &raquo;',
						'total'     => $total_pages,
						'current'   => $current_page,
					)
				);
				?>
			</div>
		</div>
	<?php endif; ?>
</div>

