<?php
/**
 * Conditional asset loading: only enqueues CSS where it is needed.
 *
 * @package AyudaWP_EU_Withdrawal
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Detect whether the current request shows the withdrawal form,
 * either via shortcode, the configured page, or the WC My Account endpoint.
 *
 * @return bool
 */
function ayudawp_euw_is_form_visible() {

	// Configured withdrawal page (resolved to the current language).
	$page_id = ayudawp_euw_get_page_id();

	if ( $page_id && is_page( $page_id ) ) {
		return true;
	}

	// Any post containing the shortcode.
	if ( is_singular() ) {

		$post = get_post();

		if ( $post && has_shortcode( $post->post_content, 'ayudawp_withdrawal_form' ) ) {
			return true;
		}
	}

	// WooCommerce My Account "withdrawal" endpoint.
	if ( function_exists( 'is_wc_endpoint_url' ) && is_wc_endpoint_url( 'withdrawal' ) ) {
		return true;
	}

	// Fallback: WC account page where the withdrawal query var is set.
	// is_wc_endpoint_url() can return false during early enqueue depending on
	// WC's query parsing order, so we double-check the global query vars.
	if ( function_exists( 'is_account_page' ) && is_account_page() ) {

		global $wp;

		if ( isset( $wp->query_vars['withdrawal'] ) ) {
			return true;
		}
	}

	return false;
}

/**
 * Enqueue the withdrawal form stylesheet.
 *
 * Shared by the page-level enqueue below and the `[ayudawp_withdrawal_form]`
 * shortcode, so the form is styled wherever it ends up being printed.
 */
function ayudawp_euw_enqueue_form_styles() {

	wp_enqueue_style(
		'ayudawp-euw-frontend',
		AYUDAWP_EUW_URL . 'assets/css/frontend.css',
		array(),
		AYUDAWP_EUW_VERSION
	);
}

/**
 * Enqueue frontend CSS only on the form page.
 */
function ayudawp_euw_enqueue_frontend() {

	if ( ! ayudawp_euw_is_form_visible() ) {
		return;
	}

	ayudawp_euw_enqueue_form_styles();
}
add_action( 'wp_enqueue_scripts', 'ayudawp_euw_enqueue_frontend' );

/**
 * Enqueue the excluded-product notice stylesheet (and its dashicons dependency).
 *
 * Shared by the automatic single-product enqueue and the
 * `[ayudawp_withdrawal_excluded_notice]` shortcode, so the notice is styled
 * wherever it ends up being printed. Dashicons is enqueued alongside because
 * the front-end does not load it by default and the notice uses a dashicon
 * glyph.
 *
 * Alongside, not as a dependency. Optimisation plugins that strip Dashicons
 * for logged-out visitors (DietPress, Perfmatters and others) deregister the
 * handle, and WordPress does not print a stylesheet whose dependency is
 * missing: the notice lost all its styles, not just the icon. Now only the
 * icon goes, and its <span> is empty and aria-hidden.
 */
function ayudawp_euw_enqueue_excluded_notice_styles() {

	wp_enqueue_style( 'dashicons' );

	wp_enqueue_style(
		'ayudawp-euw-excluded-notice',
		AYUDAWP_EUW_URL . 'assets/css/excluded-notice.css',
		array(),
		AYUDAWP_EUW_VERSION
	);
}

/**
 * Enqueue the excluded-product notice CSS where the notice can appear.
 *
 * Two cases are covered: the standard WooCommerce single product page (only
 * when that product is actually excluded, to avoid loading CSS for nothing),
 * and any singular content carrying the `[ayudawp_withdrawal_excluded_notice]`
 * shortcode, which is how page builders that bypass the single-product hook
 * place the notice. The product ID is resolved via the queried object instead
 * of the `$product` global because the global isn't populated yet when
 * `wp_enqueue_scripts` fires.
 */
function ayudawp_euw_enqueue_excluded_notice_assets() {

	if ( ! function_exists( 'ayudawp_euw_excluded_notice_is_enabled' )
		|| ! ayudawp_euw_excluded_notice_is_enabled()
	) {
		return;
	}

	if ( ! function_exists( 'wc_get_product' ) ) {
		return;
	}

	// Case 1 — standard single product page: only load when this product is
	// on an excluded status.
	if ( function_exists( 'is_product' ) && is_product() ) {

		$product_id = (int) get_queried_object_id();

		if ( $product_id
			&& function_exists( 'ayudawp_euw_is_product_excluded' )
			&& ayudawp_euw_is_product_excluded( $product_id )
		) {
			ayudawp_euw_enqueue_excluded_notice_styles();
		}

		return;
	}

	// Case 2 — any singular content that drops the shortcode into its body
	// (a plain page, or a builder that stores the shortcode in post_content).
	if ( is_singular() ) {

		$post = get_post();

		if ( $post && has_shortcode( $post->post_content, 'ayudawp_withdrawal_excluded_notice' ) ) {
			ayudawp_euw_enqueue_excluded_notice_styles();
		}
	}
}
add_action( 'wp_enqueue_scripts', 'ayudawp_euw_enqueue_excluded_notice_assets' );

/**
 * Enqueue admin CSS on the withdrawal CPT screens and on the settings page.
 *
 * @param string $hook Current admin page hook.
 */
function ayudawp_euw_enqueue_admin( $hook ) { // phpcs:ignore Generic.CodeAnalysis.UnusedFunctionParameter.Found

	$screen = function_exists( 'get_current_screen' ) ? get_current_screen() : null;

	if ( ! $screen ) {
		return;
	}

	$is_cpt_screen      = ( 'ayudawp_withdrawal' === $screen->post_type );
	$is_settings_screen = ( false !== strpos( (string) $screen->id, 'ayudawp-euw-settings' ) );

	// WC orders list (legacy and HPOS): we paint the withdrawal status badge
	// in the "Withdrawal" column there too, so the same admin CSS is needed.
	$screen_id    = (string) $screen->id;
	$is_wc_orders = ( 'edit-shop_order' === $screen_id || 'woocommerce_page_wc-orders' === $screen_id );

	if ( ! $is_cpt_screen && ! $is_settings_screen && ! $is_wc_orders ) {
		return;
	}

	wp_enqueue_style(
		'ayudawp-euw-admin',
		AYUDAWP_EUW_URL . 'assets/css/admin.css',
		array(),
		AYUDAWP_EUW_VERSION
	);

	// The settings page is the only screen with a media picker: the one that
	// swaps the bundled guarantee notice of a language for a file of your own.
	if ( $is_settings_screen ) {

		wp_enqueue_media();

		// The Scheduled Sales recommendation opens its install screen in a modal
		// (ayudawp_euw_render_scheduled_sales_notice()), which only links there
		// when it is missing and the reader can install plugins.
		if ( class_exists( 'WooCommerce' ) && ! defined( 'MSPS_VERSION' ) && current_user_can( 'install_plugins' ) ) {
			add_thickbox();
		}

		wp_enqueue_script(
			'ayudawp-euw-guarantee-admin',
			AYUDAWP_EUW_URL . 'assets/js/guarantee-admin.js',
			array( 'jquery' ),
			AYUDAWP_EUW_VERSION,
			true
		);

		// Tabs, folded help and badges of the settings page.
		wp_enqueue_script(
			'ayudawp-euw-settings-tabs',
			AYUDAWP_EUW_URL . 'assets/js/settings-tabs.js',
			array(),
			AYUDAWP_EUW_VERSION,
			true
		);

		wp_localize_script(
			'ayudawp-euw-settings-tabs',
			'ayudawpEuwSettings',
			array(
				'moreInfo' => __( 'More information', 'eu-withdrawal-compliance' ),
			)
		);

		wp_localize_script(
			'ayudawp-euw-guarantee-admin',
			'ayudawpEuwGuarantee',
			array(
				'chooseTitle'  => __( 'Choose the guarantee notice image', 'eu-withdrawal-compliance' ),
				'chooseButton' => __( 'Use this image', 'eu-withdrawal-compliance' ),
				'bundled'      => __( 'Bundled official file', 'eu-withdrawal-compliance' ),
			)
		);
	}
}
add_action( 'admin_enqueue_scripts', 'ayudawp_euw_enqueue_admin' );

/**
 * Enqueue the guarantee-notice stylesheet where the notice can appear.
 *
 * Two cases, both decided here at `wp_enqueue_scripts` time: the checkout page
 * (the same page whether it renders the classic shortcode or the Checkout
 * block, so one check covers both), and any singular content carrying the
 * `[ayudawp_guarantee_notice]` shortcode, which is how the shop's own "Legal
 * guarantee" page shows it.
 *
 * The block checkout resolves the notice later, inside `render_block`, which
 * runs after this hook, so the stylesheet cannot wait for the markup to exist.
 */
function ayudawp_euw_enqueue_guarantee_assets() {

	if ( ! function_exists( 'ayudawp_euw_guarantee_is_enabled' ) || ! ayudawp_euw_guarantee_is_enabled() ) {
		return;
	}

	$needed = false;

	if ( function_exists( 'is_checkout' ) && is_checkout()
		&& ! ( function_exists( 'is_order_received_page' ) && is_order_received_page() )
		&& 'none' !== ayudawp_euw_guarantee_display_mode()
		&& ayudawp_euw_guarantee_cart_has_goods()
	) {
		$needed = true;
	}

	if ( ! $needed && is_singular() ) {

		$post = get_post();

		if ( $post && has_shortcode( $post->post_content, 'ayudawp_guarantee_notice' ) ) {
			$needed = true;
		}
	}

	if ( ! $needed ) {
		return;
	}

	ayudawp_euw_enqueue_guarantee_styles();
}
add_action( 'wp_enqueue_scripts', 'ayudawp_euw_enqueue_guarantee_assets' );

/**
 * Enqueue the guarantee-notice stylesheet.
 *
 * Shared by the enqueue above and the `[ayudawp_guarantee_notice]` shortcode.
 * The check above only sees the shortcode in the post content, so a notice
 * placed in a synced pattern, a block theme template, a widget or a page
 * builder layout came out unstyled and full width: the shortcode enqueues it
 * too when it prints, the same way the excluded-product notice does.
 */
function ayudawp_euw_enqueue_guarantee_styles() {

	wp_enqueue_style(
		'ayudawp-euw-guarantee',
		AYUDAWP_EUW_URL . 'assets/css/guarantee.css',
		array(),
		AYUDAWP_EUW_VERSION
	);
}

/**
 * Enqueue the script that keeps the checkout consent checkboxes ticked.
 *
 * Loaded only on the checkout form (not on the order received or order pay
 * screens, which have no order review refresh) and only when at least one
 * consent applies, because WooCommerce re-renders the order review by AJAX and
 * the script puts back what the customer had ticked.
 */
function ayudawp_euw_enqueue_checkout_consent_assets() {

	if ( ! function_exists( 'is_checkout' ) || ! is_checkout() ) {
		return;
	}

	if ( ( function_exists( 'is_order_received_page' ) && is_order_received_page() )
		|| ( function_exists( 'is_wc_endpoint_url' ) && is_wc_endpoint_url( 'order-pay' ) ) ) {
		return;
	}

	if ( ! function_exists( 'ayudawp_euw_consent_applies' )
		|| ( ! ayudawp_euw_consent_applies( 'a' ) && ! ayudawp_euw_consent_applies( 'b' ) ) ) {
		return;
	}

	wp_enqueue_script(
		'ayudawp-euw-checkout-consent',
		AYUDAWP_EUW_URL . 'assets/js/checkout-consent.js',
		array( 'jquery' ),
		AYUDAWP_EUW_VERSION,
		true
	);
}
add_action( 'wp_enqueue_scripts', 'ayudawp_euw_enqueue_checkout_consent_assets' );
