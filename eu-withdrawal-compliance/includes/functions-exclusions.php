<?php
/**
 * Article 16 exclusions: products that fall outside the right of withdrawal.
 *
 * Article 16 of Directive 2011/83/EU lists categories of goods and services
 * exempted from the right of withdrawal (custom-made, perishable, sealed
 * digital content, hygiene-sealed items, etc.). This module lets the shop
 * mark individual products or whole categories as excluded so that any
 * withdrawal request landing on an order containing those items is flagged
 * for the admin to review — never auto-rejected, since a partial withdrawal
 * over the non-excluded items can still be valid.
 *
 * Hierarchical exclusion: when a parent category is marked, all of its
 * descendants inherit the exclusion automatically.
 *
 * @package AyudaWP_EU_Withdrawal
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Read the excluded categories option as a clean array of integer term IDs.
 *
 * Back-compat helper for the pre-1.5.0 settings-page chip picker that has
 * been retired in favour of per-category and per-product dropdowns. The
 * resolver still consults this list when computing inheritance so existing
 * configurations keep working until the merchant saves the new dropdown on
 * each category. `ayudawp_euw_migrate_excluded_categories_option()` rewrites
 * those values into term meta on the next admin request, after which this
 * function simply returns an empty list.
 *
 * @return array<int, int>
 */
function ayudawp_euw_get_excluded_category_ids() {

	$value = (array) get_option( 'ayudawp_euw_excluded_categories', array() );

	return array_values( array_unique( array_filter( array_map( 'absint', $value ) ) ) );
}

/**
 * One-time migration from the retired `ayudawp_euw_excluded_categories`
 * option (used by 1.4.x) to the per-category `_ayudawp_euw_withdrawal_status`
 * term meta.
 *
 * Every category previously listed in the option gets its term meta set to
 * 'art16_other' (the closest equivalent of the old "excluded, no consent"
 * semantics). Categories already carrying an explicit non-standard status
 * are left untouched. Runs once per install — the migration flag is what
 * gates re-entry.
 */
function ayudawp_euw_migrate_excluded_categories_option() {

	if ( get_option( 'ayudawp_euw_excluded_categories_migrated' ) ) {
		return;
	}

	$ids = (array) get_option( 'ayudawp_euw_excluded_categories', array() );

	foreach ( $ids as $term_id ) {

		$term_id = absint( $term_id );

		if ( ! $term_id ) {
			continue;
		}

		$existing = (string) get_term_meta( $term_id, '_ayudawp_euw_withdrawal_status', true );

		if ( '' === $existing ) {
			update_term_meta( $term_id, '_ayudawp_euw_withdrawal_status', 'art16_other' );
		}
	}

	delete_option( 'ayudawp_euw_excluded_categories' );

	update_option( 'ayudawp_euw_excluded_categories_migrated', 1, false );
}
add_action( 'admin_init', 'ayudawp_euw_migrate_excluded_categories_option' );

/**
 * Build a short breadcrumb-style label for a product_cat term so the admin
 * can disambiguate two categories that share the same leaf name.
 *
 * @param WP_Term $term Term object.
 * @return string Empty string if the term has no parent.
 */
function ayudawp_euw_get_category_breadcrumb( $term ) {

	if ( ! $term instanceof WP_Term ) {
		return '';
	}

	$ancestors = get_ancestors( $term->term_id, 'product_cat' );

	if ( empty( $ancestors ) ) {
		return '';
	}

	$ancestors = array_reverse( $ancestors );
	$names     = array();

	foreach ( $ancestors as $ancestor_id ) {

		$ancestor = get_term( (int) $ancestor_id, 'product_cat' );

		if ( $ancestor instanceof WP_Term ) {
			$names[] = $ancestor->name;
		}
	}

	return implode( ' › ', $names );
}

/**
 * Find the WP_Term that makes a product inherit an *excluded* status from
 * its categories. Back-compat wrapper around the new resolver: returns the
 * inherited term only when its status excludes the product from withdrawal
 * (Art. 16(m) digital content or any other Article 16 exception). Returns
 * null when the inheritance is "service-start consent", which keeps the
 * withdrawal right and therefore does not count as excluded.
 *
 * @param int $product_id Product ID.
 * @return WP_Term|null
 */
function ayudawp_euw_get_inherited_exclusion_term( $product_id ) {

	$inherited = ayudawp_euw_get_inherited_withdrawal_status( $product_id );

	if ( ! $inherited ) {
		return null;
	}

	if ( ! ayudawp_euw_is_excluded_status( $inherited['status'] ) ) {
		return null;
	}

	return $inherited['term'];
}

/**
 * Allowed values for the per-product / per-category withdrawal status.
 *
 * One dropdown drives both the Article 16 exclusion flag and the matching
 * checkout consent in a single choice. The empty value means "inherit from
 * category" when used on a product and "no status" when used on a category
 * (status='standard' effectively).
 *
 * @return array<string, string>
 */
function ayudawp_euw_get_withdrawal_status_options() {

	return array(
		'standard'             => __( 'Standard — withdrawal applies', 'eu-withdrawal-compliance' ),
		'art16m_digital'       => __( 'Digital content (Art. 16(m)) — excluded, requires mandatory consent at checkout', 'eu-withdrawal-compliance' ),
		'art14_4a_service'     => __( 'Service started early (Art. 14(4)(a)) — withdrawal applies, optional consent at checkout for pro-rata billing', 'eu-withdrawal-compliance' ),
		'art16l_accommodation' => __( 'Dated accommodation, transport, car rental, catering or leisure (Art. 16(l)) — excluded, no consent needed', 'eu-withdrawal-compliance' ),
		'art16_other'          => __( 'Other Article 16 exception — excluded (perishable, custom-made, hygiene-sealed, sealed media, etc.), no consent needed', 'eu-withdrawal-compliance' ),
	);
}

/**
 * Whether a withdrawal status string is one of the valid non-standard values.
 *
 * @param string $status Status candidate.
 * @return bool
 */
function ayudawp_euw_is_valid_withdrawal_status( $status ) {

	return in_array(
		(string) $status,
		array( 'standard', 'art16m_digital', 'art14_4a_service', 'art16l_accommodation', 'art16_other' ),
		true
	);
}

/**
 * Whether a withdrawal status is one of the "excluded from withdrawal" values.
 *
 * The three excluded statuses are Article 16(m) sealed digital content,
 * Article 16(l) dated services (accommodation, transport, car rental,
 * catering, leisure) and the generic "other Article 16 exception". The
 * service-start status (Art. 14(4)(a)) is *not* excluded — the consumer keeps
 * the right and only the pro-rata billing changes — so it returns false.
 *
 * Centralises the membership test that used to be repeated inline across the
 * exclusion and settings modules, so adding a fourth excluded status later is
 * a one-line change here.
 *
 * @param string $status Status candidate.
 * @return bool
 */
function ayudawp_euw_is_excluded_status( $status ) {

	return in_array(
		(string) $status,
		array( 'art16m_digital', 'art16l_accommodation', 'art16_other' ),
		true
	);
}

/**
 * Effective withdrawal status for a product, walking up category inheritance
 * and applying back-compat with the pre-1.5.0 flags.
 *
 * Resolution order:
 *   1. The product's own `_ayudawp_euw_withdrawal_status` meta if it carries
 *      any valid status — including 'standard', which here means "override
 *      whatever the category inheritance would otherwise apply".
 *   2. Back-compat with the 1.5.0 beta consent/exclusion checkboxes.
 *   3. Inherited from the product's categories (and their ancestors).
 *   4. 'standard' (no exclusion, no consent).
 *
 * @param int $product_id Product ID.
 * @return string One of: 'standard', 'art16m_digital', 'art14_4a_service', 'art16_other'.
 */
function ayudawp_euw_get_product_withdrawal_status( $product_id ) {

	$product_id = absint( $product_id );

	if ( ! $product_id ) {
		return 'standard';
	}

	$own = (string) get_post_meta( $product_id, '_ayudawp_euw_withdrawal_status', true );

	if ( ayudawp_euw_is_valid_withdrawal_status( $own ) ) {
		return $own;
	}

	// Back-compat: products coming from 1.4.x carry a `_ayudawp_euw_excluded`
	// boolean instead of the new status. Treat it as "other Article 16
	// exception" so the exclusion keeps working until the merchant edits
	// the product and the save handler upgrades the meta.
	$legacy_excluded = (string) get_post_meta( $product_id, '_ayudawp_euw_excluded', true );
	if ( 'yes' === $legacy_excluded ) {
		return 'art16_other';
	}

	// On Polylang and WPML sites the status is set on the original-language
	// product, while each language serves a different post ID. Read it from
	// there when this product carries none of its own, so a translated product
	// behaves exactly like the original: notice on the product page, consent at
	// checkout and excluded item recorded on the order. An explicit status on
	// the translation still wins, which keeps per-language overrides possible.
	$source_id = ayudawp_euw_get_source_post_id( $product_id, 'product' );
	$source_id = ( $source_id && $source_id !== $product_id ) ? $source_id : 0;

	if ( $source_id ) {

		$source_own = (string) get_post_meta( $source_id, '_ayudawp_euw_withdrawal_status', true );

		if ( ayudawp_euw_is_valid_withdrawal_status( $source_own ) ) {
			return $source_own;
		}

		if ( 'yes' === (string) get_post_meta( $source_id, '_ayudawp_euw_excluded', true ) ) {
			return 'art16_other';
		}
	}

	$inherited = ayudawp_euw_get_inherited_withdrawal_status( $product_id );

	// Translations are normally assigned the translated categories, but when
	// they carry none at all the original product's categories still apply.
	if ( null === $inherited && $source_id ) {
		$inherited = ayudawp_euw_get_inherited_withdrawal_status( $source_id );
	}

	if ( null !== $inherited ) {
		return $inherited['status'];
	}

	return 'standard';
}

/**
 * Find the closest product_cat ancestor that defines a non-standard status.
 *
 * Mirrors the previous `ayudawp_euw_get_inherited_exclusion_term()` helper
 * but resolves the full status string instead of a boolean, so a parent
 * category flagged as Art. 16(m) digital propagates the consent down too.
 *
 * @param int $product_id Product ID.
 * @return array{term: WP_Term, status: string}|null
 */
function ayudawp_euw_get_inherited_withdrawal_status( $product_id ) {

	if ( ! taxonomy_exists( 'product_cat' ) ) {
		return null;
	}

	$product_id = absint( $product_id );

	if ( ! $product_id ) {
		return null;
	}

	$product_terms = wp_get_post_terms( $product_id, 'product_cat', array( 'fields' => 'ids' ) );

	if ( is_wp_error( $product_terms ) || empty( $product_terms ) ) {
		return null;
	}

	$legacy_excluded_ids = ayudawp_euw_get_excluded_category_ids();

	foreach ( $product_terms as $term_id ) {

		$term_id = (int) $term_id;
		$chain   = array_merge(
			array( $term_id ),
			array_map( 'intval', get_ancestors( $term_id, 'product_cat' ) )
		);

		foreach ( $chain as $candidate_id ) {

			// A translated category is a separate term with its own (empty)
			// meta, so every candidate is also matched against its
			// original-language term. The term object returned stays the one in
			// the current language, which is what the shopper reads on the
			// product page and what the product editor shows.
			$source_id   = ayudawp_euw_get_source_term_id( $candidate_id, 'product_cat' );
			$source_id   = ( $source_id && $source_id !== $candidate_id ) ? $source_id : 0;
			$term_status = (string) get_term_meta( $candidate_id, '_ayudawp_euw_withdrawal_status', true );

			if ( $source_id && ! ayudawp_euw_is_valid_withdrawal_status( $term_status ) ) {
				$term_status = (string) get_term_meta( $source_id, '_ayudawp_euw_withdrawal_status', true );
			}

			if ( ayudawp_euw_is_valid_withdrawal_status( $term_status ) && 'standard' !== $term_status ) {

				$term = get_term( $candidate_id, 'product_cat' );

				if ( $term instanceof WP_Term ) {
					return array(
						'term'   => $term,
						'status' => $term_status,
					);
				}
			}

			if ( in_array( $candidate_id, $legacy_excluded_ids, true )
				|| ( $source_id && in_array( $source_id, $legacy_excluded_ids, true ) )
			) {

				$term = get_term( $candidate_id, 'product_cat' );

				if ( $term instanceof WP_Term ) {
					return array(
						'term'   => $term,
						'status' => 'art16_other',
					);
				}
			}
		}
	}

	return null;
}

/**
 * Render the per-product withdrawal status dropdown in the product editor.
 *
 * The select is always enabled — choosing any explicit option (including
 * "Standard") overrides whatever the categories would otherwise inherit.
 * Leaving it on "Inherit from category" simply clears the per-product meta
 * and lets the inheritance resolver decide.
 *
 * Inheritance is signalled in the description (not by disabling the field)
 * so the merchant can always override per product, even when a category is
 * already marked.
 */
function ayudawp_euw_render_product_exclusion_field() {

	global $post;

	$product_id = ( $post && isset( $post->ID ) ) ? (int) $post->ID : 0;
	$own        = $product_id ? (string) get_post_meta( $product_id, '_ayudawp_euw_withdrawal_status', true ) : '';
	$inherited  = $product_id ? ayudawp_euw_get_inherited_withdrawal_status( $product_id ) : null;
	$options    = ayudawp_euw_get_withdrawal_status_options();

	if ( $inherited ) {

		$inherited_status_label = isset( $options[ $inherited['status'] ] )
			? $options[ $inherited['status'] ]
			: $inherited['status'];

		/* translators: %s: short label of the status inherited from the category, e.g. "Digital content (Art. 16(m))". */
		$inherit_option_label = sprintf( __( '— Inherit from category: %s', 'eu-withdrawal-compliance' ), $inherited_status_label );

		$description = sprintf(
			/* translators: 1: status label, 2: parent category name. */
			__( 'This product currently inherits “%1$s” from the “%2$s” category. Pick any explicit status here to override the inheritance for this product only — including <em>Standard</em> to opt this product back into the regular right of withdrawal.', 'eu-withdrawal-compliance' ),
			$inherited_status_label,
			$inherited['term']->name
		);

	} else {

		$inherit_option_label = __( '— Inherit from category (Standard)', 'eu-withdrawal-compliance' );
		$description          = __( 'Defines whether this product is excluded from the EU right of withdrawal and which checkout consent (if any) is shown to the customer. Categories can set a default that products inherit.', 'eu-withdrawal-compliance' );
	}

	// On Polylang and WPML sites the status is managed on the original-language
	// product and applies to every translation, taking precedence over category
	// inheritance. Spell that out here, or this screen would look unflagged
	// while the front-end (rightly) shows the notice and asks for consent.
	$source_status = '';

	if ( $product_id && '' === $own ) {

		$source_id = ayudawp_euw_get_source_post_id( $product_id, 'product' );

		if ( $source_id && $source_id !== $product_id ) {

			$candidate = (string) get_post_meta( $source_id, '_ayudawp_euw_withdrawal_status', true );

			if ( ayudawp_euw_is_valid_withdrawal_status( $candidate ) ) {
				$source_status = $candidate;
			}
		}
	}

	if ( '' !== $source_status ) {

		$source_status_label = isset( $options[ $source_status ] ) ? $options[ $source_status ] : $source_status;

		$inherit_option_label = __( '— Inherit from the original language', 'eu-withdrawal-compliance' );

		$description = sprintf(
			/* translators: %s: short label of the status set on the original-language product, e.g. "Digital content (Art. 16(m))". */
			__( 'This is a translation and it currently takes “%s” from the product in the site original language, where this status is managed. Pick an explicit status here to override it for this translation only.', 'eu-withdrawal-compliance' ),
			$source_status_label
		);
	}

	$select_options = array( '' => $inherit_option_label ) + $options;
	?>
	<p class="form-field _ayudawp_euw_withdrawal_status_field">
		<label for="_ayudawp_euw_withdrawal_status"><?php esc_html_e( 'Withdrawal status', 'eu-withdrawal-compliance' ); ?></label>
		<select id="_ayudawp_euw_withdrawal_status" name="_ayudawp_euw_withdrawal_status" class="select short">
			<?php foreach ( $select_options as $value => $label ) : ?>
				<option value="<?php echo esc_attr( $value ); ?>" <?php selected( $own, $value ); ?>><?php echo esc_html( $label ); ?></option>
			<?php endforeach; ?>
		</select>
	</p>
	<p class="form-field ayudawp-euw-status-description-row">
		<span class="description">
			<?php
			echo wp_kses(
				$description,
				array(
					'em'     => array(),
					'strong' => array(),
				)
			);
			?>
		</span>
	</p>
	<?php
}
add_action( 'woocommerce_product_options_general_product_data', 'ayudawp_euw_render_product_exclusion_field' );

/**
 * Persist the per-product withdrawal status when the product is saved.
 *
 * Any of the four valid statuses (Standard, Digital content, Service early,
 * Other Article 16) is stored verbatim — including 'standard', which the
 * merchant can pick to explicitly override an inherited category status.
 * An empty value means "inherit from category" and clears the meta.
 *
 * @param int $post_id Product ID.
 */
function ayudawp_euw_save_product_exclusion_field( $post_id ) {

	if ( ! isset( $_POST['woocommerce_meta_nonce'] )
		|| ! wp_verify_nonce(
			sanitize_text_field( wp_unslash( $_POST['woocommerce_meta_nonce'] ) ),
			'woocommerce_save_data'
		)
	) {
		return;
	}

	if ( ! current_user_can( 'edit_product', $post_id ) ) {
		return;
	}

	$raw = isset( $_POST['_ayudawp_euw_withdrawal_status'] )
		? sanitize_key( wp_unslash( $_POST['_ayudawp_euw_withdrawal_status'] ) )
		: '';

	if ( ayudawp_euw_is_valid_withdrawal_status( $raw ) ) {
		update_post_meta( $post_id, '_ayudawp_euw_withdrawal_status', $raw );
	} else {
		delete_post_meta( $post_id, '_ayudawp_euw_withdrawal_status' );
	}

	// Tidy up the 1.4.x boolean flag so the effective status is always
	// driven by the new dropdown after the first save.
	delete_post_meta( $post_id, '_ayudawp_euw_excluded' );
}
add_action( 'woocommerce_process_product_meta', 'ayudawp_euw_save_product_exclusion_field' );

/**
 * Render the same withdrawal-status dropdown in the product category editor.
 *
 * @param WP_Term $term Term being edited.
 */
function ayudawp_euw_render_category_status_field( $term ) {

	if ( ! $term instanceof WP_Term ) {
		return;
	}

	$current = (string) get_term_meta( $term->term_id, '_ayudawp_euw_withdrawal_status', true );

	// The category dropdown has no use for an explicit "Standard" entry: the
	// empty option below already means "standard, no exclusion", and the save
	// handler stores no term meta for it. Offering both confused merchants, who
	// picked the explicit "Standard" and saw it apparently not persist (the
	// handler clears the meta for 'standard' exactly as it does for ''). Drop it
	// here; the per-product selector keeps it because there it is a real override
	// of whatever the category would otherwise inherit.
	$status_options = ayudawp_euw_get_withdrawal_status_options();
	unset( $status_options['standard'] );

	$options = array(
		'' => __( '— Standard (no exclusion, no checkout consent)', 'eu-withdrawal-compliance' ),
	) + $status_options;

	wp_nonce_field( 'ayudawp_euw_save_category_status', 'ayudawp_euw_category_status_nonce' );
	?>
	<tr class="form-field ayudawp-euw-status-row">
		<th scope="row"><label for="_ayudawp_euw_withdrawal_status"><?php esc_html_e( 'Withdrawal status', 'eu-withdrawal-compliance' ); ?></label></th>
		<td>
			<select name="_ayudawp_euw_withdrawal_status" id="_ayudawp_euw_withdrawal_status" style="width: 30em; max-width: 100%;">
				<?php foreach ( $options as $value => $label ) : ?>
					<option value="<?php echo esc_attr( $value ); ?>" <?php selected( $current, $value ); ?>><?php echo esc_html( $label ); ?></option>
				<?php endforeach; ?>
			</select>
			<p class="description"><?php esc_html_e( 'Default status for every product in this category and its descendants. Individual products can override this.', 'eu-withdrawal-compliance' ); ?></p>
		</td>
	</tr>
	<?php
}
add_action( 'product_cat_edit_form_fields', 'ayudawp_euw_render_category_status_field' );

/**
 * Persist the withdrawal status from the category editor.
 *
 * @param int $term_id Term ID.
 */
function ayudawp_euw_save_category_status_field( $term_id ) {

	if ( ! isset( $_POST['ayudawp_euw_category_status_nonce'] )
		|| ! wp_verify_nonce(
			sanitize_text_field( wp_unslash( $_POST['ayudawp_euw_category_status_nonce'] ) ),
			'ayudawp_euw_save_category_status'
		)
	) {
		return;
	}

	if ( ! current_user_can( 'manage_product_terms' ) ) {
		return;
	}

	$raw = isset( $_POST['_ayudawp_euw_withdrawal_status'] )
		? sanitize_key( wp_unslash( $_POST['_ayudawp_euw_withdrawal_status'] ) )
		: '';

	if ( '' === $raw || ! ayudawp_euw_is_valid_withdrawal_status( $raw ) || 'standard' === $raw ) {
		delete_term_meta( $term_id, '_ayudawp_euw_withdrawal_status' );
	} else {
		update_term_meta( $term_id, '_ayudawp_euw_withdrawal_status', $raw );
	}
}
add_action( 'edited_product_cat', 'ayudawp_euw_save_category_status_field' );
add_action( 'create_product_cat', 'ayudawp_euw_save_category_status_field' );

/**
 * Whether a product (by ID) is excluded from the right of withdrawal.
 *
 * Derived from the canonical withdrawal status: the three "excluded" statuses
 * — Article 16(m) digital content, Article 16(l) dated services and any other
 * Article 16 exception — return true; the service-start consent does not
 * exclude the product since the consumer keeps the right (only the pro-rata
 * billing changes).
 *
 * @param int $product_id Product ID.
 * @return bool
 */
function ayudawp_euw_is_product_excluded( $product_id ) {

	$status = ayudawp_euw_get_product_withdrawal_status( $product_id );

	return ayudawp_euw_is_excluded_status( $status );
}

/**
 * Whether the Article 16 exclusions decide what an order offers.
 *
 * Reads the "Orders with excluded products" setting, on by default. With it on,
 * an order that only holds excluded products shows neither the withdrawal button
 * nor the notice in its emails, and an order that mixes both names the excluded
 * ones in that notice. The public form is not affected: it keeps accepting and
 * flagging a request for any order.
 *
 * @return bool
 */
function ayudawp_euw_exclusions_apply_to_orders() {

	return 'yes' === get_option( 'ayudawp_euw_exclusions_in_orders', 'yes' );
}

/**
 * Whether one line of an order is a product excluded from the right of withdrawal.
 *
 * A line counts as excluded when its product is, or when its variation is, since
 * the status can be set on either. Anything that is not an object with
 * `get_product_id()` is not an order item, and is not excluded.
 *
 * Shared by the list of excluded items stored on a request and by the order-level
 * checks below, so every one of them reads a line the same way.
 *
 * @param mixed $item WC_Order_Item_Product, or anything else.
 * @return bool
 */
function ayudawp_euw_is_order_item_excluded( $item ) {

	if ( ! is_object( $item ) || ! method_exists( $item, 'get_product_id' ) ) {
		return false;
	}

	$product_id   = (int) $item->get_product_id();
	$variation_id = method_exists( $item, 'get_variation_id' ) ? (int) $item->get_variation_id() : 0;

	return ayudawp_euw_is_product_excluded( $product_id )
		|| ( $variation_id && ayudawp_euw_is_product_excluded( $variation_id ) );
}

/**
 * Build the list of excluded items contained in a WooCommerce order.
 *
 * Returns an array of associative arrays with `product_id`, `name` and
 * `quantity` so the admin notification and the CPT detail screen can show
 * them without re-loading the order.
 *
 * Takes the order itself as well as its ID, for the callers that already hold
 * the object and would only be loading it again.
 *
 * @param int|WC_Order $wc_order_id WC order ID, or the order itself.
 * @return array<int, array<string, mixed>>
 */
function ayudawp_euw_get_excluded_items_in_order( $wc_order_id ) {

	if ( is_object( $wc_order_id ) && method_exists( $wc_order_id, 'get_items' ) ) {
		$order = $wc_order_id;
	} elseif ( function_exists( 'wc_get_order' ) ) {
		$order = wc_get_order( absint( $wc_order_id ) );
	} else {
		return array();
	}

	if ( ! $order ) {
		return array();
	}

	$excluded = array();

	foreach ( $order->get_items() as $item ) {

		if ( ! ayudawp_euw_is_order_item_excluded( $item ) ) {
			continue;
		}

		$excluded[] = array(
			'product_id' => (int) $item->get_product_id(),
			'name'       => $item->get_name(),
			'quantity'   => method_exists( $item, 'get_quantity' ) ? (int) $item->get_quantity() : 1,
		);
	}

	return $excluded;
}

/**
 * Whether an order holds nothing but products excluded from the right of withdrawal.
 *
 * True only when the order has at least one line and every line is excluded. It
 * stops at the first line that is not, so the common case, an order of regular
 * products, costs a single product lookup, which matters on the My Account
 * orders list where this runs once per row. A line with no product, or with a
 * product that has since been deleted, counts as not excluded: in doubt the
 * withdrawal is offered.
 *
 * @param object $order WC_Order instance.
 * @return bool
 */
function ayudawp_euw_order_is_fully_excluded( $order ) {

	if ( ! is_object( $order ) || ! method_exists( $order, 'get_items' ) ) {
		return false;
	}

	$items = $order->get_items();

	if ( empty( $items ) ) {
		return false;
	}

	foreach ( $items as $item ) {
		if ( ! ayudawp_euw_is_order_item_excluded( $item ) ) {
			return false;
		}
	}

	return true;
}

/**
 * Names of the excluded products in an order that mixes excluded and regular ones.
 *
 * Used by the email notice to say which items the right of withdrawal does not
 * apply to. Empty when the "Orders with excluded products" setting is off, when
 * nothing in the order is excluded and when everything is. A fully excluded
 * order returns nothing on purpose: if a filter brought the notice back on it,
 * a line saying the right applies to none of its items would contradict the
 * notice it sits in.
 *
 * @param object $order WC_Order instance.
 * @return array<int, string> Unique, non-empty product names.
 */
function ayudawp_euw_get_mixed_order_excluded_names( $order ) {

	if ( ! ayudawp_euw_exclusions_apply_to_orders()
		|| ! is_object( $order )
		|| ! method_exists( $order, 'get_items' )
	) {
		return array();
	}

	$excluded = ayudawp_euw_get_excluded_items_in_order( $order );

	// Mixed: at least one excluded line, and fewer of them than lines in total.
	if ( empty( $excluded ) || count( $excluded ) >= count( $order->get_items() ) ) {
		return array();
	}

	$names = array();

	foreach ( $excluded as $item ) {

		$name = trim( wp_strip_all_tags( (string) $item['name'] ) );

		if ( '' !== $name ) {
			$names[ $name ] = $name;
		}
	}

	return array_values( $names );
}



/**
 * Whether the public-facing excluded-product notice is enabled in settings.
 *
 * @return bool
 */
function ayudawp_euw_excluded_notice_is_enabled() {

	return 'yes' === get_option( 'ayudawp_euw_excluded_notice_enabled', 'yes' );
}

/**
 * Bundled defaults for the per-status notice title/body pairs.
 *
 * Returns one entry per excluded status (`art16m_digital`,
 * `art16l_accommodation` and `art16_other`); the service-start status keeps
 * the withdrawal right and therefore has no notice. Each entry carries a
 * `title` and a `body`,
 * both translatable. The body supports `{withdrawal_page_link}` and basic
 * HTML (a, strong, em).
 *
 * @return array<string, array<string, string>>
 */
function ayudawp_euw_excluded_notice_defaults() {

	return array(
		'art16m_digital'       => array(
			'title' => __( 'Digital content — withdrawal right lost on access', 'eu-withdrawal-compliance' ),
			'body'  => __( 'This is digital content under Article 16(m) of Directive 2011/83/EU. By starting access, downloading or viewing it after purchase you expressly request its immediate supply and acknowledge losing the 14-day right of withdrawal. {withdrawal_page_link}', 'eu-withdrawal-compliance' ),
		),
		'art16l_accommodation' => array(
			'title' => __( 'Dated service — excluded from the right of withdrawal', 'eu-withdrawal-compliance' ),
			'body'  => __( 'This service is excluded from the EU right of withdrawal under Article 16(l) of Directive 2011/83/EU, which covers the provision of accommodation (other than for residential purposes), transport of goods, car rental, catering or leisure services when the contract sets a specific date or period of performance. Please check the booking conditions before purchasing. {withdrawal_page_link}', 'eu-withdrawal-compliance' ),
		),
		'art16_other'          => array(
			'title' => __( 'Excluded from the right of withdrawal', 'eu-withdrawal-compliance' ),
			'body'  => __( 'This product is excluded from the EU right of withdrawal under one of the exceptions in Article 16 of Directive 2011/83/EU (perishable, custom-made, hygiene-sealed, sealed audio, video or software media unsealed after delivery, etc.). Please check the conditions before purchasing. {withdrawal_page_link}', 'eu-withdrawal-compliance' ),
		),
	);
}

/**
 * Resolve the notice title for a given status, with stored override and
 * translatable bundled default as fallbacks.
 *
 * Also accepts the no-status argument (legacy callers) and returns the
 * generic "art16_other" title for back-compat.
 *
 * @param string $status Withdrawal status the notice should describe.
 * @return string
 */
function ayudawp_euw_excluded_notice_title( $status = 'art16_other' ) {

	$status = ayudawp_euw_is_excluded_status( $status ) ? $status : 'art16_other';

	$option_key = 'ayudawp_euw_excluded_notice_title_' . $status;
	$stored     = (string) get_option( $option_key, '' );

	if ( '' !== trim( $stored ) ) {
		return $stored;
	}

	$defaults = ayudawp_euw_excluded_notice_defaults();

	return $defaults[ $status ]['title'];
}

/**
 * Resolve the notice body for a given status, with stored override and
 * translatable bundled default as fallbacks.
 *
 * Supports the `{withdrawal_page_link}` placeholder, expanded by the
 * renderer to a link pointing to the configured withdrawal page.
 *
 * @param string $status Withdrawal status the notice should describe.
 * @return string
 */
function ayudawp_euw_excluded_notice_body( $status = 'art16_other' ) {

	$status = ayudawp_euw_is_excluded_status( $status ) ? $status : 'art16_other';

	$option_key = 'ayudawp_euw_excluded_notice_body_' . $status;
	$stored     = (string) get_option( $option_key, '' );

	if ( '' !== trim( $stored ) ) {
		return $stored;
	}

	$defaults = ayudawp_euw_excluded_notice_defaults();

	return $defaults[ $status ]['body'];
}

/**
 * Echo the "excluded from withdrawal" notice for a given product.
 *
 * Shared core used both by the automatic single-product hook and the
 * `[ayudawp_withdrawal_excluded_notice]` shortcode (through output buffering),
 * so page builders that never fire `woocommerce_single_product_summary`
 * (Elementor, Bricks, Divi) can still place the notice. Outputs nothing when
 * the notice is disabled, the product is missing or the product is not on an
 * excluded status, so callers can invoke it unconditionally.
 *
 * @param int $product_id Product ID.
 */
function ayudawp_euw_render_excluded_notice_for_product( $product_id ) {

	if ( ! ayudawp_euw_excluded_notice_is_enabled() ) {
		return;
	}

	$product_id = absint( $product_id );

	if ( ! $product_id ) {
		return;
	}

	$status = ayudawp_euw_get_product_withdrawal_status( $product_id );

	if ( ! ayudawp_euw_is_excluded_status( $status ) ) {
		return;
	}

	$title = ayudawp_euw_excluded_notice_title( $status );
	$body  = ayudawp_euw_excluded_notice_body( $status );

	if ( false !== strpos( $body, '{withdrawal_page_link}' ) ) {

		$page_id   = ayudawp_euw_get_page_id();
		$page_link = '';

		if ( $page_id && get_post( $page_id ) ) {
			$page_link = sprintf(
				'<a href="%1$s" rel="noopener nofollow">%2$s</a>',
				esc_url( get_permalink( $page_id ) ),
				esc_html__( 'See the full withdrawal policy', 'eu-withdrawal-compliance' )
			);
		}

		$body = str_replace( '{withdrawal_page_link}', $page_link, $body );
	}

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

	?>
	<div class="ayudawp-euw-excluded-notice" role="note">
		<p class="ayudawp-euw-excluded-notice__title">
			<span class="dashicons dashicons-info-outline" aria-hidden="true"></span>
			<strong><?php echo esc_html( $title ); ?></strong>
		</p>
		<p class="ayudawp-euw-excluded-notice__body"><?php echo wp_kses( $body, $allowed_html ); ?></p>
	</div>
	<?php
}

/**
 * Render an "excluded from withdrawal" notice on the single product page.
 *
 * Sits between price and add-to-cart so the consumer notices it before
 * committing to the purchase, which reinforces informed consent. The title
 * and body are chosen from settings according to the product's effective
 * withdrawal status (digital content vs. any other Article 16 exception),
 * so a perishable item and a digital course can be described separately.
 */
function ayudawp_euw_render_product_excluded_notice() {

	if ( ! function_exists( 'is_product' ) || ! is_product() ) {
		return;
	}

	global $product;

	if ( ! $product instanceof WC_Product ) {
		return;
	}

	ayudawp_euw_render_excluded_notice_for_product( $product->get_id() );
}
add_action( 'woocommerce_single_product_summary', 'ayudawp_euw_render_product_excluded_notice', 25 );

/**
 * Shortcode that prints the excluded-product notice outside the standard
 * WooCommerce single-product hook.
 *
 * Page builders (Elementor, Bricks, Divi) render their own product templates
 * and never fire `woocommerce_single_product_summary`, so the automatic notice
 * never appears. Dropping `[ayudawp_withdrawal_excluded_notice]` into the
 * product layout restores it. With no `id` attribute the shortcode resolves
 * the current product from the loop's `$product` global or the queried object,
 * so the same layout works for every product; the notice is only printed when
 * that product is actually on an excluded status.
 *
 * @param array|string $atts Shortcode attributes. `id` targets a specific
 *                           product; defaults to the current product.
 * @return string Notice HTML, or empty string when there is nothing to show.
 */
function ayudawp_euw_excluded_notice_shortcode( $atts ) {

	$atts = shortcode_atts(
		array(
			'id' => 0,
		),
		$atts,
		'ayudawp_withdrawal_excluded_notice'
	);

	$product_id = absint( $atts['id'] );

	if ( ! $product_id ) {
		global $product;

		$product_id = ( $product instanceof WC_Product )
			? (int) $product->get_id()
			: (int) get_queried_object_id();
	}

	if ( ! $product_id || ! function_exists( 'wc_get_product' ) || ! wc_get_product( $product_id ) ) {
		return '';
	}

	// The wp_enqueue_scripts pass already covers content that stores the
	// shortcode in post_content; this best-effort call also styles builders
	// that render it from their own data store.
	if ( function_exists( 'ayudawp_euw_enqueue_excluded_notice_styles' ) ) {
		ayudawp_euw_enqueue_excluded_notice_styles();
	}

	ob_start();
	ayudawp_euw_render_excluded_notice_for_product( $product_id );

	return (string) ob_get_clean();
}
add_shortcode( 'ayudawp_withdrawal_excluded_notice', 'ayudawp_euw_excluded_notice_shortcode' );
