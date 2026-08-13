<?php
/**
 * Uninstall handler.
 *
 * Removes plugin options, internal flags and the custom capabilities granted
 * to roles. Withdrawal request log entries (CPT) are preserved by default for
 * legal record-keeping. Set the AYUDAWP_EUW_DELETE_DATA constant to true in
 * wp-config.php to wipe everything, including stored requests.
 *
 * @package AyudaWP_EU_Withdrawal
 */

if ( ! defined( 'WP_UNINSTALL_PLUGIN' ) ) {
	exit;
}

// Remove plugin options: every registered setting plus the internal flags.
$ayudawp_euw_options = array(
	// General.
	'ayudawp_euw_notify_email',
	'ayudawp_euw_page_id',
	'ayudawp_euw_account_status_enabled',
	// Eligibility and deadline.
	'ayudawp_euw_allowed_statuses',
	'ayudawp_euw_accept_unmatched',
	'ayudawp_euw_deadline_basis',
	'ayudawp_euw_deadline_mode',
	'ayudawp_euw_grace_days',
	// Excluded-products notice.
	'ayudawp_euw_excluded_notice_enabled',
	// Checkout consents.
	'ayudawp_euw_consent_a_enabled',
	'ayudawp_euw_consent_a_text',
	'ayudawp_euw_consent_b_enabled',
	'ayudawp_euw_consent_b_text',
	'ayudawp_euw_consent_b_required',
	// Annex I.B model form.
	'ayudawp_euw_annex_b_enabled',
	'ayudawp_euw_trader_address',
	'ayudawp_euw_trader_email',
	'ayudawp_euw_trader_phone',
	// Public form.
	'ayudawp_euw_form_intro_enabled',
	'ayudawp_euw_form_intro_text',
	'ayudawp_euw_consumer_check_enabled',
	'ayudawp_euw_consumer_check_text',
	// Emails.
	'ayudawp_euw_from_name',
	'ayudawp_euw_from_email',
	'ayudawp_euw_status_email_body_accepted',
	'ayudawp_euw_status_email_body_rejected',
	'ayudawp_euw_status_email_body_completed',
	// Permissions.
	'ayudawp_euw_manager_roles',
	'ayudawp_euw_caps_setup_done',
	// Internal flags and legacy migrations.
	'ayudawp_euw_version',
	'ayudawp_euw_page_created_id',
	'ayudawp_euw_excluded_categories',
	'ayudawp_euw_excluded_categories_migrated',
);

foreach ( $ayudawp_euw_options as $ayudawp_euw_option ) {
	delete_option( $ayudawp_euw_option );
}

// Remove the custom capabilities the plugin granted to roles: they live in the
// wp_user_roles option, so they would otherwise outlive the plugin. On a
// reinstall the activation routine re-seeds them from scratch, admin-only.
if ( function_exists( 'wp_roles' ) ) {

	$ayudawp_euw_caps = array(
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

	foreach ( array_keys( wp_roles()->roles ) as $ayudawp_euw_role_slug ) {

		$ayudawp_euw_role = get_role( $ayudawp_euw_role_slug );

		if ( ! $ayudawp_euw_role ) {
			continue;
		}

		foreach ( $ayudawp_euw_caps as $ayudawp_euw_cap ) {
			$ayudawp_euw_role->remove_cap( $ayudawp_euw_cap );
		}
	}
}

// Optionally delete all withdrawal records.
if ( defined( 'AYUDAWP_EUW_DELETE_DATA' ) && AYUDAWP_EUW_DELETE_DATA ) {

	$posts = get_posts(
		array(
			'post_type'      => 'ayudawp_withdrawal',
			'post_status'    => 'any',
			'posts_per_page' => -1,
			'fields'         => 'ids',
		)
	);

	foreach ( $posts as $post_id ) {
		wp_delete_post( $post_id, true );
	}
}
