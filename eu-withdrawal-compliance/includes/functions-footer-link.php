<?php
/**
 * Shortcode to surface the withdrawal page link anywhere on the site.
 *
 * Article 11a introduced by Directive 2023/2673 requires the online
 * withdrawal function to be clearly identifiable and accessible throughout
 * the withdrawal period. In practice that means a permanent link in the
 * footer or an equally visible spot. This shortcode is a tiny helper for
 * cases where editing the menu/template is not the most convenient route;
 * a dedicated widget and Gutenberg block are planned for v1.6.0.
 *
 * @package AyudaWP_EU_Withdrawal
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Render the `[ayudawp_withdrawal_link]` shortcode.
 *
 * Returns an empty string if no withdrawal page is configured, so the
 * shortcode never breaks a layout when used speculatively.
 *
 * @param array $atts Shortcode attributes.
 * @return string
 */
function ayudawp_euw_withdrawal_link_shortcode( $atts ) {

	$atts = shortcode_atts(
		array(
			'text'  => '',
			'class' => '',
		),
		$atts,
		'ayudawp_withdrawal_link'
	);

	$page_id = ayudawp_euw_get_page_id();

	if ( ! $page_id || ! get_post( $page_id ) ) {
		return '';
	}

	$url = get_permalink( $page_id );

	if ( ! $url ) {
		return '';
	}

	$text = '' !== trim( (string) $atts['text'] )
		? (string) $atts['text']
		: __( 'Right of withdrawal', 'eu-withdrawal-compliance' );

	$classes = trim( 'ayudawp-euw-withdrawal-link ' . sanitize_html_class( (string) $atts['class'], '' ) );

	return sprintf(
		'<a href="%1$s" class="%2$s" rel="noopener nofollow">%3$s</a>',
		esc_url( $url ),
		esc_attr( $classes ),
		esc_html( $text )
	);
}
add_shortcode( 'ayudawp_withdrawal_link', 'ayudawp_euw_withdrawal_link_shortcode' );
