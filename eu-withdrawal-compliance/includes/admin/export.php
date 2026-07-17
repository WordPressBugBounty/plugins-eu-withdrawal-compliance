<?php
/**
 * Admin: CSV export of the withdrawal log for auditing.
 *
 * The trader carries the burden of proof for withdrawal handling, so this
 * provides a full, downloadable record of every request: from a bulk action
 * on the selected requests, or from a filtered export (status + date range)
 * useful for a consumer-protection inspection.
 *
 * @package AyudaWP_EU_Withdrawal
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Human-readable label for a handling status.
 *
 * @param string $status Stored status value.
 * @return string Translated label.
 */
function ayudawp_euw_status_label( $status ) {

	$labels = array(
		'pending'   => __( 'Pending', 'eu-withdrawal-compliance' ),
		'accepted'  => __( 'Accepted', 'eu-withdrawal-compliance' ),
		'rejected'  => __( 'Rejected', 'eu-withdrawal-compliance' ),
		'completed' => __( 'Completed', 'eu-withdrawal-compliance' ),
	);

	return isset( $labels[ $status ] ) ? $labels[ $status ] : $status;
}

/**
 * Register the "Export withdrawals" page under the Withdrawals top-level menu.
 */
function ayudawp_euw_register_export_page() {

	add_submenu_page(
		'edit.php?post_type=ayudawp_withdrawal',
		__( 'Export withdrawals', 'eu-withdrawal-compliance' ),
		__( 'Export withdrawals', 'eu-withdrawal-compliance' ),
		'edit_ayudawp_withdrawals',
		'ayudawp-euw-export',
		'ayudawp_euw_render_export_page'
	);
}
add_action( 'admin_menu', 'ayudawp_euw_register_export_page' );

/**
 * Render the filtered export form.
 */
function ayudawp_euw_render_export_page() {

	if ( ! current_user_can( 'edit_ayudawp_withdrawals' ) ) {
		wp_die( esc_html__( 'You are not allowed to access this page.', 'eu-withdrawal-compliance' ) );
	}

	$statuses = array(
		''          => __( 'All statuses', 'eu-withdrawal-compliance' ),
		'pending'   => __( 'Pending', 'eu-withdrawal-compliance' ),
		'accepted'  => __( 'Accepted', 'eu-withdrawal-compliance' ),
		'rejected'  => __( 'Rejected', 'eu-withdrawal-compliance' ),
		'completed' => __( 'Completed', 'eu-withdrawal-compliance' ),
	);

	?>
	<div class="wrap">
		<h1><?php esc_html_e( 'Export withdrawal requests', 'eu-withdrawal-compliance' ); ?></h1>
		<p><?php esc_html_e( 'Download the withdrawal log as a CSV file for auditing or to answer a consumer-protection inspection. Leave the filters empty to export every request.', 'eu-withdrawal-compliance' ); ?></p>

		<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">

			<input type="hidden" name="action" value="ayudawp_euw_export_csv">
			<?php wp_nonce_field( 'ayudawp_euw_export' ); ?>

			<table class="form-table" role="presentation">
				<tr>
					<th scope="row"><label for="ayudawp_euw_status"><?php esc_html_e( 'Status', 'eu-withdrawal-compliance' ); ?></label></th>
					<td>
						<select name="status" id="ayudawp_euw_status">
							<?php foreach ( $statuses as $value => $label ) : ?>
								<option value="<?php echo esc_attr( $value ); ?>"><?php echo esc_html( $label ); ?></option>
							<?php endforeach; ?>
						</select>
					</td>
				</tr>
				<tr>
					<th scope="row"><label for="ayudawp_euw_date_from"><?php esc_html_e( 'From date', 'eu-withdrawal-compliance' ); ?></label></th>
					<td><input type="date" name="date_from" id="ayudawp_euw_date_from" max="<?php echo esc_attr( gmdate( 'Y-m-d' ) ); ?>"></td>
				</tr>
				<tr>
					<th scope="row"><label for="ayudawp_euw_date_to"><?php esc_html_e( 'To date', 'eu-withdrawal-compliance' ); ?></label></th>
					<td><input type="date" name="date_to" id="ayudawp_euw_date_to" max="<?php echo esc_attr( gmdate( 'Y-m-d' ) ); ?>"></td>
				</tr>
				<tr>
					<th scope="row"><?php esc_html_e( 'Personal data', 'eu-withdrawal-compliance' ); ?></th>
					<td>
						<label for="ayudawp_euw_include_pii">
							<input type="checkbox" name="include_pii" id="ayudawp_euw_include_pii" value="1">
							<?php esc_html_e( 'Include IP address and user agent (data minimisation: only when needed)', 'eu-withdrawal-compliance' ); ?>
						</label>
					</td>
				</tr>
			</table>

			<?php submit_button( __( 'Export to CSV', 'eu-withdrawal-compliance' ) ); ?>
		</form>
	</div>
	<?php
}

/**
 * Handle the filtered CSV export request from the export page (admin-post).
 *
 * Reads status + date-range filters from the submitted form. The other export
 * path — a hand-picked selection from the Withdrawals listing — is streamed
 * directly by the bulk-action handler and never reaches this function.
 */
function ayudawp_euw_export_csv() {

	if ( ! current_user_can( 'edit_ayudawp_withdrawals' ) ) {
		wp_die( esc_html__( 'You are not allowed to export withdrawal requests.', 'eu-withdrawal-compliance' ) );
	}

	check_admin_referer( 'ayudawp_euw_export' );

	$query_args = array(
		'post_type'      => 'ayudawp_withdrawal',
		'post_status'    => 'publish',
		'posts_per_page' => -1,
		'no_found_rows'  => true,
		'orderby'        => 'date',
		'order'          => 'DESC',
	);

	$status    = isset( $_REQUEST['status'] ) ? sanitize_key( wp_unslash( $_REQUEST['status'] ) ) : '';
	$date_from = isset( $_REQUEST['date_from'] ) ? sanitize_text_field( wp_unslash( $_REQUEST['date_from'] ) ) : '';
	$date_to   = isset( $_REQUEST['date_to'] ) ? sanitize_text_field( wp_unslash( $_REQUEST['date_to'] ) ) : '';

	if ( $status && in_array( $status, array( 'pending', 'accepted', 'rejected', 'completed' ), true ) ) {
		$query_args['meta_query'] = array( // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_query -- admin-only, on-demand export.
			array(
				'key'   => '_ayudawp_euw_status',
				'value' => $status,
			),
		);
	}

	$date_query = array();

	if ( preg_match( '/^\d{4}-\d{2}-\d{2}$/', $date_from ) ) {
		$date_query['after'] = $date_from . ' 00:00:00';
	}

	if ( preg_match( '/^\d{4}-\d{2}-\d{2}$/', $date_to ) ) {
		$date_query['before'] = $date_to . ' 23:59:59';
	}

	if ( ! empty( $date_query ) ) {
		$date_query['inclusive'] = true;
		$query_args['date_query'] = array( $date_query );
	}

	$include_pii = ! empty( $_REQUEST['include_pii'] );

	ayudawp_euw_stream_csv( get_posts( $query_args ), $include_pii );
}
add_action( 'admin_post_ayudawp_euw_export_csv', 'ayudawp_euw_export_csv' );

/**
 * Stream the given withdrawal posts as a CSV download and exit.
 *
 * @param WP_Post[] $posts       Withdrawal posts to export.
 * @param bool      $include_pii Whether to add IP address and user-agent columns.
 */
function ayudawp_euw_stream_csv( $posts, $include_pii = false ) {

	$filename = 'withdrawals-' . gmdate( 'Ymd-His' ) . '.csv';

	nocache_headers();
	header( 'Content-Type: text/csv; charset=utf-8' );
	header( 'Content-Disposition: attachment; filename=' . $filename );

	// php://output is the HTTP response body, not the filesystem, so the
	// WordPress filesystem API does not apply here.
	$output = fopen( 'php://output', 'w' ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_fopen

	// UTF-8 BOM so spreadsheet apps open accented characters correctly.
	fwrite( $output, "\xEF\xBB\xBF" ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_fwrite

	$columns = array(
		__( 'ID', 'eu-withdrawal-compliance' ),
		__( 'Submitted at (UTC)', 'eu-withdrawal-compliance' ),
		__( 'Name', 'eu-withdrawal-compliance' ),
		__( 'Email', 'eu-withdrawal-compliance' ),
		__( 'Order', 'eu-withdrawal-compliance' ),
		__( 'Order date', 'eu-withdrawal-compliance' ),
		__( 'Scope', 'eu-withdrawal-compliance' ),
		__( 'Status', 'eu-withdrawal-compliance' ),
		__( 'Status changed at (UTC)', 'eu-withdrawal-compliance' ),
		__( 'Acknowledgement sent', 'eu-withdrawal-compliance' ),
		__( 'Acknowledgement sent at (UTC)', 'eu-withdrawal-compliance' ),
		__( 'Receipt hash (SHA-256)', 'eu-withdrawal-compliance' ),
		__( 'Excluded items', 'eu-withdrawal-compliance' ),
		__( 'Unverified', 'eu-withdrawal-compliance' ),
	);

	if ( $include_pii ) {
		$columns[] = __( 'IP address', 'eu-withdrawal-compliance' );
		$columns[] = __( 'User agent', 'eu-withdrawal-compliance' );
	}

	fputcsv( $output, array_map( 'ayudawp_euw_csv_escape', $columns ) );

	foreach ( $posts as $post ) {

		$id    = $post->ID;
		$scope = get_post_meta( $id, '_ayudawp_euw_scope', true );
		$scope = ( 'partial' === $scope )
			? __( 'Partial', 'eu-withdrawal-compliance' )
			: __( 'Full', 'eu-withdrawal-compliance' );

		$excluded     = get_post_meta( $id, '_ayudawp_euw_excluded_items', true );
		$excluded_str = '';

		if ( is_array( $excluded ) && ! empty( $excluded ) ) {
			$parts = array();
			foreach ( $excluded as $item ) {
				$item_name = isset( $item['name'] ) ? (string) $item['name'] : '';
				$item_qty  = isset( $item['quantity'] ) ? (int) $item['quantity'] : 1;
				$parts[]   = $item_name . ' x' . $item_qty;
			}
			$excluded_str = implode( '; ', $parts );
		}

		$receipt_sent = ( '1' === get_post_meta( $id, '_ayudawp_euw_receipt_sent', true ) )
			? __( 'Yes', 'eu-withdrawal-compliance' )
			: __( 'No', 'eu-withdrawal-compliance' );

		$row = array(
			$id,
			get_post_meta( $id, '_ayudawp_euw_submitted_at', true ),
			get_post_meta( $id, '_ayudawp_euw_name', true ),
			get_post_meta( $id, '_ayudawp_euw_email', true ),
			get_post_meta( $id, '_ayudawp_euw_order', true ),
			get_post_meta( $id, '_ayudawp_euw_order_date', true ),
			$scope,
			ayudawp_euw_status_label( get_post_meta( $id, '_ayudawp_euw_status', true ) ),
			get_post_meta( $id, '_ayudawp_euw_status_changed_at', true ),
			$receipt_sent,
			get_post_meta( $id, '_ayudawp_euw_receipt_sent_at', true ),
			get_post_meta( $id, '_ayudawp_euw_receipt_hash', true ),
			$excluded_str,
			ayudawp_euw_unverified_reason_label( (string) get_post_meta( $id, '_ayudawp_euw_unverified', true ) ),
		);

		if ( $include_pii ) {
			$row[] = get_post_meta( $id, '_ayudawp_euw_ip', true );
			$row[] = get_post_meta( $id, '_ayudawp_euw_user_agent', true );
		}

		fputcsv( $output, array_map( 'ayudawp_euw_csv_escape', $row ) );
	}

	fclose( $output ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_fclose

	exit;
}

/**
 * Neutralise CSV/formula injection in a cell value.
 *
 * Free-text fields (name, details) come from the public form, so a value that
 * starts with a formula trigger is prefixed with a single quote to stop
 * spreadsheet apps from executing it.
 *
 * @param mixed $value Cell value.
 * @return string Safe cell value.
 */
function ayudawp_euw_csv_escape( $value ) {

	$value = (string) $value;

	if ( '' !== $value && in_array( $value[0], array( '=', '+', '-', '@', "\t", "\r" ), true ) ) {
		$value = "'" . $value;
	}

	return $value;
}
