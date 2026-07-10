<?php
/**
 * Admin: bulk actions for the withdrawal CPT listing.
 *
 * @package AyudaWP_EU_Withdrawal
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Register bulk actions for the withdrawal listing.
 *
 * Rejection is intentionally NOT a bulk action: rejecting a withdrawal
 * request requires a written reason (legally and as a matter of customer
 * service), and bulk mode cannot collect that reason. Accept and complete
 * are safe in bulk because they do not require justification toward the
 * customer.
 *
 * The native "Edit" bulk action is also removed because the inline editor
 * shown by WordPress on this CPT surfaces fields that do not apply to
 * withdrawal requests.
 *
 * @param array $actions Existing bulk actions.
 * @return array
 */
function ayudawp_euw_bulk_actions( $actions ) {

	unset( $actions['edit'] );

	$actions['ayudawp_euw_mark_accepted']  = __( 'Mark as accepted', 'eu-withdrawal-compliance' );
	$actions['ayudawp_euw_mark_completed'] = __( 'Mark as completed', 'eu-withdrawal-compliance' );
	$actions['ayudawp_euw_export_csv']     = __( 'Export to CSV', 'eu-withdrawal-compliance' );

	return $actions;
}
add_filter( 'bulk_actions-edit-ayudawp_withdrawal', 'ayudawp_euw_bulk_actions' );

/**
 * Handle bulk status changes.
 *
 * No comment can be supplied in bulk mode, so the customer email goes out
 * with the default body for each status. Use the per-request metabox if you
 * need to include a custom message.
 *
 * @param string $redirect_to Redirect URL.
 * @param string $action      Action key.
 * @param array  $post_ids    Selected post IDs.
 * @return string Redirect URL with feedback args.
 */
function ayudawp_euw_handle_bulk_actions( $redirect_to, $action, $post_ids ) {

	// Export is not a status change: stream the selected requests as a CSV and
	// exit right here. We cannot hand off to admin-post.php by returning a
	// redirect URL, because edit.php strips the `action` query arg from the
	// bulk-action redirect target immediately after this filter runs — the
	// browser would land on a blank admin-post.php with no action to dispatch.
	// The bulk-action nonce was already verified by core (check_admin_referer
	// 'bulk-posts') before this filter fires, and the capability is re-checked
	// below. A hand-picked selection export never includes PII.
	if ( 'ayudawp_euw_export_csv' === $action ) {

		if ( ! current_user_can( 'edit_ayudawp_withdrawals' ) ) {
			return $redirect_to;
		}

		$ids = array_filter( array_map( 'absint', (array) $post_ids ) );

		if ( empty( $ids ) ) {
			return $redirect_to;
		}

		$posts = get_posts(
			array(
				'post_type'      => 'ayudawp_withdrawal',
				'post_status'    => 'publish',
				'posts_per_page' => -1,
				'no_found_rows'  => true,
				'orderby'        => 'date',
				'order'          => 'DESC',
				'post__in'       => $ids,
			)
		);

		ayudawp_euw_stream_csv( $posts );
	}

	$map = array(
		'ayudawp_euw_mark_accepted'  => 'accepted',
		'ayudawp_euw_mark_completed' => 'completed',
	);

	if ( ! isset( $map[ $action ] ) ) {
		return $redirect_to;
	}

	if ( ! current_user_can( 'edit_ayudawp_withdrawals' ) ) {
		return $redirect_to;
	}

	$new_status = $map[ $action ];
	$count      = 0;

	foreach ( $post_ids as $post_id ) {

		$post_id = absint( $post_id );

		if ( ! $post_id || get_post_type( $post_id ) !== 'ayudawp_withdrawal' ) {
			continue;
		}

		if ( ! current_user_can( 'edit_post', $post_id ) ) {
			continue;
		}

		$old_status = get_post_meta( $post_id, '_ayudawp_euw_status', true );
		$old_status = $old_status ? $old_status : 'pending';

		if ( $old_status === $new_status ) {
			continue;
		}

		update_post_meta( $post_id, '_ayudawp_euw_status', $new_status );
		ayudawp_euw_handle_status_transition( $post_id, $new_status, '' );

		$count++;
	}

	$redirect_to = add_query_arg(
		array(
			'ayudawp_euw_bulk_done'   => $count,
			'ayudawp_euw_bulk_status' => $new_status,
			'_wpnonce'                => wp_create_nonce( 'ayudawp_euw_bulk_notice' ),
		),
		$redirect_to
	);

	return $redirect_to;
}
add_filter( 'handle_bulk_actions-edit-ayudawp_withdrawal', 'ayudawp_euw_handle_bulk_actions', 10, 3 );

/**
 * Show feedback after a bulk status change.
 */
function ayudawp_euw_bulk_action_notice() {

	if ( ! isset( $_GET['_wpnonce'] )
		|| ! wp_verify_nonce(
			sanitize_text_field( wp_unslash( $_GET['_wpnonce'] ) ),
			'ayudawp_euw_bulk_notice'
		)
	) {
		return;
	}

	if ( ! isset( $_GET['ayudawp_euw_bulk_done'] ) ) {
		return;
	}

	$count  = absint( wp_unslash( $_GET['ayudawp_euw_bulk_done'] ) );
	$status = isset( $_GET['ayudawp_euw_bulk_status'] )
		? sanitize_key( wp_unslash( $_GET['ayudawp_euw_bulk_status'] ) )
		: '';

	if ( $count <= 0 ) {
		return;
	}

	$labels = array(
		'accepted'  => __( 'accepted', 'eu-withdrawal-compliance' ),
		'completed' => __( 'completed', 'eu-withdrawal-compliance' ),
	);

	$label = isset( $labels[ $status ] ) ? $labels[ $status ] : $status;

	printf(
		'<div class="notice notice-success is-dismissible"><p>%s</p></div>',
		esc_html(
			sprintf(
				/* translators: 1: number of requests, 2: status label. */
				_n(
					'%1$d withdrawal request marked as %2$s.',
					'%1$d withdrawal requests marked as %2$s.',
					$count,
					'eu-withdrawal-compliance'
				),
				$count,
				$label
			)
		)
	);
}
add_action( 'admin_notices', 'ayudawp_euw_bulk_action_notice' );
