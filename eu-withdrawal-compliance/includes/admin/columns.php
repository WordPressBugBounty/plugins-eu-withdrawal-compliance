<?php
/**
 * Admin: custom columns for the withdrawal CPT listing.
 *
 * @package AyudaWP_EU_Withdrawal
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Add custom columns to the withdrawal listing.
 *
 * @param array $columns Existing columns.
 * @return array
 */
function ayudawp_euw_admin_columns( $columns ) {

	$new = array(
		'cb'                   => $columns['cb'] ?? '',
		'title'                => __( 'Reference', 'eu-withdrawal-compliance' ),
		'ayudawp_euw_customer' => __( 'Customer', 'eu-withdrawal-compliance' ),
		'ayudawp_euw_email'    => __( 'Email', 'eu-withdrawal-compliance' ),
		'ayudawp_euw_order'    => __( 'Order', 'eu-withdrawal-compliance' ),
		'ayudawp_euw_scope'    => __( 'Scope', 'eu-withdrawal-compliance' ),
		'ayudawp_euw_status'   => __( 'Status', 'eu-withdrawal-compliance' ),
		'date'                 => __( 'Date', 'eu-withdrawal-compliance' ),
	);

	return $new;
}
add_filter( 'manage_ayudawp_withdrawal_posts_columns', 'ayudawp_euw_admin_columns' );

/**
 * Render values for each custom column.
 *
 * @param string $column Column key.
 * @param int    $post_id Post ID.
 */
function ayudawp_euw_admin_column_content( $column, $post_id ) {

	switch ( $column ) {
		case 'ayudawp_euw_customer':
			echo esc_html( get_post_meta( $post_id, '_ayudawp_euw_name', true ) );
			break;

		case 'ayudawp_euw_email':
			$email = get_post_meta( $post_id, '_ayudawp_euw_email', true );
			if ( $email ) {
				printf( '<a href="mailto:%1$s">%1$s</a>', esc_attr( $email ) );
			}
			break;

		case 'ayudawp_euw_order':
			$order   = get_post_meta( $post_id, '_ayudawp_euw_order', true );
			$wc_id   = absint( get_post_meta( $post_id, '_ayudawp_euw_wc_order_id', true ) );

			if ( $wc_id && function_exists( 'wc_get_order' ) ) {
				$wc_order = wc_get_order( $wc_id );
				if ( $wc_order ) {
					$edit_url = method_exists( $wc_order, 'get_edit_order_url' )
						? $wc_order->get_edit_order_url()
						: admin_url( 'post.php?post=' . $wc_id . '&action=edit' );
					printf(
						'<a href="%1$s">%2$s</a>',
						esc_url( $edit_url ),
						esc_html( $order )
					);
					break;
				}
			}

			echo esc_html( $order );
			break;

		case 'ayudawp_euw_scope':
			$scope = get_post_meta( $post_id, '_ayudawp_euw_scope', true );
			echo esc_html(
				'partial' === $scope
					? __( 'Partial', 'eu-withdrawal-compliance' )
					: __( 'Full', 'eu-withdrawal-compliance' )
			);
			break;

		case 'ayudawp_euw_status':
			$status = get_post_meta( $post_id, '_ayudawp_euw_status', true );
			$labels = array(
				'pending'   => __( 'Pending', 'eu-withdrawal-compliance' ),
				'accepted'  => __( 'Accepted', 'eu-withdrawal-compliance' ),
				'rejected'  => __( 'Rejected', 'eu-withdrawal-compliance' ),
				'completed' => __( 'Completed', 'eu-withdrawal-compliance' ),
			);

			$label = isset( $labels[ $status ] ) ? $labels[ $status ] : __( 'Pending', 'eu-withdrawal-compliance' );
			$class = 'ayudawp-euw-status-' . sanitize_html_class( $status ? $status : 'pending' );

			printf(
				'<span class="ayudawp-euw-status %1$s">%2$s</span>',
				esc_attr( $class ),
				esc_html( $label )
			);
			break;
	}
}
add_action( 'manage_ayudawp_withdrawal_posts_custom_column', 'ayudawp_euw_admin_column_content', 10, 2 );
