<?php
/**
 * Form submission handler.
 *
 * @package AyudaWP_EU_Withdrawal
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Collect and sanitize the withdrawal form fields from the request.
 *
 * The caller is responsible for verifying the nonce before invoking this.
 *
 * @return array Sanitized field values.
 */
function ayudawp_euw_collect_form_data() {

	// phpcs:disable WordPress.Security.NonceVerification.Missing -- nonce verified by the only caller, ayudawp_euw_handle_review(), in includes/functions-handler.php:172, before this runs.
	$data = array(
		'name'     => isset( $_POST['ayudawp_euw_name'] ) ? sanitize_text_field( wp_unslash( $_POST['ayudawp_euw_name'] ) ) : '',
		'email'    => isset( $_POST['ayudawp_euw_email'] ) ? sanitize_email( wp_unslash( $_POST['ayudawp_euw_email'] ) ) : '',
		'order'    => isset( $_POST['ayudawp_euw_order'] ) ? sanitize_text_field( wp_unslash( $_POST['ayudawp_euw_order'] ) ) : '',
		'date'     => isset( $_POST['ayudawp_euw_date'] ) ? sanitize_text_field( wp_unslash( $_POST['ayudawp_euw_date'] ) ) : '',
		'scope'    => isset( $_POST['ayudawp_euw_scope'] ) ? sanitize_key( wp_unslash( $_POST['ayudawp_euw_scope'] ) ) : 'full',
		'details'  => isset( $_POST['ayudawp_euw_details'] ) ? sanitize_textarea_field( wp_unslash( $_POST['ayudawp_euw_details'] ) ) : '',
		'privacy'  => isset( $_POST['ayudawp_euw_privacy'] ) ? '1' : '',
		'consumer' => isset( $_POST['ayudawp_euw_consumer'] ) ? '1' : '',
	);
	// phpcs:enable WordPress.Security.NonceVerification.Missing

	if ( ! in_array( $data['scope'], array( 'full', 'partial' ), true ) ) {
		$data['scope'] = 'full';
	}

	return $data;
}

/**
 * Validate a collected submission.
 *
 * On failure it redirects back with an error code and exits. On success it
 * returns the WooCommerce validation result so the caller can reuse it.
 *
 * @param array $data Sanitized values from ayudawp_euw_collect_form_data().
 * @return array WC validation result: array( 'valid', 'order_id', 'error' ).
 */
function ayudawp_euw_validate_submission( $data ) {

	// Collect every missing required field so the form can highlight each one in
	// red, instead of failing on the first one with a single generic message.
	$missing = array();

	foreach ( array( 'name', 'email', 'order', 'privacy' ) as $required ) {
		if ( empty( $data[ $required ] ) ) {
			$missing[] = $required;
		}
	}

	// When the B2B self-declaration is enabled globally it is required just like
	// the privacy consent. The shortcode attribute only controls display, so the
	// hard requirement keys off the global option to keep validation predictable.
	if ( 'yes' === get_option( 'ayudawp_euw_consumer_check_enabled', 'no' ) && empty( $data['consumer'] ) ) {
		$missing[] = 'consumer';
	}

	if ( ! empty( $missing ) ) {
		ayudawp_euw_redirect_with_error( 'fields', $data, $missing );
	}

	if ( ! is_email( $data['email'] ) ) {
		ayudawp_euw_redirect_with_error( 'email', $data, array( 'email' ) );
	}

	$wc_validation = ayudawp_euw_validate_wc_order( $data['order'], $data['email'] );

	/**
	 * Filter the validation result before it decides whether to proceed.
	 *
	 * Return an array with 'valid' => false and an 'error' code to reject the
	 * submission, e.g. from a captcha integration that rendered its widget through
	 * the `ayudawp_euw_form_before_submit` action. Custom error codes fall back to
	 * the generic message unless added to `ayudawp_euw_get_error_message`.
	 *
	 * @param array $wc_validation Result: array( 'valid', 'error', 'order_id' ),
	 *                             plus 'unverified' (and 'unverified_hint') when
	 *                             the unmatched-requests setting accepted it.
	 * @param array $data          Sanitized submission data.
	 */
	$wc_validation = apply_filters( 'ayudawp_euw_validation_result', $wc_validation, $data );

	if ( false === $wc_validation['valid'] ) {
		// Highlight the order and email fields when the pair did not match; the
		// other codes (status, expired) are about the order itself, so only the
		// notice shows, with no field marked.
		$highlight = ( 'order' === $wc_validation['error'] ) ? array( 'order', 'email' ) : array();
		ayudawp_euw_redirect_with_error( $wc_validation['error'], $data, $highlight );
	}

	return $wc_validation;
}

/**
 * Whether the public form posts to admin-post.php instead of to its own page.
 *
 * Off by default. A way back for the shop where something in front of the
 * site refuses a POST to a public page: returning true from the filter makes
 * both forms post to admin-post.php again, as they did up to 2.3.0.
 *
 * @return bool
 */
function ayudawp_euw_form_uses_admin_post() {

	/**
	 * Filter whether the withdrawal form posts to admin-post.php.
	 *
	 * @param bool $via_admin_post Default false: the form posts to its own page.
	 */
	return (bool) apply_filters( 'ayudawp_euw_form_via_admin_post', false );
}

/**
 * Step 1: validate the declaration and show the confirmation screen.
 *
 * The request is NOT registered here. Article 11a(3) of Directive 2011/83/EU
 * requires the consumer to confirm the decision through a dedicated
 * confirmation function before the withdrawal is submitted, to prevent its
 * unintended exercise. We stash the validated declaration in a short-lived,
 * single-use transient and redirect to the confirmation screen. Keeping the
 * data server-side (not in the URL or in editable hidden fields) guarantees
 * that what is registered on confirmation is exactly what was validated here.
 *
 * Hooked to wp_loaded, because the form posts to the page that holds it, and
 * to admin-post.php for pages cached with the old form and for shops that
 * switch the old address back on. Logged-in and guest users alike.
 *
 * wp_loaded is where WooCommerce handles its own front-end forms: the user is
 * already authenticated and the main query has not run, so nothing hooked to
 * template_redirect can answer the request first and drop the POST body with a
 * redirect.
 */
function ayudawp_euw_handle_review() {

	// wp-admin fires wp_loaded too, and there admin-post.php dispatches the action
	// itself a moment later: leave that request to it.
	$on_page = doing_action( 'wp_loaded' );

	if ( $on_page && is_admin() ) {
		return;
	}

	// 1. Verify nonce.
	if ( ! isset( $_POST['ayudawp_euw_nonce'] ) ) {

		// Every front-end request reaches this point, and one that does not carry
		// the nonce field of this form is simply not ours. Whether the nonce is
		// valid is decided below, on purpose: stepping aside for an invalid one
		// would turn a nonce that expired on a cached page into a form that
		// reloads and says nothing.
		if ( $on_page ) {
			return;
		}

		ayudawp_euw_redirect_with_error( 'nonce' );
		exit;
	}

	$nonce = sanitize_text_field( wp_unslash( $_POST['ayudawp_euw_nonce'] ) );

	if ( ! wp_verify_nonce( $nonce, 'ayudawp_euw_review_action' ) ) {
		ayudawp_euw_redirect_with_error( 'nonce' );
		exit;
	}

	// 2. Honeypot: if filled, treat as spam.
	if ( ! empty( $_POST['ayudawp_euw_website'] ) ) {
		ayudawp_euw_redirect_with_error( 'spam' );
	}

	// 3. Collect and validate the declaration.
	$data = ayudawp_euw_collect_form_data();

	ayudawp_euw_validate_submission( $data );

	// 4. Stash the validated declaration and send the consumer to confirm it.
	$token = wp_generate_password( 40, false );
	set_transient( 'ayudawp_euw_pending_' . $token, $data, 15 * MINUTE_IN_SECONDS );

	$url = ayudawp_euw_get_return_url();
	$url     = remove_query_arg( array( 'ayudawp_euw_error', 'ayudawp_euw_sent' ), $url );
	$url     = add_query_arg( 'ayudawp_euw_confirm', $token, $url );

	wp_safe_redirect( $url . '#ayudawp-euw-form' );
	exit;
}
add_action( 'wp_loaded', 'ayudawp_euw_handle_review', 20 );
add_action( 'admin_post_ayudawp_euw_review', 'ayudawp_euw_handle_review' );
add_action( 'admin_post_nopriv_ayudawp_euw_review', 'ayudawp_euw_handle_review' );

/**
 * Step 2: register the withdrawal once the consumer confirms.
 *
 * Triggered by the "confirm withdrawal" function (Article 11a(3)). Recovers
 * the validated declaration from the single-use transient, re-validates it
 * (the order could have changed status during the confirmation window),
 * registers the request and sends the durable-medium acknowledgement.
 *
 * Hooked to wp_loaded and to admin-post.php, like ayudawp_euw_handle_review().
 */
function ayudawp_euw_handle_confirm() {

	// Same two steps aside as in ayudawp_euw_handle_review().
	$on_page = doing_action( 'wp_loaded' );

	if ( $on_page && is_admin() ) {
		return;
	}

	// 1. Verify the confirmation nonce.
	if ( ! isset( $_POST['ayudawp_euw_confirm_nonce'] ) ) {

		if ( $on_page ) {
			return;
		}

		ayudawp_euw_redirect_with_error( 'nonce' );
		exit;
	}

	$nonce = sanitize_text_field( wp_unslash( $_POST['ayudawp_euw_confirm_nonce'] ) );

	if ( ! wp_verify_nonce( $nonce, 'ayudawp_euw_confirm_action' ) ) {
		ayudawp_euw_redirect_with_error( 'nonce' );
		exit;
	}

	// 2. Recover and immediately consume the pending declaration (single use).
	$token = isset( $_POST['ayudawp_euw_token'] ) ? sanitize_text_field( wp_unslash( $_POST['ayudawp_euw_token'] ) ) : '';
	$data  = $token ? get_transient( 'ayudawp_euw_pending_' . $token ) : false;

	if ( ! is_array( $data ) ) {
		ayudawp_euw_redirect_with_error( ayudawp_euw_token_was_confirmed( $token ) ? 'done' : 'session' );
	}

	delete_transient( 'ayudawp_euw_pending_' . $token );

	// Leave a short-lived marker behind. The confirmation screen and the "Edit
	// data" link both land on a missing transient whether the declaration was
	// already confirmed (the browser back button, a double submit) or simply
	// expired, and the two need opposite advice: "fill in the form again" would
	// walk a customer whose request is already registered into a duplicate.
	set_transient( 'ayudawp_euw_used_' . $token, 1, 15 * MINUTE_IN_SECONDS );

	// 3. Re-validate: the order may have changed status during the window.
	$wc_validation = ayudawp_euw_validate_submission( $data );

	// 4. Store the withdrawal request as a CPT entry. The title is read by the
	// shop in its log, so it is written in the language the shop is run in and not
	// in that of the page the customer happened to be on. The same goes for the
	// order note and the notification further down; the acknowledgement stays in
	// the language of the customer.
	$post_title = ayudawp_euw_in_shop_locale(
		static function () use ( $data ) {
			return sprintf(
				/* translators: 1: order number, 2: customer name. */
				__( 'Order %1$s — %2$s', 'eu-withdrawal-compliance' ),
				$data['order'],
				$data['name']
			);
		}
	);

	$post_id = wp_insert_post(
		array(
			'post_type'    => 'ayudawp_withdrawal',
			'post_status'  => 'publish',
			'post_title'   => $post_title,
			'post_content' => $data['details'],
		),
		true
	);

	if ( is_wp_error( $post_id ) || ! $post_id ) {
		ayudawp_euw_redirect_with_error( 'general' );
	}

	// 5. Save metadata.
	update_post_meta( $post_id, '_ayudawp_euw_name', $data['name'] );
	update_post_meta( $post_id, '_ayudawp_euw_email', $data['email'] );
	update_post_meta( $post_id, '_ayudawp_euw_order', $data['order'] );
	update_post_meta( $post_id, '_ayudawp_euw_order_date', $data['date'] );
	update_post_meta( $post_id, '_ayudawp_euw_scope', $data['scope'] );
	update_post_meta( $post_id, '_ayudawp_euw_ip', ayudawp_euw_get_user_ip() );
	update_post_meta( $post_id, '_ayudawp_euw_user_agent', isset( $_SERVER['HTTP_USER_AGENT'] ) ? sanitize_text_field( wp_unslash( $_SERVER['HTTP_USER_AGENT'] ) ) : '' );
	update_post_meta( $post_id, '_ayudawp_euw_status', 'pending' );

	// Who sent it, when they were logged in. This is proof of ownership at the
	// moment of submission, which is what lets the customer see the request
	// listed in their account later. The email address on the request is not:
	// WooCommerce lets a customer change the address of their account without
	// confirming it, so anyone could point their account at an address someone
	// else used as a guest.
	$submitter_id = get_current_user_id();

	if ( $submitter_id ) {
		update_post_meta( $post_id, '_ayudawp_euw_user_id', $submitter_id );
	}

	// The acknowledgement records the exact date and time of submission
	// (Article 11a(4)). That is the moment the consumer confirms, i.e. now.
	// The SHA-256 hash lets the customer verify later, from the same fields,
	// that the stored request was not altered.
	$submitted_at = current_time( 'mysql', true );
	update_post_meta( $post_id, '_ayudawp_euw_submitted_at', $submitted_at );

	$receipt_hash = ayudawp_euw_compute_receipt_hash( $post_id, $data['name'], $data['email'], $data['order'], $data['scope'], $data['date'], $submitted_at );
	update_post_meta( $post_id, '_ayudawp_euw_receipt_hash', $receipt_hash );

	// B2B self-declaration: when the consumer ticked it, store the flag, a
	// snapshot of the exact wording shown and the timestamp, as proof for the
	// merchant (same durable-record approach used for the checkout consents).
	if ( ! empty( $data['consumer'] ) ) {

		$consumer_text = (string) get_option( 'ayudawp_euw_consumer_check_text', '' );

		if ( '' === trim( $consumer_text ) && function_exists( 'ayudawp_euw_consumer_check_default_text' ) ) {
			$consumer_text = ayudawp_euw_consumer_check_default_text();
		}

		update_post_meta( $post_id, '_ayudawp_euw_consumer', '1' );
		update_post_meta( $post_id, '_ayudawp_euw_consumer_text', $consumer_text );
		update_post_meta( $post_id, '_ayudawp_euw_consumer_at', $submitted_at );
	}

	// Unmatched request accepted for manual verification (opt-in setting):
	// store the reason and, when the reference matched an order whose billing
	// email differs, an admin-only hint that speeds up the manual check. Never
	// linked to the order itself: no proof of ownership, so no order note either.
	if ( ! empty( $wc_validation['unverified'] ) ) {
		update_post_meta( $post_id, '_ayudawp_euw_unverified', sanitize_key( $wc_validation['unverified'] ) );

		if ( ! empty( $wc_validation['unverified_hint'] ) ) {
			update_post_meta( $post_id, '_ayudawp_euw_unverified_order_hint', absint( $wc_validation['unverified_hint'] ) );
		}
	}

	$excluded_items = array();
	$deadline_ts    = 0;

	if ( $wc_validation['order_id'] ) {
		update_post_meta( $post_id, '_ayudawp_euw_wc_order_id', $wc_validation['order_id'] );

		ayudawp_euw_in_shop_locale(
			static function () use ( $wc_validation, $post_id, $data ) {
				ayudawp_euw_add_wc_order_note( $wc_validation['order_id'], $post_id, $data['scope'], $data['details'] );
			}
		);

		// Flag any items in the order that fall under Article 16 exceptions
		// so the admin reviews the request manually. Never auto-rejected.
		$excluded_items = ayudawp_euw_get_excluded_items_in_order( $wc_validation['order_id'] );

		if ( ! empty( $excluded_items ) ) {
			update_post_meta( $post_id, '_ayudawp_euw_excluded_items', $excluded_items );
		}

		// Approximate deadline (from order/completion date) for the advisory
		// flag in the admin email. The real period runs from delivery, which
		// we cannot detect, so this only warns; it never blocks the request.
		if ( function_exists( 'wc_get_order' ) ) {
			$deadline_order = wc_get_order( $wc_validation['order_id'] );

			if ( $deadline_order ) {
				$deadline_ts = ayudawp_euw_get_order_deadline_timestamp( $deadline_order );
			}
		}
	}

	// 6. Send notifications. The customer email is the durable-medium
	// acknowledgement of receipt (Article 11a(4)); record whether it was
	// accepted for delivery as proof the trader fulfilled that duty.
	$receipt_sent = ayudawp_euw_send_customer_email( $data['email'], $data['name'], $data['order'], $data['scope'], $receipt_hash, $submitted_at, $data['details'], $data['date'], $post_id );

	update_post_meta( $post_id, '_ayudawp_euw_receipt_sent', $receipt_sent ? '1' : '0' );

	if ( $receipt_sent ) {
		update_post_meta( $post_id, '_ayudawp_euw_receipt_sent_at', current_time( 'mysql', true ) );
	}

	ayudawp_euw_in_shop_locale(
		static function () use ( $post_id, $data, $excluded_items, $deadline_ts ) {
			ayudawp_euw_send_admin_email( $post_id, $data['name'], $data['email'], $data['order'], $data['scope'], $data['details'], $excluded_items, $deadline_ts );
		}
	);

	/**
	 * Fires after a withdrawal request has been processed.
	 *
	 * @param int   $post_id Withdrawal CPT ID.
	 * @param array $data    Submission data.
	 */
	do_action(
		'ayudawp_euw_after_submission',
		$post_id,
		array(
			'name'    => $data['name'],
			'email'   => $data['email'],
			'order'   => $data['order'],
			'scope'   => $data['scope'],
			'details' => $data['details'],
		)
	);

	// 7. Redirect back to the form page with success flag.
	ayudawp_euw_redirect_with_success();
}
add_action( 'wp_loaded', 'ayudawp_euw_handle_confirm', 20 );
add_action( 'admin_post_ayudawp_euw_confirm', 'ayudawp_euw_handle_confirm' );
add_action( 'admin_post_nopriv_ayudawp_euw_confirm', 'ayudawp_euw_handle_confirm' );

/**
 * Get the visitor IP address respecting common proxies.
 *
 * @return string IP address or empty string.
 */
function ayudawp_euw_get_user_ip() {

	$keys = array( 'HTTP_CF_CONNECTING_IP', 'HTTP_X_FORWARDED_FOR', 'REMOTE_ADDR' );

	foreach ( $keys as $key ) {
		if ( ! empty( $_SERVER[ $key ] ) ) {
			$ip = sanitize_text_field( wp_unslash( $_SERVER[ $key ] ) );

			// X-Forwarded-For can be a comma-separated list.
			if ( false !== strpos( $ip, ',' ) ) {
				$parts = explode( ',', $ip );
				$ip    = trim( $parts[0] );
			}

			if ( filter_var( $ip, FILTER_VALIDATE_IP ) ) {
				return $ip;
			}
		}
	}

	return '';
}

/**
 * Whether a pending token was consumed by a successful confirmation.
 *
 * The pending declaration is deleted the moment it is confirmed (single use,
 * Article 11a(3)), so its absence alone cannot tell "already submitted" apart
 * from "expired". ayudawp_euw_handle_confirm() leaves a marker for as long as
 * the original window would have lasted, which is what this reads.
 *
 * @param string $token Single-use token.
 * @return bool
 */
function ayudawp_euw_token_was_confirmed( $token ) {

	$token = (string) $token;

	if ( '' === $token ) {
		return false;
	}

	return (bool) get_transient( 'ayudawp_euw_used_' . $token );
}

/**
 * Resolve where to send the consumer back to after handling a submission.
 *
 * The page holding the form, which is the request itself now that the form
 * posts to its own page. On admin-post.php, where pages cached with the old form
 * still post, it is the referer instead. When that does not resolve, the
 * configured withdrawal page is used rather than the site home, so the consumer
 * lands back on the form and sees the step that follows instead of the front
 * page with no explanation. A missing referer is not hypothetical: security
 * plugins that restrict access to /wp-admin/ intercept the POST to
 * admin-post.php before it is dispatched, and privacy setups strip the header.
 * The home URL stays as the last resort for shops that have no withdrawal page
 * configured.
 *
 * @return string A URL, or the path and query of one on this site.
 */
function ayudawp_euw_get_return_url() {

	// The form posts to the page that holds it, so that page is the request
	// itself. wp_get_referer() cannot be asked here: it answers false whenever the
	// referer is the address being requested (wp-includes/functions.php:1990),
	// which is exactly this case, and the customer would be sent to the configured
	// page, or to the home page, instead of back to the form: without the query
	// string, and out of My Account when that is where the form was.
	if ( ! is_admin() && isset( $_SERVER['REQUEST_URI'] ) && is_string( $_SERVER['REQUEST_URI'] ) ) {

		$self = wp_validate_redirect( esc_url_raw( wp_unslash( $_SERVER['REQUEST_URI'] ) ), false );

		if ( $self ) {
			return $self;
		}
	}

	$referer = wp_get_referer();

	if ( $referer ) {
		return $referer;
	}

	$page_id = ayudawp_euw_get_page_id();

	if ( $page_id ) {

		$permalink = get_permalink( $page_id );

		if ( $permalink ) {
			return $permalink;
		}
	}

	return home_url();
}

/**
 * Redirect back to the previous page with an error code.
 *
 * When the collected submission is passed in, its values are stashed in a
 * short-lived, single-use transient and a recovery token is added to the URL,
 * so the form can be re-rendered with what the customer already typed instead
 * of an empty form. The privacy consent is deliberately never preserved: the
 * consumer must actively re-accept it on each attempt.
 *
 * @param string $code    Error code.
 * @param array  $data    Optional collected submission to preserve across the redirect.
 * @param array  $invalid Optional list of field keys that failed, so the re-rendered
 *                        form can highlight each one.
 */
function ayudawp_euw_redirect_with_error( $code, $data = array(), $invalid = array() ) {

	$url = ayudawp_euw_get_return_url();

	// Drop the confirmation/edit tokens so an error raised during step 2 lands
	// back on the form (showing the error) instead of the confirmation screen,
	// and clear any stale recovery token before we add a fresh one.
	$url = remove_query_arg( array( 'ayudawp_euw_confirm', 'ayudawp_euw_edit', 'ayudawp_euw_recover' ), $url );

	$args = array(
		'ayudawp_euw_error' => $code,
		'_wpnonce'          => wp_create_nonce( 'ayudawp_euw_form_feedback' ),
	);

	if ( ! empty( $data ) && is_array( $data ) ) {
		$recover = array(
			'name'     => isset( $data['name'] ) ? $data['name'] : '',
			'email'    => isset( $data['email'] ) ? $data['email'] : '',
			'order'    => isset( $data['order'] ) ? $data['order'] : '',
			'date'     => isset( $data['date'] ) ? $data['date'] : '',
			'scope'    => isset( $data['scope'] ) ? $data['scope'] : '',
			'details'  => isset( $data['details'] ) ? $data['details'] : '',
			'_invalid' => array_map( 'sanitize_key', (array) $invalid ),
		);

		$recover_token = wp_generate_password( 20, false );
		set_transient( 'ayudawp_euw_recover_' . $recover_token, $recover, 5 * MINUTE_IN_SECONDS );
		$args['ayudawp_euw_recover'] = $recover_token;
	}

	$url = add_query_arg( $args, $url );

	wp_safe_redirect( $url . '#ayudawp-euw-form' );
	exit;
}

/**
 * Redirect back to the previous page with success flag.
 */
function ayudawp_euw_redirect_with_success() {

	$url = ayudawp_euw_get_return_url();

	$url = remove_query_arg( array( 'ayudawp_euw_error', 'ayudawp_euw_confirm' ), $url );
	$url = add_query_arg(
		array(
			'ayudawp_euw_sent' => '1',
			'_wpnonce'         => wp_create_nonce( 'ayudawp_euw_form_feedback' ),
		),
		$url
	);

	wp_safe_redirect( $url . '#ayudawp-euw-form' );
	exit;
}

/**
 * Compute the SHA-256 receipt hash for a withdrawal request.
 *
 * The same input always produces the same output, so this can be re-run from
 * the stored meta fields to verify that the original submission was not
 * tampered with.
 *
 * @param int    $post_id      Withdrawal CPT ID.
 * @param string $name         Customer name.
 * @param string $email        Customer email.
 * @param string $order        Order reference.
 * @param string $scope        Withdrawal scope (full|partial).
 * @param string $date         Order date as submitted by the customer.
 * @param string $submitted_at GMT timestamp (Y-m-d H:i:s) the request was registered.
 * @return string Lowercase 64-char SHA-256 hex digest.
 */
function ayudawp_euw_compute_receipt_hash( $post_id, $name, $email, $order, $scope, $date, $submitted_at ) {

	$payload = implode(
		'|',
		array(
			(string) absint( $post_id ),
			$name,
			$email,
			$order,
			$scope,
			$date,
			$submitted_at,
		)
	);

	return hash( 'sha256', $payload );
}

/**
 * Format a stored GMT timestamp for display in the site's timezone.
 *
 * Used for the acknowledgement email, the admin detail metabox and the CSV
 * export so submission and resolution times read consistently.
 *
 * @param string $gmt GMT datetime in MySQL format (Y-m-d H:i:s), or empty.
 * @return string Localised datetime with timezone abbreviation, or empty string.
 */
function ayudawp_euw_format_datetime( $gmt ) {

	if ( empty( $gmt ) ) {
		return '';
	}

	$timestamp = strtotime( $gmt . ' UTC' );

	if ( ! $timestamp ) {
		return '';
	}

	return wp_date( 'Y-m-d H:i T', $timestamp );
}

/**
 * Get the site name ready to print, with its HTML entities resolved.
 *
 * WordPress stores `blogname` already escaped: sanitize_option() runs the value
 * through esc_html() before writing it (wp-includes/formatting.php:4998), so a
 * name such as "Musée d'Impressionnisme" comes back from get_bloginfo() as
 * "Musée d&#039;Impressionnisme". A browser resolves that entity and shows the
 * apostrophe, but a plain-text email does not, and the customer reads the raw
 * entity in the subject line and in the sign-off.
 *
 * Decoding at the source hands every caller the real name, so plain-text output
 * stays literal and HTML output escapes it for its own context. This is the same
 * recipe WordPress uses for its own notification emails, in
 * wp-includes/pluggable.php:1835.
 *
 * @since 2.2.2
 *
 * @return string Site name with HTML entities decoded.
 */
function ayudawp_euw_get_site_name() {

	return wp_specialchars_decode( (string) get_bloginfo( 'name' ), ENT_QUOTES );
}
