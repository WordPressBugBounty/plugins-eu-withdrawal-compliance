<?php
/**
 * Native WordPress GDPR / Personal Data integration.
 *
 * Surfaces withdrawal request data through the standard
 * Tools → Export Personal Data and Tools → Erase Personal Data
 * screens, and contributes a suggested Privacy Policy section.
 *
 * @package AyudaWP_EU_Withdrawal
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Register a suggested Privacy Policy section.
 *
 * Appears under Settings → Privacy → Policy Guide so the admin can paste
 * the boilerplate into the public privacy policy of the site.
 */
function ayudawp_euw_register_privacy_policy_content() {

	if ( ! function_exists( 'wp_add_privacy_policy_content' ) ) {
		return;
	}

	$content  = '<p>' . esc_html__( 'When you submit a withdrawal request through our right-of-withdrawal form, we collect and store the following personal data for the sole purpose of fulfilling the legal traceability of consumer rights under EU Directive 2023/2673:', 'eu-withdrawal-compliance' ) . '</p>';
	$content .= '<ul>';
	$content .= '<li>' . esc_html__( 'Customer name and email address.', 'eu-withdrawal-compliance' ) . '</li>';
	$content .= '<li>' . esc_html__( 'Order reference and order date.', 'eu-withdrawal-compliance' ) . '</li>';
	$content .= '<li>' . esc_html__( 'IP address and browser User-Agent string used to submit the request.', 'eu-withdrawal-compliance' ) . '</li>';
	$content .= '<li>' . esc_html__( 'Submission timestamp (UTC) and SHA-256 receipt hash.', 'eu-withdrawal-compliance' ) . '</li>';
	$content .= '<li>' . esc_html__( 'Free-text details optionally provided by the customer.', 'eu-withdrawal-compliance' ) . '</li>';
	$content .= '</ul>';
	$content .= '<p>' . esc_html__( 'Data is stored locally on this site and is never transmitted to third-party services. You can request access to or erasure of your withdrawal data through Tools → Export Personal Data and Tools → Erase Personal Data.', 'eu-withdrawal-compliance' ) . '</p>';

	wp_add_privacy_policy_content(
		__( 'EU Withdrawal and Legal Guarantee Compliance', 'eu-withdrawal-compliance' ),
		wp_kses_post( $content )
	);
}
add_action( 'admin_init', 'ayudawp_euw_register_privacy_policy_content' );

/**
 * Register the personal data exporter for withdrawal requests.
 *
 * @param array $exporters Existing exporters keyed by ID.
 * @return array
 */
function ayudawp_euw_register_exporter( $exporters ) {

	$exporters['ayudawp-euw'] = array(
		'exporter_friendly_name' => __( 'EU Withdrawal and Legal Guarantee Compliance', 'eu-withdrawal-compliance' ),
		'callback'               => 'ayudawp_euw_personal_data_exporter',
	);

	return $exporters;
}
add_filter( 'wp_privacy_personal_data_exporters', 'ayudawp_euw_register_exporter' );

/**
 * Export every withdrawal request submitted with the given email.
 *
 * @param string $email_address Email address to look up.
 * @param int    $page          Current export page (1-indexed).
 * @return array {
 *     @type array $data Exported groups.
 *     @type bool  $done Whether the export has finished.
 * }
 */
function ayudawp_euw_personal_data_exporter( $email_address, $page = 1 ) {

	$export_items = array();
	$page         = max( 1, (int) $page );
	$per_page     = 50;

	$query = new WP_Query(
		array(
			'post_type'      => 'ayudawp_withdrawal',
			'post_status'    => 'any',
			'posts_per_page' => $per_page,
			'paged'          => $page,
			'fields'         => 'ids',
			'meta_query'     => array( // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_query
				array(
					'key'     => '_ayudawp_euw_email',
					'value'   => $email_address,
					'compare' => '=',
				),
			),
		)
	);

	foreach ( $query->posts as $post_id ) {

		$post    = get_post( $post_id );
		$details = $post ? $post->post_content : '';

		$data = array(
			array(
				'name'  => __( 'Customer name', 'eu-withdrawal-compliance' ),
				'value' => get_post_meta( $post_id, '_ayudawp_euw_name', true ),
			),
			array(
				'name'  => __( 'Customer email', 'eu-withdrawal-compliance' ),
				'value' => get_post_meta( $post_id, '_ayudawp_euw_email', true ),
			),
			array(
				'name'  => __( 'Order reference', 'eu-withdrawal-compliance' ),
				'value' => get_post_meta( $post_id, '_ayudawp_euw_order', true ),
			),
			array(
				'name'  => __( 'Order date', 'eu-withdrawal-compliance' ),
				'value' => get_post_meta( $post_id, '_ayudawp_euw_order_date', true ),
			),
			array(
				'name'  => __( 'Scope', 'eu-withdrawal-compliance' ),
				'value' => get_post_meta( $post_id, '_ayudawp_euw_scope', true ),
			),
			array(
				'name'  => __( 'Details', 'eu-withdrawal-compliance' ),
				'value' => $details,
			),
			array(
				'name'  => __( 'IP address', 'eu-withdrawal-compliance' ),
				'value' => get_post_meta( $post_id, '_ayudawp_euw_ip', true ),
			),
			array(
				'name'  => __( 'User agent', 'eu-withdrawal-compliance' ),
				'value' => get_post_meta( $post_id, '_ayudawp_euw_user_agent', true ),
			),
			array(
				'name'  => __( 'Status', 'eu-withdrawal-compliance' ),
				'value' => get_post_meta( $post_id, '_ayudawp_euw_status', true ),
			),
			array(
				'name'  => __( 'Submitted at (UTC)', 'eu-withdrawal-compliance' ),
				'value' => get_post_meta( $post_id, '_ayudawp_euw_submitted_at', true ),
			),
			array(
				'name'  => __( 'Receipt hash (SHA-256)', 'eu-withdrawal-compliance' ),
				'value' => get_post_meta( $post_id, '_ayudawp_euw_receipt_hash', true ),
			),
			array(
				'name'  => __( 'Note from the shop', 'eu-withdrawal-compliance' ),
				'value' => get_post_meta( $post_id, '_ayudawp_euw_status_comment', true ),
			),
		);

		// Strip empty rows so the export reads cleanly.
		$data = array_values(
			array_filter(
				$data,
				function ( $row ) {
					return '' !== (string) $row['value'];
				}
			)
		);

		$export_items[] = array(
			'group_id'    => 'ayudawp-euw',
			'group_label' => __( 'Withdrawal requests', 'eu-withdrawal-compliance' ),
			'item_id'     => 'ayudawp-euw-' . $post_id,
			'data'        => $data,
		);
	}

	$done = $page >= max( 1, (int) $query->max_num_pages );

	return array(
		'data' => $export_items,
		'done' => $done,
	);
}

/**
 * Register the personal data eraser for withdrawal requests.
 *
 * @param array $erasers Existing erasers keyed by ID.
 * @return array
 */
function ayudawp_euw_register_eraser( $erasers ) {

	$erasers['ayudawp-euw'] = array(
		'eraser_friendly_name' => __( 'EU Withdrawal and Legal Guarantee Compliance', 'eu-withdrawal-compliance' ),
		'callback'             => 'ayudawp_euw_personal_data_eraser',
	);

	return $erasers;
}
add_filter( 'wp_privacy_personal_data_erasers', 'ayudawp_euw_register_eraser' );

/**
 * Erase every withdrawal request submitted with the given email.
 *
 * Always queries page 1 because each pass deletes its results, so the next
 * batch of matching posts simply shifts up. The erase finishes as soon as
 * a pass returns fewer rows than the page size.
 *
 * @param string $email_address Email address to look up.
 * @param int    $page          Current page (unused, see method docblock).
 * @return array
 */
function ayudawp_euw_personal_data_eraser( $email_address, $page = 1 ) { // phpcs:ignore Generic.CodeAnalysis.UnusedFunctionParameter.Found

	$items_removed  = false;
	$items_retained = false;
	$messages       = array();
	$per_page       = 50;

	$query = new WP_Query(
		array(
			'post_type'      => 'ayudawp_withdrawal',
			'post_status'    => 'any',
			'posts_per_page' => $per_page,
			'paged'          => 1,
			'fields'         => 'ids',
			'meta_query'     => array( // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_query
				array(
					'key'     => '_ayudawp_euw_email',
					'value'   => $email_address,
					'compare' => '=',
				),
			),
		)
	);

	foreach ( $query->posts as $post_id ) {

		if ( wp_delete_post( $post_id, true ) ) {
			$items_removed = true;
		} else {
			$items_retained = true;
			$messages[]     = sprintf(
				/* translators: %d: withdrawal request ID. */
				__( 'Withdrawal request #%d could not be deleted.', 'eu-withdrawal-compliance' ),
				$post_id
			);
		}
	}

	$done = count( $query->posts ) < $per_page;

	return array(
		'items_removed'  => $items_removed,
		'items_retained' => $items_retained,
		'messages'       => $messages,
		'done'           => $done,
	);
}
