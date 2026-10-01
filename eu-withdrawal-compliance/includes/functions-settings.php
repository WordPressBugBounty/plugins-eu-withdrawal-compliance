<?php
/**
 * Settings page using the Settings API.
 *
 * @package AyudaWP_EU_Withdrawal
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Add the settings page as a submenu of the withdrawal CPT top-level menu.
 *
 * The plugin always exposes a top-level "Withdrawals" menu (registered by
 * the CPT itself) and hangs the settings page below it. The same menu
 * layout is used whether WooCommerce is active or not so admins always
 * find the plugin in the same place.
 */
function ayudawp_euw_register_settings_page() {

	add_submenu_page(
		'edit.php?post_type=ayudawp_withdrawal',
		__( 'EU Withdrawal settings', 'eu-withdrawal-compliance' ),
		__( 'Settings', 'eu-withdrawal-compliance' ),
		'manage_options',
		'ayudawp-euw-settings',
		'ayudawp_euw_settings_page_html'
	);
}
add_action( 'admin_menu', 'ayudawp_euw_register_settings_page' );

/**
 * Register settings, sections and fields.
 */
function ayudawp_euw_register_settings() {

	register_setting(
		'ayudawp_euw_settings_group',
		'ayudawp_euw_notify_email',
		array(
			'type'              => 'string',
			'sanitize_callback' => 'ayudawp_euw_sanitize_email_list',
			'default'           => get_option( 'admin_email' ),
		)
	);

	register_setting(
		'ayudawp_euw_settings_group',
		'ayudawp_euw_page_id',
		array(
			'type'              => 'integer',
			'sanitize_callback' => 'absint',
			'default'           => 0,
		)
	);

	register_setting(
		'ayudawp_euw_settings_group',
		'ayudawp_euw_account_status_enabled',
		array(
			'type'              => 'string',
			'sanitize_callback' => 'ayudawp_euw_sanitize_yes_no',
			'default'           => 'yes',
		)
	);

	register_setting(
		'ayudawp_euw_settings_group',
		'ayudawp_euw_grace_days',
		array(
			'type'              => 'integer',
			'sanitize_callback' => 'absint',
			'default'           => 0,
		)
	);

	register_setting(
		'ayudawp_euw_settings_group',
		'ayudawp_euw_deadline_basis',
		array(
			'type'              => 'string',
			'sanitize_callback' => 'ayudawp_euw_sanitize_deadline_basis',
			'default'           => 'order_date',
		)
	);

	register_setting(
		'ayudawp_euw_settings_group',
		'ayudawp_euw_allowed_statuses',
		array(
			'type'              => 'array',
			'sanitize_callback' => 'ayudawp_euw_sanitize_allowed_statuses',
			'default'           => array( 'processing', 'completed' ),
		)
	);

	register_setting(
		'ayudawp_euw_settings_group',
		'ayudawp_euw_accept_unmatched',
		array(
			'type'              => 'string',
			'sanitize_callback' => 'ayudawp_euw_sanitize_yes_no',
			'default'           => 'no',
		)
	);

	register_setting(
		'ayudawp_euw_settings_group',
		'ayudawp_euw_exclusions_in_orders',
		array(
			'type'              => 'string',
			'sanitize_callback' => 'ayudawp_euw_sanitize_yes_no',
			'default'           => 'yes',
		)
	);

	add_settings_section(
		'ayudawp_euw_main_section',
		__( 'General', 'eu-withdrawal-compliance' ),
		'ayudawp_euw_settings_section_callback',
		'ayudawp-euw-settings'
	);

	add_settings_field(
		'ayudawp_euw_notify_email',
		__( 'Notification email', 'eu-withdrawal-compliance' ),
		'ayudawp_euw_field_email_callback',
		'ayudawp-euw-settings',
		'ayudawp_euw_main_section'
	);

	add_settings_field(
		'ayudawp_euw_page_id',
		__( 'Withdrawal page', 'eu-withdrawal-compliance' ),
		'ayudawp_euw_field_page_callback',
		'ayudawp-euw-settings',
		'ayudawp_euw_main_section'
	);

	add_settings_field(
		'ayudawp_euw_account_status_enabled',
		__( 'Status in the customer account', 'eu-withdrawal-compliance' ),
		'ayudawp_euw_field_account_status_callback',
		'ayudawp-euw-settings',
		'ayudawp_euw_main_section'
	);

	add_settings_section(
		'ayudawp_euw_eligibility_section',
		__( 'Eligible order statuses', 'eu-withdrawal-compliance' ),
		'ayudawp_euw_eligibility_section_callback',
		'ayudawp-euw-settings'
	);

	add_settings_field(
		'ayudawp_euw_allowed_statuses',
		__( 'Show withdrawal option for', 'eu-withdrawal-compliance' ),
		'ayudawp_euw_field_allowed_statuses_callback',
		'ayudawp-euw-settings',
		'ayudawp_euw_eligibility_section'
	);

	add_settings_field(
		'ayudawp_euw_exclusions_in_orders',
		__( 'Orders with excluded products', 'eu-withdrawal-compliance' ),
		'ayudawp_euw_field_exclusions_in_orders_callback',
		'ayudawp-euw-settings',
		'ayudawp_euw_eligibility_section'
	);

	add_settings_field(
		'ayudawp_euw_accept_unmatched',
		__( 'Accept unmatched requests', 'eu-withdrawal-compliance' ),
		'ayudawp_euw_field_accept_unmatched_callback',
		'ayudawp-euw-settings',
		'ayudawp_euw_eligibility_section'
	);

	add_settings_section(
		'ayudawp_euw_deadline_section',
		__( 'Withdrawal deadline', 'eu-withdrawal-compliance' ),
		'ayudawp_euw_deadline_section_callback',
		'ayudawp-euw-settings'
	);

	add_settings_field(
		'ayudawp_euw_deadline_basis',
		__( 'Calculate deadline from', 'eu-withdrawal-compliance' ),
		'ayudawp_euw_field_deadline_basis_callback',
		'ayudawp-euw-settings',
		'ayudawp_euw_deadline_section'
	);

	add_settings_field(
		'ayudawp_euw_grace_days',
		__( 'Grace days', 'eu-withdrawal-compliance' ),
		'ayudawp_euw_field_grace_days_callback',
		'ayudawp-euw-settings',
		'ayudawp_euw_deadline_section'
	);

	add_settings_field(
		'ayudawp_euw_deadline_mode',
		__( 'Deadline enforcement', 'eu-withdrawal-compliance' ),
		'ayudawp_euw_field_deadline_mode_callback',
		'ayudawp-euw-settings',
		'ayudawp_euw_deadline_section'
	);

	add_settings_section(
		'ayudawp_euw_exclusions_section',
		__( 'Article 16 exclusions', 'eu-withdrawal-compliance' ),
		'ayudawp_euw_exclusions_section_callback',
		'ayudawp-euw-settings'
	);

	// Checkout consent settings (Art. 16(m) and Art. 14(4)(a)).
	register_setting(
		'ayudawp_euw_settings_group',
		'ayudawp_euw_consent_a_enabled',
		array(
			'type'              => 'string',
			'sanitize_callback' => 'ayudawp_euw_sanitize_yes_no',
			'default'           => 'yes',
		)
	);

	register_setting(
		'ayudawp_euw_settings_group',
		'ayudawp_euw_consent_a_text',
		array(
			'type'              => 'string',
			'sanitize_callback' => 'wp_kses_post',
			'default'           => '',
		)
	);

	register_setting(
		'ayudawp_euw_settings_group',
		'ayudawp_euw_consent_b_enabled',
		array(
			'type'              => 'string',
			'sanitize_callback' => 'ayudawp_euw_sanitize_yes_no',
			'default'           => 'yes',
		)
	);

	register_setting(
		'ayudawp_euw_settings_group',
		'ayudawp_euw_consent_b_required',
		array(
			'type'              => 'string',
			'sanitize_callback' => 'ayudawp_euw_sanitize_yes_no',
			'default'           => 'no',
		)
	);

	register_setting(
		'ayudawp_euw_settings_group',
		'ayudawp_euw_consent_b_text',
		array(
			'type'              => 'string',
			'sanitize_callback' => 'wp_kses_post',
			'default'           => '',
		)
	);

	// Annex I.B settings.
	register_setting(
		'ayudawp_euw_settings_group',
		'ayudawp_euw_annex_b_enabled',
		array(
			'type'              => 'string',
			'sanitize_callback' => 'ayudawp_euw_sanitize_yes_no',
			'default'           => 'yes',
		)
	);

	register_setting(
		'ayudawp_euw_settings_group',
		'ayudawp_euw_trader_address',
		array(
			'type'              => 'string',
			'sanitize_callback' => 'sanitize_textarea_field',
			'default'           => '',
		)
	);

	register_setting(
		'ayudawp_euw_settings_group',
		'ayudawp_euw_trader_phone',
		array(
			'type'              => 'string',
			'sanitize_callback' => 'sanitize_text_field',
			'default'           => '',
		)
	);

	// Excluded-product notice settings.
	register_setting(
		'ayudawp_euw_settings_group',
		'ayudawp_euw_excluded_notice_enabled',
		array(
			'type'              => 'string',
			'sanitize_callback' => 'ayudawp_euw_sanitize_yes_no',
			'default'           => 'yes',
		)
	);

	register_setting(
		'ayudawp_euw_settings_group',
		'ayudawp_euw_excluded_notice_title_art16m_digital',
		array(
			'type'              => 'string',
			'sanitize_callback' => 'sanitize_text_field',
			'default'           => '',
		)
	);

	register_setting(
		'ayudawp_euw_settings_group',
		'ayudawp_euw_excluded_notice_body_art16m_digital',
		array(
			'type'              => 'string',
			'sanitize_callback' => 'wp_kses_post',
			'default'           => '',
		)
	);

	register_setting(
		'ayudawp_euw_settings_group',
		'ayudawp_euw_excluded_notice_title_art16l_accommodation',
		array(
			'type'              => 'string',
			'sanitize_callback' => 'sanitize_text_field',
			'default'           => '',
		)
	);

	register_setting(
		'ayudawp_euw_settings_group',
		'ayudawp_euw_excluded_notice_body_art16l_accommodation',
		array(
			'type'              => 'string',
			'sanitize_callback' => 'wp_kses_post',
			'default'           => '',
		)
	);

	register_setting(
		'ayudawp_euw_settings_group',
		'ayudawp_euw_excluded_notice_title_art16_other',
		array(
			'type'              => 'string',
			'sanitize_callback' => 'sanitize_text_field',
			'default'           => '',
		)
	);

	register_setting(
		'ayudawp_euw_settings_group',
		'ayudawp_euw_excluded_notice_body_art16_other',
		array(
			'type'              => 'string',
			'sanitize_callback' => 'wp_kses_post',
			'default'           => '',
		)
	);

	// Withdrawal deadline enforcement mode (advisory by default; strict re-enables the gate).
	register_setting(
		'ayudawp_euw_settings_group',
		'ayudawp_euw_deadline_mode',
		array(
			'type'              => 'string',
			'sanitize_callback' => 'ayudawp_euw_sanitize_deadline_mode',
			'default'           => 'advisory',
		)
	);

	// Trader contact email shown in the Annex I.B model form.
	register_setting(
		'ayudawp_euw_settings_group',
		'ayudawp_euw_trader_email',
		array(
			'type'              => 'string',
			'sanitize_callback' => 'sanitize_email',
			'default'           => '',
		)
	);

	// Email sender overrides, applied only to this plugin's own emails.
	register_setting(
		'ayudawp_euw_settings_group',
		'ayudawp_euw_from_name',
		array(
			'type'              => 'string',
			'sanitize_callback' => 'sanitize_text_field',
			'default'           => '',
		)
	);

	register_setting(
		'ayudawp_euw_settings_group',
		'ayudawp_euw_from_email',
		array(
			'type'              => 'string',
			'sanitize_callback' => 'sanitize_email',
			'default'           => '',
		)
	);

	// Editable bodies for the status-change emails (empty falls back to default).
	register_setting(
		'ayudawp_euw_settings_group',
		'ayudawp_euw_status_email_body_accepted',
		array(
			'type'              => 'string',
			'sanitize_callback' => 'sanitize_textarea_field',
			'default'           => '',
		)
	);

	register_setting(
		'ayudawp_euw_settings_group',
		'ayudawp_euw_status_email_body_rejected',
		array(
			'type'              => 'string',
			'sanitize_callback' => 'sanitize_textarea_field',
			'default'           => '',
		)
	);

	register_setting(
		'ayudawp_euw_settings_group',
		'ayudawp_euw_status_email_body_completed',
		array(
			'type'              => 'string',
			'sanitize_callback' => 'sanitize_textarea_field',
			'default'           => '',
		)
	);

	// Public-form intro text: toggle plus editable copy (the legal note stays fixed).
	register_setting(
		'ayudawp_euw_settings_group',
		'ayudawp_euw_form_intro_enabled',
		array(
			'type'              => 'string',
			'sanitize_callback' => 'ayudawp_euw_sanitize_yes_no',
			'default'           => 'yes',
		)
	);

	register_setting(
		'ayudawp_euw_settings_group',
		'ayudawp_euw_form_intro_text',
		array(
			'type'              => 'string',
			'sanitize_callback' => 'sanitize_textarea_field',
			'default'           => '',
		)
	);

	// Optional B2B self-declaration checkbox on the public form (off by default).
	register_setting(
		'ayudawp_euw_settings_group',
		'ayudawp_euw_consumer_check_enabled',
		array(
			'type'              => 'string',
			'sanitize_callback' => 'ayudawp_euw_sanitize_yes_no',
			'default'           => 'no',
		)
	);

	register_setting(
		'ayudawp_euw_settings_group',
		'ayudawp_euw_consumer_check_text',
		array(
			'type'              => 'string',
			'sanitize_callback' => 'sanitize_textarea_field',
			'default'           => '',
		)
	);

	// Roles (besides administrator) allowed to view and manage requests.
	register_setting(
		'ayudawp_euw_settings_group',
		'ayudawp_euw_manager_roles',
		array(
			'type'              => 'array',
			'sanitize_callback' => 'ayudawp_euw_sanitize_manager_roles',
			'default'           => array(),
		)
	);

	// Harmonised EU legal guarantee notice (Implementing Regulation (EU) 2025/1960).
	register_setting(
		'ayudawp_euw_settings_group',
		'ayudawp_euw_guarantee_enabled',
		array(
			'type'              => 'string',
			'sanitize_callback' => 'ayudawp_euw_sanitize_yes_no',
			'default'           => 'no',
		)
	);

	register_setting(
		'ayudawp_euw_settings_group',
		'ayudawp_euw_guarantee_display',
		array(
			'type'              => 'string',
			'sanitize_callback' => 'ayudawp_euw_sanitize_guarantee_display',
			'default'           => 'full',
		)
	);

	register_setting(
		'ayudawp_euw_settings_group',
		'ayudawp_euw_guarantee_page_id',
		array(
			'type'              => 'integer',
			'sanitize_callback' => 'absint',
			'default'           => 0,
		)
	);

	register_setting(
		'ayudawp_euw_settings_group',
		'ayudawp_euw_guarantee_email_ids',
		array(
			'type'              => 'array',
			'sanitize_callback' => 'ayudawp_euw_sanitize_guarantee_email_ids',
			'default'           => array( 'customer_on_hold_order', 'customer_processing_order' ),
		)
	);

	register_setting(
		'ayudawp_euw_settings_group',
		'ayudawp_euw_guarantee_pdf_attach',
		array(
			'type'              => 'string',
			'sanitize_callback' => 'ayudawp_euw_sanitize_yes_no',
			'default'           => 'yes',
		)
	);

	register_setting(
		'ayudawp_euw_settings_group',
		'ayudawp_euw_guarantee_es_note',
		array(
			'type'              => 'string',
			'sanitize_callback' => 'ayudawp_euw_sanitize_guarantee_es_note',
			'default'           => 'auto',
		)
	);

	register_setting(
		'ayudawp_euw_settings_group',
		'ayudawp_euw_guarantee_terms_anchor',
		array(
			'type'              => 'string',
			'sanitize_callback' => 'sanitize_title',
			'default'           => '',
		)
	);

	register_setting(
		'ayudawp_euw_settings_group',
		'ayudawp_euw_guarantee_order_note',
		array(
			'type'              => 'string',
			'sanitize_callback' => 'ayudawp_euw_sanitize_yes_no',
			'default'           => 'yes',
		)
	);

	register_setting(
		'ayudawp_euw_settings_group',
		'ayudawp_euw_guarantee_fallback_lang',
		array(
			'type'              => 'string',
			'sanitize_callback' => 'ayudawp_euw_sanitize_guarantee_lang',
			'default'           => 'en',
		)
	);

	register_setting(
		'ayudawp_euw_settings_group',
		'ayudawp_euw_guarantee_custom_files',
		array(
			'type'              => 'array',
			'sanitize_callback' => 'ayudawp_euw_sanitize_guarantee_custom_files',
			'default'           => array(),
		)
	);

	// New sections (rendered after the existing General/Eligibility/Deadline/Exclusions sections).
	add_settings_section(
		'ayudawp_euw_consent_section',
		__( 'Checkout consent', 'eu-withdrawal-compliance' ),
		'ayudawp_euw_consent_section_callback',
		'ayudawp-euw-settings'
	);

	add_settings_field(
		'ayudawp_euw_consent_a_enabled',
		__( 'Digital content consent', 'eu-withdrawal-compliance' ),
		'ayudawp_euw_field_consent_a_callback',
		'ayudawp-euw-settings',
		'ayudawp_euw_consent_section'
	);

	add_settings_field(
		'ayudawp_euw_consent_b_enabled',
		__( 'Service-start consent', 'eu-withdrawal-compliance' ),
		'ayudawp_euw_field_consent_b_callback',
		'ayudawp-euw-settings',
		'ayudawp_euw_consent_section'
	);

	add_settings_section(
		'ayudawp_euw_annex_b_section',
		__( 'Model withdrawal form', 'eu-withdrawal-compliance' ),
		'ayudawp_euw_annex_b_section_callback',
		'ayudawp-euw-settings'
	);

	add_settings_field(
		'ayudawp_euw_annex_b_enabled',
		__( 'Show model form', 'eu-withdrawal-compliance' ),
		'ayudawp_euw_field_annex_b_enabled_callback',
		'ayudawp-euw-settings',
		'ayudawp_euw_annex_b_section'
	);

	add_settings_field(
		'ayudawp_euw_trader_address',
		__( 'Trader postal address', 'eu-withdrawal-compliance' ),
		'ayudawp_euw_field_trader_address_callback',
		'ayudawp-euw-settings',
		'ayudawp_euw_annex_b_section'
	);

	add_settings_field(
		'ayudawp_euw_trader_phone',
		__( 'Trader phone (optional)', 'eu-withdrawal-compliance' ),
		'ayudawp_euw_field_trader_phone_callback',
		'ayudawp-euw-settings',
		'ayudawp_euw_annex_b_section'
	);

	add_settings_field(
		'ayudawp_euw_trader_email',
		__( 'Trader contact email', 'eu-withdrawal-compliance' ),
		'ayudawp_euw_field_trader_email_callback',
		'ayudawp-euw-settings',
		'ayudawp_euw_annex_b_section'
	);

	add_settings_section(
		'ayudawp_euw_excluded_notice_section',
		__( 'Excluded products notice', 'eu-withdrawal-compliance' ),
		'ayudawp_euw_excluded_notice_section_callback',
		'ayudawp-euw-settings'
	);

	add_settings_field(
		'ayudawp_euw_excluded_notice_enabled',
		__( 'Show notice on excluded products', 'eu-withdrawal-compliance' ),
		'ayudawp_euw_field_excluded_notice_enabled_callback',
		'ayudawp-euw-settings',
		'ayudawp_euw_excluded_notice_section'
	);

	add_settings_field(
		'ayudawp_euw_excluded_notice_digital',
		__( 'Notice for digital content (Art. 16(m))', 'eu-withdrawal-compliance' ),
		'ayudawp_euw_field_excluded_notice_digital_callback',
		'ayudawp-euw-settings',
		'ayudawp_euw_excluded_notice_section'
	);

	add_settings_field(
		'ayudawp_euw_excluded_notice_accommodation',
		__( 'Notice for dated services (Art. 16(l))', 'eu-withdrawal-compliance' ),
		'ayudawp_euw_field_excluded_notice_accommodation_callback',
		'ayudawp-euw-settings',
		'ayudawp_euw_excluded_notice_section'
	);

	add_settings_field(
		'ayudawp_euw_excluded_notice_other',
		__( 'Notice for other Article 16 exceptions', 'eu-withdrawal-compliance' ),
		'ayudawp_euw_field_excluded_notice_other_callback',
		'ayudawp-euw-settings',
		'ayudawp_euw_excluded_notice_section'
	);

	add_settings_section(
		'ayudawp_euw_guarantee_section',
		__( 'EU legal guarantee notice (GARAN)', 'eu-withdrawal-compliance' ),
		'ayudawp_euw_guarantee_section_callback',
		'ayudawp-euw-settings'
	);

	add_settings_field(
		'ayudawp_euw_guarantee_enabled',
		__( 'Show the harmonised notice', 'eu-withdrawal-compliance' ),
		'ayudawp_euw_field_guarantee_enabled_callback',
		'ayudawp-euw-settings',
		'ayudawp_euw_guarantee_section'
	);

	add_settings_field(
		'ayudawp_euw_guarantee_display',
		__( 'How it shows at the checkout', 'eu-withdrawal-compliance' ),
		'ayudawp_euw_field_guarantee_display_callback',
		'ayudawp-euw-settings',
		'ayudawp_euw_guarantee_section'
	);

	add_settings_field(
		'ayudawp_euw_guarantee_page_id',
		__( 'Legal guarantee page', 'eu-withdrawal-compliance' ),
		'ayudawp_euw_field_guarantee_page_callback',
		'ayudawp-euw-settings',
		'ayudawp_euw_guarantee_section'
	);

	add_settings_field(
		'ayudawp_euw_guarantee_email_ids',
		__( 'Emails that carry it', 'eu-withdrawal-compliance' ),
		'ayudawp_euw_field_guarantee_emails_callback',
		'ayudawp-euw-settings',
		'ayudawp_euw_guarantee_section'
	);

	add_settings_field(
		'ayudawp_euw_guarantee_es_note',
		__( 'Spanish three-year note', 'eu-withdrawal-compliance' ),
		'ayudawp_euw_field_guarantee_es_note_callback',
		'ayudawp-euw-settings',
		'ayudawp_euw_guarantee_section'
	);

	add_settings_field(
		'ayudawp_euw_guarantee_order_note',
		__( 'Record it on the order', 'eu-withdrawal-compliance' ),
		'ayudawp_euw_field_guarantee_order_note_callback',
		'ayudawp-euw-settings',
		'ayudawp_euw_guarantee_section'
	);

	add_settings_field(
		'ayudawp_euw_guarantee_fallback_lang',
		__( 'Language used as a fallback', 'eu-withdrawal-compliance' ),
		'ayudawp_euw_field_guarantee_fallback_callback',
		'ayudawp-euw-settings',
		'ayudawp_euw_guarantee_section'
	);

	add_settings_field(
		'ayudawp_euw_guarantee_custom_files',
		__( 'Replace the bundled files', 'eu-withdrawal-compliance' ),
		'ayudawp_euw_field_guarantee_custom_files_callback',
		'ayudawp-euw-settings',
		'ayudawp_euw_guarantee_section'
	);

	add_settings_section(
		'ayudawp_euw_emails_section',
		__( 'Withdrawal emails', 'eu-withdrawal-compliance' ),
		'ayudawp_euw_emails_section_callback',
		'ayudawp-euw-settings'
	);

	add_settings_field(
		'ayudawp_euw_from_name',
		__( 'From name', 'eu-withdrawal-compliance' ),
		'ayudawp_euw_field_from_name_callback',
		'ayudawp-euw-settings',
		'ayudawp_euw_emails_section'
	);

	add_settings_field(
		'ayudawp_euw_from_email',
		__( 'From address', 'eu-withdrawal-compliance' ),
		'ayudawp_euw_field_from_email_callback',
		'ayudawp-euw-settings',
		'ayudawp_euw_emails_section'
	);

	add_settings_field(
		'ayudawp_euw_status_email_body_accepted',
		__( 'Accepted email text', 'eu-withdrawal-compliance' ),
		'ayudawp_euw_field_status_email_accepted_callback',
		'ayudawp-euw-settings',
		'ayudawp_euw_emails_section'
	);

	add_settings_field(
		'ayudawp_euw_status_email_body_rejected',
		__( 'Rejected email text', 'eu-withdrawal-compliance' ),
		'ayudawp_euw_field_status_email_rejected_callback',
		'ayudawp-euw-settings',
		'ayudawp_euw_emails_section'
	);

	add_settings_field(
		'ayudawp_euw_status_email_body_completed',
		__( 'Completed email text', 'eu-withdrawal-compliance' ),
		'ayudawp_euw_field_status_email_completed_callback',
		'ayudawp-euw-settings',
		'ayudawp_euw_emails_section'
	);

	add_settings_section(
		'ayudawp_euw_form_section',
		__( 'Public withdrawal form', 'eu-withdrawal-compliance' ),
		'ayudawp_euw_form_section_callback',
		'ayudawp-euw-settings'
	);

	add_settings_field(
		'ayudawp_euw_form_intro_text',
		__( 'Intro text', 'eu-withdrawal-compliance' ),
		'ayudawp_euw_field_form_intro_callback',
		'ayudawp-euw-settings',
		'ayudawp_euw_form_section'
	);

	add_settings_field(
		'ayudawp_euw_consumer_check_enabled',
		__( 'Consumer self-declaration', 'eu-withdrawal-compliance' ),
		'ayudawp_euw_field_consumer_check_callback',
		'ayudawp-euw-settings',
		'ayudawp_euw_form_section'
	);

	add_settings_section(
		'ayudawp_euw_permissions_section',
		__( 'Permissions', 'eu-withdrawal-compliance' ),
		'ayudawp_euw_permissions_section_callback',
		'ayudawp-euw-settings'
	);

	add_settings_field(
		'ayudawp_euw_manager_roles',
		__( 'Roles that manage requests', 'eu-withdrawal-compliance' ),
		'ayudawp_euw_field_manager_roles_callback',
		'ayudawp-euw-settings',
		'ayudawp_euw_permissions_section'
	);

	add_settings_section(
		'ayudawp_euw_link_visibility_section',
		__( 'Withdrawal link visibility', 'eu-withdrawal-compliance' ),
		'ayudawp_euw_link_visibility_section_callback',
		'ayudawp-euw-settings'
	);

	// Drop a submitted text that is identical to the bundled default, so the
	// setting stays empty and the text keeps following the language of the
	// visitor. It runs after the sanitize callback registered above, which
	// register_setting() hooks at priority 10 (and with a single argument, so
	// the option name has to be picked up from a filter of our own).
	foreach ( array_keys( ayudawp_euw_editable_text_defaults() ) as $editable_option ) {
		add_filter( "sanitize_option_{$editable_option}", 'ayudawp_euw_discard_default_text', 20, 2 );
	}

	// The fields of these settings are not printed while WooCommerce is inactive,
	// so the form sends nothing for them. Keep what is stored instead of reading
	// that as "switched off".
	foreach ( ayudawp_euw_woocommerce_only_settings() as $woocommerce_option ) {
		add_filter( "pre_update_option_{$woocommerce_option}", 'ayudawp_euw_keep_setting_without_woocommerce', 10, 2 );
	}
}
add_action( 'admin_init', 'ayudawp_euw_register_settings' );

/**
 * Sanitize an option that only accepts the literal values "yes" or "no".
 *
 * @param mixed $value Submitted value.
 * @return string Either 'yes' or 'no'.
 */
function ayudawp_euw_sanitize_yes_no( $value ) {

	return 'yes' === $value ? 'yes' : 'no';
}

/**
 * Settings whose field is only printed while WooCommerce is active.
 *
 * Their field callbacks print a note and return when WooCommerce is missing, so
 * the settings form sends nothing for them. The names are spelled out one by one
 * on purpose, so the list can be searched and compared with the callbacks.
 * Whoever adds a field that bails out without WooCommerce has to add its option
 * here, or saving the page with WooCommerce off wipes it.
 *
 * @return array<int, string> Option names.
 */
function ayudawp_euw_woocommerce_only_settings() {

	return array(
		'ayudawp_euw_account_status_enabled',
		'ayudawp_euw_allowed_statuses',
		'ayudawp_euw_exclusions_in_orders',
		'ayudawp_euw_accept_unmatched',
		'ayudawp_euw_consent_a_enabled',
		'ayudawp_euw_consent_a_text',
		'ayudawp_euw_consent_b_enabled',
		'ayudawp_euw_consent_b_required',
		'ayudawp_euw_consent_b_text',
		'ayudawp_euw_excluded_notice_enabled',
		'ayudawp_euw_excluded_notice_title_art16m_digital',
		'ayudawp_euw_excluded_notice_body_art16m_digital',
		'ayudawp_euw_excluded_notice_title_art16l_accommodation',
		'ayudawp_euw_excluded_notice_body_art16l_accommodation',
		'ayudawp_euw_excluded_notice_title_art16_other',
		'ayudawp_euw_excluded_notice_body_art16_other',
		'ayudawp_euw_guarantee_email_ids',
		'ayudawp_euw_guarantee_pdf_attach',
		'ayudawp_euw_guarantee_order_note',
	);
}

/**
 * Keep a WooCommerce-only setting as it was when the page is saved without WooCommerce.
 *
 * The settings page is one form posted to wp-admin/options.php, which hands null
 * to update_option() for every option of the group that the form did not send.
 * A field that was not printed is not a field the trader emptied, but the
 * sanitizers cannot tell the two apart and turn that null into 'no', an empty
 * array or an empty string. Returning the previous value here makes
 * update_option() leave the option alone, because it returns early when nothing
 * changes.
 *
 * It only acts on options.php, so code or WP-CLI changing one of these options
 * while WooCommerce is off still works. It is hooked on
 * `pre_update_option_{$option}` because that filter is handed the previous value,
 * the stored one or the default given to register_setting(), which a sanitize
 * callback never sees.
 *
 * @param mixed $value     New value, already sanitized.
 * @param mixed $old_value Previous value.
 * @return mixed The previous value when the settings form is saved without WooCommerce, the new one otherwise.
 */
function ayudawp_euw_keep_setting_without_woocommerce( $value, $old_value ) {

	if ( class_exists( 'WooCommerce' ) ) {
		return $value;
	}

	global $pagenow;

	return ( 'options.php' === $pagenow ) ? $old_value : $value;
}

/**
 * Map every setting that holds customer-facing copy to its bundled default.
 *
 * Each of these texts ships as a regular translatable string and is only stored
 * as an option when the trader writes their own wording. Keeping the map in one
 * place is what lets the editors show the default as a placeholder instead of
 * pre-filling it, the sanitizer discard a value identical to it, and the upgrade
 * routine clean up installs that stored one before 2.1.2.
 *
 * The default providers live in the modules that own each text and are loaded
 * after this file, so they are guarded: the map is always built at runtime.
 *
 * @return array<string, string> Option name => bundled default text.
 */
function ayudawp_euw_editable_text_defaults() {

	$defaults = array();

	if ( function_exists( 'ayudawp_euw_form_intro_default' ) ) {
		$defaults['ayudawp_euw_form_intro_text'] = ayudawp_euw_form_intro_default();
	}

	if ( function_exists( 'ayudawp_euw_consumer_check_default_text' ) ) {
		$defaults['ayudawp_euw_consumer_check_text'] = ayudawp_euw_consumer_check_default_text();
	}

	if ( function_exists( 'ayudawp_euw_consent_a_default_text' ) ) {
		$defaults['ayudawp_euw_consent_a_text'] = ayudawp_euw_consent_a_default_text();
	}

	if ( function_exists( 'ayudawp_euw_consent_b_default_text' ) ) {
		$defaults['ayudawp_euw_consent_b_text'] = ayudawp_euw_consent_b_default_text();
	}

	if ( function_exists( 'ayudawp_euw_excluded_notice_defaults' ) ) {
		foreach ( ayudawp_euw_excluded_notice_defaults() as $status => $texts ) {
			$defaults[ 'ayudawp_euw_excluded_notice_title_' . $status ] = $texts['title'];
			$defaults[ 'ayudawp_euw_excluded_notice_body_' . $status ]  = $texts['body'];
		}
	}

	if ( function_exists( 'ayudawp_euw_status_email_defaults' ) ) {
		foreach ( ayudawp_euw_status_email_defaults() as $status => $body ) {
			$defaults[ 'ayudawp_euw_status_email_body_' . $status ] = $body;
		}
	}

	return $defaults;
}

/**
 * Bundled default text for a single editable setting.
 *
 * @param string $option Option name.
 * @return string Default text, or an empty string when the option has none.
 */
function ayudawp_euw_editable_text_default( $option ) {

	$defaults = ayudawp_euw_editable_text_defaults();

	return isset( $defaults[ $option ] ) ? $defaults[ $option ] : '';
}

/**
 * Discard a submitted text that is identical to the bundled default.
 *
 * Storing the default verbatim looks harmless but freezes the text in the
 * language the admin happened to be using: every reader of these settings
 * prints the stored value as is, so the string stops going through translation
 * and shows in that one language on a multilingual shop. Saving an empty value
 * instead keeps the setting on the bundled string, which follows the language
 * of each visitor.
 *
 * Hooked on `sanitize_option_{$option}` at priority 20, after the sanitize
 * callback of each setting, and with two arguments so it knows which option it
 * is looking at. The value arrives already unslashed and trimmed, because
 * wp-admin/options.php does both before calling update_option().
 *
 * @param mixed  $value  Already sanitized value.
 * @param string $option Option name being sanitized.
 * @return mixed The value, or an empty string when it matches the default.
 */
function ayudawp_euw_discard_default_text( $value, $option ) {

	$default = ayudawp_euw_editable_text_default( (string) $option );

	if ( '' === $default ) {
		return $value;
	}

	return ( trim( (string) $value ) === trim( $default ) ) ? '' : $value;
}

/**
 * Clear settings that only hold a copy of a bundled default text.
 *
 * Until 2.1.2 the editors pre-filled each textarea with the bundled default, so
 * the first "Save changes" on the settings page stored it verbatim, whatever the
 * setting was actually being changed. From then on the text was printed as
 * stored and no longer followed the language of the visitor, which on a
 * multilingual shop left those texts stuck in the language of the admin who
 * saved. This restores the fallback on installs that went through that.
 *
 * Compares against the default in every locale the site has installed plus the
 * original English, which covers the language the admin could have been using.
 * A text the trader actually wrote never matches and is left untouched.
 */
function ayudawp_euw_clear_stored_default_texts() {

	$locales = array_unique( array_merge( array( 'en_US', get_locale() ), get_available_languages() ) );

	foreach ( $locales as $locale ) {

		$switched = switch_to_locale( $locale );
		$defaults = ayudawp_euw_editable_text_defaults();

		if ( $switched ) {
			restore_previous_locale();
		}

		foreach ( $defaults as $option => $default ) {

			$stored = trim( (string) get_option( $option, '' ) );

			if ( '' !== $stored && $stored === trim( $default ) ) {
				update_option( $option, '' );
			}
		}
	}
}

/**
 * Sanitize a comma-separated list of notification emails.
 *
 * Splits on commas, validates each address with is_email() and drops the
 * invalid ones, so a typo in one address never discards the rest. Returns the
 * cleaned addresses joined back with ", " (de-duplicated, case-insensitively);
 * an empty result lets the notification fall back to the site admin email.
 *
 * @param mixed $value Submitted value (one address or a comma-separated list).
 * @return string Comma-separated list of valid emails, possibly empty.
 */
function ayudawp_euw_sanitize_email_list( $value ) {

	$emails = array();

	foreach ( explode( ',', (string) $value ) as $candidate ) {

		$candidate = sanitize_email( trim( $candidate ) );

		if ( '' !== $candidate && is_email( $candidate ) ) {
			$emails[ strtolower( $candidate ) ] = $candidate;
		}
	}

	return implode( ', ', $emails );
}

/**
 * Sanitize the manager-roles option to a list of valid, non-administrator roles.
 *
 * The administrator is always granted in code and is never stored here, so it
 * can never be unticked and locked out.
 *
 * @param mixed $value Submitted value.
 * @return array<int, string>
 */
function ayudawp_euw_sanitize_manager_roles( $value ) {

	$value = is_array( $value ) ? $value : array();
	$roles = function_exists( 'wp_roles' ) ? wp_roles()->roles : array();
	$valid = array();

	foreach ( $value as $slug ) {

		$slug = sanitize_key( (string) $slug );

		if ( '' === $slug || 'administrator' === $slug ) {
			continue;
		}

		if ( isset( $roles[ $slug ] ) ) {
			$valid[ $slug ] = $slug;
		}
	}

	return array_values( $valid );
}

/**
 * Section description.
 */
function ayudawp_euw_settings_section_callback() {

	echo '<p>' . esc_html__( 'Configure where notifications are sent and which page hosts the withdrawal form.', 'eu-withdrawal-compliance' ) . '</p>';
}

/**
 * Email field callback.
 */
function ayudawp_euw_field_email_callback() {

	$value = get_option( 'ayudawp_euw_notify_email', get_option( 'admin_email' ) );

	printf(
		'<input type="email" name="ayudawp_euw_notify_email" value="%s" class="regular-text" multiple>',
		esc_attr( $value )
	);

	echo '<p class="description">' . wp_kses( __( '<strong>Recommended.</strong> Address that receives a notification each time a customer submits a withdrawal request. Separate several addresses with commas to notify more than one recipient. Defaults to the site admin email if left empty.', 'eu-withdrawal-compliance' ), array( 'strong' => array() ) ) . '</p>';
}

/**
 * Page selector field callback.
 */
function ayudawp_euw_field_page_callback() {

	$selected = (int) get_option( 'ayudawp_euw_page_id', 0 );

	echo wp_kses(
		wp_dropdown_pages(
			array(
				'name'              => 'ayudawp_euw_page_id',
				'show_option_none'  => __( '— Select a page —', 'eu-withdrawal-compliance' ),
				'option_none_value' => '0',
				'selected'          => $selected,
				'echo'              => 0,
			)
		),
		array(
			'select' => array(
				'name'  => true,
				'id'    => true,
				'class' => true,
			),
			'option' => array(
				'class'    => true,
				'value'    => true,
				'selected' => true,
			),
		)
	);

	echo '<p class="description">' . wp_kses( __( '<strong>Mandatory.</strong> Page where the withdrawal form is published. Make sure it includes the <code>[ayudawp_withdrawal_form]</code> shortcode. The plugin creates one automatically on activation.', 'eu-withdrawal-compliance' ), array( 'strong' => array(), 'code' => array() ) ) . '</p>';

	$problem = ayudawp_euw_get_page_problem( $selected );

	if ( '' !== $problem ) {
		echo '<p class="ayudawp-euw-page-warning"><span class="dashicons dashicons-warning" aria-hidden="true"></span> ' . esc_html( $problem ) . '</p>';
	}

	if ( ayudawp_euw_page_is_untouched_template( $selected ) ) {

		$notice = sprintf(
			/* translators: %s: URL of the page editor. */
			__( 'This page still holds the text the plugin wrote on activation. It is a sample: <a href="%s">review it with your legal advisor</a> and delete the sections that do not apply to your shop.', 'eu-withdrawal-compliance' ),
			esc_url( (string) get_edit_post_link( $selected ) )
		);

		echo '<p class="ayudawp-euw-page-warning"><span class="dashicons dashicons-info-outline" aria-hidden="true"></span> ' . wp_kses( $notice, array( 'a' => array( 'href' => array() ) ) ) . '</p>';
	}
}

/**
 * Whether the configured page is the bundled template, still unedited.
 *
 * The sample text used to carry its own "review before publishing" paragraph in
 * the page content, which meant it was published to customers whenever nobody
 * deleted it. The reminder lives here instead, and disappears on its own the
 * first time the page is saved.
 *
 * @param int $page_id Configured page ID.
 * @return bool
 */
function ayudawp_euw_page_is_untouched_template( $page_id ) {

	$page_id = absint( $page_id );

	if ( ! $page_id || $page_id !== absint( get_option( 'ayudawp_euw_page_created_id', 0 ) ) ) {
		return false;
	}

	$page = get_post( $page_id );

	if ( ! $page ) {
		return false;
	}

	// wp_insert_post() stamps both dates alike, so any later save moves the
	// modified one. Cheaper and more reliable than diffing the content against
	// a template that is translated per locale.
	return $page->post_modified_gmt === $page->post_date_gmt;
}

/**
 * Describe what is wrong with the configured withdrawal page, if anything.
 *
 * A page that was trashed, unpublished or emptied of the shortcode fails
 * silently: every link the plugin prints keeps pointing at it and the customer
 * lands somewhere without a form, which is the one thing Article 11a asks the
 * trader to make easy to find. Reported next to the selector, without blocking
 * the save: the shop may be mid-rebuild.
 *
 * @param int $page_id Configured page ID.
 * @return string Human-readable problem, or an empty string when all is well.
 */
function ayudawp_euw_get_page_problem( $page_id ) {

	$page_id = absint( $page_id );

	if ( ! $page_id ) {
		return __( 'No page selected yet, so the links to the withdrawal form (emails, product notices, the model form) have nowhere to point.', 'eu-withdrawal-compliance' );
	}

	$page = get_post( $page_id );

	if ( ! $page || 'page' !== $page->post_type ) {
		return __( 'The selected page no longer exists. Pick another one, or let the plugin create a new page.', 'eu-withdrawal-compliance' );
	}

	if ( 'trash' === $page->post_status ) {
		return __( 'The selected page is in the trash, so customers following a withdrawal link reach a page not found.', 'eu-withdrawal-compliance' );
	}

	if ( 'publish' !== $page->post_status ) {
		return __( 'The selected page is not published, so only logged-in editors can see the form.', 'eu-withdrawal-compliance' );
	}

	// Page builders keep their layout elsewhere and leave post_content empty or
	// full of their own markup, so an absent shortcode there is not proof that
	// the form is missing. Only flag it when the content is plain and has none.
	if ( '' !== trim( $page->post_content ) && ! has_shortcode( $page->post_content, 'ayudawp_withdrawal_form' ) ) {
		return __( 'The selected page does not contain the [ayudawp_withdrawal_form] shortcode. If you build it with a page builder or a block that renders the form, ignore this notice.', 'eu-withdrawal-compliance' );
	}

	return '';
}

/**
 * "Status in the customer account" field callback.
 */
function ayudawp_euw_field_account_status_callback() {

	if ( ! class_exists( 'WooCommerce' ) ) {
		echo '<p class="description">' . esc_html__( 'WooCommerce is not active, so there is no customer account where the status could be shown. Customers still receive the acknowledgement of receipt and every status change by email.', 'eu-withdrawal-compliance' ) . '</p>';
		return;
	}

	$enabled = get_option( 'ayudawp_euw_account_status_enabled', 'yes' );

	?>
	<label>
		<input type="checkbox" name="ayudawp_euw_account_status_enabled" value="yes" <?php checked( 'yes', $enabled ); ?>>
		<?php esc_html_e( 'Let customers follow their withdrawal requests from My Account', 'eu-withdrawal-compliance' ); ?>
	</label>
	<p class="description">
		<?php
		echo wp_kses(
			__( '<strong>Recommended.</strong> Lists the customer\'s own requests, with their status and the note you write when resolving them, at the top of the "Right of withdrawal" tab, and replaces the withdrawal button of an order that already has a request with its current status. Unticked, a submitted request only leaves a trace in the emails the customer receives.', 'eu-withdrawal-compliance' ),
			array( 'strong' => array() )
		);
		?>
	</p>
	<?php
}

/**
 * Eligibility section description.
 */
function ayudawp_euw_eligibility_section_callback() {

	echo '<p>' . esc_html__( 'Pick the WooCommerce order statuses for which the withdrawal button and email notice should be offered. The choice applies to both the "My Account" button and the notice injected into transactional emails. Unchecking every status disables the prompt without disabling the plugin.', 'eu-withdrawal-compliance' ) . '</p>';
}

/**
 * Sanitize the allowed statuses option.
 *
 * Accepts both prefixed (`wc-processing`) and unprefixed (`processing`) keys
 * and stores the unprefixed form, aligned with `WC_Order::get_status()`.
 *
 * @param mixed $value Submitted value.
 * @return array<int, string>
 */
function ayudawp_euw_sanitize_allowed_statuses( $value ) {

	if ( ! is_array( $value ) ) {
		return array();
	}

	$valid_keys = function_exists( 'wc_get_order_statuses' )
		? array_map(
			static function ( $key ) {
				return 0 === strpos( $key, 'wc-' ) ? substr( $key, 3 ) : $key;
			},
			array_keys( wc_get_order_statuses() )
		)
		: array();

	$normalized = array();

	foreach ( $value as $candidate ) {

		$candidate = sanitize_key( (string) $candidate );

		if ( 0 === strpos( $candidate, 'wc-' ) ) {
			$candidate = substr( $candidate, 3 );
		}

		if ( '' === $candidate ) {
			continue;
		}

		if ( ! empty( $valid_keys ) && ! in_array( $candidate, $valid_keys, true ) ) {
			continue;
		}

		$normalized[ $candidate ] = $candidate;
	}

	return array_values( $normalized );
}

/**
 * Allowed statuses checkbox list callback.
 */
function ayudawp_euw_field_allowed_statuses_callback() {

	if ( ! function_exists( 'wc_get_order_statuses' ) ) {
		echo '<p class="description">' . esc_html__( 'WooCommerce is not active, so there are no order statuses to choose from.', 'eu-withdrawal-compliance' ) . '</p>';
		return;
	}

	$statuses = wc_get_order_statuses();
	$selected = (array) get_option( 'ayudawp_euw_allowed_statuses', array( 'processing', 'completed' ) );

	echo '<fieldset>';
	echo '<legend class="screen-reader-text">' . esc_html__( 'Order statuses eligible for withdrawal', 'eu-withdrawal-compliance' ) . '</legend>';

	foreach ( $statuses as $key => $label ) {

		$slug = 0 === strpos( $key, 'wc-' ) ? substr( $key, 3 ) : $key;

		printf(
			'<label style="display:block; margin-bottom:4px;"><input type="checkbox" name="ayudawp_euw_allowed_statuses[]" value="%1$s" %2$s> %3$s (<code>%1$s</code>)</label>',
			esc_attr( $slug ),
			checked( in_array( $slug, $selected, true ), true, false ),
			esc_html( $label )
		);
	}

	echo '</fieldset>';

	echo '<p class="description">' . wp_kses( __( '<strong>Recommended.</strong> Defaults to Processing and Completed. Plugins that register additional statuses (e.g. shipping plugins) appear here automatically.', 'eu-withdrawal-compliance' ), array( 'strong' => array() ) ) . '</p>';

	// A sentence of its own, so the one above keeps its translations.
	echo '<p class="description">' . esc_html__( 'These statuses decide every place the withdrawal is offered: the button in My Account, the form, and the notice in the WooCommerce email sent when an order reaches On hold, Processing, Completed or Refunded.', 'eu-withdrawal-compliance' ) . '</p>';
}

/**
 * "Orders with excluded products" checkbox callback.
 */
function ayudawp_euw_field_exclusions_in_orders_callback() {

	if ( ! class_exists( 'WooCommerce' ) ) {
		echo '<p class="description">' . esc_html__( 'WooCommerce is not active, so there are no orders this option could apply to.', 'eu-withdrawal-compliance' ) . '</p>';
		return;
	}

	$enabled = get_option( 'ayudawp_euw_exclusions_in_orders', 'yes' );

	?>
	<fieldset>
		<label>
			<input type="checkbox" name="ayudawp_euw_exclusions_in_orders" value="yes" <?php checked( 'yes', $enabled ); ?>>
			<?php esc_html_e( 'Leave excluded products out of what an order offers', 'eu-withdrawal-compliance' ); ?>
		</label>
		<p class="description">
			<?php
			echo wp_kses(
				__( '<strong>Recommended.</strong> An order that only holds products excluded from the right of withdrawal shows neither the withdrawal button nor the notice in its emails, and an order that mixes both names the excluded ones in that notice. The withdrawal form keeps accepting a request for any order and flags it for your review. Untick it if you grant the withdrawal on excluded products too.', 'eu-withdrawal-compliance' ),
				array( 'strong' => array() )
			);
			?>
		</p>
	</fieldset>
	<?php
}

/**
 * Accept-unmatched-requests checkbox callback.
 */
function ayudawp_euw_field_accept_unmatched_callback() {

	if ( ! function_exists( 'wc_get_order' ) ) {
		echo '<p class="description">' . esc_html__( 'WooCommerce is not active. Without an order database to check against, every request is registered as-is, so this option does not apply.', 'eu-withdrawal-compliance' ) . '</p>';
		return;
	}

	$enabled = get_option( 'ayudawp_euw_accept_unmatched', 'no' );

	?>
	<fieldset>
		<label>
			<input type="checkbox" name="ayudawp_euw_accept_unmatched" value="yes" <?php checked( 'yes', $enabled ); ?>>
			<?php esc_html_e( 'Register requests that do not match any order, flagged as “Unverified”', 'eu-withdrawal-compliance' ); ?>
		</label>
		<p class="description">
			<?php
			echo wp_kses(
				__( '<strong>Optional, off by default.</strong> By default a request is rejected upfront when the order number and email do not match a WooCommerce order. With this option enabled it is registered anyway, marked as <em>Unverified</em> in the log and highlighted in the notification email, and you verify it manually against your records before deciding. Useful when customers mistype the reference, or when some sales happen outside WooCommerce. Unverified requests are never linked to an order (no order note, no status or deadline checks) and the acknowledgement of receipt is still sent to the address submitted.', 'eu-withdrawal-compliance' ),
				array(
					'strong' => array(),
					'em'     => array(),
				)
			);
			?>
		</p>
	</fieldset>
	<?php
}

/**
 * Deadline section description.
 */
function ayudawp_euw_deadline_section_callback() {

	echo '<p>' . esc_html__( 'EU Directive 2011/83 sets a 14-day withdrawal period. The plugin no longer auto-rejects requests based on this date: because the period legally runs from delivery (which the shop cannot detect automatically), requests that look past the window are flagged in the admin notification for manual review instead of being blocked. These settings tune that advisory calculation.', 'eu-withdrawal-compliance' ) . '</p>';
}

/**
 * Sanitize the deadline basis option to one of the allowed values.
 *
 * @param mixed $value Submitted value.
 * @return string Allowed key, falling back to 'order_date'.
 */
function ayudawp_euw_sanitize_deadline_basis( $value ) {

	$value   = sanitize_key( (string) $value );
	$allowed = array( 'order_date', 'completion_date' );

	return in_array( $value, $allowed, true ) ? $value : 'order_date';
}

/**
 * Sanitize the deadline enforcement mode.
 *
 * @param mixed $value Submitted value.
 * @return string Either 'advisory' (default) or 'strict'.
 */
function ayudawp_euw_sanitize_deadline_mode( $value ) {

	return 'strict' === $value ? 'strict' : 'advisory';
}

/**
 * Deadline basis selector callback.
 */
function ayudawp_euw_field_deadline_basis_callback() {

	$value = get_option( 'ayudawp_euw_deadline_basis', 'order_date' );

	$options = array(
		'order_date'      => __( 'Order date (when the customer placed the order)', 'eu-withdrawal-compliance' ),
		'completion_date' => __( 'Completion date (when the WooCommerce order was marked as completed)', 'eu-withdrawal-compliance' ),
	);

	echo '<select name="ayudawp_euw_deadline_basis" id="ayudawp_euw_deadline_basis">';
	foreach ( $options as $key => $label ) {
		printf(
			'<option value="%1$s" %2$s>%3$s</option>',
			esc_attr( $key ),
			selected( $value, $key, false ),
			esc_html( $label )
		);
	}
	echo '</select>';

	echo '<p class="description">' . wp_kses( __( '<strong>Recommended.</strong> Pick the date used to compute the advisory deadline flag shown to the admin. If you choose Completion date but the order is not yet completed, the plugin falls back to the order date.', 'eu-withdrawal-compliance' ), array( 'strong' => array() ) ) . '</p>';
}

/**
 * Grace days numeric field callback.
 */
function ayudawp_euw_field_grace_days_callback() {

	$value = (int) get_option( 'ayudawp_euw_grace_days', 0 );

	printf(
		'<input type="number" name="ayudawp_euw_grace_days" id="ayudawp_euw_grace_days" value="%d" min="0" max="365" step="1" class="small-text"> %s',
		(int) $value,
		esc_html__( 'days', 'eu-withdrawal-compliance' )
	);

	echo '<p class="description">' . wp_kses( __( '<strong>Optional.</strong> Extra days added on top of the 14-day minimum when computing the advisory deadline flag. Useful when you offer a longer return window than required.', 'eu-withdrawal-compliance' ), array( 'strong' => array() ) ) . '</p>';
}

/**
 * Deadline enforcement mode selector callback.
 */
function ayudawp_euw_field_deadline_mode_callback() {

	$value = get_option( 'ayudawp_euw_deadline_mode', 'advisory' );

	$options = array(
		'advisory' => __( 'Advisory — never block, only flag late requests for manual review', 'eu-withdrawal-compliance' ),
		'strict'   => __( 'Strict — hide the button and reject requests past the deadline plus grace days', 'eu-withdrawal-compliance' ),
	);

	echo '<select name="ayudawp_euw_deadline_mode" id="ayudawp_euw_deadline_mode">';
	foreach ( $options as $key => $label ) {
		printf(
			'<option value="%1$s" %2$s>%3$s</option>',
			esc_attr( $key ),
			selected( $value, $key, false ),
			esc_html( $label )
		);
	}
	echo '</select>';

	echo '<p class="description">' . wp_kses( __( '<strong>Recommended: Advisory.</strong> Because the legal 14-day period runs from delivery (which the shop cannot detect automatically), strict mode can turn away consumers still within their real window. Use it only when your catalogue and process make the order or completion date a reliable proxy.', 'eu-withdrawal-compliance' ), array( 'strong' => array() ) ) . '</p>';
}

/**
 * Article 16 exclusions section — informational block.
 *
 * Article 16 of EU Directive 2011/83 lets traders exempt certain goods or
 * services from the right of withdrawal. Since 1.5.0, that flag is set per
 * product (and inherited from product categories) via the "Withdrawal
 * status" dropdown — no longer from a global list in this settings page.
 * The two buttons below are short-cuts to those two screens; we never
 * managed exclusions in two places at the same time on purpose, so this
 * section is intentionally informational only.
 *
 * Existing values from the pre-1.5.0 chip picker are migrated to the new
 * per-category term meta by `ayudawp_euw_migrate_excluded_categories_option()`,
 * so upgrading does not lose any configuration.
 */
function ayudawp_euw_exclusions_section_callback() {

	echo '<p>' . esc_html__( 'Article 16 of EU Directive 2011/83 lists categories of goods exempted from the right of withdrawal: custom-made products, perishable goods, sealed digital content opened by the consumer, hygiene-sealed items, etc. Withdrawal requests on orders containing excluded items are flagged for manual review — never auto-rejected, since a partial withdrawal over the rest of the order can still be valid.', 'eu-withdrawal-compliance' ) . '</p>';

	echo '<p>' . wp_kses(
		__( 'Each product (and product category) now carries its own <strong>Withdrawal status</strong> dropdown that controls both the Article 16 exclusion and the matching checkout consent in one place. Set the status on a category to apply it to every product underneath; override on a specific product when needed.', 'eu-withdrawal-compliance' ),
		array( 'strong' => array() )
	) . '</p>';

	echo '<p>' . esc_html__( 'An order that only holds excluded products does not offer the withdrawal at all, and a mixed one names them in its email notice. The “Orders with excluded products” option under “Eligible order statuses” switches this off.', 'eu-withdrawal-compliance' ) . '</p>';

	if ( ! class_exists( 'WooCommerce' ) ) {

		echo '<p class="description">' . esc_html__( 'WooCommerce is not active, so there are no product categories or products to configure yet.', 'eu-withdrawal-compliance' ) . '</p>';

		return;
	}

	$categories_url = admin_url( 'edit-tags.php?taxonomy=product_cat&post_type=product' );
	$products_url   = admin_url( 'edit.php?post_type=product' );
	?>
	<p>
		<a href="<?php echo esc_url( $categories_url ); ?>" target="_blank" rel="noopener noreferrer" class="button button-secondary">
			<?php esc_html_e( 'Open product categories', 'eu-withdrawal-compliance' ); ?>
			<span class="dashicons dashicons-external" aria-hidden="true" style="font-size:14px;line-height:1.4;vertical-align:middle;margin-left:4px;"></span>
		</a>
		<a href="<?php echo esc_url( $products_url ); ?>" target="_blank" rel="noopener noreferrer" class="button button-secondary">
			<?php esc_html_e( 'Open products list', 'eu-withdrawal-compliance' ); ?>
			<span class="dashicons dashicons-external" aria-hidden="true" style="font-size:14px;line-height:1.4;vertical-align:middle;margin-left:4px;"></span>
		</a>
	</p>
	<p class="description">
		<?php esc_html_e( 'Edit a category or a product, look for the "Withdrawal status" dropdown, pick the status that fits and save. Categories from previous versions of the plugin have been migrated automatically.', 'eu-withdrawal-compliance' ); ?>
	</p>
	<?php
}

/**
 * Checkout consent section description.
 */
function ayudawp_euw_consent_section_callback() {

	echo '<p>' . esc_html__( 'These two checkboxes can be injected at the WooCommerce checkout for products (or product categories) that you flag in the product editor. They cover the two checkout-time consents the EU consumer-rights regime expects when you want to apply specific exceptions to the right of withdrawal.', 'eu-withdrawal-compliance' ) . '</p>';

	echo '<p>' . esc_html__( 'The fields are independent from the Article 16 exclusion flag — tick the corresponding consent type in the product or category edit screen for every item where you want the checkbox to appear at checkout.', 'eu-withdrawal-compliance' ) . '</p>';

	if ( class_exists( 'WooCommerce' ) && ayudawp_euw_checkout_uses_block() ) {

		echo '<p><strong>' . esc_html__( 'Heads up:', 'eu-withdrawal-compliance' ) . '</strong> ';
		echo esc_html__( 'your checkout page uses the WooCommerce Checkout block. This version of the plugin injects the consents only into the classic shortcode checkout. To use them in v1.5.0, replace the Checkout block with the [woocommerce_checkout] shortcode in the checkout page. Block-based checkout support is planned for a future release.', 'eu-withdrawal-compliance' );
		echo '</p>';
	}
}

/**
 * Detect whether the configured WooCommerce checkout page uses the block.
 *
 * @return bool
 */
function ayudawp_euw_checkout_uses_block() {

	if ( ! function_exists( 'wc_get_page_id' ) || ! function_exists( 'has_block' ) ) {
		return false;
	}

	$checkout_page_id = wc_get_page_id( 'checkout' );

	if ( $checkout_page_id <= 0 ) {
		return false;
	}

	$page = get_post( $checkout_page_id );

	if ( ! $page ) {
		return false;
	}

	return has_block( 'woocommerce/checkout', $page );
}

/**
 * Type A consent field callback (mandatory, digital content).
 */
function ayudawp_euw_field_consent_a_callback() {

	if ( ! class_exists( 'WooCommerce' ) ) {
		echo '<p class="description">' . esc_html__( 'WooCommerce is not active, so checkout consents cannot be injected.', 'eu-withdrawal-compliance' ) . '</p>';
		return;
	}

	$enabled = get_option( 'ayudawp_euw_consent_a_enabled', 'yes' );
	$text    = (string) get_option( 'ayudawp_euw_consent_a_text', '' );
	$default = ayudawp_euw_editable_text_default( 'ayudawp_euw_consent_a_text' );

	?>
	<fieldset>
		<label>
			<input type="checkbox" name="ayudawp_euw_consent_a_enabled" value="yes" <?php checked( 'yes', $enabled ); ?>>
			<?php esc_html_e( 'Enable globally', 'eu-withdrawal-compliance' ); ?>
		</label>
		<p class="description">
			<?php
			echo wp_kses(
				__( '<strong>Mandatory if you sell digital content.</strong> Required for online courses, downloads, software, eBooks and paid memberships when you want to rely on the Article 16(m) exception of Directive 2011/83/EU (Art. 103.m TRLGDCU in Spain). Without this checkbox accepted and recorded, the consumer keeps the 14-day withdrawal right even after accessing the content. Otherwise <strong>Optional</strong>. When in doubt, consult a lawyer.', 'eu-withdrawal-compliance' ),
				array( 'strong' => array() )
			);
			?>
		</p>
		<p>
			<label for="ayudawp_euw_consent_a_text"><strong><?php esc_html_e( 'Checkbox text shown at checkout', 'eu-withdrawal-compliance' ); ?></strong></label>
			<textarea name="ayudawp_euw_consent_a_text" id="ayudawp_euw_consent_a_text" rows="3" class="large-text" placeholder="<?php echo esc_attr( $default ); ?>"><?php echo esc_textarea( $text ); ?></textarea>
		</p>
		<p class="description">
			<?php esc_html_e( 'Leave it empty to use the bundled text shown in the field, which follows the language of each customer. Your own text is shown exactly as written, in every language. Basic HTML allowed: links, strong, em.', 'eu-withdrawal-compliance' ); ?>
		</p>
	</fieldset>
	<?php
}

/**
 * Type B consent field callback (optional, service started early).
 */
function ayudawp_euw_field_consent_b_callback() {

	if ( ! class_exists( 'WooCommerce' ) ) {
		echo '<p class="description">' . esc_html__( 'WooCommerce is not active, so checkout consents cannot be injected.', 'eu-withdrawal-compliance' ) . '</p>';
		return;
	}

	$enabled = get_option( 'ayudawp_euw_consent_b_enabled', 'yes' );
	$text    = (string) get_option( 'ayudawp_euw_consent_b_text', '' );
	$default = ayudawp_euw_editable_text_default( 'ayudawp_euw_consent_b_text' );

	?>
	<fieldset>
		<label>
			<input type="checkbox" name="ayudawp_euw_consent_b_enabled" value="yes" <?php checked( 'yes', $enabled ); ?>>
			<?php esc_html_e( 'Enable globally', 'eu-withdrawal-compliance' ); ?>
		</label>
		<p class="description">
			<?php
			echo wp_kses(
				__( '<strong>Optional.</strong> Useful for ongoing services (maintenance plans, consultancy, SaaS, subscriptions). Backed by Article 14(4)(a) of Directive 2011/83/EU (Art. 108.3 TRLGDCU in Spain). With this checkbox accepted and recorded, you may charge a pro-rated amount for the work done if the customer withdraws within the 14-day window. Without it, an early withdrawal forces a full refund regardless of the work already delivered.', 'eu-withdrawal-compliance' ),
				array( 'strong' => array() )
			);
			?>
		</p>
		<p>
			<label for="ayudawp_euw_consent_b_text"><strong><?php esc_html_e( 'Checkbox text shown at checkout', 'eu-withdrawal-compliance' ); ?></strong></label>
			<textarea name="ayudawp_euw_consent_b_text" id="ayudawp_euw_consent_b_text" rows="3" class="large-text" placeholder="<?php echo esc_attr( $default ); ?>"><?php echo esc_textarea( $text ); ?></textarea>
		</p>
		<p class="description">
			<?php esc_html_e( 'Leave it empty to use the bundled text shown in the field, which follows the language of each customer. Your own text is shown exactly as written, in every language. Basic HTML allowed: links, strong, em.', 'eu-withdrawal-compliance' ); ?>
		</p>
		<p>
			<label>
				<input type="checkbox" name="ayudawp_euw_consent_b_required" value="yes" <?php checked( 'yes', get_option( 'ayudawp_euw_consent_b_required', 'no' ) ); ?>>
				<strong><?php esc_html_e( 'Require it to place the order', 'eu-withdrawal-compliance' ); ?></strong>
			</label>
		</p>
		<p class="description">
			<?php
			echo wp_kses(
				__( '<strong>Optional.</strong> Off by default, because asking for the service to start early is the customer\'s choice. Tick it only if your service always starts inside the 14-day window (live sessions, bookings, anything delivered on a date the customer picks), so that placing the order without that request would not make sense. With it on, the checkbox becomes mandatory like the digital content one and the order cannot be placed without it.', 'eu-withdrawal-compliance' ),
				array( 'strong' => array() )
			);
			?>
		</p>
	</fieldset>
	<?php
}

/**
 * Annex I.B section description.
 */
function ayudawp_euw_annex_b_section_callback() {

	echo '<p>' . esc_html__( 'Annex I.B of Directive 2011/83/EU defines a standard model withdrawal form (Anexo B in the Spanish TRLGDCU). Providing it is part of the pre-contractual information obligations of Art. 6(1)(h) — the online function from Directive 2023/2673 complements but does not replace this requirement. Enable the block to expose the model right below the public withdrawal form, with a printable view available from the same page.', 'eu-withdrawal-compliance' ) . '</p>';
}

/**
 * Annex I.B enabled field callback.
 */
function ayudawp_euw_field_annex_b_enabled_callback() {

	$enabled = get_option( 'ayudawp_euw_annex_b_enabled', 'yes' );

	?>
	<label>
		<input type="checkbox" name="ayudawp_euw_annex_b_enabled" value="yes" <?php checked( 'yes', $enabled ); ?>>
		<?php esc_html_e( 'Display the model form below the public withdrawal form', 'eu-withdrawal-compliance' ); ?>
	</label>
	<p class="description">
		<?php
		echo wp_kses(
			__( '<strong>Recommended.</strong> Providing the model withdrawal form is a pre-contractual information obligation under Art. 6(1)(h) of Directive 2011/83/EU. The model is generated from the shop name, address and notification email so it works out of the box.', 'eu-withdrawal-compliance' ),
			array( 'strong' => array() )
		);
		?>
	</p>
	<?php
}

/**
 * Trader postal address field callback.
 *
 * Free-text address shown in the Annex I.B model form as the destination the
 * consumer sends the withdrawal to. When empty, the model falls back to the
 * WooCommerce store address (if WooCommerce is active); on non-WooCommerce
 * sites there is no fallback, so this field is the only way to provide the
 * address that Annex I.B requires.
 */
function ayudawp_euw_field_trader_address_callback() {

	$value = (string) get_option( 'ayudawp_euw_trader_address', '' );

	printf(
		'<textarea name="ayudawp_euw_trader_address" id="ayudawp_euw_trader_address" rows="4" class="large-text" placeholder="%1$s">%2$s</textarea>',
		esc_attr__( 'Acme Ltd., 123 Example Street, 28001 Madrid, Spain', 'eu-withdrawal-compliance' ),
		esc_textarea( $value )
	);

	if ( class_exists( 'WooCommerce' ) ) {
		echo '<p class="description">' . wp_kses( __( '<strong>Recommended.</strong> Postal address the consumer sends the withdrawal to, shown in the Annex I.B model form. Leave empty to use your WooCommerce store address, or fill it in to override it (for example, a dedicated returns address).', 'eu-withdrawal-compliance' ), array( 'strong' => array() ) ) . '</p>';
	} else {
		echo '<p class="description">' . wp_kses( __( '<strong>Required for the model form.</strong> Postal address the consumer sends the withdrawal to. Annex I.B of Directive 2011/83/EU requires the trader’s full address. Without WooCommerce there is no store address to fall back on, so enter it here or the form will show a placeholder instead.', 'eu-withdrawal-compliance' ), array( 'strong' => array() ) ) . '</p>';
	}
}

/**
 * Trader phone field callback.
 */
function ayudawp_euw_field_trader_phone_callback() {

	$value = (string) get_option( 'ayudawp_euw_trader_phone', '' );

	printf(
		'<input type="text" name="ayudawp_euw_trader_phone" value="%1$s" class="regular-text" placeholder="%2$s">',
		esc_attr( $value ),
		esc_attr__( '+34 600 123 456', 'eu-withdrawal-compliance' )
	);

	echo '<p class="description">' . wp_kses( __( '<strong>Optional.</strong> Annex I.B includes a phone line in the trader address block — leave empty if you only want email contact.', 'eu-withdrawal-compliance' ), array( 'strong' => array() ) ) . '</p>';
}

/**
 * Trader contact email field callback.
 *
 * Dedicated contact address shown in the Annex I.B model form. Read with
 * priority over the (possibly multi-recipient) notification email, so the
 * public model form can point consumers to a clean contact mailbox.
 */
function ayudawp_euw_field_trader_email_callback() {

	$value = (string) get_option( 'ayudawp_euw_trader_email', '' );

	printf(
		'<input type="email" name="ayudawp_euw_trader_email" value="%s" class="regular-text">',
		esc_attr( $value )
	);

	echo '<p class="description">' . wp_kses( __( '<strong>Optional.</strong> Contact email shown in the Annex I.B model form. Leave empty to use the first notification address above, falling back to the site admin email.', 'eu-withdrawal-compliance' ), array( 'strong' => array() ) ) . '</p>';
}

/**
 * Excluded-notice section description.
 */
function ayudawp_euw_excluded_notice_section_callback() {

	echo '<p>' . esc_html__( 'Surface a visible warning on the public product page whenever the product is flagged as excluded from the right of withdrawal (directly or by category inheritance). Reinforces informed consent and reduces post-purchase complaints, but is not legally required as such — it complements the Article 16 exclusions you already manage above.', 'eu-withdrawal-compliance' ) . '</p>';
}

/**
 * Excluded-notice enabled field callback.
 */
function ayudawp_euw_field_excluded_notice_enabled_callback() {

	if ( ! class_exists( 'WooCommerce' ) ) {
		echo '<p class="description">' . esc_html__( 'WooCommerce is not active, so there are no product pages where the notice could be rendered.', 'eu-withdrawal-compliance' ) . '</p>';
		return;
	}

	$enabled = get_option( 'ayudawp_euw_excluded_notice_enabled', 'yes' );

	?>
	<label>
		<input type="checkbox" name="ayudawp_euw_excluded_notice_enabled" value="yes" <?php checked( 'yes', $enabled ); ?>>
		<?php esc_html_e( 'Show the notice on excluded product pages', 'eu-withdrawal-compliance' ); ?>
	</label>
	<p class="description">
		<?php
		echo wp_kses(
			__( '<strong>Recommended.</strong> Renders between price and add-to-cart button on the single product page when the product is marked as excluded. Reinforces informed consent before the purchase.', 'eu-withdrawal-compliance' ),
			array( 'strong' => array() )
		);
		?>
	</p>
	<?php
}

/**
 * Render the title+body editor for a given excluded-notice status.
 *
 * Outputs the input + textarea pair and a single description so the admin
 * sees one cohesive editor per status (digital content, Article 16(l) dated
 * services or other Article 16 exception). Reused by the three status-specific
 * callbacks below.
 *
 * @param string $status One of 'art16m_digital', 'art16l_accommodation' or 'art16_other'.
 */
function ayudawp_euw_render_excluded_notice_status_editor( $status ) {

	if ( ! class_exists( 'WooCommerce' ) ) {
		echo '<p class="description">' . esc_html__( 'WooCommerce is not active, so there are no product pages where the notice could be rendered.', 'eu-withdrawal-compliance' ) . '</p>';
		return;
	}

	if ( ! ayudawp_euw_is_excluded_status( $status ) ) {
		return;
	}

	$title_option = 'ayudawp_euw_excluded_notice_title_' . $status;
	$body_option  = 'ayudawp_euw_excluded_notice_body_' . $status;

	$title         = (string) get_option( $title_option, '' );
	$title_default = ayudawp_euw_editable_text_default( $title_option );

	$body         = (string) get_option( $body_option, '' );
	$body_default = ayudawp_euw_editable_text_default( $body_option );
	?>
	<p>
		<label for="<?php echo esc_attr( $title_option ); ?>"><strong><?php esc_html_e( 'Title', 'eu-withdrawal-compliance' ); ?></strong></label>
		<br>
		<input type="text" name="<?php echo esc_attr( $title_option ); ?>" id="<?php echo esc_attr( $title_option ); ?>" value="<?php echo esc_attr( $title ); ?>" placeholder="<?php echo esc_attr( $title_default ); ?>" class="regular-text">
	</p>
	<p>
		<label for="<?php echo esc_attr( $body_option ); ?>"><strong><?php esc_html_e( 'Body', 'eu-withdrawal-compliance' ); ?></strong></label>
		<br>
		<textarea name="<?php echo esc_attr( $body_option ); ?>" id="<?php echo esc_attr( $body_option ); ?>" rows="3" class="large-text" placeholder="<?php echo esc_attr( $body_default ); ?>"><?php echo esc_textarea( $body ); ?></textarea>
	</p>
	<p class="description">
		<?php
		echo wp_kses(
			__( '<strong>Optional.</strong> Leave a field empty to use the bundled text shown in it, which follows the language of each visitor; your own text is shown exactly as written, in every language. The body supports basic HTML (links, strong, em) and the <code>{withdrawal_page_link}</code> placeholder, which expands to a link to the configured withdrawal page.', 'eu-withdrawal-compliance' ),
			array(
				'strong' => array(),
				'code'   => array(),
			)
		);
		?>
	</p>
	<?php
}

/**
 * Excluded-notice digital-content editor callback.
 */
function ayudawp_euw_field_excluded_notice_digital_callback() {

	ayudawp_euw_render_excluded_notice_status_editor( 'art16m_digital' );
}

/**
 * Excluded-notice dated-services (Art. 16(l)) editor callback.
 */
function ayudawp_euw_field_excluded_notice_accommodation_callback() {

	ayudawp_euw_render_excluded_notice_status_editor( 'art16l_accommodation' );
}

/**
 * Excluded-notice other-Article-16 editor callback.
 */
function ayudawp_euw_field_excluded_notice_other_callback() {

	ayudawp_euw_render_excluded_notice_status_editor( 'art16_other' );
}

/**
 * Withdrawal link visibility section description and how-to.
 *
 * Pure documentation: no fields to register — the section explains how to
 * link the withdrawal page from the footer using the three available paths
 * (classic menus, block-theme editor, shortcode).
 */
function ayudawp_euw_link_visibility_section_callback() {

	echo '<p>' . esc_html__( 'Article 11a introduced by Directive 2023/2673 requires the online withdrawal function to be clearly identifiable and accessible throughout the withdrawal period. The standard practice is a permanent link in the site footer (next to the privacy policy and terms of service). The plugin never injects anything automatically — pick whichever of the three routes below fits your theme.', 'eu-withdrawal-compliance' ) . '</p>';

	echo '<ol class="ayudawp-euw-link-howto">';

	echo '<li><strong>' . esc_html__( 'Classic themes (Appearance → Menus)', 'eu-withdrawal-compliance' ) . '</strong><br>';
	echo esc_html__( 'Open the menu assigned to your footer, add the "Right of withdrawal" page from the left column and save. The link will appear in the footer alongside the rest of the menu items.', 'eu-withdrawal-compliance' );
	echo '</li>';

	echo '<li><strong>' . esc_html__( 'Block themes (Appearance → Editor)', 'eu-withdrawal-compliance' ) . '</strong><br>';
	echo esc_html__( 'Open the Editor, edit the Footer template part, insert a Navigation or Page List block and link it to the "Right of withdrawal" page. Save the template.', 'eu-withdrawal-compliance' );
	echo '</li>';

	echo '<li><strong>' . esc_html__( 'Shortcode', 'eu-withdrawal-compliance' ) . '</strong><br>';
	echo esc_html__( 'Use the following shortcode anywhere a shortcode is accepted (text widgets, classic-editor pages, the "Shortcode" block in block themes):', 'eu-withdrawal-compliance' );
	echo ' <code>[ayudawp_withdrawal_link]</code><br>';
	echo esc_html__( 'Optional attributes:', 'eu-withdrawal-compliance' );
	echo ' <code>text="Withdrawal"</code>, <code>class="my-footer-link"</code>.';
	echo '</li>';

	echo '</ol>';
}

/**
 * Sanitize the guarantee-notice display mode.
 *
 * @param mixed $value Submitted value.
 * @return string One of 'full', 'details', 'popover' or 'none'.
 */
function ayudawp_euw_sanitize_guarantee_display( $value ) {

	$value = sanitize_key( (string) $value );

	return in_array( $value, array( 'full', 'details', 'popover', 'none' ), true ) ? $value : 'full';
}

/**
 * Sanitize the Spanish-note mode.
 *
 * @param mixed $value Submitted value.
 * @return string One of 'auto', 'yes' or 'no'.
 */
function ayudawp_euw_sanitize_guarantee_es_note( $value ) {

	$value = sanitize_key( (string) $value );

	return in_array( $value, array( 'auto', 'yes', 'no' ), true ) ? $value : 'auto';
}

/**
 * Sanitize a language code against the languages the notice exists in.
 *
 * @param mixed $value Submitted value.
 * @return string Two-letter language code, falling back to English.
 */
function ayudawp_euw_sanitize_guarantee_lang( $value ) {

	$value = sanitize_key( (string) $value );

	return in_array( $value, ayudawp_euw_guarantee_languages(), true ) ? $value : 'en';
}

/**
 * Sanitize the list of emails that carry the guarantee notice.
 *
 * Only customer emails are accepted. The notice is information for the buyer,
 * and the attachments filter that mails the PDF has no "is this the admin copy"
 * flag to lean on, so keeping admin IDs out of the stored list is what keeps
 * the PDF out of the shop's own inbox.
 *
 * @param mixed $value Submitted value.
 * @return array<int, string>
 */
function ayudawp_euw_sanitize_guarantee_email_ids( $value ) {

	$available = array_keys( ayudawp_euw_guarantee_customer_emails() );

	// Without WooCommerce there is no list to check against, and the field is not
	// even printed, so nothing that arrives here was ticked by anyone. Keep what
	// is stored. Saving the settings page with WooCommerce switched off used to
	// empty the list, and a key posted by hand was stored without being checked.
	if ( empty( $available ) ) {

		$stored = get_option( 'ayudawp_euw_guarantee_email_ids', array( 'customer_on_hold_order', 'customer_processing_order' ) );

		return is_array( $stored ) ? $stored : array();
	}

	if ( ! is_array( $value ) ) {
		return array();
	}

	$valid = array();

	foreach ( $value as $candidate ) {

		$candidate = sanitize_key( (string) $candidate );

		if ( '' === $candidate || ! in_array( $candidate, $available, true ) ) {
			continue;
		}

		$valid[ $candidate ] = $candidate;
	}

	return array_values( $valid );
}

/**
 * Sanitize the per-language media-library overrides.
 *
 * @param mixed $value Submitted value.
 * @return array<string, int> Language code => attachment ID.
 */
function ayudawp_euw_sanitize_guarantee_custom_files( $value ) {

	if ( ! is_array( $value ) ) {
		return array();
	}

	$languages = ayudawp_euw_guarantee_languages();
	$clean     = array();

	foreach ( $value as $lang => $attachment_id ) {

		$lang = sanitize_key( (string) $lang );
		$id   = absint( $attachment_id );

		if ( ! $id || ! in_array( $lang, $languages, true ) ) {
			continue;
		}

		if ( ! wp_attachment_is_image( $id ) ) {
			continue;
		}

		$clean[ $lang ] = $id;
	}

	return $clean;
}

/**
 * Customer-facing WooCommerce emails, as ID => title.
 *
 * Asks WooCommerce rather than hardcoding a list, so emails added by other
 * plugins (a shipping notification, a subscription renewal) can be ticked too.
 * Empty when WooCommerce is not active.
 *
 * @return array<string, string>
 */
function ayudawp_euw_guarantee_customer_emails() {

	if ( ! function_exists( 'WC' ) || ! WC() || ! is_callable( array( WC(), 'mailer' ) ) ) {
		return array();
	}

	$emails = WC()->mailer()->get_emails();

	if ( ! is_array( $emails ) ) {
		return array();
	}

	$list = array();

	foreach ( $emails as $email ) {

		if ( ! is_object( $email ) || empty( $email->id ) ) {
			continue;
		}

		if ( ! is_callable( array( $email, 'is_customer_email' ) ) || ! $email->is_customer_email() ) {
			continue;
		}

		$title = is_callable( array( $email, 'get_title' ) ) ? (string) $email->get_title() : (string) $email->id;

		$list[ (string) $email->id ] = $title;
	}

	return $list;
}

/**
 * EU legal guarantee notice section description.
 */
function ayudawp_euw_guarantee_section_callback() {

	// Landing point for the link in the announcement notice, which comes from
	// another admin screen and has to arrive at this section rather than at the
	// top of a settings page with eleven of them. The <h2> is printed by
	// do_settings_sections() right above, out of reach, so the anchor goes here
	// and the stylesheet reserves the space above it.
	echo '<span id="ayudawp-euw-guarantee" class="ayudawp-euw-anchor" aria-hidden="true"></span>';

	echo '<p>' . wp_kses(
		__( 'From <strong>27 September 2026</strong>, anyone selling goods to consumers in the EU has to display the harmonised notice on the legal guarantee of conformity: Article 22a of Directive 2011/83/EU, added by Directive (EU) 2024/825, with the design fixed by Implementing Regulation (EU) 2025/1960. The plugin ships the official file in the 24 EU languages and shows it unedited, as the regulation requires. It does not apply to services or digital content, so the notice is only shown when the cart or the order contains at least one physical product.', 'eu-withdrawal-compliance' ),
		array( 'strong' => array() )
	) . '</p>';

	if ( ! class_exists( 'WooCommerce' ) ) {
		echo '<p class="description">' . wp_kses(
			__( 'WooCommerce is not active, so there is no checkout or order email to add the notice to. You can still publish it on a page with the <code>[ayudawp_guarantee_notice]</code> shortcode.', 'eu-withdrawal-compliance' ),
			array( 'code' => array() )
		) . '</p>';

	} elseif ( ! ayudawp_euw_guarantee_shop_sells_goods() ) {

		// The module only shows the notice next to goods, so a shop of courses or
		// downloads that switches it on sees nothing change and takes it for broken.
		echo '<p class="description">' . esc_html__( 'Your catalogue has no physical products at the moment. The notice only applies to goods, so nothing is shown for now, even with the module switched on, and it will appear by itself once you publish one.', 'eu-withdrawal-compliance' ) . '</p>';
	}
}

/**
 * Guarantee notice on/off field callback.
 */
function ayudawp_euw_field_guarantee_enabled_callback() {

	$enabled = get_option( 'ayudawp_euw_guarantee_enabled', 'no' );

	?>
	<label>
		<input type="checkbox" name="ayudawp_euw_guarantee_enabled" value="yes" <?php checked( 'yes', $enabled ); ?>>
		<?php esc_html_e( 'Show the EU harmonised legal guarantee notice', 'eu-withdrawal-compliance' ); ?>
	</label>
	<p class="description">
		<?php
		echo wp_kses(
			__( '<strong>Mandatory if you sell goods to consumers in the EU.</strong> Switches on the whole module: the notice above the place-order button, the notice in the customer order emails, the <code>[ayudawp_guarantee_notice]</code> and <code>[ayudawp_guarantee_link]</code> shortcodes and the record on the order. Each of those has its own setting below.', 'eu-withdrawal-compliance' ),
			array(
				'strong' => array(),
				'code'   => array(),
			)
		);
		?>
	</p>
	<?php
}

/**
 * Display mode field callback.
 */
function ayudawp_euw_field_guarantee_display_callback() {

	$value = ayudawp_euw_guarantee_display_mode();

	$options = array(
		'full'    => __( 'The whole notice, above the place-order button', 'eu-withdrawal-compliance' ),
		'details' => __( 'A line that expands into the notice', 'eu-withdrawal-compliance' ),
		'popover' => __( 'A button that opens the notice large over the page', 'eu-withdrawal-compliance' ),
		'none'    => __( 'Nothing at the checkout (I place it myself)', 'eu-withdrawal-compliance' ),
	);

	echo '<select name="ayudawp_euw_guarantee_display" id="ayudawp_euw_guarantee_display">';

	foreach ( $options as $key => $label ) {
		printf(
			'<option value="%1$s" %2$s>%3$s</option>',
			esc_attr( $key ),
			selected( $value, $key, false ),
			esc_html( $label )
		);
	}

	echo '</select>';

	echo '<p class="description">' . wp_kses(
		__( '<strong>Recommended: the whole notice.</strong> The Commission guidelines accept showing it behind a first click, but the regulation only describes that nested format for the GARAN label, not for the notice, and the reading is contested. Use one of the collapsed modes when your checkout column is narrow: in Storefront the order review is about 279px wide, where a full-width notice is rendered too small to read, which is a compliance problem of its own. The popover opens it at a readable size on any layout.', 'eu-withdrawal-compliance' ),
		array( 'strong' => array() )
	) . '</p>';
}

/**
 * Legal guarantee page selector callback.
 */
function ayudawp_euw_field_guarantee_page_callback() {

	$selected = (int) get_option( 'ayudawp_euw_guarantee_page_id', 0 );

	echo wp_kses(
		wp_dropdown_pages(
			array(
				'name'              => 'ayudawp_euw_guarantee_page_id',
				'show_option_none'  => __( '— Select a page —', 'eu-withdrawal-compliance' ),
				'option_none_value' => '0',
				'selected'          => $selected,
				'echo'              => 0,
			)
		),
		array(
			'select' => array(
				'name'  => true,
				'id'    => true,
				'class' => true,
			),
			'option' => array(
				'class'    => true,
				'value'    => true,
				'selected' => true,
			),
		)
	);

	echo '<p class="description">' . wp_kses(
		__( '<strong>Optional.</strong> Page where you publish the notice in full, with the <code>[ayudawp_guarantee_notice]</code> shortcode. Once it is selected, <code>[ayudawp_guarantee_link]</code> links to it from your footer, the same way the withdrawal link does. Leave it unselected if you do not want a separate page.', 'eu-withdrawal-compliance' ),
		array(
			'strong' => array(),
			'code'   => array(),
		)
	) . '</p>';
}

/**
 * Emails and PDF attachment field callback.
 */
function ayudawp_euw_field_guarantee_emails_callback() {

	$emails = ayudawp_euw_guarantee_customer_emails();

	if ( empty( $emails ) ) {
		echo '<p class="description">' . esc_html__( 'WooCommerce is not active, so there are no order emails to add the notice to.', 'eu-withdrawal-compliance' ) . '</p>';
		return;
	}

	$selected = ayudawp_euw_guarantee_email_ids();

	echo '<fieldset>';
	echo '<legend class="screen-reader-text">' . esc_html__( 'Emails that carry the guarantee notice', 'eu-withdrawal-compliance' ) . '</legend>';

	foreach ( $emails as $id => $title ) {
		printf(
			'<label style="display:block; margin-bottom:4px;"><input type="checkbox" name="ayudawp_euw_guarantee_email_ids[]" value="%1$s" %2$s> %3$s (<code>%1$s</code>)</label>',
			esc_attr( $id ),
			checked( in_array( $id, $selected, true ), true, false ),
			esc_html( $title )
		);
	}

	echo '</fieldset>';

	echo '<p class="description">' . wp_kses(
		__( '<strong>Recommended.</strong> The Commission guidelines ask for the notice in the order confirmation as well as on the site. Defaults to the on-hold and processing emails. Only customer emails are listed: the notice never goes to the shop.', 'eu-withdrawal-compliance' ),
		array( 'strong' => array() )
	) . '</p>';

	?>
	<p>
		<label>
			<input type="checkbox" name="ayudawp_euw_guarantee_pdf_attach" value="yes" <?php checked( 'yes', get_option( 'ayudawp_euw_guarantee_pdf_attach', 'yes' ) ); ?>>
			<strong><?php esc_html_e( 'Attach the official PDF to those emails', 'eu-withdrawal-compliance' ); ?></strong>
		</label>
	</p>
	<p class="description">
		<?php
		echo wp_kses(
			__( '<strong>Recommended.</strong> Many mail clients block remote images by default, and then the notice in the email body is an empty frame. The attached PDF is the copy that always arrives, and it is the official file in the customer language, around 100 KB.', 'eu-withdrawal-compliance' ),
			array( 'strong' => array() )
		);
		?>
	</p>
	<?php
}

/**
 * Spanish three-year note field callback.
 */
function ayudawp_euw_field_guarantee_es_note_callback() {

	$value = (string) get_option( 'ayudawp_euw_guarantee_es_note', 'auto' );

	$options = array(
		'auto' => __( 'Automatic — only when the shop base country is Spain', 'eu-withdrawal-compliance' ),
		'yes'  => __( 'Always show it', 'eu-withdrawal-compliance' ),
		'no'   => __( 'Never show it', 'eu-withdrawal-compliance' ),
	);

	echo '<select name="ayudawp_euw_guarantee_es_note" id="ayudawp_euw_guarantee_es_note">';

	foreach ( $options as $key => $label ) {
		printf(
			'<option value="%1$s" %2$s>%3$s</option>',
			esc_attr( $key ),
			selected( $value, $key, false ),
			esc_html( $label )
		);
	}

	echo '</select>';

	echo '<p class="description">' . wp_kses(
		__( '<strong>Recommended: automatic.</strong> The notice says "minimum two years" because that is the EU floor, and it cannot be edited. In Spain the legal guarantee on new goods is three years from delivery (Art. 120.1 TRLGDCU), so a short line is printed next to the notice, never inside it. Second-hand goods may carry a shorter period, never under a year, which the notice already explains.', 'eu-withdrawal-compliance' ),
		array( 'strong' => array() )
	) . '</p>';

	$anchor = (string) get_option( 'ayudawp_euw_guarantee_terms_anchor', '' );

	?>
	<p>
		<label for="ayudawp_euw_guarantee_terms_anchor"><strong><?php esc_html_e( 'Anchor of the guarantee section in your terms', 'eu-withdrawal-compliance' ); ?></strong></label>
		<br>
		<input type="text" name="ayudawp_euw_guarantee_terms_anchor" id="ayudawp_euw_guarantee_terms_anchor" value="<?php echo esc_attr( $anchor ); ?>" class="regular-text" placeholder="legal-guarantee">
	</p>
	<p class="description">
		<?php
		echo wp_kses(
			__( '<strong>Optional.</strong> The note links to your terms and conditions page. Write here the <code>id</code> of the heading that covers the legal guarantee there, without the <code>#</code>, and the link lands on that section instead of the top of the page.', 'eu-withdrawal-compliance' ),
			array(
				'strong' => array(),
				'code'   => array(),
			)
		);
		?>
	</p>
	<?php
}

/**
 * Order-note field callback.
 */
function ayudawp_euw_field_guarantee_order_note_callback() {

	if ( ! class_exists( 'WooCommerce' ) ) {
		echo '<p class="description">' . esc_html__( 'WooCommerce is not active, so there are no orders to record the notice on.', 'eu-withdrawal-compliance' ) . '</p>';
		return;
	}

	?>
	<label>
		<input type="checkbox" name="ayudawp_euw_guarantee_order_note" value="yes" <?php checked( 'yes', get_option( 'ayudawp_euw_guarantee_order_note', 'yes' ) ); ?>>
		<?php esc_html_e( 'Add a private order note with the notice shown at the checkout', 'eu-withdrawal-compliance' ); ?>
	</label>
	<p class="description">
		<?php
		echo wp_kses(
			__( '<strong>Recommended.</strong> Records the language and the display mode of the notice each buyer was shown, which is what lets you answer a consumer-protection inspection about an order placed months ago. One note per order, private, never shown to the customer.', 'eu-withdrawal-compliance' ),
			array( 'strong' => array() )
		);
		?>
	</p>
	<?php
}

/**
 * Fallback-language field callback.
 */
function ayudawp_euw_field_guarantee_fallback_callback() {

	$value = (string) get_option( 'ayudawp_euw_guarantee_fallback_lang', 'en' );
	$names = ayudawp_euw_guarantee_language_names();

	echo '<select name="ayudawp_euw_guarantee_fallback_lang" id="ayudawp_euw_guarantee_fallback_lang">';

	foreach ( $names as $code => $name ) {
		printf(
			'<option value="%1$s" %2$s>%3$s (%1$s)</option>',
			esc_attr( $code ),
			selected( $value, $code, false ),
			esc_html( $name )
		);
	}

	echo '</select>';

	echo '<p class="description">' . esc_html__( 'The Commission publishes the notice in the 24 official EU languages. A shop in a language that is not one of them gets the official language of its own member state: Catalan, Basque and Galician are served the Spanish notice, Luxembourgish the French one. This setting covers everything else.', 'eu-withdrawal-compliance' ) . '</p>';
}

/**
 * Media-library replacement field callback.
 */
function ayudawp_euw_field_guarantee_custom_files_callback() {

	$languages = ayudawp_euw_guarantee_site_languages();
	$names     = ayudawp_euw_guarantee_language_names();
	$custom    = get_option( 'ayudawp_euw_guarantee_custom_files', array() );
	$custom    = is_array( $custom ) ? $custom : array();

	// A grid rather than a stack of paragraphs: the language names are of
	// different widths, so free-flowing rows leave the buttons at a different
	// distance on every line. Three columns keep language, button and state
	// lined up however many languages the shop has.
	echo '<fieldset class="ayudawp-euw-guarantee-files">';

	foreach ( $languages as $lang ) {

		$id       = isset( $custom[ $lang ] ) ? absint( $custom[ $lang ] ) : 0;
		$filename = '';

		if ( $id ) {
			$file     = get_attached_file( $id );
			$filename = $file ? basename( $file ) : '';
		}

		?>
		<div class="ayudawp-euw-guarantee-file">
			<span class="ayudawp-euw-guarantee-file__lang"><?php echo esc_html( isset( $names[ $lang ] ) ? $names[ $lang ] : $lang ); ?></span>
			<input type="hidden" class="ayudawp-euw-guarantee-file__id" name="ayudawp_euw_guarantee_custom_files[<?php echo esc_attr( $lang ); ?>]" value="<?php echo esc_attr( (string) $id ); ?>">
			<button type="button" class="button button-small ayudawp-euw-guarantee-file__choose">
				<?php
				printf(
					/* translators: %s: language name, for example Español. */
					esc_html__( 'Choose image for %s', 'eu-withdrawal-compliance' ),
					esc_html( isset( $names[ $lang ] ) ? $names[ $lang ] : $lang )
				);
				?>
			</button>
			<span class="ayudawp-euw-guarantee-file__state">
				<span class="ayudawp-euw-guarantee-file__name"><?php echo esc_html( '' !== $filename ? $filename : __( 'Bundled official file', 'eu-withdrawal-compliance' ) ); ?></span>
				<button type="button" class="button-link ayudawp-euw-guarantee-file__clear" <?php echo $id ? '' : 'style="display:none"'; ?>><?php esc_html_e( 'Use the bundled file', 'eu-withdrawal-compliance' ); ?></button>
			</span>
		</div>
		<?php
	}

	echo '</fieldset>';

	echo '<p class="description">' . esc_html__( 'Optional. The plugin already ships the official file for every language above, so leave this alone unless you have a reason: serving the file from your own CDN, or a revision the Commission publishes before the plugin ships it. Whatever you choose is shown unedited, so it has to be the official notice in that language, in colour and complete.', 'eu-withdrawal-compliance' ) . '</p>';
}

/**
 * Withdrawal emails section description.
 */
function ayudawp_euw_emails_section_callback() {

	echo '<p>' . esc_html__( 'Customise who the plugin emails come from and the wording of the status-change messages your customers receive. Leave a field empty to keep the current default.', 'eu-withdrawal-compliance' ) . '</p>';
}

/**
 * From-name field callback.
 */
function ayudawp_euw_field_from_name_callback() {

	$value = (string) get_option( 'ayudawp_euw_from_name', '' );

	printf(
		'<input type="text" name="ayudawp_euw_from_name" value="%1$s" class="regular-text" placeholder="%2$s">',
		esc_attr( $value ),
		esc_attr( ayudawp_euw_get_site_name() )
	);

	echo '<p class="description">' . wp_kses( __( '<strong>Optional.</strong> Sender name for the plugin’s emails (acknowledgement, admin notification, status updates). Leave empty to use the site default.', 'eu-withdrawal-compliance' ), array( 'strong' => array() ) ) . '</p>';
}

/**
 * From-address field callback.
 */
function ayudawp_euw_field_from_email_callback() {

	$value = (string) get_option( 'ayudawp_euw_from_email', '' );

	printf(
		'<input type="email" name="ayudawp_euw_from_email" value="%s" class="regular-text">',
		esc_attr( $value )
	);

	echo '<p class="description">' . wp_kses( __( '<strong>Optional.</strong> Sender address for the plugin’s emails. Using an address on your own domain improves deliverability. Leave empty to use the WordPress default. Applies only to this plugin’s emails.', 'eu-withdrawal-compliance' ), array( 'strong' => array() ) ) . '</p>';
}

/**
 * Render the editable body for a status-change email.
 *
 * Shows a textarea pre-filled with the current effective text (the stored
 * override or the bundled default) so the admin can tweak the wording. An empty
 * value falls back to the bundled default, mirroring the consent and excluded-
 * notice editors.
 *
 * @param string $status One of 'accepted', 'rejected' or 'completed'.
 */
function ayudawp_euw_render_status_email_body_editor( $status ) {

	$defaults = function_exists( 'ayudawp_euw_status_email_defaults' ) ? ayudawp_euw_status_email_defaults() : array();

	if ( ! isset( $defaults[ $status ] ) ) {
		return;
	}

	$option = 'ayudawp_euw_status_email_body_' . $status;
	$text   = (string) get_option( $option, '' );

	printf(
		'<textarea name="%1$s" id="%1$s" rows="3" class="large-text" placeholder="%3$s">%2$s</textarea>',
		esc_attr( $option ),
		esc_textarea( $text ),
		esc_attr( $defaults[ $status ] )
	);

	echo '<p class="description">' . esc_html__( 'Leave it empty to use the bundled text shown in the field, which follows the language of the shop. The order number and a sober sign-off are always appended automatically, as is any per-request comment you add when changing the status.', 'eu-withdrawal-compliance' ) . '</p>';
}

/**
 * Accepted status email body callback.
 */
function ayudawp_euw_field_status_email_accepted_callback() {

	ayudawp_euw_render_status_email_body_editor( 'accepted' );
}

/**
 * Rejected status email body callback.
 */
function ayudawp_euw_field_status_email_rejected_callback() {

	ayudawp_euw_render_status_email_body_editor( 'rejected' );
}

/**
 * Completed status email body callback.
 */
function ayudawp_euw_field_status_email_completed_callback() {

	ayudawp_euw_render_status_email_body_editor( 'completed' );
}

/**
 * Public form section description.
 */
function ayudawp_euw_form_section_callback() {

	echo '<p>' . esc_html__( 'Fine-tune the public withdrawal form. The fixed legal note shown below the intro cannot be edited because it must stay identical across the form, the confirmation screen and the acknowledgement email.', 'eu-withdrawal-compliance' ) . '</p>';
}

/**
 * Form intro text field callback.
 */
function ayudawp_euw_field_form_intro_callback() {

	$enabled = get_option( 'ayudawp_euw_form_intro_enabled', 'yes' );
	$text    = (string) get_option( 'ayudawp_euw_form_intro_text', '' );
	$default = ayudawp_euw_editable_text_default( 'ayudawp_euw_form_intro_text' );

	?>
	<fieldset>
		<label>
			<input type="checkbox" name="ayudawp_euw_form_intro_enabled" value="yes" <?php checked( 'yes', $enabled ); ?>>
			<?php esc_html_e( 'Show the intro paragraph above the form', 'eu-withdrawal-compliance' ); ?>
		</label>
		<p>
			<label for="ayudawp_euw_form_intro_text"><strong><?php esc_html_e( 'Intro text', 'eu-withdrawal-compliance' ); ?></strong></label>
			<textarea name="ayudawp_euw_form_intro_text" id="ayudawp_euw_form_intro_text" rows="3" class="large-text" placeholder="<?php echo esc_attr( $default ); ?>"><?php echo esc_textarea( $text ); ?></textarea>
		</p>
		<p class="description">
			<?php esc_html_e( 'Untick the box to hide the intro entirely. Leave the text empty to use the bundled text shown in the field, which follows the language of each visitor; your own text is shown exactly as written, in every language. Plain text only; the fixed legal note is always shown below it.', 'eu-withdrawal-compliance' ); ?>
		</p>
	</fieldset>
	<?php
}

/**
 * Consumer self-declaration (B2B) field callback.
 */
function ayudawp_euw_field_consumer_check_callback() {

	$enabled = get_option( 'ayudawp_euw_consumer_check_enabled', 'no' );
	$text    = (string) get_option( 'ayudawp_euw_consumer_check_text', '' );
	$default = ayudawp_euw_editable_text_default( 'ayudawp_euw_consumer_check_text' );

	?>
	<fieldset>
		<label>
			<input type="checkbox" name="ayudawp_euw_consumer_check_enabled" value="yes" <?php checked( 'yes', $enabled ); ?>>
			<?php esc_html_e( 'Require a “bought as a consumer” self-declaration on the form', 'eu-withdrawal-compliance' ); ?>
		</label>
		<p class="description">
			<?php
			echo wp_kses(
				__( '<strong>Optional, off by default.</strong> The EU right of withdrawal protects consumers (natural persons acting outside their trade or profession), not B2B buyers. When enabled, the form shows a required checkbox where the customer declares they bought as a consumer. This is a good-faith self-declaration, not a legal guarantee — review B2B cases manually.', 'eu-withdrawal-compliance' ),
				array( 'strong' => array() )
			);
			?>
		</p>
		<p>
			<label for="ayudawp_euw_consumer_check_text"><strong><?php esc_html_e( 'Declaration text shown on the form', 'eu-withdrawal-compliance' ); ?></strong></label>
			<textarea name="ayudawp_euw_consumer_check_text" id="ayudawp_euw_consumer_check_text" rows="2" class="large-text" placeholder="<?php echo esc_attr( $default ); ?>"><?php echo esc_textarea( $text ); ?></textarea>
		</p>
		<p class="description">
			<?php esc_html_e( 'Leave it empty to use the bundled text shown in the field, which follows the language of each visitor. Your own text is shown exactly as written, in every language. Plain text only.', 'eu-withdrawal-compliance' ); ?>
		</p>
	</fieldset>
	<?php
}

/**
 * Permissions section description.
 */
function ayudawp_euw_permissions_section_callback() {

	echo '<p>' . esc_html__( 'Choose which user roles, besides the administrator, can view and manage withdrawal requests. Because each request stores personal data (name, email, IP), access is scoped to the roles you select here instead of every editor on the site.', 'eu-withdrawal-compliance' ) . '</p>';
}

/**
 * Manager roles field callback: a checkbox per editable role.
 */
function ayudawp_euw_field_manager_roles_callback() {

	$selected = (array) get_option( 'ayudawp_euw_manager_roles', array() );
	$roles    = function_exists( 'wp_roles' ) ? wp_roles()->roles : array();

	echo '<fieldset>';
	echo '<legend class="screen-reader-text">' . esc_html__( 'Roles allowed to manage withdrawal requests', 'eu-withdrawal-compliance' ) . '</legend>';

	printf(
		'<label style="display:block; margin-bottom:4px;"><input type="checkbox" checked disabled> %s</label>',
		esc_html__( 'Administrator (always allowed)', 'eu-withdrawal-compliance' )
	);

	foreach ( $roles as $slug => $role ) {

		if ( 'administrator' === $slug ) {
			continue;
		}

		$name = isset( $role['name'] ) ? translate_user_role( $role['name'] ) : $slug;

		printf(
			'<label style="display:block; margin-bottom:4px;"><input type="checkbox" name="ayudawp_euw_manager_roles[]" value="%1$s" %2$s> %3$s</label>',
			esc_attr( $slug ),
			checked( in_array( $slug, $selected, true ), true, false ),
			esc_html( $name )
		);
	}

	echo '</fieldset>';

	echo '<p class="description">' . wp_kses( __( '<strong>Privacy hardening.</strong> The administrator always has access. Tick a role to let it view and manage requests; unticking it revokes access immediately. New sites start with the administrator only.', 'eu-withdrawal-compliance' ), array( 'strong' => array() ) ) . '</p>';
}

/**
 * Whether WooCommerce is serving its own order withdrawal feature.
 *
 * WooCommerce 11.1 (September 2026) added `order_withdrawal`, a withdrawal form
 * of its own under My Account, shipped off by default in Settings > Advanced >
 * Features. Read through FeaturesUtil rather than the raw option, because the
 * option name is internal and the feature only counts as enabled once the
 * features controller says so.
 *
 * @return bool
 */
function ayudawp_euw_native_withdrawal_enabled() {

	$features = 'Automattic\\WooCommerce\\Utilities\\FeaturesUtil';

	if ( ! class_exists( $features ) || ! method_exists( $features, 'feature_is_enabled' ) ) {
		return false;
	}

	return (bool) $features::feature_is_enabled( 'order_withdrawal' );
}

/**
 * Warn when both this plugin and the WooCommerce feature are answering at once.
 *
 * Two live withdrawal flows mean two forms, two endpoints and two separate
 * records of the same right, and which acknowledgement the customer gets
 * depends on the form they happened to find. Neither of the two knows about
 * the other, so nothing merges them afterwards. Rendered inside the settings
 * page, where the decision is taken, not as a site-wide admin notice.
 */
function ayudawp_euw_render_native_feature_notice() {

	if ( ! ayudawp_euw_native_withdrawal_enabled() ) {
		return;
	}

	$features_url = admin_url( 'admin.php?page=wc-settings&tab=advanced&section=features' );

	?>
	<div class="notice notice-warning inline">
		<p>
			<strong><?php esc_html_e( 'WooCommerce is also running its own withdrawal form.', 'eu-withdrawal-compliance' ); ?></strong>
		</p>
		<p>
			<?php esc_html_e( 'The "Order withdrawal" feature added in WooCommerce 11.1 is enabled on this site. With both running, your customers can reach two different forms at two different addresses, each one keeping its own record and sending its own acknowledgement, and nothing brings the two together afterwards.', 'eu-withdrawal-compliance' ); ?>
		</p>
		<p>
			<?php
			printf(
				/* translators: %s: link to the WooCommerce features screen. */
				esc_html__( 'Keep just one of them: turn the WooCommerce feature off in %s, or deactivate this plugin.', 'eu-withdrawal-compliance' ),
				'<a href="' . esc_url( $features_url ) . '">' . esc_html__( 'WooCommerce > Settings > Advanced > Features', 'eu-withdrawal-compliance' ) . '</a>'
			);
			?>
		</p>
	</div>
	<?php
}

/**
 * Render the legal disclaimer block shown right before the Save button.
 *
 * Rendered once on the settings page (not as a WP notice) so it reads as a
 * sober reminder rather than an alarm. Uses the section dashicon in neutral
 * grey so it does not compete with form fields above.
 */
function ayudawp_euw_render_legal_disclaimer() {

	?>
	<div class="ayudawp-euw-legal-disclaimer" role="note">
		<p class="ayudawp-euw-legal-disclaimer__title">
			<span class="dashicons dashicons-info-outline" aria-hidden="true"></span>
			<?php esc_html_e( 'Legal note', 'eu-withdrawal-compliance' ); ?>
		</p>
		<p class="ayudawp-euw-legal-disclaimer__body">
			<?php
			esc_html_e(
				'This plugin provides optional technical tools to help comply with the EU withdrawal right (Directive 2011/83/EU as amended by Directive 2023/2673). It does not guarantee legal compliance. The correct configuration depends on your business model, catalog and jurisdiction. For your specific case, consult a lawyer specialised in consumer law.',
				'eu-withdrawal-compliance'
			);
			?>
		</p>
	</div>
	<?php
}

/**
 * Render the settings page.
 */
function ayudawp_euw_settings_page_html() {

	if ( ! current_user_can( 'manage_options' ) ) {
		return;
	}
	?>
	<div class="wrap">
		<h1><?php echo esc_html( get_admin_page_title() ); ?></h1>

		<?php
		ayudawp_euw_render_native_feature_notice();
		
		$page_id = (int) get_option( 'ayudawp_euw_page_id', 0 );

		if ( $page_id && get_post( $page_id ) ) {
			$page_url = get_permalink( $page_id );
			printf(
				'<p>%1$s <a href="%2$s" target="_blank" rel="noopener nofollow">%3$s</a></p>',
				esc_html__( 'Withdrawal form public URL:', 'eu-withdrawal-compliance' ),
				esc_url( $page_url ),
				esc_html( $page_url )
			);
		}
		?>

		<form action="options.php" method="post">
			<?php
			settings_fields( 'ayudawp_euw_settings_group' );
			do_settings_sections( 'ayudawp-euw-settings' );
			ayudawp_euw_render_legal_disclaimer();
			submit_button( __( 'Save changes', 'eu-withdrawal-compliance' ) );
			?>
		</form>

		<hr>

		<h2><?php esc_html_e( 'How to use this plugin', 'eu-withdrawal-compliance' ); ?></h2>

		<p><?php esc_html_e( 'You can place the withdrawal form anywhere on your site using the following shortcode:', 'eu-withdrawal-compliance' ); ?></p>
		<p><code>[ayudawp_withdrawal_form]</code></p>

		<?php if ( function_exists( 'wc_get_account_endpoint_url' ) ) : ?>
			<p>
				<?php
				$endpoint_url = wc_get_account_endpoint_url( 'withdrawal' );
				printf(
					/* translators: %s: WooCommerce My Account withdrawal endpoint URL. */
					esc_html__( 'WooCommerce customers can also access the form from their account at: %s', 'eu-withdrawal-compliance' ),
					'<a href="' . esc_url( $endpoint_url ) . '" target="_blank" rel="noopener"><code>' . esc_html( $endpoint_url ) . '</code></a>'
				);
				?>
			</p>
		<?php endif; ?>

		<p>
			<?php
			esc_html_e(
				'For maximum visibility, link the withdrawal page from your footer next to your privacy policy and terms of service. Customers must be able to find it within a couple of clicks from any page on the site.',
				'eu-withdrawal-compliance'
			);
			?>
		</p>

		<hr>

		<h2><?php esc_html_e( 'About the EU withdrawal function', 'eu-withdrawal-compliance' ); ?></h2>
		<p>
			<?php
			echo wp_kses(
				__( 'This plugin implements the digital withdrawal function required by <a href="https://eur-lex.europa.eu/eli/dir/2023/2673/oj" target="_blank" rel="noopener">EU Directive 2023/2673</a>, applicable to all EU online retailers from June 19, 2026.', 'eu-withdrawal-compliance' ),
				array(
					'a' => array(
						'href'   => array(),
						'target' => array(),
						'rel'    => array(),
					),
				)
			);
			?>
		</p>

		<?php
		// Promotional banner with rotating AyudaWP services.
		if ( class_exists( 'Ayudawp_Euw_Promo_Banner' ) ) {
			$promo_banner = new Ayudawp_Euw_Promo_Banner( 'aeuw' );
			$promo_banner->render( 'horizontal' );
		}
		?>
	</div>
	<?php
}
