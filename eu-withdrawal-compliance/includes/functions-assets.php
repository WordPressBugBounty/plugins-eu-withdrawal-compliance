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
 * Enqueue frontend CSS only on the form page.
 */
function ayudawp_euw_enqueue_frontend() {

	if ( ! ayudawp_euw_is_form_visible() ) {
		return;
	}

	wp_enqueue_style(
		'ayudawp-euw-frontend',
		AYUDAWP_EUW_URL . 'assets/css/frontend.css',
		array(),
		AYUDAWP_EUW_VERSION
	);
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
 */
function ayudawp_euw_enqueue_excluded_notice_styles() {

	wp_enqueue_style( 'dashicons' );

	wp_enqueue_style(
		'ayudawp-euw-excluded-notice',
		AYUDAWP_EUW_URL . 'assets/css/excluded-notice.css',
		array( 'dashicons' ),
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
 * Also loads Thickbox on the settings page so the promo banner can open the
 * "plugin information" modals when the user clicks an Install button.
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

	// Thickbox is only needed on the settings page where the promo banner lives.
	if ( $is_settings_screen ) {
		add_thickbox();
	}
}
add_action( 'admin_enqueue_scripts', 'ayudawp_euw_enqueue_admin' );
