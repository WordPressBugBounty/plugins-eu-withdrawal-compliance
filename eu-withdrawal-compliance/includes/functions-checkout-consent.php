<?php
/**
 * Double-consent checkboxes at the WooCommerce checkout.
 *
 * Implements the two checkout-time consents required to apply specific
 * exceptions to the EU right of withdrawal:
 *
 * - Type A (mandatory): Article 16(m) of Directive 2011/83/EU. Required for
 *   digital content supplied on a non-tangible medium so the consumer
 *   acknowledges losing the withdrawal right upon starting access.
 * - Type B (optional): Article 14(4)(a) of Directive 2011/83/EU. Lets the
 *   trader charge a pro-rated amount when the service starts within the
 *   14-day window at the consumer's explicit request.
 *
 * Whether each checkbox shows up at the checkout is decided per cart: the
 * module inspects each item, honours direct flags on the product and falls
 * back to category-level inheritance via the same helpers used by the
 * Article 16 exclusions module.
 *
 * All consent metadata (text snapshot, accepted/declined, timestamp, IP and
 * user agent) is persisted on the WC order through the HPOS-safe meta API so
 * the trader can later prove the consent in case of a dispute.
 *
 * @package AyudaWP_EU_Withdrawal
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/*
 * The file declares its functions unconditionally and registers every WC
 * hook at require time. WooCommerce loads alphabetically after this plugin,
 * so `class_exists( 'WooCommerce' )` is not yet true on load — guarding the
 * whole file with it would skip every `add_action()` call below. The
 * individual callbacks check WC at call time when they need to.
 */

/**
 * Default text for the Type A consent checkbox (Art. 16(m), digital content).
 *
 * @return string
 */
function ayudawp_euw_consent_a_default_text() {

	return __( 'I expressly request immediate access to the digital content and acknowledge that, as soon as I start accessing, viewing or downloading it, I lose the 14-day right of withdrawal under Article 16(m) of Directive 2011/83/EU (Art. 103.m TRLGDCU in Spain).', 'eu-withdrawal-compliance' );
}

/**
 * Default text for the Type B consent checkbox (Art. 14(4)(a), prorated service).
 *
 * @return string
 */
function ayudawp_euw_consent_b_default_text() {

	return __( 'I expressly request that the service starts within the 14-day withdrawal period. I understand that if I withdraw before the period ends, I will have to pay the amount proportionate to the service already provided up to the date of my withdrawal request, under Article 14(4)(a) of Directive 2011/83/EU (Art. 108.3 TRLGDCU in Spain).', 'eu-withdrawal-compliance' );
}

/**
 * Whether a product (by ID) requires a given checkout consent type.
 *
 * Both consents are now derived from the canonical withdrawal status set
 * on the product (or inherited from its categories):
 *   - 'a' → required only when the status is 'art16m_digital'.
 *   - 'b' → required only when the status is 'art14_4a_service'.
 *
 * @param int    $product_id Product ID.
 * @param string $type       Consent type: 'a' or 'b'.
 * @return bool
 */
function ayudawp_euw_product_requires_consent( $product_id, $type ) {

	$product_id = absint( $product_id );
	$type       = ( 'a' === $type || 'b' === $type ) ? $type : '';

	if ( ! $product_id || '' === $type || ! function_exists( 'ayudawp_euw_get_product_withdrawal_status' ) ) {
		return false;
	}

	$status = ayudawp_euw_get_product_withdrawal_status( $product_id );

	if ( 'a' === $type ) {
		return 'art16m_digital' === $status;
	}

	return 'art14_4a_service' === $status;
}

/**
 * Whether the current cart contains at least one item requiring a consent type.
 *
 * @param string $type Consent type: 'a' or 'b'.
 * @return bool
 */
function ayudawp_euw_cart_requires_consent( $type ) {

	if ( ! function_exists( 'WC' ) || ! WC()->cart ) {
		return false;
	}

	foreach ( WC()->cart->get_cart() as $cart_item ) {

		$product_id   = isset( $cart_item['product_id'] ) ? (int) $cart_item['product_id'] : 0;
		$variation_id = isset( $cart_item['variation_id'] ) ? (int) $cart_item['variation_id'] : 0;

		if ( ayudawp_euw_product_requires_consent( $product_id, $type ) ) {
			return true;
		}

		if ( $variation_id && ayudawp_euw_product_requires_consent( $variation_id, $type ) ) {
			return true;
		}
	}

	return false;
}

/**
 * Resolve the configured text for a consent type, falling back to the default.
 *
 * @param string $type Consent type: 'a' or 'b'.
 * @return string
 */
function ayudawp_euw_get_consent_text( $type ) {

	$type = ( 'a' === $type || 'b' === $type ) ? $type : '';

	if ( '' === $type ) {
		return '';
	}

	$stored  = (string) get_option( 'ayudawp_euw_consent_' . $type . '_text', '' );
	$default = ( 'a' === $type )
		? ayudawp_euw_consent_a_default_text()
		: ayudawp_euw_consent_b_default_text();

	return '' !== trim( $stored ) ? $stored : $default;
}

/**
 * Whether a consent type is globally enabled in settings.
 *
 * @param string $type Consent type: 'a' or 'b'.
 * @return bool
 */
function ayudawp_euw_consent_is_enabled( $type ) {

	$type = ( 'a' === $type || 'b' === $type ) ? $type : '';

	if ( '' === $type ) {
		return false;
	}

	// Enabled by default — opt-out, not opt-in.
	$value = get_option( 'ayudawp_euw_consent_' . $type . '_enabled', 'yes' );

	return 'yes' === $value;
}

/**
 * Whether a consent type applies to the current checkout.
 *
 * Single gate for the three places that have to agree: the checkbox rendered,
 * the validation that demands it and the record written on the order. When they
 * disagree a consent is either shown and never recorded, or demanded and never
 * shown, so they all read this.
 *
 * @param string $type Consent type: 'a' or 'b'.
 * @return bool
 */
function ayudawp_euw_consent_applies( $type ) {

	$applies = ayudawp_euw_consent_is_enabled( $type ) && ayudawp_euw_cart_requires_consent( $type );

	/**
	 * Filters whether a checkout consent applies to the current cart.
	 *
	 * The default answer is "the consent is enabled in settings and the cart
	 * holds at least one product on the matching withdrawal status". Use this to
	 * decide per cart, customer or product what that rule cannot express, for
	 * example asking for the digital-content consent only above a given amount,
	 * or never asking a returning customer twice.
	 *
	 * @param bool   $applies Whether the consent applies.
	 * @param string $type    Consent type: 'a' (Art. 16(m)) or 'b' (Art. 14(4)(a)).
	 */
	return (bool) apply_filters( 'ayudawp_euw_consent_applies', $applies, $type );
}

/**
 * Whether a consent type must be ticked before the order can be placed.
 *
 * Type A is always required: the Article 16(m) exception exists only if the
 * consumer expressly consents, so an unticked box means the exception does not
 * apply and the order cannot claim it. Type B is optional by default, because
 * asking for early performance is the consumer's choice, but a shop whose
 * service always starts immediately (live sessions, bookings inside the
 * withdrawal window) can make it a condition of the sale.
 *
 * @param string $type Consent type: 'a' or 'b'.
 * @return bool
 */
function ayudawp_euw_consent_is_required( $type ) {

	$required = ( 'a' === $type )
		? true
		: ( 'yes' === get_option( 'ayudawp_euw_consent_b_required', 'no' ) );

	/**
	 * Filters whether a checkout consent is mandatory.
	 *
	 * @param bool   $required Whether the consent must be accepted.
	 * @param string $type     Consent type: 'a' (Art. 16(m)) or 'b' (Art. 14(4)(a)).
	 */
	return (bool) apply_filters( 'ayudawp_euw_consent_is_required', $required, $type );
}

/**
 * Error message shown when a required consent is left unticked.
 *
 * @param string $type Consent type: 'a' or 'b'.
 * @return string
 */
function ayudawp_euw_consent_error_message( $type ) {

	if ( 'a' === $type ) {
		return __( 'You must accept the digital content consent required to apply the withdrawal-right exception under Article 16(m) of Directive 2011/83/EU.', 'eu-withdrawal-compliance' );
	}

	return __( 'You must accept the early service start consent under Article 14(4)(a) of Directive 2011/83/EU to place this order.', 'eu-withdrawal-compliance' );
}

/**
 * Where the consent checkboxes go at the checkout, as chosen in the settings.
 *
 * @return string 'button' (next to the Place order button, the default) or 'order_notes'.
 */
function ayudawp_euw_consent_location() {

	return 'order_notes' === get_option( 'ayudawp_euw_consent_location', 'button' ) ? 'order_notes' : 'button';
}

/**
 * Checkout hook that matches the location chosen in the settings.
 *
 * @return string Hook name.
 */
function ayudawp_euw_consent_location_hook() {

	return 'order_notes' === ayudawp_euw_consent_location() ? 'woocommerce_after_order_notes' : 'woocommerce_review_order_before_submit';
}

/**
 * Render the consent checkboxes inside the checkout form.
 *
 * The location is chosen in the settings (see `ayudawp_euw_register_consent_render()`).
 * By default it is the order review panel, next to the Place order button, which
 * every checkout renders. Page builders that split the checkout into modules
 * (Divi) render each module from the full checkout and hide what is not theirs
 * by CSS, and Divi's Shipping module hides `.woocommerce-additional-fields`,
 * which is where `woocommerce_after_order_notes` lands.
 *
 * WooCommerce renders the panel again on every `update_order_review` and the
 * fragment brings the checkbox once (a single hook, so it is not duplicated;
 * it was when the checkbox was attached to several hooks at once), and
 * `assets/js/checkout-consent.js` gives the tick back after `updated_checkout`.
 *
 * The static guard remains as a safety net against a hook that fires more than
 * once in the same request, which is what Divi does.
 */
function ayudawp_euw_checkout_render_consent_fields() {

	static $rendered = false;

	if ( $rendered ) {
		return;
	}

	$types = array_filter( array( 'a', 'b' ), 'ayudawp_euw_consent_applies' );

	if ( empty( $types ) ) {
		return;
	}

	$rendered = true;

	$allowed_html = array(
		'a'      => array(
			'href'   => array(),
			'target' => array(),
			'rel'    => array(),
		),
		'strong' => array(),
		'em'     => array(),
		'br'     => array(),
	);

	echo '<div class="ayudawp-euw-checkout-consents">';

	foreach ( $types as $type ) {

		$required = ayudawp_euw_consent_is_required( $type );
		$field_id = 'ayudawp_euw_consent_' . $type;

		$classes = array( 'form-row', 'form-row-wide', 'ayudawp-euw-checkout-consent', 'ayudawp-euw-checkout-consent--' . $type );

		if ( $required ) {
			$classes[] = 'validate-required';
		}
		?>
		<p class="<?php echo esc_attr( implode( ' ', $classes ) ); ?>">
			<label class="checkbox" for="<?php echo esc_attr( $field_id ); ?>">
				<input type="checkbox"
					id="<?php echo esc_attr( $field_id ); ?>"
					name="<?php echo esc_attr( $field_id ); ?>"
					value="1"
					class="input-checkbox"
					<?php echo esc_attr( $required ? 'required' : '' ); ?>>
				<span class="ayudawp-euw-checkout-consent__text">
					<?php echo wp_kses( ayudawp_euw_get_consent_text( $type ), $allowed_html ); ?>
					<?php if ( $required ) : ?>
						<span class="required" aria-hidden="true">*</span>
					<?php endif; ?>
				</span>
			</label>
		</p>
		<?php
	}

	echo '</div>';
}

/**
 * Hook the consent render at the checkout location.
 *
 * Registered on `wp_loaded` so a theme's functions.php (which loads after every
 * plugin) can still filter the location. Without a filter, both possible hooks
 * are attached and the setting is read only when one of them fires, that is,
 * only at the checkout: reading it on `wp_loaded` would cost a query on every
 * request of a site that never saved the settings and has no persistent object
 * cache. With the filter, the filter wins and receives the hook of the setting;
 * returning an empty string renders nothing.
 */
function ayudawp_euw_register_consent_render() {

	if ( has_filter( 'ayudawp_euw_consent_hook' ) ) {

		/**
		 * Filters the checkout hook where the consent checkboxes are rendered.
		 *
		 * @param string $hook Hook name. Receives the hook of the location chosen in the
		 *                     settings (`woocommerce_review_order_before_submit` or
		 *                     `woocommerce_after_order_notes`). Empty string to render nothing.
		 */
		$hook = (string) apply_filters( 'ayudawp_euw_consent_hook', ayudawp_euw_consent_location_hook() );

		if ( '' === $hook ) {
			return;
		}

		/**
		 * Filters the priority of the consent render on its checkout hook.
		 *
		 * @param int    $priority Hook priority.
		 * @param string $hook     Hook the render is attached to.
		 */
		$priority = (int) apply_filters( 'ayudawp_euw_consent_hook_priority', 10, $hook );

		add_action( $hook, 'ayudawp_euw_checkout_render_consent_fields', $priority );

		return;
	}

	foreach ( array( 'woocommerce_review_order_before_submit', 'woocommerce_after_order_notes' ) as $hook ) {

		/** This filter is documented above. */
		$priority = (int) apply_filters( 'ayudawp_euw_consent_hook_priority', 10, $hook );

		add_action( $hook, 'ayudawp_euw_render_consent_at_location', $priority );
	}
}
add_action( 'wp_loaded', 'ayudawp_euw_register_consent_render' );

/**
 * Render the consent checkboxes only on the hook chosen in the settings.
 */
function ayudawp_euw_render_consent_at_location() {

	if ( current_action() === ayudawp_euw_consent_location_hook() ) {
		ayudawp_euw_checkout_render_consent_fields();
	}
}

/**
 * Reject the checkout submission when the mandatory Type A consent is missing.
 *
 * Hooked to `woocommerce_after_checkout_validation` rather than the earlier
 * `woocommerce_checkout_process`. Both fire during the classic checkout
 * submission, but express gateways that render their own button (WooCommerce
 * PayPal Payments and similar smart buttons) run their pre-popup form check on
 * `woocommerce_after_checkout_validation` only; `woocommerce_checkout_process`
 * fires later, when the order is created after the PayPal window is approved.
 * Validating here makes the missing-consent error block the PayPal popup from
 * opening at all, instead of letting the customer through PayPal and rejecting
 * the order on return (the confusing flow reported with PayPal Payments). The
 * native "Place order" button keeps working, because this hook runs on that
 * submission too.
 */
function ayudawp_euw_checkout_validate_consents() {

	foreach ( array( 'a', 'b' ) as $type ) {

		if ( ! ayudawp_euw_consent_applies( $type ) || ! ayudawp_euw_consent_is_required( $type ) ) {
			continue;
		}

		if ( empty( $_POST[ 'ayudawp_euw_consent_' . $type ] ) ) { // phpcs:ignore WordPress.Security.NonceVerification.Missing -- WooCommerce verifies the checkout nonce earlier on the same hook chain.

			wc_add_notice(
				esc_html( ayudawp_euw_consent_error_message( $type ) ),
				'error'
			);
		}
	}
}
add_action( 'woocommerce_after_checkout_validation', 'ayudawp_euw_checkout_validate_consents' );

/**
 * Persist consent metadata on the WC order in an HPOS-safe way.
 *
 * @param WC_Order $order WooCommerce order.
 * @param array    $data  Posted checkout data (unused).
 */
function ayudawp_euw_checkout_save_consents( $order, $data ) { // phpcs:ignore Generic.CodeAnalysis.UnusedFunctionParameter.Found

	if ( ! $order instanceof WC_Order ) {
		return;
	}

	$timestamp = gmdate( 'c' );
	$ip        = function_exists( 'ayudawp_euw_get_user_ip' ) ? ayudawp_euw_get_user_ip() : '';
	$ua        = isset( $_SERVER['HTTP_USER_AGENT'] )
		? sanitize_text_field( wp_unslash( $_SERVER['HTTP_USER_AGENT'] ) )
		: '';

	$types = array( 'a', 'b' );

	foreach ( $types as $type ) {

		if ( ! ayudawp_euw_consent_applies( $type ) ) {
			continue;
		}

		$accepted = ! empty( $_POST[ 'ayudawp_euw_consent_' . $type ] ) ? 'yes' : 'no'; // phpcs:ignore WordPress.Security.NonceVerification.Missing -- WooCommerce verified the checkout nonce earlier in the request lifecycle.

		$order->update_meta_data( '_ayudawp_euw_consent_' . $type . '_accepted', $accepted );
		$order->update_meta_data( '_ayudawp_euw_consent_' . $type . '_text', ayudawp_euw_get_consent_text( $type ) );
		$order->update_meta_data( '_ayudawp_euw_consent_' . $type . '_timestamp', $timestamp );
		$order->update_meta_data( '_ayudawp_euw_consent_' . $type . '_ip', $ip );
		$order->update_meta_data( '_ayudawp_euw_consent_' . $type . '_ua', $ua );
	}

	$order->save();
}
add_action( 'woocommerce_checkout_create_order', 'ayudawp_euw_checkout_save_consents', 10, 2 );

