<?php
/**
 * Admin: detail and status metaboxes plus status transition lifecycle.
 *
 * @package AyudaWP_EU_Withdrawal
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Add metabox with full request details.
 */
function ayudawp_euw_register_metabox() {

	add_meta_box(
		'ayudawp_euw_details',
		__( 'Withdrawal request details', 'eu-withdrawal-compliance' ),
		'ayudawp_euw_metabox_content',
		'ayudawp_withdrawal',
		'normal',
		'high'
	);

	add_meta_box(
		'ayudawp_euw_status',
		__( 'Status', 'eu-withdrawal-compliance' ),
		'ayudawp_euw_metabox_status',
		'ayudawp_withdrawal',
		'side',
		'high'
	);
}
add_action( 'add_meta_boxes', 'ayudawp_euw_register_metabox' );

/**
 * Render the details metabox.
 *
 * @param WP_Post $post Current post.
 */
function ayudawp_euw_metabox_content( $post ) {

	ayudawp_euw_render_metabox_unverified_notice( (int) $post->ID );

	$fields = array(
		'name'         => array(
			'label' => __( 'Customer name', 'eu-withdrawal-compliance' ),
			'meta'  => '_ayudawp_euw_name',
		),
		'email'        => array(
			'label' => __( 'Email', 'eu-withdrawal-compliance' ),
			'meta'  => '_ayudawp_euw_email',
		),
		'order'        => array(
			'label' => __( 'Order number', 'eu-withdrawal-compliance' ),
			'meta'  => '_ayudawp_euw_order',
		),
		'order_date'   => array(
			'label' => __( 'Order date', 'eu-withdrawal-compliance' ),
			'meta'  => '_ayudawp_euw_order_date',
		),
		'scope'        => array(
			'label' => __( 'Scope', 'eu-withdrawal-compliance' ),
			'meta'  => '_ayudawp_euw_scope',
		),
		'submitted_at' => array(
			'label' => __( 'Submitted at (UTC)', 'eu-withdrawal-compliance' ),
			'meta'  => '_ayudawp_euw_submitted_at',
		),
		'changed_at'   => array(
			'label' => __( 'Status changed at (UTC)', 'eu-withdrawal-compliance' ),
			'meta'  => '_ayudawp_euw_status_changed_at',
		),
		'receipt_hash' => array(
			'label' => __( 'Receipt hash (SHA-256)', 'eu-withdrawal-compliance' ),
			'meta'  => '_ayudawp_euw_receipt_hash',
		),
		'ip'           => array(
			'label' => __( 'IP address', 'eu-withdrawal-compliance' ),
			'meta'  => '_ayudawp_euw_ip',
		),
		'user_agent'   => array(
			'label' => __( 'User agent', 'eu-withdrawal-compliance' ),
			'meta'  => '_ayudawp_euw_user_agent',
		),
	);

	echo '<table class="ayudawp-euw-meta-table"><tbody>';

	foreach ( $fields as $key => $field ) {
		$value = get_post_meta( $post->ID, $field['meta'], true );

		if ( 'scope' === $key ) {
			$value = ( 'partial' === $value )
				? __( 'Partial', 'eu-withdrawal-compliance' )
				: __( 'Full', 'eu-withdrawal-compliance' );
		}

		if ( 'changed_at' === $key && '' === $value ) {
			$value = '—';
		}

		printf(
			'<tr><th scope="row">%1$s</th><td>%2$s</td></tr>',
			esc_html( $field['label'] ),
			esc_html( $value )
		);
	}

	echo '</tbody></table>';

	$excluded_items = get_post_meta( $post->ID, '_ayudawp_euw_excluded_items', true );

	if ( is_array( $excluded_items ) && ! empty( $excluded_items ) ) {
		echo '<h4>' . esc_html__( 'Article 16 exclusions detected in this order', 'eu-withdrawal-compliance' ) . '</h4>';
		echo '<ul class="ayudawp-euw-excluded-items">';
		foreach ( $excluded_items as $item ) {
			$name     = isset( $item['name'] ) ? (string) $item['name'] : '';
			$quantity = isset( $item['quantity'] ) ? (int) $item['quantity'] : 1;
			printf(
				'<li>%1$s × %2$d</li>',
				esc_html( $name ),
				esc_html( $quantity )
			);
		}
		echo '</ul>';
		echo '<p class="description">' . esc_html__( 'These items were flagged as excluded from the right of withdrawal at the time the request was submitted. Review manually before accepting or rejecting; a partial withdrawal over the rest of the order may still be valid.', 'eu-withdrawal-compliance' ) . '</p>';
	}

	echo '<h4>' . esc_html__( 'Additional information', 'eu-withdrawal-compliance' ) . '</h4>';
	echo '<div class="ayudawp-euw-details">';
	echo wp_kses_post( wpautop( $post->post_content ) );
	echo '</div>';

	ayudawp_euw_render_metabox_checkout_consents( (int) $post->ID );

	ayudawp_euw_render_metabox_consumer_declaration( (int) $post->ID );
}

/**
 * Render a warning when the request was registered without a matching order.
 *
 * Shown at the top of the details metabox so the admin sees, before anything
 * else, that this request must be verified manually against the shop records
 * (the "Accept unmatched requests" setting registered it as unverified).
 *
 * @param int $post_id Withdrawal CPT ID.
 */
function ayudawp_euw_render_metabox_unverified_notice( $post_id ) {

	$reason = (string) get_post_meta( $post_id, '_ayudawp_euw_unverified', true );

	if ( '' === $reason ) {
		return;
	}

	echo '<div class="notice notice-warning inline ayudawp-euw-unverified-notice"><p>';
	echo '<strong>' . esc_html__( 'Unverified request.', 'eu-withdrawal-compliance' ) . '</strong> ';

	if ( 'email_mismatch' === $reason ) {

		$hint     = absint( get_post_meta( $post_id, '_ayudawp_euw_unverified_order_hint', true ) );
		$edit_url = '';

		if ( $hint && function_exists( 'wc_get_order' ) ) {
			$hint_order = wc_get_order( $hint );

			if ( $hint_order ) {
				$edit_url = method_exists( $hint_order, 'get_edit_order_url' )
					? $hint_order->get_edit_order_url()
					: admin_url( 'post.php?post=' . $hint . '&action=edit' );
			}
		}

		if ( '' !== $edit_url ) {
			printf(
				wp_kses(
					/* translators: 1: order edit URL, 2: WooCommerce order ID. */
					__( 'The reference matches <a href="%1$s">order #%2$d</a>, but its billing email differs from the address submitted.', 'eu-withdrawal-compliance' ),
					array( 'a' => array( 'href' => array() ) )
				),
				esc_url( $edit_url ),
				absint( $hint )
			);
		} else {
			esc_html_e( 'The reference matches an existing order, but its billing email differs from the address submitted.', 'eu-withdrawal-compliance' );
		}
	} else {
		esc_html_e( 'No order was found for the reference provided. It may be a typo or a purchase made outside WooCommerce.', 'eu-withdrawal-compliance' );
	}

	echo ' ' . esc_html__( 'Verify it manually against your records before accepting or rejecting.', 'eu-withdrawal-compliance' );
	echo '</p></div>';
}

/**
 * Render the captured checkout consents stored on the linked WC order.
 *
 * Outputs a clean table for each consent type that was active when the
 * order was placed, including the exact text shown to the customer, the
 * accepted/declined state, timestamp, IP and user agent. Skips silently
 * when there is no WC order linked or no consent meta to display so the
 * metabox stays compact for requests where the section does not apply.
 *
 * @param int $post_id Withdrawal CPT ID.
 */
function ayudawp_euw_render_metabox_checkout_consents( $post_id ) {

	$wc_order_id = absint( get_post_meta( $post_id, '_ayudawp_euw_wc_order_id', true ) );

	if ( ! $wc_order_id || ! function_exists( 'wc_get_order' ) ) {
		return;
	}

	$order = wc_get_order( $wc_order_id );

	if ( ! $order instanceof WC_Order ) {
		return;
	}

	$types = array(
		'a' => __( 'Type A — Digital content (Art. 16(m))', 'eu-withdrawal-compliance' ),
		'b' => __( 'Type B — Service started early (Art. 14(4)(a))', 'eu-withdrawal-compliance' ),
	);

	$rendered_any = false;
	ob_start();

	foreach ( $types as $type => $label ) {

		$accepted  = (string) $order->get_meta( '_ayudawp_euw_consent_' . $type . '_accepted' );
		$text      = (string) $order->get_meta( '_ayudawp_euw_consent_' . $type . '_text' );
		$timestamp = (string) $order->get_meta( '_ayudawp_euw_consent_' . $type . '_timestamp' );
		$ip        = (string) $order->get_meta( '_ayudawp_euw_consent_' . $type . '_ip' );
		$ua        = (string) $order->get_meta( '_ayudawp_euw_consent_' . $type . '_ua' );

		if ( '' === $accepted && '' === $text && '' === $timestamp ) {
			continue;
		}

		$rendered_any = true;

		$accepted_label = ( 'yes' === $accepted )
			? __( 'Accepted', 'eu-withdrawal-compliance' )
			: __( 'Declined', 'eu-withdrawal-compliance' );

		?>
		<h5><?php echo esc_html( $label ); ?></h5>
		<table class="ayudawp-euw-meta-table">
			<tbody>
				<tr>
					<th scope="row"><?php esc_html_e( 'Status', 'eu-withdrawal-compliance' ); ?></th>
					<td><?php echo esc_html( $accepted_label ); ?></td>
				</tr>
				<tr>
					<th scope="row"><?php esc_html_e( 'Text shown at checkout', 'eu-withdrawal-compliance' ); ?></th>
					<td><?php echo wp_kses_post( $text ); ?></td>
				</tr>
				<tr>
					<th scope="row"><?php esc_html_e( 'Timestamp (UTC)', 'eu-withdrawal-compliance' ); ?></th>
					<td><?php echo esc_html( $timestamp ); ?></td>
				</tr>
				<tr>
					<th scope="row"><?php esc_html_e( 'IP address', 'eu-withdrawal-compliance' ); ?></th>
					<td><?php echo esc_html( $ip ); ?></td>
				</tr>
				<tr>
					<th scope="row"><?php esc_html_e( 'User agent', 'eu-withdrawal-compliance' ); ?></th>
					<td><?php echo esc_html( $ua ); ?></td>
				</tr>
			</tbody>
		</table>
		<?php
	}

	$buffer = ob_get_clean();

	if ( ! $rendered_any ) {
		return;
	}

	echo '<h4>' . esc_html__( 'Checkout consents', 'eu-withdrawal-compliance' ) . '</h4>';
	echo wp_kses_post( $buffer );
	echo '<p class="description">' . esc_html__( 'Snapshot of the consent text exactly as shown to the customer at checkout, together with timestamp, IP and user agent. Use as durable proof if the customer later contests the request.', 'eu-withdrawal-compliance' ) . '</p>';
}

/**
 * Render the B2B consumer self-declaration captured with the request, if any.
 *
 * Shows the exact wording the customer agreed to and the timestamp, as durable
 * proof that they declared having bought as a consumer. Outputs nothing when the
 * declaration was not part of the request (the option was off at submission).
 *
 * @param int $post_id Withdrawal CPT ID.
 */
function ayudawp_euw_render_metabox_consumer_declaration( $post_id ) {

	if ( '1' !== (string) get_post_meta( $post_id, '_ayudawp_euw_consumer', true ) ) {
		return;
	}

	$text = (string) get_post_meta( $post_id, '_ayudawp_euw_consumer_text', true );
	$when = (string) get_post_meta( $post_id, '_ayudawp_euw_consumer_at', true );

	echo '<h4>' . esc_html__( 'Consumer self-declaration', 'eu-withdrawal-compliance' ) . '</h4>';
	echo '<table class="ayudawp-euw-meta-table"><tbody>';

	printf(
		'<tr><th scope="row">%1$s</th><td>%2$s</td></tr>',
		esc_html__( 'Declared', 'eu-withdrawal-compliance' ),
		esc_html__( 'The customer confirmed they bought as a consumer.', 'eu-withdrawal-compliance' )
	);

	if ( '' !== trim( $text ) ) {
		printf(
			'<tr><th scope="row">%1$s</th><td>%2$s</td></tr>',
			esc_html__( 'Statement shown', 'eu-withdrawal-compliance' ),
			esc_html( $text )
		);
	}

	printf(
		'<tr><th scope="row">%1$s</th><td>%2$s</td></tr>',
		esc_html__( 'Timestamp (UTC)', 'eu-withdrawal-compliance' ),
		esc_html( '' !== $when ? $when : '—' )
	);

	echo '</tbody></table>';
}

/**
 * Render the status metabox with a save button.
 *
 * @param WP_Post $post Current post.
 */
function ayudawp_euw_metabox_status( $post ) {

	wp_nonce_field( 'ayudawp_euw_save_status', 'ayudawp_euw_status_nonce' );

	$status = get_post_meta( $post->ID, '_ayudawp_euw_status', true );
	$status = $status ? $status : 'pending';

	$statuses = array(
		'pending'   => __( 'Pending', 'eu-withdrawal-compliance' ),
		'accepted'  => __( 'Accepted', 'eu-withdrawal-compliance' ),
		'rejected'  => __( 'Rejected', 'eu-withdrawal-compliance' ),
		'completed' => __( 'Completed', 'eu-withdrawal-compliance' ),
	);

	echo '<p>';
	echo '<label for="ayudawp_euw_status_field"><strong>' . esc_html__( 'Set status', 'eu-withdrawal-compliance' ) . '</strong></label><br>';
	echo '<select name="ayudawp_euw_status_field" id="ayudawp_euw_status_field" style="width:100%">';

	foreach ( $statuses as $key => $label ) {
		printf(
			'<option value="%1$s" %2$s>%3$s</option>',
			esc_attr( $key ),
			selected( $status, $key, false ),
			esc_html( $label )
		);
	}

	echo '</select>';
	echo '</p>';
	echo '<p class="description">' . esc_html__( 'Track the internal handling state of this request.', 'eu-withdrawal-compliance' ) . '</p>';

	echo '<p>';
	echo '<label for="ayudawp_euw_status_comment"><strong>' . esc_html__( 'Comments for the customer', 'eu-withdrawal-compliance' ) . '</strong></label><br>';
	echo '<textarea name="ayudawp_euw_status_comment" id="ayudawp_euw_status_comment" rows="4" style="width:100%" placeholder="' . esc_attr__( 'Will be included in the email sent to the customer when the status changes.', 'eu-withdrawal-compliance' ) . '"></textarea>';
	echo '</p>';
	echo '<p class="description">' . esc_html__( 'Required when rejecting a request, optional when marking as completed. Not stored — sent in the email and saved to the order note.', 'eu-withdrawal-compliance' ) . '</p>';
}

/**
 * Save status changes from the metabox.
 *
 * Detects status transitions and triggers side effects: linked WC order note
 * and a customer email. The free-form admin comment is forwarded to the
 * customer email and the order note, but is not persisted as post meta — we
 * deliberately keep it ephemeral to avoid stockpiling per-request comment
 * history outside the audit trail (order notes already serve that purpose).
 *
 * @param int $post_id Post ID.
 */
function ayudawp_euw_save_status( $post_id ) {

	if ( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE ) {
		return;
	}

	if ( ! isset( $_POST['ayudawp_euw_status_nonce'] )
		|| ! wp_verify_nonce(
			sanitize_text_field( wp_unslash( $_POST['ayudawp_euw_status_nonce'] ) ),
			'ayudawp_euw_save_status'
		)
	) {
		return;
	}

	if ( ! current_user_can( 'edit_post', $post_id ) ) {
		return;
	}

	if ( get_post_type( $post_id ) !== 'ayudawp_withdrawal' ) {
		return;
	}

	if ( ! isset( $_POST['ayudawp_euw_status_field'] ) ) {
		return;
	}

	$new_status = sanitize_key( wp_unslash( $_POST['ayudawp_euw_status_field'] ) );
	$allowed    = array( 'pending', 'accepted', 'rejected', 'completed' );

	if ( ! in_array( $new_status, $allowed, true ) ) {
		return;
	}

	$comment = isset( $_POST['ayudawp_euw_status_comment'] )
		? sanitize_textarea_field( wp_unslash( $_POST['ayudawp_euw_status_comment'] ) )
		: '';

	$old_status = get_post_meta( $post_id, '_ayudawp_euw_status', true );
	$old_status = $old_status ? $old_status : 'pending';

	// Reject without a comment is not allowed: the customer must be told why.
	if ( 'rejected' === $new_status && '' === $comment && 'rejected' !== $old_status ) {
		set_transient(
			'ayudawp_euw_status_error_' . get_current_user_id(),
			__( 'A comment is required when rejecting a withdrawal request. Status not changed.', 'eu-withdrawal-compliance' ),
			60
		);
		return;
	}

	update_post_meta( $post_id, '_ayudawp_euw_status', $new_status );

	if ( $new_status !== $old_status ) {
		ayudawp_euw_handle_status_transition( $post_id, $new_status, $comment );
	}
}
add_action( 'save_post_ayudawp_withdrawal', 'ayudawp_euw_save_status' );

/**
 * Show the metabox validation error after a redirect-after-save.
 */
function ayudawp_euw_status_admin_notice() {

	$key     = 'ayudawp_euw_status_error_' . get_current_user_id();
	$message = get_transient( $key );

	if ( ! $message ) {
		return;
	}

	delete_transient( $key );

	printf(
		'<div class="notice notice-error is-dismissible"><p>%s</p></div>',
		esc_html( $message )
	);
}
add_action( 'admin_notices', 'ayudawp_euw_status_admin_notice' );

/**
 * React to a status change: write a note on the linked WC order and email the customer.
 *
 * @param int    $post_id    Withdrawal CPT ID.
 * @param string $new_status Target status.
 * @param string $comment    Optional admin comment to forward to the customer.
 */
function ayudawp_euw_handle_status_transition( $post_id, $new_status, $comment = '' ) {

	// Record when the request was acted upon. This is only reached on a real
	// transition (both the metabox and the bulk action guard against no-ops),
	// so it doubles as the resolution timestamp once the status is final.
	update_post_meta( $post_id, '_ayudawp_euw_status_changed_at', current_time( 'mysql', true ) );

	$wc_order_id = absint( get_post_meta( $post_id, '_ayudawp_euw_wc_order_id', true ) );

	if ( $wc_order_id ) {
		ayudawp_euw_add_status_order_note( $wc_order_id, $post_id, $new_status, $comment );
	}

	if ( in_array( $new_status, array( 'accepted', 'rejected', 'completed' ), true ) ) {

		$email = get_post_meta( $post_id, '_ayudawp_euw_email', true );
		$name  = get_post_meta( $post_id, '_ayudawp_euw_name', true );
		$order = get_post_meta( $post_id, '_ayudawp_euw_order', true );

		if ( $email && is_email( $email ) ) {
			ayudawp_euw_send_status_email( $email, $name, $order, $new_status, $comment );
		}
	}

	/**
	 * Fires after a withdrawal request status changes.
	 *
	 * @param int    $post_id    Withdrawal CPT ID.
	 * @param string $new_status New status.
	 * @param string $comment    Admin comment.
	 */
	do_action( 'ayudawp_euw_after_status_change', $post_id, $new_status, $comment );
}
