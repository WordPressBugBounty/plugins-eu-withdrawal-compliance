<?php
/**
 * Plugin Name:       EU Withdrawal and Legal Guarantee Compliance
 * Plugin URI:        https://servicios.ayudawp.com
 * Description:       Free EU consumer-rights toolkit: withdrawal function (Directive 2023/2673) and the harmonised legal guarantee notice (Directive 2024/825), checkout consents, Annex I.B model form, Article 16 exclusions, SHA-256 proof, native GDPR integration.
 * Version:           2.3.2
 * Requires at least: 6.0
 * Tested up to:      7.1
 * Requires PHP:      7.4
 * Author:            Fernando Tellado
 * Author URI:        https://tellado.es
 * License:           GPL v2 or later
 * License URI:       https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain:       eu-withdrawal-compliance
 * WC requires at least: 7.0
 * WC tested up to:   11.1
 *
 * @package AyudaWP_EU_Withdrawal
 */

// Prevent direct file access.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

// Plugin constants.
define( 'AYUDAWP_EUW_VERSION', '2.3.2' );
define( 'AYUDAWP_EUW_FILE', __FILE__ );
define( 'AYUDAWP_EUW_DIR', plugin_dir_path( __FILE__ ) );
define( 'AYUDAWP_EUW_URL', plugin_dir_url( __FILE__ ) );
define( 'AYUDAWP_EUW_BASENAME', plugin_basename( __FILE__ ) );

// Load core files.
require_once AYUDAWP_EUW_DIR . 'includes/functions-multilang.php';
require_once AYUDAWP_EUW_DIR . 'includes/functions-cpt.php';
require_once AYUDAWP_EUW_DIR . 'includes/functions-form.php';
require_once AYUDAWP_EUW_DIR . 'includes/functions-shortcode.php';
require_once AYUDAWP_EUW_DIR . 'includes/functions-handler.php';
require_once AYUDAWP_EUW_DIR . 'includes/functions-emails.php';
require_once AYUDAWP_EUW_DIR . 'includes/functions-admin.php';
require_once AYUDAWP_EUW_DIR . 'includes/functions-assets.php';
require_once AYUDAWP_EUW_DIR . 'includes/functions-settings.php';
require_once AYUDAWP_EUW_DIR . 'includes/functions-woocommerce.php';
require_once AYUDAWP_EUW_DIR . 'includes/functions-emails-wc.php';
require_once AYUDAWP_EUW_DIR . 'includes/functions-exclusions.php';
require_once AYUDAWP_EUW_DIR . 'includes/functions-checkout-consent.php';
require_once AYUDAWP_EUW_DIR . 'includes/functions-annex-b.php';
require_once AYUDAWP_EUW_DIR . 'includes/functions-guarantee.php';
require_once AYUDAWP_EUW_DIR . 'includes/functions-guarantee-checkout.php';
require_once AYUDAWP_EUW_DIR . 'includes/functions-guarantee-emails.php';
require_once AYUDAWP_EUW_DIR . 'includes/functions-footer-link.php';
require_once AYUDAWP_EUW_DIR . 'includes/functions-privacy.php';
require_once AYUDAWP_EUW_DIR . 'includes/class-ayudawp-euw-promo-banner.php';

/**
 * Activation hook: schedule cleanup task and create default page.
 */
function ayudawp_euw_activate() {

	// A brand-new install starts with the harmonised guarantee notice already
	// switched on: it is mandatory from 27 September 2026 for anyone selling
	// goods in the EU, and nobody installing a compliance plugin today wants it
	// off. Sites updating from an earlier version get it off plus a notice
	// instead (see the upgrade routine below), because switching it on for them
	// would change their checkout without anyone asking for it.
	if ( ! get_option( 'ayudawp_euw_version' ) ) {
		update_option( 'ayudawp_euw_guarantee_enabled', 'yes' );
	}

	// Register the CPT and the WooCommerce My Account "withdrawal" endpoint
	// BEFORE flushing rewrite rules. Both register on the `init` hook
	// during normal page loads, but `init` does not run during activation
	// hooks, so we have to call them explicitly here. Otherwise the flush
	// would persist a rewrite ruleset without our endpoint, leading to a
	// 404 on /my-account/withdrawal/.
	ayudawp_euw_register_cpt();
	ayudawp_euw_register_wc_endpoint();
	flush_rewrite_rules();

	// Scope the request CPT to the administrator by granting the dedicated
	// capabilities. On a fresh install only the administrator gets them; the
	// admin opts other roles in from the Permissions section.
	ayudawp_euw_setup_capabilities( false );

	// Track the version we just installed so subsequent updates can detect
	// schema changes that require another flush_rewrite_rules() pass.
	update_option( 'ayudawp_euw_version', AYUDAWP_EUW_VERSION );

	// Trigger the welcome notice on the next admin page load.
	set_transient( 'ayudawp_euw_just_activated', 1, MINUTE_IN_SECONDS );

	// Create the withdrawal page if it does not exist yet.
	$page_id = (int) get_option( 'ayudawp_euw_page_id', 0 );

	if ( ! $page_id || ! get_post( $page_id ) ) {

		$h2_right = __( 'Your 14-day right of withdrawal', 'eu-withdrawal-compliance' );
		$p_right  = __( 'You have a 14-day withdrawal period from the moment you receive your order to cancel your purchase, without giving any reason and without penalty, as established by EU Directive 2023/2673 and the consumer protection laws applicable in your country.', 'eu-withdrawal-compliance' );

		$h2_how = __( 'How to exercise it', 'eu-withdrawal-compliance' );
		$p_how  = __( 'Fill in the form below. You will receive an automatic confirmation by email once your request is registered. The refund will be processed within the legal deadline that applies to your purchase.', 'eu-withdrawal-compliance' );

		$h2_excl = __( 'When the right of withdrawal does not apply', 'eu-withdrawal-compliance' );
		$p_excl  = __( 'EU Directive 2011/83/EU lists several categories of goods and services excluded from the 14-day right of withdrawal. The sections below cover the cases that may apply to this shop — remove the ones you do not sell.', 'eu-withdrawal-compliance' );

		$h3_digital = __( 'Digital content downloaded with your express consent', 'eu-withdrawal-compliance' );
		$p_digital  = __( 'When you purchase digital content (downloadable files, streamed videos, online courses with immediate access, software licences, etc.) and expressly request its immediate supply, acknowledging that you lose the withdrawal right by doing so, the 14-day withdrawal period no longer applies — Art. 16(m) of Directive 2011/83/EU. The express consent is collected at checkout.', 'eu-withdrawal-compliance' );

		$h3_service = __( 'Services started during the withdrawal period at your request', 'eu-withdrawal-compliance' );
		$p_service  = __( 'When you ask us to start providing a service before the 14-day withdrawal period has expired, you may still withdraw, but you owe us a pro-rated amount for the work already performed — Art. 14(4)(a) of Directive 2011/83/EU. Your express request is collected at checkout.', 'eu-withdrawal-compliance' );

		$h3_goods = __( 'Custom-made, perishable, hygiene-sealed or sealed media items', 'eu-withdrawal-compliance' );
		$p_goods  = __( 'Items made to your specifications or clearly personalised, goods likely to deteriorate or expire rapidly (perishables), items sealed for hygiene or health protection and unsealed after delivery, and sealed audio, video or software recordings unsealed after delivery are excluded from the right of withdrawal — Art. 16(a), (c), (d), (e), (i) of Directive 2011/83/EU.', 'eu-withdrawal-compliance' );

		$h3_dated = __( 'Dated accommodation, rentals, catering or leisure services', 'eu-withdrawal-compliance' );
		$p_dated  = __( 'Services tied to a specific date or period of performance — accommodation other than for residential purpose (hotels, vacation rentals), vehicle rental, transport of goods, catering and leisure services such as event tickets — are excluded from the right of withdrawal — Art. 16(l) of Directive 2011/83/EU.', 'eu-withdrawal-compliance' );

		// The page is created ready to publish, so it carries no "sample
		// template, review before publishing" paragraph: that warning used to
		// travel as the first block of the content and went live, in front of
		// the shop's customers, whenever nobody remembered to delete it. It is
		// now shown in the dashboard instead, next to the page selector in
		// Settings, for as long as the page stays untouched.
		$blocks = array(
			sprintf( "<!-- wp:heading -->\n<h2 class=\"wp-block-heading\">%s</h2>\n<!-- /wp:heading -->", esc_html( $h2_right ) ),
			sprintf( "<!-- wp:paragraph -->\n<p>%s</p>\n<!-- /wp:paragraph -->", esc_html( $p_right ) ),
			sprintf( "<!-- wp:heading -->\n<h2 class=\"wp-block-heading\">%s</h2>\n<!-- /wp:heading -->", esc_html( $h2_how ) ),
			sprintf( "<!-- wp:paragraph -->\n<p>%s</p>\n<!-- /wp:paragraph -->", esc_html( $p_how ) ),
			sprintf( "<!-- wp:heading -->\n<h2 class=\"wp-block-heading\">%s</h2>\n<!-- /wp:heading -->", esc_html( $h2_excl ) ),
			sprintf( "<!-- wp:paragraph -->\n<p>%s</p>\n<!-- /wp:paragraph -->", esc_html( $p_excl ) ),
			sprintf( "<!-- wp:heading {\"level\":3} -->\n<h3 class=\"wp-block-heading\">%s</h3>\n<!-- /wp:heading -->", esc_html( $h3_digital ) ),
			sprintf( "<!-- wp:paragraph -->\n<p>%s</p>\n<!-- /wp:paragraph -->", esc_html( $p_digital ) ),
			sprintf( "<!-- wp:heading {\"level\":3} -->\n<h3 class=\"wp-block-heading\">%s</h3>\n<!-- /wp:heading -->", esc_html( $h3_service ) ),
			sprintf( "<!-- wp:paragraph -->\n<p>%s</p>\n<!-- /wp:paragraph -->", esc_html( $p_service ) ),
			sprintf( "<!-- wp:heading {\"level\":3} -->\n<h3 class=\"wp-block-heading\">%s</h3>\n<!-- /wp:heading -->", esc_html( $h3_goods ) ),
			sprintf( "<!-- wp:paragraph -->\n<p>%s</p>\n<!-- /wp:paragraph -->", esc_html( $p_goods ) ),
			sprintf( "<!-- wp:heading {\"level\":3} -->\n<h3 class=\"wp-block-heading\">%s</h3>\n<!-- /wp:heading -->", esc_html( $h3_dated ) ),
			sprintf( "<!-- wp:paragraph -->\n<p>%s</p>\n<!-- /wp:paragraph -->", esc_html( $p_dated ) ),
			'<!-- wp:shortcode -->[ayudawp_withdrawal_form]<!-- /wp:shortcode -->',
		);

		$content = implode( "\n\n", $blocks );

		$new_page_id = wp_insert_post(
			array(
				'post_title'   => __( 'Right of withdrawal', 'eu-withdrawal-compliance' ),
				'post_content' => $content,
				'post_status'  => 'publish',
				'post_type'    => 'page',
				'post_author'  => get_current_user_id(),
			),
			true
		);

		if ( ! is_wp_error( $new_page_id ) ) {
			update_option( 'ayudawp_euw_page_id', $new_page_id );

			// Remember which page the plugin wrote, so Settings can tell the
			// bundled template apart from a page the trader has written.
			update_option( 'ayudawp_euw_page_created_id', $new_page_id );
		}
	}
}
register_activation_hook( __FILE__, 'ayudawp_euw_activate' );

/**
 * Deactivation hook.
 */
function ayudawp_euw_deactivate() {
	flush_rewrite_rules();
}
register_deactivation_hook( __FILE__, 'ayudawp_euw_deactivate' );

/**
 * Upgrade routine: runs once after the plugin version changes.
 *
 * Runs at init priority 100 (after add_rewrite_endpoint registers our
 * endpoint at default priority 10), so the new ruleset includes
 * /my-account/withdrawal/. Only triggers when the stored version differs
 * from the running version, so we never pay the cost on regular requests.
 *
 * Without the flush, users updating from 1.0.0 (where the activation hook
 * flushed before the endpoint was registered) would keep getting a 404
 * on the My Account withdrawal endpoint until they manually re-saved
 * the Permalinks settings page.
 */
function ayudawp_euw_maybe_flush_rewrite_rules() {

	if ( get_option( 'ayudawp_euw_version' ) === AYUDAWP_EUW_VERSION ) {
		return;
	}

	flush_rewrite_rules();

	// Set up the scoped CPT capabilities on upgrade. The first run seeds the
	// Permissions option from the roles that could see requests before (those
	// with edit_others_posts), so an existing site keeps its current access
	// until the admin tightens it from the new Permissions section.
	ayudawp_euw_setup_capabilities( true );

	// Drop the settings that merely store a bundled default text, so those
	// texts go back to following the language of each visitor. Installs
	// updating from 2.1.1 or earlier stored them the first time the settings
	// page was saved, because the editors used to pre-fill each field.
	ayudawp_euw_clear_stored_default_texts();

	// The guarantee notice module arrives switched off on an existing shop, and
	// announces itself in the dashboard: it prints an official full-page notice
	// right above the place-order button, which is not a change to make on
	// someone's checkout while they are not looking. `false` tells an install
	// that never saw the option apart from one that switched it off on purpose.
	if ( false === get_option( 'ayudawp_euw_guarantee_enabled', false ) ) {
		update_option( 'ayudawp_euw_guarantee_enabled', 'no' );
		update_option( 'ayudawp_euw_guarantee_announce', 'yes' );
	}

	update_option( 'ayudawp_euw_version', AYUDAWP_EUW_VERSION );
}
add_action( 'init', 'ayudawp_euw_maybe_flush_rewrite_rules', 100 );

/**
 * Declare WooCommerce HPOS compatibility.
 */
add_action(
	'before_woocommerce_init',
	function () {
		if ( class_exists( '\Automattic\WooCommerce\Utilities\FeaturesUtil' ) ) {
			\Automattic\WooCommerce\Utilities\FeaturesUtil::declare_compatibility(
				'custom_order_tables',
				__FILE__,
				true
			);
		}
	}
);

/**
 * Resolve the URL of the settings page.
 *
 * @return string
 */
function ayudawp_euw_get_settings_url() {

	return admin_url( 'edit.php?post_type=ayudawp_withdrawal&page=ayudawp-euw-settings' );
}

/**
 * Add quick links (Settings, Withdrawals) to the plugin row in Plugins screen.
 *
 * @param array $links Existing action links.
 * @return array
 */
function ayudawp_euw_plugin_action_links( $links ) {

	$custom = array(
		'settings'    => sprintf(
			'<a href="%s">%s</a>',
			esc_url( ayudawp_euw_get_settings_url() ),
			esc_html__( 'Settings', 'eu-withdrawal-compliance' )
		),
		'withdrawals' => sprintf(
			'<a href="%s">%s</a>',
			esc_url( admin_url( 'edit.php?post_type=ayudawp_withdrawal' ) ),
			esc_html__( 'Withdrawals', 'eu-withdrawal-compliance' )
		),
	);

	return array_merge( $custom, $links );
}
add_filter( 'plugin_action_links_' . AYUDAWP_EUW_BASENAME, 'ayudawp_euw_plugin_action_links' );

/**
 * Show a one-time admin notice right after the plugin is activated.
 */
function ayudawp_euw_activation_notice() {

	if ( ! get_transient( 'ayudawp_euw_just_activated' ) ) {
		return;
	}

	if ( ! current_user_can( 'manage_options' ) ) {
		return;
	}

	delete_transient( 'ayudawp_euw_just_activated' );

	$page_id   = (int) get_option( 'ayudawp_euw_page_id', 0 );
	$page_edit = ( $page_id && get_post( $page_id ) ) ? get_edit_post_link( $page_id ) : '';
	?>
	<div class="notice notice-success is-dismissible">
		<p>
			<strong><?php esc_html_e( 'EU Withdrawal and Legal Guarantee Compliance is active.', 'eu-withdrawal-compliance' ); ?></strong>
		</p>
		<p>
			<?php
			esc_html_e( 'A "Right of withdrawal" page with a sample legal template was created automatically. Review the text, link the page from your footer and configure the notification email.', 'eu-withdrawal-compliance' );
			?>
		</p>
		<p>
			<a href="<?php echo esc_url( ayudawp_euw_get_settings_url() ); ?>" class="button button-primary">
				<?php esc_html_e( 'Open settings', 'eu-withdrawal-compliance' ); ?>
			</a>
			<?php if ( $page_edit ) : ?>
				<a href="<?php echo esc_url( $page_edit ); ?>" class="button">
					<?php esc_html_e( 'Edit withdrawal page', 'eu-withdrawal-compliance' ); ?>
				</a>
			<?php endif; ?>
		</p>
	</div>
	<?php
}
add_action( 'admin_notices', 'ayudawp_euw_activation_notice' );
