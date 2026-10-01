<?php
/**
 * The harmonised guarantee notice at the WooCommerce checkout.
 *
 * Both checkouts land the notice in the same place, right above the button
 * that places the order, which is the "prominent place" the directive asks
 * for at the moment the consumer commits.
 *
 * Classic checkout uses `woocommerce_review_order_before_submit`. WooCommerce
 * repaints that panel on every `update_order_review` AJAX call, which is what
 * rules it out for the consent checkboxes (they hold state and would be reset),
 * but the notice holds no state: it is the same markup every time, so a repaint
 * is a no-op. Verified: it neither duplicates nor disappears when the customer
 * changes address, shipping or payment.
 *
 * Block checkout uses `render_block_woocommerce/checkout-actions-block`,
 * prepending the markup to the actions block. It survives because WooCommerce's
 * render-parent-block passes non-block HTML through html-react-parser and keeps
 * it. What does not survive is JavaScript: any listener attached to this markup
 * is dropped, which is why the notice is declarative (`<details>` and the native
 * popover attributes) and carries no script of its own.
 *
 * @package AyudaWP_EU_Withdrawal
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/*
 * Hooks are registered at require time, like the checkout-consent module: this
 * file loads before WooCommerce, so a `class_exists( 'WooCommerce' )` guard
 * around the whole file would skip every add_action() below. Each callback
 * checks what it needs when it runs.
 */

/**
 * The notice for this checkout request, or an empty string.
 *
 * Resolved once per request and reused by the two checkouts and by the order
 * note, which otherwise rebuilds the whole markup just to find out whether
 * anything was shown. The markup comes back unescaped and each of the two
 * callers below runs it through `wp_kses()` where it prints it, so the escaping
 * is visible at the point of output instead of hidden behind a helper.
 *
 * @return string Raw HTML, to be escaped by the caller.
 */
function ayudawp_euw_guarantee_checkout_html() {

	static $html = null;

	if ( null !== $html ) {
		return $html;
	}

	$html = '';

	if ( ! ayudawp_euw_guarantee_is_enabled() || 'none' === ayudawp_euw_guarantee_display_mode() ) {
		return $html;
	}

	if ( ! ayudawp_euw_guarantee_cart_has_goods() ) {
		return $html;
	}

	$html = ayudawp_euw_guarantee_notice_html();

	return $html;
}

/**
 * Print the notice above the "Place order" button of the classic checkout.
 */
function ayudawp_euw_guarantee_render_classic_checkout() {

	echo wp_kses( ayudawp_euw_guarantee_checkout_html(), ayudawp_euw_guarantee_allowed_html() );
}
add_action( 'woocommerce_review_order_before_submit', 'ayudawp_euw_guarantee_render_classic_checkout' );

/**
 * Prepend the notice to the actions block of the block checkout.
 *
 * @param string $block_content Rendered block HTML.
 * @return string
 */
function ayudawp_euw_guarantee_render_block_checkout( $block_content ) {

	return wp_kses( ayudawp_euw_guarantee_checkout_html(), ayudawp_euw_guarantee_allowed_html() ) . $block_content;
}
add_filter( 'render_block_woocommerce/checkout-actions-block', 'ayudawp_euw_guarantee_render_block_checkout' );

/**
 * Record on the order which notice the customer was shown at the checkout.
 *
 * Written from both checkouts: `woocommerce_checkout_order_created` for the
 * classic one and `woocommerce_store_api_checkout_order_processed` for the
 * block one. A meta flag guards the note, because a store can reach the Store
 * API from a classic page and fire both in the same request.
 *
 * @param WC_Order $order Order being created.
 */
function ayudawp_euw_guarantee_record_on_order( $order ) {

	if ( ! $order instanceof WC_Order ) {
		return;
	}

	if ( 'yes' !== get_option( 'ayudawp_euw_guarantee_order_note', 'yes' ) ) {
		return;
	}

	if ( '' === ayudawp_euw_guarantee_checkout_html() ) {
		return;
	}

	if ( $order->get_meta( '_ayudawp_euw_guarantee_shown' ) ) {
		return;
	}

	$lang = ayudawp_euw_guarantee_lang();
	$mode = ayudawp_euw_guarantee_display_mode();

	$order->update_meta_data( '_ayudawp_euw_guarantee_shown', $lang . '|' . $mode );

	$order->add_order_note(
		sprintf(
			/* translators: 1: two-letter language code, 2: display mode (full, details or popover). */
			__( 'EU harmonised legal guarantee notice shown at checkout (language: %1$s, mode: %2$s).', 'eu-withdrawal-compliance' ),
			strtoupper( $lang ),
			$mode
		)
	);

	$order->save();
}
add_action( 'woocommerce_checkout_order_created', 'ayudawp_euw_guarantee_record_on_order' );
add_action( 'woocommerce_store_api_checkout_order_processed', 'ayudawp_euw_guarantee_record_on_order' );
