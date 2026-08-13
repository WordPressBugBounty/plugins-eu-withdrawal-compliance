<?php
/**
 * Email notifications.
 *
 * @package AyudaWP_EU_Withdrawal
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Default bodies for the status-change emails.
 *
 * Single source of truth for the bundled wording, reused by the settings
 * editor (to pre-fill the textareas) and by the sender below (as the fallback
 * when the admin leaves a field empty).
 *
 * @return array<string, string>
 */
function ayudawp_euw_status_email_defaults() {

	return array(
		'accepted'  => __( 'We have accepted your withdrawal request. We will proceed with the refund according to the legal deadline.', 'eu-withdrawal-compliance' ),
		'rejected'  => __( 'We have reviewed your withdrawal request and unfortunately we cannot accept it.', 'eu-withdrawal-compliance' ),
		'completed' => __( 'Your withdrawal has been processed and the refund issued. The funds may take a few business days to appear in your account.', 'eu-withdrawal-compliance' ),
	);
}

/**
 * Run a wp_mail() call with the plugin's configured From name/address applied.
 *
 * The wp_mail_from / wp_mail_from_name filters are global, so (like WooCommerce)
 * we add them just before sending and remove them right after, to avoid leaking
 * the override onto unrelated site emails. Each override is skipped when its
 * setting is empty, preserving the site default and the previous behaviour.
 *
 * @param callable $send Callback that performs the wp_mail() call and returns its result.
 * @return mixed Whatever $send returns (wp_mail()'s boolean).
 */
function ayudawp_euw_with_sender( $send ) {

	$from_name  = trim( (string) get_option( 'ayudawp_euw_from_name', '' ) );
	$from_email = trim( (string) get_option( 'ayudawp_euw_from_email', '' ) );

	$name_filter  = null;
	$email_filter = null;

	if ( '' !== $from_name ) {
		$name_filter = static function () use ( $from_name ) {
			return $from_name;
		};
		add_filter( 'wp_mail_from_name', $name_filter );
	}

	if ( '' !== $from_email && is_email( $from_email ) ) {
		$email_filter = static function () use ( $from_email ) {
			return $from_email;
		};
		add_filter( 'wp_mail_from', $email_filter );
	}

	$result = call_user_func( $send );

	if ( $name_filter ) {
		remove_filter( 'wp_mail_from_name', $name_filter );
	}

	if ( $email_filter ) {
		remove_filter( 'wp_mail_from', $email_filter );
	}

	return $result;
}

/**
 * Send the acknowledgement of receipt to the customer.
 *
 * This email is the durable-medium acknowledgement required by Article 11a(4)
 * of Directive 2011/83/EU: it reproduces the content of the declaration and
 * the date and time of its submission, and carries the verifiable SHA-256
 * receipt hash. The return value mirrors wp_mail() so the caller can record
 * whether the acknowledgement was accepted for delivery (burden of proof).
 *
 * @param string $email        Customer email.
 * @param string $name         Customer name.
 * @param string $order        Order reference.
 * @param string $scope        Withdrawal scope.
 * @param string $receipt_hash Optional SHA-256 receipt hash to include as proof.
 * @param string $submitted_at Optional GMT timestamp (Y-m-d H:i:s) of submission.
 * @param string $details      Optional free-text details of the declaration.
 * @param string $date         Optional order date as provided by the customer.
 * @param int    $request_id   Optional request ID, used to decide whether to link the account screen.
 * @return bool Whether the email was accepted for delivery.
 */
function ayudawp_euw_send_customer_email( $email, $name, $order, $scope, $receipt_hash = '', $submitted_at = '', $details = '', $date = '', $request_id = 0 ) {

	$site_name = get_bloginfo( 'name' );

	$subject = sprintf(
		/* translators: %s: site name. */
		__( '[%s] We received your withdrawal request', 'eu-withdrawal-compliance' ),
		$site_name
	);

	$scope_label = ( 'partial' === $scope )
		? __( 'Partial withdrawal (specific products only)', 'eu-withdrawal-compliance' )
		: __( 'Full withdrawal', 'eu-withdrawal-compliance' );

	$lines = array(
		sprintf(
			/* translators: %s: customer name. */
			__( 'Hi %s,', 'eu-withdrawal-compliance' ),
			$name
		),
		'',
		__( 'We have received and registered your withdrawal request. This message is your acknowledgement of receipt on a durable medium, as required by EU consumer law. Please keep it as proof.', 'eu-withdrawal-compliance' ),
		'',
		__( 'Content of your declaration:', 'eu-withdrawal-compliance' ),
		sprintf( '- %s: %s', __( 'Name', 'eu-withdrawal-compliance' ), $name ),
		sprintf( '- %s: %s', __( 'Order', 'eu-withdrawal-compliance' ), $order ),
	);

	if ( '' !== $date ) {
		$lines[] = sprintf( '- %s: %s', __( 'Order date', 'eu-withdrawal-compliance' ), $date );
	}

	$lines[] = sprintf( '- %s: %s', __( 'Scope', 'eu-withdrawal-compliance' ), $scope_label );

	if ( '' !== $details ) {
		$lines[] = sprintf( '- %s: %s', __( 'Affected products / additional information', 'eu-withdrawal-compliance' ), $details );
	}

	$submitted_display = ayudawp_euw_format_datetime( $submitted_at );

	if ( '' !== $submitted_display ) {
		$lines[] = '';
		$lines[] = sprintf( '%s: %s', __( 'Date and time of submission', 'eu-withdrawal-compliance' ), $submitted_display );
	}

	if ( '' !== $receipt_hash ) {
		$lines[] = '';
		$lines[] = '----';
		$lines[] = __( 'Receipt verification code (keep this email as proof of submission):', 'eu-withdrawal-compliance' );
		$lines[] = $receipt_hash;
	}

	$lines[] = '';
	$lines[] = ayudawp_euw_legal_conditions_text();
	$lines[] = '';
	$lines[] = __( 'We will review the request and confirm next steps within 24 hours. If you do not hear from us, please reply to this email.', 'eu-withdrawal-compliance' );

	$account_url = ayudawp_euw_get_customer_requests_url( $email, $request_id );

	if ( '' !== $account_url ) {
		$lines[] = '';
		$lines[] = __( 'You can also follow your withdrawal requests from your account:', 'eu-withdrawal-compliance' );
		$lines[] = $account_url;
	}

	$lines[] = '';
	$lines[] = sprintf(
		/* translators: %s: site name. */
		__( 'Thanks, the %s team', 'eu-withdrawal-compliance' ),
		$site_name
	);

	$message = implode( "\r\n", $lines );

	$headers = array( 'Content-Type: text/plain; charset=UTF-8' );

	return ayudawp_euw_with_sender(
		static function () use ( $email, $subject, $message, $headers ) {
			return wp_mail( $email, $subject, $message, $headers );
		}
	);
}

/**
 * Send notification to the shop admin.
 *
 * @param int    $post_id        Withdrawal CPT ID.
 * @param string $name           Customer name.
 * @param string $email          Customer email.
 * @param string $order          Order reference.
 * @param string $scope          Withdrawal scope.
 * @param string $details        Free-text details.
 * @param array  $excluded_items Items in the order that match an Article 16 exclusion, if any.
 * @param int    $deadline_ts    Approximate withdrawal-window close timestamp; when in the past, an advisory flag is added.
 */
function ayudawp_euw_send_admin_email( $post_id, $name, $email, $order, $scope, $details, $excluded_items = array(), $deadline_ts = 0 ) {

	$notify_option = (string) get_option( 'ayudawp_euw_notify_email', get_option( 'admin_email' ) );

	// The notification option may hold several comma-separated recipients.
	// Validate each and drop the invalid ones; if none survive, fall back to the
	// site admin email so a request is never silently un-notified. wp_mail()
	// accepts the resulting array of recipients directly.
	$admin_email = array();

	foreach ( explode( ',', $notify_option ) as $candidate ) {

		$candidate = sanitize_email( trim( $candidate ) );

		if ( '' !== $candidate && is_email( $candidate ) ) {
			$admin_email[] = $candidate;
		}
	}

	if ( empty( $admin_email ) ) {
		$admin_email = array( get_option( 'admin_email' ) );
	}

	$site_name = get_bloginfo( 'name' );

	// Saved before this email is composed, so the flag is already on the post.
	$unverified = (string) get_post_meta( $post_id, '_ayudawp_euw_unverified', true );

	$subject = ( '' !== $unverified )
		? sprintf(
			/* translators: 1: site name, 2: order number. */
			__( '[%1$s] New withdrawal request (unverified) — order %2$s', 'eu-withdrawal-compliance' ),
			$site_name,
			$order
		)
		: sprintf(
			/* translators: 1: site name, 2: order number. */
			__( '[%1$s] New withdrawal request — order %2$s', 'eu-withdrawal-compliance' ),
			$site_name,
			$order
		);

	$scope_label = ( 'partial' === $scope )
		? __( 'Partial withdrawal', 'eu-withdrawal-compliance' )
		: __( 'Full withdrawal', 'eu-withdrawal-compliance' );

	// This email is composed inside the customer's public submission request, where the
	// current user is the (often logged-out) customer and lacks the capability to edit the
	// request. get_edit_post_link() checks that capability and would return an empty string,
	// leaving the admin notification without a link. Build the URL directly instead; wp-admin
	// re-checks capabilities when the recipient opens it.
	$edit_link = add_query_arg(
		array(
			'post'   => (int) $post_id,
			'action' => 'edit',
		),
		admin_url( 'post.php' )
	);

	$lines = array(
		__( 'A new withdrawal request has just been submitted.', 'eu-withdrawal-compliance' ),
		'',
		sprintf( '%s: %s', __( 'Customer', 'eu-withdrawal-compliance' ), $name ),
		sprintf( '%s: %s', __( 'Email', 'eu-withdrawal-compliance' ), $email ),
		sprintf( '%s: %s', __( 'Order', 'eu-withdrawal-compliance' ), $order ),
		sprintf( '%s: %s', __( 'Scope', 'eu-withdrawal-compliance' ), $scope_label ),
		'',
		__( 'Details:', 'eu-withdrawal-compliance' ),
		( ! empty( $details ) ? $details : __( '(empty)', 'eu-withdrawal-compliance' ) ),
	);

	if ( '' !== $unverified ) {
		$lines[] = '';
		$lines[] = __( '⚠ UNVERIFIED REQUEST: these details could not be matched with an order.', 'eu-withdrawal-compliance' );

		if ( 'email_mismatch' === $unverified ) {

			$hint = absint( get_post_meta( $post_id, '_ayudawp_euw_unverified_order_hint', true ) );

			if ( $hint ) {
				$lines[] = sprintf(
					/* translators: %d: WooCommerce order ID. */
					__( 'The reference matches order #%d, but its billing email differs from the address submitted.', 'eu-withdrawal-compliance' ),
					$hint
				);
			} else {
				$lines[] = __( 'The reference matches an existing order, but its billing email differs from the address submitted.', 'eu-withdrawal-compliance' );
			}
		} else {
			$lines[] = __( 'No order was found for the reference provided. It may be a typo or a purchase made outside WooCommerce.', 'eu-withdrawal-compliance' );
		}

		$lines[] = __( 'It was registered because “Accept unmatched requests” is enabled. Verify it manually against your records before deciding.', 'eu-withdrawal-compliance' );
	}

	if ( ! empty( $excluded_items ) ) {
		$lines[] = '';
		$lines[] = __( '⚠ The order contains items flagged as excluded from the right of withdrawal (Article 16):', 'eu-withdrawal-compliance' );
		foreach ( $excluded_items as $item ) {
			$lines[] = sprintf( '- %s × %d', $item['name'], (int) $item['quantity'] );
		}
		$lines[] = __( 'Review manually before accepting or rejecting. A partial withdrawal over non-excluded items may still be valid.', 'eu-withdrawal-compliance' );
	}

	if ( $deadline_ts && time() > $deadline_ts ) {
		$lines[] = '';
		$lines[] = __( '⚠ This request may be outside the 14-day window based on the order date.', 'eu-withdrawal-compliance' );
		$lines[] = __( 'The legal period runs from the delivery date (or, for digital content, from the start of the download), which the plugin cannot detect automatically. Check the actual delivery date (e.g. the carrier tracking) before deciding. Never auto-rejected.', 'eu-withdrawal-compliance' );
	}

	$lines[] = '';
	$lines[] = __( 'View in admin:', 'eu-withdrawal-compliance' );
	$lines[] = $edit_link;

	/**
	 * Filter the lines of the admin notification email before they are joined.
	 *
	 * Each entry is one plain-text line, so integrators can add, remove or
	 * reorder lines to customise the notification without rebuilding the email.
	 *
	 * @param array $lines   Lines of the notification body.
	 * @param array $context Request context: post_id, name, email, order, scope, details.
	 */
	$lines = apply_filters(
		'ayudawp_euw_admin_email_lines',
		$lines,
		array(
			'post_id' => (int) $post_id,
			'name'    => $name,
			'email'   => $email,
			'order'   => $order,
			'scope'   => $scope,
			'details' => $details,
		)
	);

	$message = implode( "\r\n", (array) $lines );

	// Sanitize the Reply-To header values to prevent CRLF header injection.
	$clean_name  = sanitize_text_field( $name );
	$clean_email = sanitize_email( $email );

	$headers = array( 'Content-Type: text/plain; charset=UTF-8' );

	if ( $clean_email ) {
		$headers[] = sprintf( 'Reply-To: %s <%s>', $clean_name, $clean_email );
	}

	ayudawp_euw_with_sender(
		static function () use ( $admin_email, $subject, $message, $headers ) {
			return wp_mail( $admin_email, $subject, $message, $headers );
		}
	);
}

/**
 * Notify the customer that the status of their withdrawal request changed.
 *
 * Only fires for accepted/rejected/completed transitions; pending is the
 * initial state and already covered by the submission acknowledgement.
 *
 * @param string $email      Customer email.
 * @param string $name       Customer name.
 * @param string $order      Order reference.
 * @param string $status     New status (accepted|rejected|completed).
 * @param string $comment    Optional admin comment.
 * @param int    $request_id Optional request ID, used to decide whether to link the account screen.
 */
function ayudawp_euw_send_status_email( $email, $name, $order, $status, $comment = '', $request_id = 0 ) {

	$site_name = get_bloginfo( 'name' );

	$subjects = array(
		'accepted'  => sprintf(
			/* translators: %s: site name. */
			__( '[%s] Your withdrawal request has been accepted', 'eu-withdrawal-compliance' ),
			$site_name
		),
		'rejected'  => sprintf(
			/* translators: %s: site name. */
			__( '[%s] Your withdrawal request has been rejected', 'eu-withdrawal-compliance' ),
			$site_name
		),
		'completed' => sprintf(
			/* translators: %s: site name. */
			__( '[%s] Your withdrawal has been completed', 'eu-withdrawal-compliance' ),
			$site_name
		),
	);

	if ( ! isset( $subjects[ $status ] ) ) {
		return;
	}

	// Admins can override each status body in Settings; an empty field falls back
	// to the bundled default. The order number and sign-off below are always
	// appended in code, so the editable text is only the lead paragraph.
	$defaults = ayudawp_euw_status_email_defaults();
	$body     = (string) get_option( 'ayudawp_euw_status_email_body_' . $status, '' );

	if ( '' === trim( $body ) ) {
		$body = $defaults[ $status ];
	}

	$lines = array(
		sprintf(
			/* translators: %s: customer name. */
			__( 'Hi %s,', 'eu-withdrawal-compliance' ),
			$name
		),
		'',
		$body,
		'',
		sprintf( '%s: %s', __( 'Order', 'eu-withdrawal-compliance' ), $order ),
	);

	if ( '' !== $comment ) {
		$lines[] = '';
		$lines[] = __( 'Additional information from our team:', 'eu-withdrawal-compliance' );
		$lines[] = $comment;
	}

	$account_url = ayudawp_euw_get_customer_requests_url( $email, $request_id );

	if ( '' !== $account_url ) {
		$lines[] = '';
		$lines[] = __( 'You can also follow your withdrawal requests from your account:', 'eu-withdrawal-compliance' );
		$lines[] = $account_url;
	}

	$lines[] = '';
	$lines[] = sprintf(
		/* translators: %s: site name. */
		__( 'Thanks, the %s team', 'eu-withdrawal-compliance' ),
		$site_name
	);

	$message = implode( "\r\n", $lines );

	$headers = array( 'Content-Type: text/plain; charset=UTF-8' );

	ayudawp_euw_with_sender(
		static function () use ( $email, $subjects, $status, $message, $headers ) {
			return wp_mail( $email, $subjects[ $status ], $message, $headers );
		}
	);
}
