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
 * Validate that a given order/email pair belongs to a real WC order
 * and that the 14-day withdrawal window is still open.
 *
 * If WooCommerce is not active, the function returns valid by default
 * because we cannot check anything against an order database. When WC is
 * active, the order must exist and the email must match its billing email;
 * sites that genuinely accept non-WC purchases can opt back into the
 * lenient behaviour through the `ayudawp_euw_allow_unverified_order` filter.
 *
 * @param string $order_ref Order number or ID provided by the user.
 * @param string $email     Customer email.
 * @return array {
 *     @type bool   $valid     Whether the order is valid.
 *     @type string $error     Error code if invalid.
 *     @type int    $order_id  WooCommerce order ID if matched.
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

	// If WooCommerce is active but we cannot match the order, fail validation.
	// A filter allows opting back into the previous lenient behaviour for sites
	// that genuinely accept non-WC purchases (manual invoices, marketplaces).
	if ( ! $order ) {
		if ( apply_filters( 'ayudawp_euw_allow_unverified_order', false, $order_ref, $email ) ) {
			return $default;
		}

		return array(
			'valid'    => false,
			'error'    => 'order',
			'order_id' => 0,
		);
	}

	// If we have a WC order, verify the email matches.
	$order_email = method_exists( $order, 'get_billing_email' ) ? $order->get_billing_email() : '';

	if ( ! empty( $order_email ) && strtolower( trim( $order_email ) ) !== strtolower( trim( $email ) ) ) {
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

	$prefill_ref = ayudawp_euw_get_prefill_order_id();
	$atts        = ( '' !== $prefill_ref ) ? ayudawp_euw_get_order_prefill( $prefill_ref ) : array();

	if ( empty( $atts ) ) {
		$user = wp_get_current_user();
		$atts = array(
			'email' => ( $user && ! empty( $user->user_email ) ) ? $user->user_email : '',
		);
	}

	ayudawp_euw_render_form( $atts );
}
add_action( 'woocommerce_account_withdrawal_endpoint', 'ayudawp_euw_account_endpoint_content' );

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

	// Order-status eligibility gates every entry point. The strict-mode deadline
	// is applied further down, and only to a brand-new request: a rejected one can
	// still be contested past the deadline, since strict mode blocks opening new
	// requests, not re-engaging a process that is already open.
	if ( ! ayudawp_euw_should_show_withdrawal( $order, false ) ) {
		return $actions;
	}

	$existing = ayudawp_euw_get_request_for_order( $order->get_id() );

	if ( $existing ) {

		$existing_status = get_post_meta( $existing, '_ayudawp_euw_status', true );
		$existing_status = $existing_status ? $existing_status : 'pending';

		// A non-rejected request is already open: nothing to offer the customer here.
		if ( 'rejected' !== $existing_status ) {
			return $actions;
		}

		// A rejection is the only decision the customer may legitimately contest,
		// and re-submitting the form is their only channel, so we keep the button
		// (relabelled) even past the deadline in strict mode.
		$button_label = __( 'Contest the rejection', 'eu-withdrawal-compliance' );

	} elseif ( ! ayudawp_euw_should_show_withdrawal( $order ) ) {

		// No request yet: a brand-new one is also subject to the strict-mode
		// deadline. Past it, offer nothing.
		return $actions;

	} else {

		$button_label = __( 'Withdraw from contract', 'eu-withdrawal-compliance' );
	}

	$endpoint_url = wc_get_account_endpoint_url( 'withdrawal' );
	$order_ref    = method_exists( $order, 'get_order_number' ) ? $order->get_order_number() : $order->get_id();
	$endpoint_url = add_query_arg(
		array(
			'order_id' => $order_ref,
			'_wpnonce' => wp_create_nonce( 'ayudawp_euw_prefill_' . $order_ref ),
		),
		$endpoint_url
	);

	$actions['ayudawp_euw'] = array(
		'url'  => $endpoint_url,
		'name' => $button_label,
	);

	return $actions;
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
		$order_id = sanitize_text_field( wp_unslash( $_GET['order_id'] ) ); // phpcs:ignore WordPress.Security.NonceVerification.Recommended
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
 * @param int $wc_order_id WC order ID.
 * @return int Withdrawal CPT ID or 0.
 */
function ayudawp_euw_get_request_for_order( $wc_order_id ) {

	$wc_order_id = absint( $wc_order_id );

	if ( ! $wc_order_id ) {
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
