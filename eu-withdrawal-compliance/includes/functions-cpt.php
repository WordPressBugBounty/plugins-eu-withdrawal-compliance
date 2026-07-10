<?php
/**
 * Custom Post Type to log withdrawal requests.
 *
 * @package AyudaWP_EU_Withdrawal
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Register the withdrawal CPT used to store every request received.
 *
 * It is non-public, only visible in the admin area, so logs cannot be
 * crawled or accessed from the frontend.
 */
function ayudawp_euw_register_cpt() {

	$labels = array(
		'name'                  => _x( 'Withdrawals', 'post type general name', 'eu-withdrawal-compliance' ),
		'singular_name'         => _x( 'Withdrawal', 'post type singular name', 'eu-withdrawal-compliance' ),
		'menu_name'             => _x( 'Withdrawals', 'admin menu', 'eu-withdrawal-compliance' ),
		'name_admin_bar'        => _x( 'Withdrawal', 'add new on admin bar', 'eu-withdrawal-compliance' ),
		'all_items'             => __( 'Withdrawals', 'eu-withdrawal-compliance' ),
		'edit_item'             => __( 'Edit withdrawal', 'eu-withdrawal-compliance' ),
		'new_item'              => __( 'New withdrawal', 'eu-withdrawal-compliance' ),
		'view_item'             => __( 'View withdrawal', 'eu-withdrawal-compliance' ),
		'view_items'            => __( 'View withdrawals', 'eu-withdrawal-compliance' ),
		'search_items'          => __( 'Search withdrawals', 'eu-withdrawal-compliance' ),
		'not_found'             => __( 'No withdrawals found.', 'eu-withdrawal-compliance' ),
		'not_found_in_trash'    => __( 'No withdrawals in Trash.', 'eu-withdrawal-compliance' ),
		'filter_items_list'     => __( 'Filter withdrawals list', 'eu-withdrawal-compliance' ),
		'items_list_navigation' => __( 'Withdrawals list navigation', 'eu-withdrawal-compliance' ),
		'items_list'            => __( 'Withdrawals list', 'eu-withdrawal-compliance' ),
	);

	$args = array(
		'labels'             => $labels,
		'public'             => false,
		'publicly_queryable' => false,
		'show_ui'            => true,
		'show_in_menu'       => true,
		'show_in_rest'       => false,
		'query_var'          => false,
		'rewrite'            => false,
		'capability_type'    => array( 'ayudawp_withdrawal', 'ayudawp_withdrawals' ),
		'capabilities'       => array(
			'create_posts' => 'do_not_allow', // Only created via the frontend form.
		),
		'map_meta_cap'       => true,
		'has_archive'        => false,
		'hierarchical'       => false,
		'menu_position'      => 25,
		'menu_icon'          => 'dashicons-undo',
		'supports'           => array( 'title' ),
	);

	register_post_type( 'ayudawp_withdrawal', $args );
}
add_action( 'init', 'ayudawp_euw_register_cpt' );

/**
 * Register a custom status for processed withdrawals.
 */
function ayudawp_euw_register_status() {

	register_post_status(
		'ayudawp_processed',
		array(
			'label'                     => _x( 'Processed', 'withdrawal status', 'eu-withdrawal-compliance' ),
			'public'                    => false,
			'internal'                  => false,
			'protected'                 => true,
			'show_in_admin_status_list' => true,
			'show_in_admin_all_list'    => true,
			/* translators: %s: number of items. */
			'label_count'               => _n_noop(
				'Processed <span class="count">(%s)</span>',
				'Processed <span class="count">(%s)</span>',
				'eu-withdrawal-compliance'
			),
		)
	);
}
add_action( 'init', 'ayudawp_euw_register_status' );

/**
 * Primitive capabilities that govern the withdrawal CPT.
 *
 * Granted to the administrator (always) and to the roles the admin selects in
 * the Permissions section, so access to requests (which hold personal data) is
 * scoped instead of riding on the generic `edit_others_posts` capability.
 *
 * @return array<int, string>
 */
function ayudawp_euw_get_manager_caps() {

	return array(
		'edit_ayudawp_withdrawals',
		'edit_others_ayudawp_withdrawals',
		'edit_published_ayudawp_withdrawals',
		'edit_private_ayudawp_withdrawals',
		'read_private_ayudawp_withdrawals',
		'publish_ayudawp_withdrawals',
		'delete_ayudawp_withdrawals',
		'delete_others_ayudawp_withdrawals',
		'delete_published_ayudawp_withdrawals',
		'delete_private_ayudawp_withdrawals',
	);
}

/**
 * Roles (other than administrator) that could manage requests before this
 * dedicated capability existed, i.e. those holding `edit_others_posts`.
 *
 * Used to seed the Permissions option on upgrade so existing setups keep the
 * access they had instead of being silently locked out.
 *
 * @return array<int, string>
 */
function ayudawp_euw_detect_legacy_manager_roles() {

	$roles = array();

	if ( ! function_exists( 'wp_roles' ) ) {
		return $roles;
	}

	foreach ( array_keys( wp_roles()->roles ) as $slug ) {

		if ( 'administrator' === $slug ) {
			continue;
		}

		$role_obj = get_role( $slug );

		if ( $role_obj && $role_obj->has_cap( 'edit_others_posts' ) ) {
			$roles[] = $slug;
		}
	}

	return $roles;
}

/**
 * Grant the CPT capabilities to the selected roles and remove them from the rest.
 *
 * The administrator is skipped here (always granted in ayudawp_euw_setup_capabilities)
 * so it can never be locked out. Idempotent: safe to call on every save or upgrade.
 *
 * @param array<int, string> $roles Role slugs allowed to manage requests.
 */
function ayudawp_euw_apply_manager_roles( $roles ) {

	if ( ! function_exists( 'wp_roles' ) ) {
		return;
	}

	$caps  = ayudawp_euw_get_manager_caps();
	$roles = array_map( 'sanitize_key', (array) $roles );

	foreach ( array_keys( wp_roles()->roles ) as $slug ) {

		if ( 'administrator' === $slug ) {
			continue;
		}

		$role_obj = get_role( $slug );

		if ( ! $role_obj ) {
			continue;
		}

		$grant = in_array( $slug, $roles, true );

		foreach ( $caps as $cap ) {
			if ( $grant ) {
				$role_obj->add_cap( $cap );
			} else {
				$role_obj->remove_cap( $cap );
			}
		}
	}
}

/**
 * Ensure the administrator always holds the CPT capabilities and apply the
 * configured manager roles.
 *
 * The first time it runs it seeds the Permissions option: on a fresh install
 * ($seed_from_existing = false) it stays admin-only; on an upgrade from a version
 * that used generic post caps ($seed_from_existing = true) it seeds the roles
 * that could see requests before, so nobody loses access on update and the admin
 * can then tighten it. A one-shot flag keeps later upgrades from re-seeding.
 *
 * @param bool $seed_from_existing Whether to seed the option from legacy roles.
 */
function ayudawp_euw_setup_capabilities( $seed_from_existing = false ) {

	$admin = get_role( 'administrator' );

	if ( $admin ) {
		foreach ( ayudawp_euw_get_manager_caps() as $cap ) {
			$admin->add_cap( $cap );
		}
	}

	if ( '' === (string) get_option( 'ayudawp_euw_caps_setup_done', '' ) ) {

		$seed = $seed_from_existing ? ayudawp_euw_detect_legacy_manager_roles() : array();

		update_option( 'ayudawp_euw_manager_roles', $seed );
		update_option( 'ayudawp_euw_caps_setup_done', '1' );
	}

	ayudawp_euw_apply_manager_roles( (array) get_option( 'ayudawp_euw_manager_roles', array() ) );
}

/**
 * Re-apply the manager roles whenever the Permissions option changes.
 *
 * Hooked to both add_ and update_option so it fires on the first save too; it
 * reads the fresh value so the hook signatures do not matter.
 */
function ayudawp_euw_sync_manager_roles() {

	ayudawp_euw_apply_manager_roles( (array) get_option( 'ayudawp_euw_manager_roles', array() ) );
}
add_action( 'update_option_ayudawp_euw_manager_roles', 'ayudawp_euw_sync_manager_roles' );
add_action( 'add_option_ayudawp_euw_manager_roles', 'ayudawp_euw_sync_manager_roles' );
