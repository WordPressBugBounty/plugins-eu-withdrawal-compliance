<?php
/**
 * Harmonised EU notice on the legal guarantee of conformity.
 *
 * Implements the notice that Article 22a of Directive 2011/83/EU (added by
 * Directive (EU) 2024/825) makes mandatory, with the design fixed by
 * Implementing Regulation (EU) 2025/1960 and applicable from 27 September 2026.
 * Traders selling goods to consumers in the EU have to display the official
 * notice in colour, in full, unedited, legible at default display size, in a
 * prominent place and with a clickable link to the same destination as its QR
 * code. It does not apply to B2B sales, nor to digital content or services,
 * which is why every entry point here is gated by "the cart or the order holds
 * at least one non-virtual product".
 *
 * This file is the core: language resolution, where each official file lives,
 * the markup of the three display modes and the Spanish three-year note. The
 * checkout and the emails live in their own files and read from here.
 *
 * The official files are bundled under `assets/guarantee-notice/`, byte for
 * byte as the Commission publishes them (see CHECKSUMS.txt there), because the
 * regulation does not allow editing them. The one exception is the English PNG,
 * rendered from the official English PDF at the same size as the other 23,
 * since the Commission's PNG package ships no English version.
 *
 * @package AyudaWP_EU_Withdrawal
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Two-letter codes of the languages the Commission publishes the notice in.
 *
 * The 24 official EU languages. Kept as a literal list instead of scanning the
 * directory on every request: the set only changes when the Commission
 * publishes a new one, and a directory listing on each page load to learn
 * something that never changes is exactly the kind of I/O this plugin avoids.
 *
 * @return array<int, string>
 */
function ayudawp_euw_guarantee_languages() {

	return array(
		'bg',
		'cs',
		'da',
		'de',
		'el',
		'en',
		'es',
		'et',
		'fi',
		'fr',
		'ga',
		'hr',
		'hu',
		'it',
		'lt',
		'lv',
		'mt',
		'nl',
		'pl',
		'pt',
		'ro',
		'sk',
		'sl',
		'sv',
	);
}

/**
 * Map a language with no official notice onto the one its speakers are served.
 *
 * Catalan, Basque and Galician have no official file, and neither do Welsh or
 * Luxembourgish, so a shop in those languages would otherwise get nothing. Each
 * one falls back to an official language of the same member state, which is the
 * one its consumers can act on. The list is deliberately short and explicit:
 * anything not in it goes through the configured fallback instead.
 *
 * @return array<string, string> Language code => language code of the notice shown.
 */
function ayudawp_euw_guarantee_language_aliases() {

	$aliases = array(
		// Spain.
		'ca'  => 'es',
		'eu'  => 'es',
		'gl'  => 'es',
		'ast' => 'es',
		'an'  => 'es',
		// Other member states.
		'lb'  => 'fr',
		'cy'  => 'en',
		'gd'  => 'en',
		'fy'  => 'nl',
		'co'  => 'fr',
		'oc'  => 'fr',
		'br'  => 'fr',
		'rm'  => 'de',
		'sc'  => 'it',
		'fur' => 'it',
		'lij' => 'it',
		'szl' => 'pl',
		'ksh' => 'de',
		'bar' => 'de',
		'hsb' => 'de',
		'dsb' => 'de',
		'me'  => 'hr',
		'bs'  => 'hr',
		'sr'  => 'hr',
		'nb'  => 'da',
		'nn'  => 'da',
		'is'  => 'da',
	);

	/**
	 * Filters the map of languages with no official notice onto the one shown instead.
	 *
	 * Keys and values are two-letter language codes. A value that is not one of
	 * the 24 official languages is ignored.
	 *
	 * @param array<string, string> $aliases Language code => language code shown.
	 */
	return (array) apply_filters( 'ayudawp_euw_guarantee_language_aliases', $aliases );
}

/**
 * Language of the notice served for a given locale.
 *
 * Resolution order: the two-letter language of the locale when the Commission
 * publishes that language, then the alias map above, then the configured
 * fallback, then English. Pass nothing to resolve the locale of the current
 * request, which inside a WooCommerce email is the language of the order:
 * WooCommerce Multilingual and Polylang for WooCommerce switch the locale
 * before rendering it, the same reason the withdrawal notice already follows
 * the order language there.
 *
 * @param string $locale Optional locale. Defaults to the locale of this request.
 * @return string One of the 24 official language codes.
 */
function ayudawp_euw_guarantee_lang( $locale = '' ) {

	$locale = '' !== (string) $locale ? (string) $locale : determine_locale();
	$lang   = strtolower( substr( $locale, 0, 2 ) );

	$available = ayudawp_euw_guarantee_languages();

	if ( in_array( $lang, $available, true ) ) {
		return $lang;
	}

	$aliases = ayudawp_euw_guarantee_language_aliases();

	if ( isset( $aliases[ $lang ] ) && in_array( $aliases[ $lang ], $available, true ) ) {
		return $aliases[ $lang ];
	}

	$fallback = strtolower( (string) get_option( 'ayudawp_euw_guarantee_fallback_lang', 'en' ) );

	/**
	 * Filters the language used when the visitor's own has no official notice.
	 *
	 * @param string $fallback Two-letter language code from settings.
	 * @param string $locale   Locale being resolved.
	 */
	$fallback = (string) apply_filters( 'ayudawp_euw_guarantee_fallback_lang', $fallback, $locale );

	return in_array( $fallback, $available, true ) ? $fallback : 'en';
}

/**
 * Destination of the QR code printed on the official notice.
 *
 * Decoded from the official QR codes: every language points at the guarantees
 * page of Your Europe in that language. The notice also prints the address in
 * text ("europa.eu/youreurope/garantías" in Spanish), which redirects to the
 * same page, and the regulation asks for a clickable link to that destination.
 *
 * @param string $lang Two-letter language code.
 * @return string
 */
function ayudawp_euw_guarantee_youreurope_url( $lang ) {

	$lang = sanitize_key( (string) $lang );
	$lang = in_array( $lang, ayudawp_euw_guarantee_languages(), true ) ? $lang : 'en';

	return 'https://europa.eu/youreurope/citizens/consumers/shopping/guarantees-returns/index_' . $lang . '.htm';
}

/**
 * Attachment ID of the media-library file replacing a bundled notice, if any.
 *
 * Lets a shop serve its own copy of the official file (for example one hosted
 * on its CDN, or a newer revision published by the Commission before this
 * plugin ships it). Anything that is not a real image attachment is ignored, so
 * a stale ID falls back to the bundled file instead of printing nothing.
 *
 * @param string $lang Two-letter language code.
 * @return int Attachment ID, or 0 when there is no usable override.
 */
function ayudawp_euw_guarantee_custom_image_id( $lang ) {

	$lang   = sanitize_key( (string) $lang );
	$custom = get_option( 'ayudawp_euw_guarantee_custom_files', array() );
	$id     = ( is_array( $custom ) && isset( $custom[ $lang ] ) ) ? absint( $custom[ $lang ] ) : 0;

	/**
	 * Filters the media-library attachment used instead of the bundled notice.
	 *
	 * @param int    $id   Attachment ID from settings, or 0.
	 * @param string $lang Two-letter language code.
	 */
	$id = absint( apply_filters( 'ayudawp_euw_guarantee_image_id', $id, $lang ) );

	if ( ! $id || ! wp_attachment_is_image( $id ) ) {
		return 0;
	}

	return $id;
}

/**
 * URL and pixel size of the notice image for a language.
 *
 * Returns the media-library override when one is configured and usable, and the
 * bundled official file otherwise. The bundled files are all 1654x2339, so the
 * dimensions are constants rather than a getimagesize() call on every render:
 * they let the browser reserve the space before the image arrives, which is
 * what keeps the "Place order" button from jumping.
 *
 * @param string $lang Two-letter language code.
 * @return array{url: string, width: int, height: int} Empty url when nothing can be served.
 */
function ayudawp_euw_guarantee_image( $lang ) {

	$empty = array(
		'url'    => '',
		'width'  => 0,
		'height' => 0,
	);

	$lang = sanitize_key( (string) $lang );

	if ( ! in_array( $lang, ayudawp_euw_guarantee_languages(), true ) ) {
		return $empty;
	}

	$custom_id = ayudawp_euw_guarantee_custom_image_id( $lang );

	if ( $custom_id ) {

		$url = wp_get_attachment_url( $custom_id );

		if ( $url ) {

			$meta = wp_get_attachment_metadata( $custom_id );

			return array(
				'url'    => $url,
				'width'  => isset( $meta['width'] ) ? (int) $meta['width'] : 0,
				'height' => isset( $meta['height'] ) ? (int) $meta['height'] : 0,
			);
		}
	}

	if ( ! file_exists( AYUDAWP_EUW_DIR . 'assets/guarantee-notice/notice-' . $lang . '.png' ) ) {
		return $empty;
	}

	return array(
		'url'    => AYUDAWP_EUW_URL . 'assets/guarantee-notice/notice-' . $lang . '.png',
		'width'  => 1654,
		'height' => 2339,
	);
}

/**
 * Absolute path of the official PDF for a language, ready to attach.
 *
 * Only ever returns a path inside the plugin's own notice directory, resolved
 * with realpath() and checked against that directory: the value ends up in
 * `woocommerce_email_attachments`, so anything that could be steered elsewhere
 * would be a way to mail an arbitrary server file to a customer.
 *
 * @param string $lang Two-letter language code.
 * @return string Absolute path, or an empty string when there is none.
 */
function ayudawp_euw_guarantee_pdf_path( $lang ) {

	$lang = sanitize_key( (string) $lang );

	if ( ! in_array( $lang, ayudawp_euw_guarantee_languages(), true ) ) {
		return '';
	}

	$base = realpath( AYUDAWP_EUW_DIR . 'assets/guarantee-notice' );
	$path = realpath( AYUDAWP_EUW_DIR . 'assets/guarantee-notice/notice-' . $lang . '.pdf' );

	if ( ! $base || ! $path || 0 !== strpos( $path, $base . DIRECTORY_SEPARATOR ) ) {
		return '';
	}

	return is_readable( $path ) ? $path : '';
}

/**
 * Whether the module is switched on.
 *
 * @return bool
 */
function ayudawp_euw_guarantee_is_enabled() {

	return 'yes' === get_option( 'ayudawp_euw_guarantee_enabled', 'no' );
}

/**
 * Display mode of the notice at the checkout.
 *
 * 'full' is the default on purpose. The Commission guidelines accept showing
 * the notice behind a first click, but the regulation itself only describes the
 * nested format for the GARAN label, not for the notice, and that reading is
 * contested. Showing it in full is the reading nobody disputes; the other two
 * modes exist for layouts where a full-width notice would be rendered illegibly
 * small, which is its own compliance problem. 'none' leaves the checkout alone
 * for shops that place the notice themselves, and does not affect the emails,
 * the shortcode or the footer link.
 *
 * @return string One of 'full', 'details', 'popover' or 'none'.
 */
function ayudawp_euw_guarantee_display_mode() {

	$mode = (string) get_option( 'ayudawp_euw_guarantee_display', 'full' );

	return in_array( $mode, array( 'full', 'details', 'popover', 'none' ), true ) ? $mode : 'full';
}

/**
 * Whether the Spanish three-year note applies to this shop.
 *
 * The notice says "minimum two years" because that is the EU floor, and it
 * cannot be edited. In Spain the legal guarantee for new goods is three years
 * from delivery (Art. 120.1 TRLGDCU), so the shorter figure printed on the
 * notice would understate what the consumer is entitled to. The note is shown
 * next to the notice, never inside it. Second-hand goods can be sold with a
 * shorter period, never under a year, and the notice already says so.
 *
 * Automatic when the WooCommerce base country is Spain, and forceable either
 * way for shops selling into Spain from elsewhere.
 *
 * @return bool
 */
function ayudawp_euw_guarantee_note_applies() {

	$setting = (string) get_option( 'ayudawp_euw_guarantee_es_note', 'auto' );

	if ( 'yes' === $setting ) {
		return true;
	}

	if ( 'no' === $setting ) {
		return false;
	}

	if ( ! function_exists( 'WC' ) || ! WC() || ! isset( WC()->countries ) || ! WC()->countries ) {
		return false;
	}

	return 'ES' === WC()->countries->get_base_country();
}

/**
 * The Spanish three-year note, as plain text or as HTML with the terms link.
 *
 * @param bool $link Whether to append the link to the terms and conditions page.
 * @return string Escaped HTML when $link is true, plain text otherwise. Empty when it does not apply.
 */
function ayudawp_euw_guarantee_note( $link = true ) {

	if ( ! ayudawp_euw_guarantee_note_applies() ) {
		return '';
	}

	$text = __( 'In Spain the legal guarantee on new goods is three years from delivery.', 'eu-withdrawal-compliance' );

	if ( ! $link ) {
		return $text;
	}

	$html     = esc_html( $text );
	$terms_id = function_exists( 'wc_terms_and_conditions_page_id' ) ? (int) wc_terms_and_conditions_page_id() : 0;

	if ( ! $terms_id || ! get_post( $terms_id ) ) {
		return $html;
	}

	$url    = (string) get_permalink( $terms_id );
	$anchor = sanitize_title( (string) get_option( 'ayudawp_euw_guarantee_terms_anchor', '' ) );

	if ( '' === $url ) {
		return $html;
	}

	if ( '' !== $anchor ) {
		$url .= '#' . $anchor;
	}

	return $html . ' ' . sprintf(
		'<a href="%1$s" target="_blank" rel="noopener nofollow">%2$s</a>',
		esc_url( $url ),
		esc_html__( 'Read the full terms', 'eu-withdrawal-compliance' )
	);
}

/**
 * HTML allowed in the notice: post content plus the native popover attributes.
 *
 * `wp_kses_post()` is not enough for the popover mode. WordPress 7.1 allows
 * `popover` on div and `popovertarget` / `popovertargetaction` on button, but
 * 6.0, 6.5 and 6.8 allow none of them (checked in their own kses.php), and this
 * plugin supports 6.0 upwards; on top of that, no version allows `autofocus` on
 * a button or `decoding` on an image. Stripped, the button stops opening
 * anything and the notice silently disappears behind a control that does
 * nothing, so the markup goes through `wp_kses()` with this list instead.
 *
 * The additions are attributes on elements the post list already allows. No new
 * element is opened up.
 *
 * @return array<string, array<string, bool>>
 */
function ayudawp_euw_guarantee_allowed_html() {

	$allowed = wp_kses_allowed_html( 'post' );

	if ( ! isset( $allowed['div'] ) || ! is_array( $allowed['div'] ) ) {
		$allowed['div'] = array();
	}

	if ( ! isset( $allowed['button'] ) || ! is_array( $allowed['button'] ) ) {
		$allowed['button'] = array();
	}

	if ( ! isset( $allowed['img'] ) || ! is_array( $allowed['img'] ) ) {
		$allowed['img'] = array();
	}

	$allowed['div']['popover']                = true;
	$allowed['button']['type']                = true;
	$allowed['button']['popovertarget']       = true;
	$allowed['button']['popovertargetaction'] = true;
	$allowed['button']['autofocus']           = true;
	$allowed['img']['decoding']               = true;

	return $allowed;
}

/**
 * The official notice itself: figure, image linked to the full-size file, caption.
 *
 * The image links to its own file because at phone width the notice renders
 * around 330px wide, where the small print stops being readable; opening the
 * file is how the consumer enlarges it. The caption carries the clickable link
 * to the same destination as the QR code, which the regulation requires.
 *
 * @param string $lang Two-letter language code.
 * @return string Raw HTML, or an empty string when the file is missing.
 */
function ayudawp_euw_guarantee_figure_html( $lang ) {

	$image = ayudawp_euw_guarantee_image( $lang );

	if ( '' === $image['url'] ) {
		return '';
	}

	$dimensions = '';

	if ( $image['width'] > 0 && $image['height'] > 0 ) {
		$dimensions = sprintf( ' width="%1$d" height="%2$d"', (int) $image['width'], (int) $image['height'] );
	}

	return sprintf(
		'<figure class="ayudawp-euw-guarantee__notice"><a href="%1$s" target="_blank" rel="noopener nofollow"><img src="%1$s"%2$s alt="%3$s" loading="lazy" decoding="async"></a><figcaption><a href="%4$s" target="_blank" rel="noopener nofollow">%5$s</a></figcaption></figure>',
		esc_url( $image['url'] ),
		$dimensions,
		esc_attr__( 'European Union harmonised notice on the legal guarantee of conformity', 'eu-withdrawal-compliance' ),
		esc_url( ayudawp_euw_guarantee_youreurope_url( $lang ) ),
		esc_html__( 'More about your guarantee rights on the European Commission website', 'eu-withdrawal-compliance' )
	);
}

/**
 * Label of the control that opens the notice in the two collapsed modes.
 *
 * @return string
 */
function ayudawp_euw_guarantee_toggle_label() {

	return __( 'Check your legal guarantee rights', 'eu-withdrawal-compliance' );
}

/**
 * Full notice markup for the front-end, in the configured display mode.
 *
 * Deliberately declarative: no JavaScript of our own and no event listeners.
 * At the block checkout this HTML is handed to html-react-parser by
 * WooCommerce's render-parent-block, which keeps the markup but drops any
 * listener attached to it, so the disclosure and the popover are built with
 * `<details>` and the native popover attributes, which survive the trip.
 *
 * @param string $mode Optional display mode override ('full', 'details', 'popover').
 * @return string Raw HTML, escaped by the caller with ayudawp_euw_guarantee_allowed_html().
 */
function ayudawp_euw_guarantee_notice_html( $mode = '' ) {

	static $instance = 0;

	$lang   = ayudawp_euw_guarantee_lang();
	$figure = ayudawp_euw_guarantee_figure_html( $lang );

	if ( '' === $figure ) {
		return '';
	}

	$mode = in_array( $mode, array( 'full', 'details', 'popover' ), true ) ? $mode : ayudawp_euw_guarantee_display_mode();

	if ( 'none' === $mode ) {
		return '';
	}

	if ( 'details' === $mode ) {

		$figure = sprintf(
			'<details class="ayudawp-euw-guarantee__disclosure"><summary>%1$s</summary>%2$s</details>',
			esc_html( ayudawp_euw_guarantee_toggle_label() ),
			$figure
		);

	} elseif ( 'popover' === $mode ) {

		++$instance;
		$id = 'ayudawp-euw-guarantee-notice-' . $instance;

		$figure = sprintf(
			'<button type="button" class="ayudawp-euw-guarantee__open" popovertarget="%1$s">%2$s</button><div id="%1$s" class="ayudawp-euw-guarantee__popover" popover><button type="button" class="ayudawp-euw-guarantee__close" popovertarget="%1$s" popovertargetaction="hide" autofocus>%3$s</button>%4$s</div>',
			esc_attr( $id ),
			esc_html( ayudawp_euw_guarantee_toggle_label() ),
			esc_html__( 'Close', 'eu-withdrawal-compliance' ),
			$figure
		);
	}

	$note = ayudawp_euw_guarantee_note();

	$html = sprintf(
		'<div class="ayudawp-euw-guarantee ayudawp-euw-guarantee--%1$s">%2$s%3$s</div>',
		esc_attr( $mode ),
		$figure,
		'' !== $note ? '<p class="ayudawp-euw-guarantee__note">' . $note . '</p>' : ''
	);

	/**
	 * Filters the markup of the harmonised guarantee notice.
	 *
	 * Whatever is returned is escaped afterwards with the allowed-HTML list of
	 * `ayudawp_euw_guarantee_allowed_html()`, so anything outside it is dropped.
	 * The official image must not be edited, cropped or altered.
	 *
	 * @param string $html Notice markup.
	 * @param string $lang Two-letter language code of the notice shown.
	 * @param string $mode Display mode in use.
	 */
	return (string) apply_filters( 'ayudawp_euw_guarantee_notice_html', $html, $lang, $mode );
}

/**
 * The notice, escaped and ready to print.
 *
 * @param string $mode Optional display mode override.
 * @return string
 */
function ayudawp_euw_guarantee_get_safe_html( $mode = '' ) {

	$html = ayudawp_euw_guarantee_notice_html( $mode );

	return '' !== $html ? wp_kses( $html, ayudawp_euw_guarantee_allowed_html() ) : '';
}

/**
 * Whether the current cart holds at least one physical product.
 *
 * The notice is about goods: it does not apply to digital content or services,
 * so a cart with nothing but virtual products gets none. Reads the product
 * object already loaded in the cart item instead of fetching it again.
 *
 * @return bool
 */
function ayudawp_euw_guarantee_cart_has_goods() {

	if ( ! function_exists( 'WC' ) || ! WC() || ! WC()->cart ) {
		return false;
	}

	foreach ( WC()->cart->get_cart() as $cart_item ) {

		if ( isset( $cart_item['data'] ) && $cart_item['data'] instanceof WC_Product && ! $cart_item['data']->is_virtual() ) {
			return true;
		}
	}

	return false;
}

/**
 * Whether an order holds at least one physical product.
 *
 * A line whose product no longer exists (deleted from the catalogue after the
 * sale) counts as goods: the alternative is dropping the notice from an order
 * that did contain them.
 *
 * @param WC_Order $order Order.
 * @return bool
 */
function ayudawp_euw_guarantee_order_has_goods( $order ) {

	if ( ! $order instanceof WC_Order ) {
		return false;
	}

	foreach ( $order->get_items() as $item ) {

		$product = is_callable( array( $item, 'get_product' ) ) ? $item->get_product() : null;

		if ( ! $product instanceof WC_Product ) {
			return true;
		}

		if ( ! $product->is_virtual() ) {
			return true;
		}
	}

	return false;
}

/**
 * Render the `[ayudawp_guarantee_notice]` shortcode.
 *
 * For the shop's own "Legal guarantee" page, where the notice is shown in full
 * regardless of the checkout display mode unless the `mode` attribute says
 * otherwise. Unlike the checkout, it is not gated by the cart: the page exists
 * to inform, and a shop that sells goods has goods to inform about.
 *
 * @param array $atts Shortcode attributes.
 * @return string
 */
function ayudawp_euw_guarantee_notice_shortcode( $atts ) {

	$atts = shortcode_atts(
		array(
			'mode' => 'full',
		),
		$atts,
		'ayudawp_guarantee_notice'
	);

	if ( ! ayudawp_euw_guarantee_is_enabled() ) {
		return '';
	}

	return ayudawp_euw_guarantee_get_safe_html( (string) $atts['mode'] );
}
add_shortcode( 'ayudawp_guarantee_notice', 'ayudawp_euw_guarantee_notice_shortcode' );

/**
 * Native name of each language the notice exists in.
 *
 * Native names on purpose, the way WordPress lists locales in its own language
 * selector: they need no translation and every reader recognises their own.
 *
 * @return array<string, string> Language code => native name.
 */
function ayudawp_euw_guarantee_language_names() {

	return array(
		'bg' => 'Български',
		'cs' => 'Čeština',
		'da' => 'Dansk',
		'de' => 'Deutsch',
		'el' => 'Ελληνικά',
		'en' => 'English',
		'es' => 'Español',
		'et' => 'Eesti',
		'fi' => 'Suomi',
		'fr' => 'Français',
		'ga' => 'Gaeilge',
		'hr' => 'Hrvatski',
		'hu' => 'Magyar',
		'it' => 'Italiano',
		'lt' => 'Lietuvių',
		'lv' => 'Latviešu',
		'mt' => 'Malti',
		'nl' => 'Nederlands',
		'pl' => 'Polski',
		'pt' => 'Português',
		'ro' => 'Română',
		'sk' => 'Slovenčina',
		'sl' => 'Slovenščina',
		'sv' => 'Svenska',
	);
}

/**
 * Languages this site can actually serve the notice in.
 *
 * The site language, plus every language configured in Polylang or WPML,
 * resolved through the same rules the front-end uses (so a Catalan site shows
 * up here as Spanish, which is the notice its visitors get). Used by the
 * settings screen, which would otherwise list 24 languages to a shop that only
 * ever serves one.
 *
 * @return array<int, string> Language codes, without duplicates.
 */
function ayudawp_euw_guarantee_site_languages() {

	$locales = array( get_locale() );

	if ( function_exists( 'pll_languages_list' ) ) {

		$list = pll_languages_list( array( 'fields' => 'locale' ) );

		if ( is_array( $list ) ) {
			$locales = array_merge( $locales, $list );
		}
	}

	if ( has_filter( 'wpml_active_languages' ) ) {

		$list = apply_filters( 'wpml_active_languages', null, array( 'skip_missing' => 0 ) ); // phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedHooknameFound -- Consuming WPML's own documented filter, not a hook defined by this plugin.

		if ( is_array( $list ) ) {
			foreach ( $list as $language ) {
				if ( isset( $language['default_locale'] ) ) {
					$locales[] = (string) $language['default_locale'];
				} elseif ( isset( $language['language_code'] ) ) {
					$locales[] = (string) $language['language_code'];
				}
			}
		}
	}

	$languages = array();

	foreach ( $locales as $locale ) {

		$lang = ayudawp_euw_guarantee_lang( (string) $locale );

		$languages[ $lang ] = $lang;
	}

	return array_values( $languages );
}

/**
 * URL of the settings page, landing on the guarantee-notice section.
 *
 * The settings page carries eleven sections, so a link to its top leaves the
 * reader hunting for the one the notice is talking about. The anchor is printed
 * by the section callback.
 *
 * @return string
 */
function ayudawp_euw_guarantee_settings_url() {

	return ayudawp_euw_get_settings_url() . '#ayudawp-euw-guarantee';
}

/**
 * Announce the module once on shops updating from an earlier version.
 *
 * The notice module arrives switched off on an existing shop, so somebody has
 * to be told it exists, and told before 27 September 2026 rather than after.
 * The notice is dismissible and, unlike the activation one, is not held in a
 * transient: an object cache that drops transients would silently swallow the
 * only warning the shop gets.
 */
function ayudawp_euw_guarantee_announce_notice() {

	if ( 'yes' !== get_option( 'ayudawp_euw_guarantee_announce', 'no' ) ) {
		return;
	}

	if ( ! current_user_can( 'manage_options' ) ) {
		return;
	}

	// Already on (from the settings page, or from the button below): nothing
	// left to announce.
	if ( ayudawp_euw_guarantee_is_enabled() ) {
		update_option( 'ayudawp_euw_guarantee_announce', 'no' );
		return;
	}

	$settings_url = ayudawp_euw_guarantee_settings_url();

	// The anchor is appended after the nonce so it stays at the end of the URL,
	// where the browser expects the fragment, instead of inside the query string.
	$enable_url = wp_nonce_url(
		add_query_arg( 'ayudawp_euw_guarantee_action', 'enable', ayudawp_euw_get_settings_url() ),
		'ayudawp_euw_guarantee_action'
	) . '#ayudawp-euw-guarantee';

	$dismiss_url = wp_nonce_url(
		add_query_arg( 'ayudawp_euw_guarantee_action', 'dismiss', ayudawp_euw_get_settings_url() ),
		'ayudawp_euw_guarantee_action'
	) . '#ayudawp-euw-guarantee';

	?>
	<div class="notice notice-info">
		<p>
			<strong><?php esc_html_e( 'New: the harmonised EU notice on the legal guarantee of conformity.', 'eu-withdrawal-compliance' ); ?></strong>
		</p>
		<p>
			<?php
			esc_html_e( 'From 27 September 2026, every shop selling goods to consumers in the EU has to display the official notice on the legal guarantee of conformity, unedited and in a prominent place. The plugin now ships it in the 24 EU languages and can place it above the place-order button and in your order emails. It is switched off until you say so, because it changes what your customers see at the checkout.', 'eu-withdrawal-compliance' );
			?>
		</p>
		<p>
			<a href="<?php echo esc_url( $enable_url ); ?>" class="button button-primary">
				<?php esc_html_e( 'Switch the notice on', 'eu-withdrawal-compliance' ); ?>
			</a>
			<a href="<?php echo esc_url( $settings_url ); ?>" class="button">
				<?php esc_html_e( 'See the settings first', 'eu-withdrawal-compliance' ); ?>
			</a>
			<a href="<?php echo esc_url( $dismiss_url ); ?>" class="button-link">
				<?php esc_html_e( 'Dismiss', 'eu-withdrawal-compliance' ); ?>
			</a>
		</p>
	</div>
	<?php
}
add_action( 'admin_notices', 'ayudawp_euw_guarantee_announce_notice' );

/**
 * Handle the two buttons of the announcement notice.
 *
 * Both actions write an option, so both are gated by the capability that owns
 * the settings of this plugin and by a nonce, and both land back on the
 * settings page where the result is visible.
 */
function ayudawp_euw_guarantee_handle_announce_action() {

	if ( ! isset( $_GET['ayudawp_euw_guarantee_action'] ) ) {
		return;
	}

	if ( ! current_user_can( 'manage_options' ) ) {
		return;
	}

	check_admin_referer( 'ayudawp_euw_guarantee_action' );

	$action = sanitize_key( wp_unslash( $_GET['ayudawp_euw_guarantee_action'] ) );

	if ( 'enable' === $action ) {
		update_option( 'ayudawp_euw_guarantee_enabled', 'yes' );
	}

	update_option( 'ayudawp_euw_guarantee_announce', 'no' );

	wp_safe_redirect( ayudawp_euw_guarantee_settings_url() );
	exit;
}
add_action( 'admin_init', 'ayudawp_euw_guarantee_handle_announce_action' );
