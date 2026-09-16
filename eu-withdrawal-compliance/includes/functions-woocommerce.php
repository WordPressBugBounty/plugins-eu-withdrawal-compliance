<?php
/**
 * WooCommerce integration: order validation, My Account endpoint and order notes.
 *
 * @package AyudaWP_EU_Withdrawal
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Resolve the user-supplied order reference to a WC_Order instance.
 *
 * Accepts both the WordPress internal order ID (when no numbering plugin is
 * active) and the displayed order number stored by plugins such as
 * "WooCommerce Sequential Order Numbers" (free + Pro) or "Custom Order Numbers
 * for WooCommerce" (WPFactory) in the standard `_order_number` post meta.
 *
 * Resolution strategies, in order of specificity:
 *   1. `ayudawp_euw_pre_resolve_wc_order` filter — short-circuit hook for
 *      plugins with non-meta numbering schemes (e.g. YITH Sequential Order
 *      Number, custom ERP integrations).
 *   2. Lookup by `_order_number` meta — covers the de-facto standard used by
 *      the most popular numbering plugins.
 *   3. Literal ID lookup with strict cross-check against `get_order_number()`
 *      — only accepts when the displayed number matches what the customer
 *      typed. Prevents access by guessing internal IDs in stores that use
 *      custom numbering.
 *
 * The resolved order (or false) is finally passed through the
 * `ayudawp_euw_resolve_wc_order` filter for late override / auditing.
 *
 * @param string $order_ref Raw order reference as typed by the customer.
 * @return WC_Order|false WC_Order instance, or false when no match was found.
 */
function ayudawp_euw_resolve_wc_order( $order_ref ) {

	$order_ref = is_scalar( $order_ref ) ? trim( (string) $order_ref ) : '';

	if ( '' === $order_ref || ! function_exists( 'wc_get_order' ) ) {
		return false;
	}

	/**
	 * Filter the resolution result before built-in strategies run.
	 *
	 * Return a WC_Order instance to short-circuit. Return false to reject
	 * explicitly. Return null (default) to let the built-in strategies run.
	 *
	 * @param WC_Order|false|null $pre       Pre-resolved order, or null to fall through.
	 * @param string              $order_ref Raw order reference typed by the customer.
	 */
	$pre = apply_filters( 'ayudawp_euw_pre_resolve_wc_order', null, $order_ref );

	if ( $pre instanceof WC_Order ) {
		return $pre;
	}

	if ( false === $pre ) {
		return false;
	}

	$order = false;

	// Strategy 1 — custom order-number meta lookup. HPOS/CPT agnostic via
	// wc_get_orders(). Different "custom order number" plugins store the visible
	// number under different meta keys, so we try a known list in priority order
	// and stop at the first match.
	if ( function_exists( 'wc_get_orders' ) ) {

		/**
		 * Filter the order-number meta keys checked when resolving a typed order
		 * reference, in priority order (first match wins).
		 *
		 * Covers WPFactory / WooCommerce Sequential Order Numbers
		 * (`_order_number`, `_order_number_formatted`) and Tyche "Custom Order
		 * Numbers for WooCommerce" (`_alg_wc_full_custom_order_number`,
		 * `_alg_wc_custom_order_number`) out of the box. Add a key here to
		 * support any other numbering plugin.
		 *
		 * @param array<int, string> $meta_keys Meta keys to query, in priority order.
		 * @param string             $order_ref Raw order reference typed by the customer.
		 */
		$meta_keys = apply_filters(
			'ayudawp_euw_order_number_meta_keys',
			array(
				'_order_number',
				'_order_number_formatted',
				'_alg_wc_full_custom_order_number',
				'_alg_wc_custom_order_number',
			),
			$order_ref
		);

		foreach ( (array) $meta_keys as $meta_key ) {

			$meta_key = (string) $meta_key;

			if ( '' === $meta_key ) {
				continue;
			}

			$matches = wc_get_orders(
				array(
					'limit'      => 1,
					'meta_key'   => $meta_key,  // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_key
					'meta_value' => $order_ref, // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_value
					'orderby'    => 'date',
					'order'      => 'DESC',
					'return'     => 'ids',
					'status'     => array_keys( wc_get_order_statuses() ),
				)
			);

			if ( ! empty( $matches ) ) {
				$order = wc_get_order( (int) $matches[0] );
				break;
			}
		}
	}

	// Strategy 2 — literal ID with strict displayed-number cross-check.
	if ( ! $order && ctype_digit( $order_ref ) ) {
		$candidate = wc_get_order( absint( $order_ref ) );

		if ( $candidate ) {
			$displayed = method_exists( $candidate, 'get_order_number' )
				? (string) $candidate->get_order_number()
				: (string) $candidate->get_id();

			if ( trim( $displayed ) === $order_ref ) {
				$order = $candidate;
			}
		}
	}

	/**
	 * Filter the final resolution result.
	 *
	 * @param WC_Order|false $order     Resolved order or false.
	 * @param string         $order_ref Raw order reference typed by the customer.
	 */
	return apply_filters( 'ayudawp_euw_resolve_wc_order', $order, $order_ref );
}

/**
 * Whether unmatched requests are accepted and flagged instead of rejected.
 *
 * Controlled by the "Accept unmatched requests" checkbox in the eligibility
 * settings. Off by default: the strict order/email check stays the standard
 * behaviour unless the merchant opts in.
 *
 * @return bool
 */
function ayudawp_euw_accepts_unmatched() {

	return 'yes' === get_option( 'ayudawp_euw_accept_unmatched', 'no' );
}

/**
 * Human-readable label for the unverified-request reason stored on a request.
 *
 * Shared by the CSV export and any listing that prints the flag. Returns an
 * empty string for a matched request (no meta stored).
 *
 * @param string $reason Stored reason ('order_not_found'|'email_mismatch'), or ''.
 * @return string Translated label, or empty string.
 */
function ayudawp_euw_unverified_reason_label( $reason ) {

	if ( '' === (string) $reason ) {
		return '';
	}

	$labels = array(
		'order_not_found' => __( 'Order not found', 'eu-withdrawal-compliance' ),
		'email_mismatch'  => __( 'Email mismatch', 'eu-withdrawal-compliance' ),
	);

	return isset( $labels[ $reason ] ) ? $labels[ $reason ] : __( 'Unverified', 'eu-withdrawal-compliance' );
}

/**
 * Validate that a given order/email pair belongs to a real WC order
 * and that the 14-day withdrawal window is still open.
 *
 * If WooCommerce is not active, the function returns valid by default
 * because we cannot check anything against an order database. When WC is
 * active, the order must exist and the email must match its billing email.
 * Two escape hatches relax the check for unmatched pairs: the
 * "Accept unmatched requests" setting registers the request anyway, flagged
 * as unverified for manual review, and the legacy
 * `ayudawp_euw_allow_unverified_order` filter keeps its original behaviour
 * (accept as plain valid, no flag) for sites that treat non-WC purchases as
 * normal business.
 *
 * @param string $order_ref Order number or ID provided by the user.
 * @param string $email     Customer email.
 * @return array {
 *     @type bool   $valid           Whether the request may proceed.
 *     @type string $error           Error code if invalid.
 *     @type int    $order_id        WooCommerce order ID if matched.
 *     @type string $unverified      Optional. Reason the request was accepted
 *                                   without a matched order ('order_not_found'
 *                                   or 'email_mismatch').
 *     @type int    $unverified_hint Optional. Order ID whose billing email did
 *                                   not match, stored as an admin-only hint.
 * }
 */
function ayudawp_euw_validate_wc_order( $order_ref, $email ) {

	$default = array(
		'valid'    => true,
		'error'    => '',
		'order_id' => 0,
	);

	// Skip validation if WooCommerce is not active.
	if ( ! function_exists( 'wc_get_order' ) ) {
		return $default;
	}

	$order = ayudawp_euw_resolve_wc_order( $order_ref );

	// If WooCommerce is active but we cannot match the order, the request fails
	// validation by default. Two opt-ins relax that: the "Accept unmatched
	// requests" setting registers it anyway, flagged as unverified for manual
	// review, and the legacy filter keeps accepting it as plain valid for sites
	// that genuinely take non-WC purchases (manual invoices, marketplaces).
	if ( ! $order ) {
		if ( ayudawp_euw_accepts_unmatched() ) {
			return array(
				'valid'      => true,
				'error'      => '',
				'order_id'   => 0,
				'unverified' => 'order_not_found',
			);
		}

		if ( apply_filters( 'ayudawp_euw_allow_unverified_order', false, $order_ref, $email ) ) {
			return $default;
		}

		return array(
			'valid'    => false,
			'error'    => 'order',
			'order_id' => 0,
		);
	}

	// If we have a WC order, verify the email matches. On mismatch, the
	// unmatched-requests setting also applies: the request is registered
	// unlinked (typing an order number is no proof of ownership), with the
	// order ID stored separately as an admin-only hint for the manual check.
	$order_email = method_exists( $order, 'get_billing_email' ) ? $order->get_billing_email() : '';

	if ( ! empty( $order_email ) && strtolower( trim( $order_email ) ) !== strtolower( trim( $email ) ) ) {
		if ( ayudawp_euw_accepts_unmatched() ) {
			return array(
				'valid'           => true,
				'error'           => '',
				'order_id'        => 0,
				'unverified'      => 'email_mismatch',
				'unverified_hint' => $order->get_id(),
			);
		}

		return array(
			'valid'    => false,
			'error'    => 'order',
			'order_id' => 0,
		);
	}

	// The order status must be one of the configured eligible statuses — the
	// same gate the My Account button and the email notice use. This keeps
	// every entry point consistent: an order that doesn't qualify for the
	// button can't slip a request through the public form either. When the
	// admin has cleared the whitelist entirely we don't gate here (the feature
	// is effectively disabled) rather than block every submission.
	$allowed_statuses = ayudawp_euw_get_allowed_statuses( $order );

	if ( ! empty( $allowed_statuses )
		&& method_exists( $order, 'get_status' )
		&& ! in_array( $order->get_status(), $allowed_statuses, true )
	) {
		return array(
			'valid'    => false,
			'error'    => 'status',
			'order_id' => 0,
		);
	}

	// In strict mode, re-apply the deadline as a hard gate — but only for a
	// brand-new request. A valid order/email pair can reach the form or the email
	// link even when the button is hidden, so a new request past the window (plus
	// grace days) must be rejected. A rejected request being contested is exempt:
	// strict mode blocks opening new processes, not re-engaging one already decided
	// (the My Account "Contest the rejection" button stays visible for it).
	if ( ayudawp_euw_deadline_is_strict() && ! ayudawp_euw_order_has_rejected_request( $order->get_id() ) ) {

		$deadline = ayudawp_euw_get_order_deadline_timestamp( $order );

		if ( $deadline && time() > $deadline ) {
			return array(
				'valid'    => false,
				'error'    => 'expired',
				'order_id' => 0,
			);
		}
	}

	// In advisory mode (the default) the 14-day window is not a hard gate. For
	// goods the period runs from physical possession of the order (Art. 9(2)(b)
	// of Directive 2011/83/EU), a date the shop cannot detect automatically, so
	// rejecting on a date we only approximate (order or completion date) risked
	// turning away consumers still within their legal window. Eligibility is
	// governed by the order status above; the approximate deadline is surfaced to
	// the admin as an advisory flag in the notification email so a human can
	// verify the real delivery date and decide. Never auto-rejected here.
	return array(
		'valid'    => true,
		'error'    => '',
		'order_id' => $order->get_id(),
	);
}

/**
 * Compute the timestamp at which the approximate withdrawal window closes.
 *
 * Reads the deadline basis (order date vs completion date) and the grace days
 * from the plugin settings, then applies the `ayudawp_euw_grace_days` filter
 * for backward compatibility with installs that customised the window before
 * the UI was added.
 *
 * Since 1.8.0 this value no longer gates submissions or the button: it only
 * feeds the advisory "may be past the window" flag in the admin notification
 * email. The real period runs from delivery (Art. 9(2)(b) of Directive
 * 2011/83/EU), which the shop verifies manually.
 *
 * @param object $order WC_Order instance.
 * @return int Unix timestamp, or 0 if no usable date is available.
 */
function ayudawp_euw_get_order_deadline_timestamp( $order ) {

	$basis     = get_option( 'ayudawp_euw_deadline_basis', 'order_date' );
	$base_date = null;

	if ( 'completion_date' === $basis && method_exists( $order, 'get_date_completed' ) ) {
		$base_date = $order->get_date_completed();
	}

	if ( ! $base_date && method_exists( $order, 'get_date_created' ) ) {
		$base_date = $order->get_date_created();
	}

	if ( ! $base_date ) {
		return 0;
	}

	$deadline = $base_date->getTimestamp() + ( 14 * DAY_IN_SECONDS );

	$option_grace = (int) get_option( 'ayudawp_euw_grace_days', 0 );

	/** This filter is documented in includes/functions-woocommerce.php */
	$grace_days = (int) apply_filters( 'ayudawp_euw_grace_days', $option_grace );
	$deadline  += $grace_days * DAY_IN_SECONDS;

	return $deadline;
}

/**
 * Whether the withdrawal deadline is enforced in strict mode.
 *
 * Default ('advisory') keeps the 1.8.0 behaviour: the approximate 14-day window
 * never gates a request, it only flags it for manual review. In 'strict' mode
 * the merchant opts back into hiding the entry points and rejecting requests
 * once the window (plus grace days) has elapsed.
 *
 * @return bool
 */
function ayudawp_euw_deadline_is_strict() {

	return 'strict' === get_option( 'ayudawp_euw_deadline_mode', 'advisory' );
}

/**
 * Return the order statuses for which the withdrawal button/notice is offered.
 *
 * Statuses are stored and returned without the `wc-` prefix to align with
 * `WC_Order::get_status()`. Defaults to processing and completed.
 *
 * @param object|null $order Optional WC_Order, passed to the filter.
 * @return array<int, string>
 */
function ayudawp_euw_get_allowed_statuses( $order = null ) {

	$stored = get_option( 'ayudawp_euw_allowed_statuses', array( 'processing', 'completed' ) );

	if ( ! is_array( $stored ) ) {
		$stored = array( 'processing', 'completed' );
	}

	$statuses = array_values( array_filter( array_map( 'sanitize_key', $stored ) ) );

	/**
	 * Filter the order statuses considered eligible for the withdrawal flow.
	 *
	 * Use this filter to force a specific list programmatically, regardless of
	 * the option saved in settings.
	 *
	 * @param array<int, string> $statuses Status keys without the `wc-` prefix.
	 * @param object|null        $order    Current WC_Order, when available.
	 */
	return (array) apply_filters( 'ayudawp_euw_allowed_statuses', $statuses, $order );
}

/**
 * Decide whether the withdrawal button/notice should be shown for an order.
 *
 * Reused by the My Account action and the email notice injector: the order must
 * be present and its status must be in the configured whitelist. In strict mode
 * the approximate deadline is also enforced, unless $check_deadline is false.
 *
 * @param object $order         WC_Order instance.
 * @param bool   $check_deadline Whether to apply the strict-mode deadline cut.
 *                               Pass false to gate on order status only, for the
 *                               "Contest the rejection" path on an already open
 *                               request, which strict mode must not hide.
 * @return bool
 */
function ayudawp_euw_should_show_withdrawal( $order, $check_deadline = true ) {

	if ( ! $order || ! method_exists( $order, 'get_status' ) ) {
		return false;
	}

	$allowed = ayudawp_euw_get_allowed_statuses( $order );

	if ( empty( $allowed ) || ! in_array( $order->get_status(), $allowed, true ) ) {
		return false;
	}

	// In strict mode the merchant opts back into hiding the entry points once the
	// approximate 14-day window (plus grace days) has elapsed. Advisory mode (the
	// default) never gates here: the window only approximates the legal period
	// (which runs from delivery), so it is surfaced to the admin as a flag
	// instead of hiding the button from a consumer who may still be within it.
	if ( $check_deadline && ayudawp_euw_deadline_is_strict() ) {

		$deadline = ayudawp_euw_get_order_deadline_timestamp( $order );

		if ( $deadline && time() > $deadline ) {
			return false;
		}
	}

	return true;
}

/**
 * Whether an order's most recent withdrawal request was rejected.
 *
 * A rejected request is the one case where the customer may legitimately
 * re-engage the process, to contest the rejection. Strict mode must not hide the
 * My Account button nor block the contesting submission for it: strict mode
 * blocks opening NEW requests, not handling one that is already open.
 *
 * @param int $wc_order_id WC order ID.
 * @return bool
 */
function ayudawp_euw_order_has_rejected_request( $wc_order_id ) {

	$existing = ayudawp_euw_get_request_for_order( $wc_order_id );

	if ( ! $existing ) {
		return false;
	}

	$status = get_post_meta( $existing, '_ayudawp_euw_status', true );

	return 'rejected' === ( $status ? $status : 'pending' );
}

/**
 * Add a private note to the WooCommerce order linking to the withdrawal log.
 *
 * @param int    $wc_order_id WC order ID.
 * @param int    $cpt_id      Withdrawal CPT ID.
 * @param string $scope       Withdrawal scope.
 * @param string $details     Customer-provided details.
 */
function ayudawp_euw_add_wc_order_note( $wc_order_id, $cpt_id, $scope, $details ) {

	if ( ! function_exists( 'wc_get_order' ) ) {
		return;
	}

	$order = wc_get_order( $wc_order_id );

	if ( ! $order ) {
		return;
	}

	$scope_label = ( 'partial' === $scope )
		? __( 'partial', 'eu-withdrawal-compliance' )
		: __( 'full', 'eu-withdrawal-compliance' );

	$note = sprintf(
		/* translators: 1: scope label, 2: details, 3: log ID. */
		__( 'EU withdrawal request received (%1$s). Details: %2$s. Log ID: #%3$d', 'eu-withdrawal-compliance' ),
		$scope_label,
		( ! empty( $details ) ? $details : '—' ),
		$cpt_id
	);

	$order->add_order_note( $note, 0, false );
	$order->update_meta_data( '_ayudawp_euw_request_id', $cpt_id );
	$order->save();
}

/**
 * Add a private note to the WC order when a withdrawal status changes.
 *
 * @param int    $wc_order_id WC order ID.
 * @param int    $cpt_id      Withdrawal CPT ID.
 * @param string $status      New status.
 * @param string $comment     Optional admin comment.
 */
function ayudawp_euw_add_status_order_note( $wc_order_id, $cpt_id, $status, $comment = '' ) {

	if ( ! function_exists( 'wc_get_order' ) ) {
		return;
	}

	$order = wc_get_order( $wc_order_id );

	if ( ! $order ) {
		return;
	}

	$labels = array(
		'pending'   => __( 'pending', 'eu-withdrawal-compliance' ),
		'accepted'  => __( 'accepted', 'eu-withdrawal-compliance' ),
		'rejected'  => __( 'rejected', 'eu-withdrawal-compliance' ),
		'completed' => __( 'completed', 'eu-withdrawal-compliance' ),
	);

	$label = isset( $labels[ $status ] ) ? $labels[ $status ] : $status;

	$note = sprintf(
		/* translators: 1: status label, 2: log ID. */
		__( 'EU withdrawal request %1$s. Log ID: #%2$d', 'eu-withdrawal-compliance' ),
		$label,
		$cpt_id
	);

	if ( '' !== $comment ) {
		$note .= "\n" . sprintf(
			/* translators: %s: admin comment. */
			__( 'Comment: %s', 'eu-withdrawal-compliance' ),
			$comment
		);
	}

	$order->add_order_note( $note, 0, false );
}

/**
 * Register the My Account endpoint for WooCommerce.
 */
function ayudawp_euw_register_wc_endpoint() {

	add_rewrite_endpoint( 'withdrawal', EP_ROOT | EP_PAGES );
}
add_action( 'init', 'ayudawp_euw_register_wc_endpoint' );

/**
 * Add the endpoint to WooCommerce's My Account query vars.
 *
 * @param array $vars Query vars.
 * @return array
 */
function ayudawp_euw_add_query_var( $vars ) {

	$vars[] = 'withdrawal';
	return $vars;
}
add_filter( 'query_vars', 'ayudawp_euw_add_query_var', 0 );

/**
 * Add the menu item to WooCommerce My Account navigation.
 *
 * @param array $items Navigation items.
 * @return array
 */
function ayudawp_euw_add_account_menu_item( $items ) {

	if ( ! function_exists( 'wc_get_endpoint_url' ) ) {
		return $items;
	}

	// Insert the new item before the logout link.
	$logout = isset( $items['customer-logout'] ) ? array( 'customer-logout' => $items['customer-logout'] ) : array();

	if ( $logout ) {
		unset( $items['customer-logout'] );
	}

	$items['withdrawal'] = __( 'Right of withdrawal', 'eu-withdrawal-compliance' );

	return array_merge( $items, $logout );
}
add_filter( 'woocommerce_account_menu_items', 'ayudawp_euw_add_account_menu_item' );

/**
 * Render the form inside the My Account endpoint.
 *
 * Two entry points land here:
 *   - The per-order "Withdraw from contract" button, which carries a per-order
 *     nonce (validated by ayudawp_euw_get_prefill_order_id). Only then do we
 *     pull the real billing details from the order and lock them, so what the
 *     customer submits always matches the order on file.
 *   - A direct visit to the "Right of withdrawal" My Account menu item, which
 *     has no nonce: we render a blank form with just the account email and
 *     never expose order details to a plain endpoint visit.
 *
 * ayudawp_euw_render_form() echoes the markup directly with per-token
 * esc_attr / esc_html / esc_url; no late wrapping is needed here.
 */
function ayudawp_euw_account_endpoint_content() {

	// The review screen of step 2 is the dedicated confirmation function of
	// Article 11a(3) and stays free of anything else, so the list is skipped
	// while it is on screen.
	$confirming = ( '' !== ayudawp_euw_get_confirm_token() );
	$listed     = false;

	if ( ! $confirming && ayudawp_euw_show_account_status() ) {
		$listed = ayudawp_euw_render_account_requests();
	}

	$prefill_ref = ayudawp_euw_get_prefill_order_id();
	$atts        = ( '' !== $prefill_ref ) ? ayudawp_euw_get_order_prefill( $prefill_ref ) : array();

	if ( empty( $atts ) ) {
		$user = wp_get_current_user();
		$atts = array(
			'email' => ( $user && ! empty( $user->user_email ) ) ? $user->user_email : '',
		);
	}

	// The heading only earns its place once something precedes the form. With
	// no requests yet the endpoint is what it has always been, a form.
	if ( $listed ) {
		printf(
			'<h2 class="ayudawp-euw-account-heading">%s</h2>',
			esc_html__( 'Submit a new request', 'eu-withdrawal-compliance' )
		);
	}

	ayudawp_euw_render_form( $atts );
}
add_action( 'woocommerce_account_withdrawal_endpoint', 'ayudawp_euw_account_endpoint_content' );

/**
 * IDs of the withdrawal requests that belong to a customer.
 *
 * Two criteria, both of them proof of ownership. Requests sent while logged in
 * carry the author's user ID, which covers the ones sent from the public form
 * and the unverified ones, never linked to an order and otherwise invisible to
 * the person who sent them. The order link covers the rest, including requests
 * sent with an address other than the account's, common when the billing email
 * of the order is not the one used to register.
 *
 * Matching by the address of the account, which is what the GDPR exporter does,
 * is deliberately NOT one of them: that exporter runs on an address the owner
 * has confirmed through the WordPress data-request flow, while WooCommerce lets
 * a customer change the address of their own account without confirming it. A
 * self-service list keyed on it would hand anyone the requests sent as a guest
 * from any address that has no account on the shop. Guest requests therefore
 * stay out of the account until there is a way to claim them, and their trace
 * remains the acknowledgement email.
 *
 * @param int $user_id Optional user ID. Defaults to the current user.
 * @param int $limit   Maximum number of requests to return.
 * @return array<int, int> Request IDs, newest first.
 */
function ayudawp_euw_get_customer_requests( $user_id = 0, $limit = 20 ) {

	$user_id = $user_id ? absint( $user_id ) : get_current_user_id();

	if ( ! $user_id ) {
		return array();
	}

	$criteria = array(
		'relation' => 'OR',
		array(
			'key'   => '_ayudawp_euw_user_id',
			'value' => $user_id,
		),
	);

	$order_ids = ayudawp_euw_get_customer_order_ids( $user_id );

	if ( ! empty( $order_ids ) ) {
		$criteria[] = array(
			'key'     => '_ayudawp_euw_wc_order_id',
			'value'   => $order_ids,
			'compare' => 'IN',
		);
	}

	$query = new WP_Query(
		array(
			'post_type'              => 'ayudawp_withdrawal',
			'post_status'            => 'any',
			'posts_per_page'         => absint( $limit ),
			'orderby'                => 'date',
			'order'                  => 'DESC',
			'fields'                 => 'ids',
			'no_found_rows'          => true,
			'update_post_term_cache' => false,
			// phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_query -- One bounded query, on a screen a customer opens to read their own requests, over meta keys indexed by WordPress. The alternative would be a custom table.
			'meta_query'             => $criteria,
		)
	);

	return array_map( 'absint', $query->posts );
}

/**
 * Order IDs of a customer, capped so the lookup above stays bounded.
 *
 * The cap is not a coverage problem in practice: a request sent with the same
 * address as the account is matched by email regardless of how old its order is.
 *
 * @param int $user_id User ID.
 * @return array<int, int>
 */
function ayudawp_euw_get_customer_order_ids( $user_id ) {

	if ( ! function_exists( 'wc_get_orders' ) ) {
		return array();
	}

	$orders = wc_get_orders(
		array(
			'customer_id' => absint( $user_id ),
			'limit'       => 100,
			'orderby'     => 'date',
			'order'       => 'DESC',
			'return'      => 'ids',
		)
	);

	return is_array( $orders ) ? array_map( 'absint', $orders ) : array();
}

/**
 * Render the customer's own withdrawal requests above the form.
 *
 * Echoes nothing when the customer has no requests: an empty table pushing the
 * form down is worse than the form on its own.
 *
 * @return bool Whether anything was rendered.
 */
function ayudawp_euw_render_account_requests() {

	$requests = ayudawp_euw_get_customer_requests();

	if ( empty( $requests ) ) {
		return false;
	}

	$date_format = get_option( 'date_format' );
	?>
	<section class="ayudawp-euw-requests" id="ayudawp-euw-requests">

		<h2 class="ayudawp-euw-account-heading"><?php esc_html_e( 'Your withdrawal requests', 'eu-withdrawal-compliance' ); ?></h2>

		<table class="ayudawp-euw-requests-table">
			<thead>
				<tr>
					<th scope="col"><?php esc_html_e( 'Submitted', 'eu-withdrawal-compliance' ); ?></th>
					<th scope="col"><?php esc_html_e( 'Order', 'eu-withdrawal-compliance' ); ?></th>
					<th scope="col"><?php esc_html_e( 'Scope', 'eu-withdrawal-compliance' ); ?></th>
					<th scope="col"><?php esc_html_e( 'Status', 'eu-withdrawal-compliance' ); ?></th>
				</tr>
			</thead>
			<tbody>
				<?php foreach ( $requests as $request_id ) : ?>
					<?php
					$status = get_post_meta( $request_id, '_ayudawp_euw_status', true );
					$status = $status ? $status : 'pending';

					$submitted = get_post_meta( $request_id, '_ayudawp_euw_submitted_at', true );
					$submitted = $submitted ? strtotime( $submitted . ' UTC' ) : get_post_time( 'U', true, $request_id );

					$resolved = get_post_meta( $request_id, '_ayudawp_euw_status_changed_at', true );
					$resolved = $resolved ? strtotime( $resolved . ' UTC' ) : 0;

					$order_ref = (string) get_post_meta( $request_id, '_ayudawp_euw_order', true );
					$order_id  = absint( get_post_meta( $request_id, '_ayudawp_euw_wc_order_id', true ) );
					$comment   = (string) get_post_meta( $request_id, '_ayudawp_euw_status_comment', true );
					$hash      = (string) get_post_meta( $request_id, '_ayudawp_euw_receipt_hash', true );

					$scope_label = ( 'partial' === get_post_meta( $request_id, '_ayudawp_euw_scope', true ) )
						? __( 'Specific products', 'eu-withdrawal-compliance' )
						: __( 'Full order', 'eu-withdrawal-compliance' );
					?>
					<tr>
						<td data-title="<?php esc_attr_e( 'Submitted', 'eu-withdrawal-compliance' ); ?>">
							<?php echo esc_html( $submitted ? wp_date( $date_format, $submitted ) : '—' ); ?>
						</td>
						<td data-title="<?php esc_attr_e( 'Order', 'eu-withdrawal-compliance' ); ?>">
							<?php if ( $order_id && function_exists( 'wc_get_endpoint_url' ) && function_exists( 'wc_get_page_permalink' ) ) : ?>
								<a href="<?php echo esc_url( wc_get_endpoint_url( 'view-order', $order_id, wc_get_page_permalink( 'myaccount' ) ) ); ?>">
									<?php echo esc_html( $order_ref ); ?>
								</a>
							<?php else : ?>
								<?php echo esc_html( '' !== $order_ref ? $order_ref : '—' ); ?>
							<?php endif; ?>
						</td>
						<td data-title="<?php esc_attr_e( 'Scope', 'eu-withdrawal-compliance' ); ?>">
							<?php echo esc_html( $scope_label ); ?>
						</td>
						<td data-title="<?php esc_attr_e( 'Status', 'eu-withdrawal-compliance' ); ?>">

							<span class="ayudawp-euw-request-status ayudawp-euw-request-status--<?php echo esc_attr( sanitize_html_class( $status ) ); ?>">
								<?php echo esc_html( ayudawp_euw_customer_status_label( $status ) ); ?>
							</span>

							<span class="ayudawp-euw-request-detail">
								<?php if ( 'pending' === $status ) : ?>
									<?php esc_html_e( 'Awaiting review against the legal deadlines and conditions.', 'eu-withdrawal-compliance' ); ?>
								<?php elseif ( $resolved ) : ?>
									<?php
									printf(
										/* translators: %s: date the request was resolved. */
										esc_html__( 'Resolved on %s.', 'eu-withdrawal-compliance' ),
										esc_html( wp_date( $date_format, $resolved ) )
									);
									?>
								<?php endif; ?>

								<?php if ( '' !== $comment ) : ?>
									<span class="ayudawp-euw-request-note">
										<?php
										printf(
											/* translators: %s: note written by the shop when resolving the request. */
											esc_html__( 'Note from the shop: %s', 'eu-withdrawal-compliance' ),
											esc_html( $comment )
										);
										?>
									</span>
								<?php endif; ?>

								<?php if ( '' !== $hash ) : ?>
									<span class="ayudawp-euw-request-hash">
										<?php
										printf(
											/* translators: %s: SHA-256 acknowledgement code. */
											esc_html__( 'Receipt code: %s', 'eu-withdrawal-compliance' ),
											esc_html( $hash )
										);
										?>
									</span>
								<?php endif; ?>
							</span>

							<?php if ( 'rejected' === $status && $order_id ) : ?>
								<a class="ayudawp-euw-button ayudawp-euw-button--secondary" href="<?php echo esc_url( ayudawp_euw_get_prefill_endpoint_url( $order_ref ) ); ?>">
									<?php esc_html_e( 'Contest the rejection', 'eu-withdrawal-compliance' ); ?>
								</a>
							<?php endif; ?>
						</td>
					</tr>
				<?php endforeach; ?>
			</tbody>
		</table>
	</section>
	<?php

	return true;
}

/**
 * URL where a customer can follow their requests, for the plugin's emails.
 *
 * Empty unless the address belongs to an account, WooCommerce provides the My
 * Account screen and the request itself will actually be listed there. Pointing
 * someone at a screen that turns out to be empty is worse than not mentioning
 * it: a request sent as a guest is not attributed to any account, even when the
 * address happens to have one.
 *
 * @param string $email      Customer email address.
 * @param int    $request_id Optional request this email is about.
 * @return string
 */
function ayudawp_euw_get_customer_requests_url( $email, $request_id = 0 ) {

	$url = ayudawp_euw_get_requests_list_url();

	if ( '' === $url || ! ayudawp_euw_show_account_status() || ! is_email( $email ) ) {
		return '';
	}

	$user = get_user_by( 'email', $email );

	if ( ! $user ) {
		return '';
	}

	if ( $request_id && ! ayudawp_euw_request_belongs_to_user( $request_id, $user->ID ) ) {
		return '';
	}

	return $url;
}

/**
 * Whether a request is attributed to a user, by the same rules as the account list.
 *
 * @param int $request_id Request ID.
 * @param int $user_id    User ID.
 * @return bool
 */
function ayudawp_euw_request_belongs_to_user( $request_id, $user_id ) {

	$request_id = absint( $request_id );
	$user_id    = absint( $user_id );

	if ( ! $request_id || ! $user_id ) {
		return false;
	}

	if ( absint( get_post_meta( $request_id, '_ayudawp_euw_user_id', true ) ) === $user_id ) {
		return true;
	}

	$order_id = absint( get_post_meta( $request_id, '_ayudawp_euw_wc_order_id', true ) );

	if ( ! $order_id || ! function_exists( 'wc_get_order' ) ) {
		return false;
	}

	$order = wc_get_order( $order_id );

	return ( $order && method_exists( $order, 'get_customer_id' ) && absint( $order->get_customer_id() ) === $user_id );
}

/**
 * Build the locked pre-fill set from a verified order reference in My Account.
 *
 * Called only after ayudawp_euw_get_prefill_order_id() has validated the
 * per-order nonce, so we know the visit came from the plugin's own "Withdraw
 * from contract" button. The order must belong to the logged-in customer; a
 * customer must never see another customer's billing data. When the order can
 * be resolved and is owned, name, billing email and order date are taken from
 * it and locked; otherwise only the order reference is pre-filled.
 *
 * @param string $order_ref Verified order reference.
 * @return array Prefill atts for ayudawp_euw_render_form().
 */
function ayudawp_euw_get_order_prefill( $order_ref ) {

	// Without WooCommerce we can't resolve the order; pre-fill just the
	// reference so the customer still has the number filled in.
	if ( ! function_exists( 'wc_get_order' ) ) {
		return array( 'order_id' => $order_ref );
	}

	$order = ayudawp_euw_resolve_wc_order( $order_ref );

	if ( ! $order || ! method_exists( $order, 'get_customer_id' ) ) {
		return array( 'order_id' => $order_ref );
	}

	// Defence in depth: the button only renders on the customer's own orders,
	// but never pre-fill another account's details even if the link is shared.
	if ( (int) $order->get_customer_id() !== get_current_user_id() ) {
		return array( 'order_id' => $order_ref );
	}

	$name = method_exists( $order, 'get_formatted_billing_full_name' )
		? trim( $order->get_formatted_billing_full_name() )
		: '';

	$date = ( method_exists( $order, 'get_date_created' ) && $order->get_date_created() )
		? $order->get_date_created()->date( 'Y-m-d' )
		: '';

	return array(
		'order_id' => $order_ref,
		'email'    => method_exists( $order, 'get_billing_email' ) ? $order->get_billing_email() : '',
		'name'     => $name,
		'date'     => $date,
		'lock'     => true,
	);
}

/**
 * Add a "Withdraw" action button to each order in My Account orders list.
 *
 * @param array  $actions Order actions.
 * @param object $order   WC_Order.
 * @return array
 */
function ayudawp_euw_add_order_action( $actions, $order ) {

	if ( ! function_exists( 'wc_get_account_endpoint_url' ) ) {
		return $actions;
	}

	// Reporting a request that exists is not the same as offering a new one, so
	// this runs before the order-status gate below: an order that has since
	// moved to a status which offers nothing (refunded, cancelled) is exactly
	// when the customer comes looking for what happened to their request. An
	// open request used to leave this slot empty, so the button simply vanished
	// from the row and left no trace anywhere in the account. The lookup skips
	// its fallback query, so rows without a request cost nothing: the mirror it
	// reads has been written on every linked request since 1.4.0.
	$existing        = ayudawp_euw_get_request_for_order( $order->get_id(), false );
	$existing_status = '';

	if ( $existing ) {

		$existing_status = get_post_meta( $existing, '_ayudawp_euw_status', true );
		$existing_status = $existing_status ? $existing_status : 'pending';

		if ( ayudawp_euw_show_account_status() ) {
			$actions['ayudawp_euw_status'] = array(
				'url'  => ayudawp_euw_get_requests_list_url(),
				'name' => sprintf(
					/* translators: %s: withdrawal status, e.g. "Submitted". */
					__( 'Withdrawal: %s', 'eu-withdrawal-compliance' ),
					ayudawp_euw_customer_status_label( $existing_status )
				),
			);
		}
	}

	// Order-status eligibility gates every entry point. The strict-mode deadline
	// is applied further down, and only to a brand-new request: a rejected one can
	// still be contested past the deadline, since strict mode blocks opening new
	// requests, not re-engaging a process that is already open.
	if ( ! ayudawp_euw_should_show_withdrawal( $order, false ) ) {
		return $actions;
	}

	if ( $existing ) {

		// A non-rejected request is already open: nothing to offer the customer here.
		if ( 'rejected' !== $existing_status ) {
			return $actions;
		}

		// A rejection is the only decision the customer may legitimately contest,
		// and re-submitting the form is their only channel, so we keep the button
		// (relabelled) even past the deadline in strict mode.
		$button_label = __( 'Contest the rejection', 'eu-withdrawal-compliance' );
		/* translators: %s: order number. */
		$aria_template = __( 'Contest the rejection of the withdrawal request for order %s', 'eu-withdrawal-compliance' );

	} elseif ( ! ayudawp_euw_should_show_withdrawal( $order ) ) {

		// No request yet: a brand-new one is also subject to the strict-mode
		// deadline. Past it, offer nothing.
		return $actions;

	} else {

		// Short label because this button shares a narrow column with "View" and
		// wrapped badly on several themes. The literal wording the directive
		// suggests stays where it belongs: the footer link and the transactional
		// emails, which are the permanent withdrawal function, plus the aria-label
		// here, so assistive technology still announces the full sentence. This
		// button is only a shortcut that pre-fills the form; nothing is withdrawn
		// until the "Confirm withdrawal" button of Article 11a(3).
		$button_label = __( 'Withdraw from order', 'eu-withdrawal-compliance' );
		/* translators: %s: order number. */
		$aria_template = __( 'Withdraw from contract for order %s', 'eu-withdrawal-compliance' );
	}

	$order_ref = method_exists( $order, 'get_order_number' ) ? $order->get_order_number() : $order->get_id();

	$actions['ayudawp_euw'] = array(
		'url'        => ayudawp_euw_get_prefill_endpoint_url( $order_ref ),
		'name'       => $button_label,
		// Without this, the WooCommerce template builds the label itself and reads
		// out "Withdraw from order order number 396" (templates/myaccount/orders.php,
		// lines 78-84 in 11.1.0).
		'aria-label' => sprintf( $aria_template, $order_ref ),
	);

	return $actions;
}

/**
 * Build the signed My Account form URL that pre-fills a given order.
 *
 * The nonce is created against the specific order reference and verified by
 * ayudawp_euw_get_prefill_order_id(), so only links the plugin generated itself
 * unlock the locked pre-fill of the customer's own order details.
 *
 * @param string $order_ref Order number as shown to the customer.
 * @return string Endpoint URL, or an empty string without WooCommerce.
 */
function ayudawp_euw_get_prefill_endpoint_url( $order_ref ) {

	if ( ! function_exists( 'wc_get_account_endpoint_url' ) ) {
		return '';
	}

	return add_query_arg(
		array(
			'order_id' => $order_ref,
			'_wpnonce' => wp_create_nonce( 'ayudawp_euw_prefill_' . $order_ref ),
		),
		wc_get_account_endpoint_url( 'withdrawal' )
	);
}

/**
 * URL of the customer's own list of withdrawal requests.
 *
 * @return string My Account endpoint URL, or an empty string without WooCommerce.
 */
function ayudawp_euw_get_requests_list_url() {

	if ( ! function_exists( 'wc_get_account_endpoint_url' ) ) {
		return '';
	}

	return wc_get_account_endpoint_url( 'withdrawal' ) . '#ayudawp-euw-requests';
}

/**
 * Whether the customer-facing status of a request is shown in My Account.
 *
 * @return bool
 */
function ayudawp_euw_show_account_status() {

	return 'yes' === get_option( 'ayudawp_euw_account_status_enabled', 'yes' );
}

/**
 * Customer-facing label for a withdrawal status.
 *
 * Deliberately not the wording of the admin column. On the customer's orders
 * table the order status cell of the same row can already read "Completed"
 * (delivered) while the withdrawal is completed too (refunded), and the two
 * meanings side by side read as a contradiction, so the refund is named after
 * what the customer gets back. "Not accepted" over "Rejected" for the same
 * reason: it is a decision the customer may still contest, not a verdict.
 *
 * @param string $status Stored status.
 * @return string Translated label.
 */
function ayudawp_euw_customer_status_label( $status ) {

	$labels = array(
		'pending'   => __( 'Submitted', 'eu-withdrawal-compliance' ),
		'accepted'  => __( 'Accepted', 'eu-withdrawal-compliance' ),
		'rejected'  => __( 'Not accepted', 'eu-withdrawal-compliance' ),
		'completed' => __( 'Refund issued', 'eu-withdrawal-compliance' ),
	);

	return isset( $labels[ $status ] ) ? $labels[ $status ] : $labels['pending'];
}
add_filter( 'woocommerce_my_account_my_orders_actions', 'ayudawp_euw_add_order_action', 10, 2 );

/**
 * Read and verify the prefill order reference from the current request.
 *
 * The nonce is created in ayudawp_euw_add_order_action() against the
 * specific order reference, so only links generated by the plugin itself
 * trigger the prefill. Returns an empty string when the nonce is missing
 * or invalid.
 *
 * @return string Validated order reference, or empty string.
 */
function ayudawp_euw_get_prefill_order_id() {

	if ( ! isset( $_GET['_wpnonce'], $_GET['order_id'] ) ) {
		return '';
	}

	$order_id = sanitize_text_field( wp_unslash( $_GET['order_id'] ) );
	$nonce    = sanitize_text_field( wp_unslash( $_GET['_wpnonce'] ) );

	if ( ! wp_verify_nonce( $nonce, 'ayudawp_euw_prefill_' . $order_id ) ) {
		return '';
	}

	return $order_id;
}

/**
 * Pre-fill the order number on the shortcode-rendered form.
 *
 * Two kinds of link land on a shortcode page:
 *
 *   1. The nonce-signed My Account "Withdraw from contract" link. We honour it
 *      first via ayudawp_euw_get_prefill_order_id(), which validates the
 *      per-order nonce. (The same nonce gates the locked personal-data pre-fill
 *      on the My Account endpoint, handled separately in
 *      ayudawp_euw_get_order_prefill(); this filter only ever sets order_id.)
 *   2. The "Withdraw from contract here" link in the transactional emails,
 *      which carries a bare ?order_id and no nonce. An email link cannot hold a
 *      valid nonce — nonces are tied to a user session and expire within hours,
 *      an email is neither bound to a session nor short-lived — so requiring one
 *      here simply meant the email never pre-filled anything.
 *
 * Pre-filling only the order number is safe without a nonce: it is not secret
 * (it is printed in the customer's own receipt), it lands in a plain editable
 * text field, it triggers no state change, and the real gate runs on submit,
 * where the order number and email are validated together against the database.
 * Personal data is never pre-filled through this filter.
 *
 * @param array $atts Shortcode atts.
 * @return array
 */
function ayudawp_euw_prefill_from_query( $atts ) {

	$order_id = ayudawp_euw_get_prefill_order_id();

	// Fallback: accept a bare ?order_id (e.g. from the transactional email link).
	if ( '' === $order_id && isset( $_GET['order_id'] ) ) { // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- pre-fills a non-secret, editable field; the submission is validated server-side.
		$order_id = sanitize_text_field( wp_unslash( $_GET['order_id'] ) ); // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- same read as the line above: pre-fills a non-secret, editable field, and the submission is validated server-side.
	}

	if ( '' !== $order_id ) {
		$atts['order_id'] = $order_id;
	}

	return $atts;
}
add_filter( 'shortcode_atts_ayudawp_withdrawal_form', 'ayudawp_euw_prefill_from_query' );

/**
 * Find the most recent withdrawal request linked to a WC order.
 *
 * Returns 0 if none exists.
 *
 * @param int  $wc_order_id WC order ID.
 * @param bool $deep        Whether to fall back to a meta query when the order
 *                          carries no link. Worth it on the admin column, which
 *                          reports on a handful of orders and should never miss
 *                          one; skipped on customer-facing rows, where it would
 *                          add a query for every order that has no request.
 * @return int Withdrawal CPT ID or 0.
 */
function ayudawp_euw_get_request_for_order( $wc_order_id, $deep = true ) {

	$wc_order_id = absint( $wc_order_id );

	if ( ! $wc_order_id ) {
		return 0;
	}

	// Fast path. The link is mirrored on the order itself when the request is
	// registered (see ayudawp_euw_add_wc_order_note), and orders on a listing
	// screen are already loaded, so this resolves from cache instead of running
	// a meta query per row. Entries deleted since fall through to the query.
	if ( function_exists( 'wc_get_order' ) ) {

		$order = wc_get_order( $wc_order_id );

		if ( $order && method_exists( $order, 'get_meta' ) ) {

			$mirrored = absint( $order->get_meta( '_ayudawp_euw_request_id' ) );

			if ( $mirrored && 'ayudawp_withdrawal' === get_post_type( $mirrored ) ) {
				return $mirrored;
			}
		}
	}

	if ( ! $deep ) {
		return 0;
	}

	$query = new WP_Query(
		array(
			'post_type'              => 'ayudawp_withdrawal',
			'post_status'            => 'any',
			'posts_per_page'         => 1,
			'orderby'                => 'date',
			'order'                  => 'DESC',
			'fields'                 => 'ids',
			'no_found_rows'          => true,
			'update_post_meta_cache' => false,
			'update_post_term_cache' => false,
			// phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_query -- Single-row lookup limited to one row, on a meta key indexed by WordPress (`wp_postmeta.meta_key`). Used to paint the "Withdrawal" column on the orders screen, where alternatives would require a custom table or per-order shadow option.
			'meta_query'             => array(
				array(
					'key'   => '_ayudawp_euw_wc_order_id',
					'value' => $wc_order_id,
				),
			),
		)
	);

	if ( empty( $query->posts ) ) {
		return 0;
	}

	return (int) $query->posts[0];
}

/**
 * Add the "Withdrawal" column to the WC orders screen (legacy + HPOS).
 *
 * @param array $columns Existing columns.
 * @return array
 */
function ayudawp_euw_add_orders_column( $columns ) {

	$new = array();

	foreach ( $columns as $key => $label ) {
		$new[ $key ] = $label;

		// Insert after the order status column for both legacy and HPOS.
		if ( 'order_status' === $key ) {
			$new['ayudawp_euw_withdrawal'] = __( 'Withdrawal', 'eu-withdrawal-compliance' );
		}
	}

	// If neither table has an order_status column, append at the end.
	if ( ! isset( $new['ayudawp_euw_withdrawal'] ) ) {
		$new['ayudawp_euw_withdrawal'] = __( 'Withdrawal', 'eu-withdrawal-compliance' );
	}

	return $new;
}
add_filter( 'manage_edit-shop_order_columns', 'ayudawp_euw_add_orders_column' );
add_filter( 'manage_woocommerce_page_wc-orders_columns', 'ayudawp_euw_add_orders_column' );

/**
 * Render the "Withdrawal" column for legacy CPT-based orders.
 *
 * @param string $column  Column key.
 * @param int    $post_id Post ID.
 */
function ayudawp_euw_render_orders_column_legacy( $column, $post_id ) {

	if ( 'ayudawp_euw_withdrawal' !== $column ) {
		return;
	}

	echo wp_kses_post( ayudawp_euw_orders_column_html( $post_id ) );
}
add_action( 'manage_shop_order_posts_custom_column', 'ayudawp_euw_render_orders_column_legacy', 10, 2 );

/**
 * Render the "Withdrawal" column for HPOS orders.
 *
 * @param string $column Column key.
 * @param object $order  WC_Order instance.
 */
function ayudawp_euw_render_orders_column_hpos( $column, $order ) {

	if ( 'ayudawp_euw_withdrawal' !== $column ) {
		return;
	}

	$order_id = is_object( $order ) && method_exists( $order, 'get_id' ) ? $order->get_id() : 0;

	echo wp_kses_post( ayudawp_euw_orders_column_html( $order_id ) );
}
add_action( 'manage_woocommerce_page_wc-orders_custom_column', 'ayudawp_euw_render_orders_column_hpos', 10, 2 );

/**
 * Build the HTML shown inside the "Withdrawal" column for an order.
 *
 * @param int $wc_order_id WC order ID.
 * @return string Already-escaped HTML.
 */
function ayudawp_euw_orders_column_html( $wc_order_id ) {

	$cpt_id = ayudawp_euw_get_request_for_order( $wc_order_id );

	if ( ! $cpt_id ) {
		return '<span class="ayudawp-euw-status ayudawp-euw-status-empty" aria-hidden="true">—</span><span class="screen-reader-text">' . esc_html__( 'No withdrawal request', 'eu-withdrawal-compliance' ) . '</span>';
	}

	$status = get_post_meta( $cpt_id, '_ayudawp_euw_status', true );
	$status = $status ? $status : 'pending';

	$labels = array(
		'pending'   => __( 'Pending', 'eu-withdrawal-compliance' ),
		'accepted'  => __( 'Accepted', 'eu-withdrawal-compliance' ),
		'rejected'  => __( 'Rejected', 'eu-withdrawal-compliance' ),
		'completed' => __( 'Completed', 'eu-withdrawal-compliance' ),
	);

	$label    = isset( $labels[ $status ] ) ? $labels[ $status ] : $labels['pending'];
	$class    = 'ayudawp-euw-status-' . sanitize_html_class( $status );
	$edit_url = get_edit_post_link( $cpt_id );

	return sprintf(
		'<a href="%1$s" class="ayudawp-euw-status %2$s">%3$s</a>',
		esc_url( $edit_url ),
		esc_attr( $class ),
		esc_html( $label )
	);
}
