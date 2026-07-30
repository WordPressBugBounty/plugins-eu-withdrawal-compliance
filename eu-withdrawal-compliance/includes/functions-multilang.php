<?php
/**
 * Multilingual support: resolving page and object IDs across languages.
 *
 * Everything the plugin needs in order to behave correctly on a Polylang or
 * WPML site lives here: the withdrawal page resolved forward to the visitor's
 * language, and products and categories resolved back to the language their
 * metadata was set in. Every helper returns its input unchanged when no
 * multilingual plugin is active, so single-language sites pay nothing for it.
 *
 * The declarative half of this support is `wpml-config.xml`, which has to sit
 * in the plugin root because that is the only place WPML and Polylang read it
 * from: it exposes the settings holding customer-facing copy to String
 * Translation and copies the withdrawal status over to translations.
 *
 * @package AyudaWP_EU_Withdrawal
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Resolve the ID of the configured withdrawal page for the current language.
 *
 * Reads the canonical page stored in `ayudawp_euw_page_id` and, on a
 * multilingual site, maps it to its translation in the active language so the
 * front-end points each visitor at the page in their own language instead of
 * always the original: the printable Annex I.B form, the WooCommerce email
 * button, the excluded-product link and the `[ayudawp_withdrawal_link]`
 * shortcode. Polylang (`pll_get_post()`) and WPML (the `wpml_object_id`
 * filter) are both supported; with no multilingual plugin the canonical ID is
 * returned unchanged, so this is a no-op on single-language sites.
 *
 * Admin-side reads (the settings selector, the activation notice edit link)
 * deliberately keep using the raw option: there the trader manages the single
 * canonical page, not a per-language view.
 *
 * @return int Resolved page ID, or 0 when none is configured.
 */
function ayudawp_euw_get_page_id() {

	$page_id = (int) get_option( 'ayudawp_euw_page_id', 0 );

	if ( ! $page_id ) {
		return 0;
	}

	// Polylang: use the translation in the current language when it exists,
	// otherwise fall back to the canonical page.
	if ( function_exists( 'pll_get_post' ) ) {

		$translated = (int) pll_get_post( $page_id );

		return $translated ? $translated : $page_id;
	}

	// WPML: the filter returns the canonical ID itself when no translation
	// exists (fourth argument true), so the result is always usable.
	if ( has_filter( 'wpml_object_id' ) ) {

		$translated = (int) apply_filters( 'wpml_object_id', $page_id, 'page', true ); // phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedHooknameFound -- Consuming WPML's own documented filter, not a hook defined by this plugin.

		return $translated ? $translated : $page_id;
	}

	return $page_id;
}

/**
 * Resolve a post ID back to its counterpart in the site's original language.
 *
 * The mirror image of `ayudawp_euw_get_page_id()`: instead of following the
 * visitor's language, this walks back to the language the content was authored
 * in. The plugin's product metadata (the `_ayudawp_euw_withdrawal_status` meta)
 * is set on the original product, while Polylang and WPML serve a separate post
 * per language, so a translated product would otherwise look unflagged: no
 * excluded-product notice, no Art. 16(m) / 14(4)(a) consent at checkout and no
 * excluded item recorded on the order.
 *
 * The given ID is returned unchanged when no multilingual plugin is active,
 * when the post already is the original, or when the translation cannot be
 * resolved, so callers can use the result unconditionally. Single-language
 * sites never leave the first guard.
 *
 * @param int    $post_id   Post ID, possibly a translation.
 * @param string $post_type Post type, required by WPML's filter.
 * @return int Original-language post ID, or the given ID when it cannot be resolved.
 */
function ayudawp_euw_get_source_post_id( $post_id, $post_type = 'post' ) {

	$post_id = absint( $post_id );

	if ( ! $post_id ) {
		return 0;
	}

	// Polylang: ask for this post's translation in the default language.
	if ( function_exists( 'pll_get_post' ) && function_exists( 'pll_default_language' ) ) {

		$default = pll_default_language();

		if ( ! $default ) {
			return $post_id;
		}

		$source = (int) pll_get_post( $post_id, $default );

		return $source ? $source : $post_id;
	}

	// WPML: same idea through its documented filter, which returns the original
	// ID when there is nothing to map (third argument true).
	if ( has_filter( 'wpml_object_id' ) ) {

		$default = (string) apply_filters( 'wpml_default_language', null ); // phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedHooknameFound -- Consuming WPML's own documented filter, not a hook defined by this plugin.

		if ( '' === $default ) {
			return $post_id;
		}

		$source = (int) apply_filters( 'wpml_object_id', $post_id, $post_type, true, $default ); // phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedHooknameFound -- Consuming WPML's own documented filter, not a hook defined by this plugin.

		return $source ? $source : $post_id;
	}

	return $post_id;
}

/**
 * Resolve a term ID back to its counterpart in the site's original language.
 *
 * Term-level sibling of `ayudawp_euw_get_source_post_id()`, for the category
 * withdrawal status stored as term meta. Translated product categories are
 * separate terms with their own IDs and their own (empty) meta, so category
 * inheritance has to be resolved against the original term.
 *
 * @param int    $term_id  Term ID, possibly a translation.
 * @param string $taxonomy Taxonomy name.
 * @return int Original-language term ID, or the given ID when it cannot be resolved.
 */
function ayudawp_euw_get_source_term_id( $term_id, $taxonomy ) {

	$term_id  = absint( $term_id );
	$taxonomy = (string) $taxonomy;

	if ( ! $term_id || '' === $taxonomy ) {
		return 0;
	}

	// Polylang.
	if ( function_exists( 'pll_get_term' ) && function_exists( 'pll_default_language' ) ) {

		$default = pll_default_language();

		if ( ! $default ) {
			return $term_id;
		}

		$source = (int) pll_get_term( $term_id, $default );

		return $source ? $source : $term_id;
	}

	// WPML takes the taxonomy name where post types go for posts.
	if ( has_filter( 'wpml_object_id' ) ) {

		$default = (string) apply_filters( 'wpml_default_language', null ); // phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedHooknameFound -- Consuming WPML's own documented filter, not a hook defined by this plugin.

		if ( '' === $default ) {
			return $term_id;
		}

		$source = (int) apply_filters( 'wpml_object_id', $term_id, $taxonomy, true, $default ); // phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedHooknameFound -- Consuming WPML's own documented filter, not a hook defined by this plugin.

		return $source ? $source : $term_id;
	}

	return $term_id;
}
