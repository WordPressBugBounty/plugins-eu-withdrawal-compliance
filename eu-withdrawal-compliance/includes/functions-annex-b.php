<?php
/**
 * Annex I.B model withdrawal form.
 *
 * Article 6(1)(h) and Annex I.B of Directive 2011/83/EU oblige traders to
 * provide consumers with a standardised model withdrawal form. The online
 * function introduced by Directive 2023/2673 complements but does not
 * replace this obligation, so the plugin renders the model dynamically from
 * shop data (so every trader gets a usable one without editing PHP) and
 * exposes it as:
 *
 * - A collapsible block below the public withdrawal form.
 * - A standalone printable view through a query var (?ayudawp_euw_annex_b=1).
 *
 * The settings module owns the on/off switch and the optional phone field.
 *
 * @package AyudaWP_EU_Withdrawal
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Whether the Annex I.B block is enabled in settings.
 *
 * @return bool
 */
function ayudawp_euw_annex_b_is_enabled() {

	return 'yes' === get_option( 'ayudawp_euw_annex_b_enabled', 'yes' );
}

/**
 * Build the trader-side data array used to populate the model.
 *
 * Pulls the trader name from WordPress and the email/phone from the plugin
 * settings. The address comes from the dedicated setting when filled in, and
 * otherwise falls back to the WooCommerce store address when available — so
 * non-WooCommerce sites can still provide the address Annex I.B requires.
 *
 * @return array<string, string>
 */
function ayudawp_euw_annex_b_get_trader_data() {

	$name = ayudawp_euw_get_site_name();

	// Contact email for the model form. Priority: the dedicated trader email,
	// then the first notification recipient (the option may hold a comma-separated
	// list), then the site admin email. The model form carries a single public
	// contact address, not the internal notification list.
	$email = trim( (string) get_option( 'ayudawp_euw_trader_email', '' ) );

	if ( '' === $email ) {
		$notify = (string) get_option( 'ayudawp_euw_notify_email', '' );
		$parts  = array_map( 'trim', explode( ',', $notify ) );
		$email  = isset( $parts[0] ) ? $parts[0] : '';
	}

	if ( '' === $email || ! is_email( $email ) ) {
		$email = (string) get_option( 'admin_email' );
	}

	$phone = (string) get_option( 'ayudawp_euw_trader_phone', '' );

	$address_lines = array();

	$manual_address = trim( (string) get_option( 'ayudawp_euw_trader_address', '' ) );

	if ( '' !== $manual_address ) {

		// A manually configured address always wins: it is the only source on
		// non-WooCommerce sites and lets a shop override the store address with
		// a dedicated returns address. Split into lines so the renderer can
		// nl2br() each one.
		foreach ( preg_split( '/\r\n|\r|\n/', $manual_address ) as $line ) {

			$line = trim( $line );

			if ( '' !== $line ) {
				$address_lines[] = $line;
			}
		}
	} elseif ( class_exists( 'WooCommerce' ) && function_exists( 'WC' ) ) {

		$countries = WC()->countries;

		if ( $countries ) {

			$address_1 = trim( (string) $countries->get_base_address() );
			$address_2 = trim( (string) $countries->get_base_address_2() );
			$city      = trim( (string) $countries->get_base_city() );
			$postcode  = trim( (string) $countries->get_base_postcode() );
			$state     = trim( (string) $countries->get_base_state() );
			$country   = trim( (string) $countries->get_base_country() );

			if ( '' !== $address_1 ) {
				$address_lines[] = $address_1;
			}
			if ( '' !== $address_2 ) {
				$address_lines[] = $address_2;
			}

			$city_line = trim( $postcode . ' ' . $city );

			if ( '' !== $city_line ) {
				$address_lines[] = $city_line;
			}

			if ( '' !== $state ) {
				$address_lines[] = $state;
			}

			if ( '' !== $country && $countries->countries && isset( $countries->countries[ $country ] ) ) {
				$address_lines[] = $countries->countries[ $country ];
			} elseif ( '' !== $country ) {
				$address_lines[] = $country;
			}
		}
	}

	return array(
		'name'    => $name,
		'address' => implode( "\n", $address_lines ),
		'email'   => $email,
		'phone'   => $phone,
	);
}

/**
 * Build the inner HTML of the Annex I.B model form.
 *
 * Returns escaped HTML, ready to be embedded inside an `<article>` wrapper
 * (used by both the collapsible block and the printable view).
 *
 * @return string
 */
function ayudawp_euw_annex_b_get_inner_html() {

	$trader = ayudawp_euw_annex_b_get_trader_data();

	// Trader fields are kept raw here and escaped at the point of output below,
	// so the escaping is visible at every echo (no pre-escaped variables and no
	// EscapeOutput suppressions). The address preserves its line breaks via
	// nl2br(), let back in through wp_kses() with <br> as the only allowed tag.
	$name  = '' !== $trader['name'] ? $trader['name'] : __( '[Trader name]', 'eu-withdrawal-compliance' );
	$email = '' !== $trader['email'] ? $trader['email'] : __( '[Email address]', 'eu-withdrawal-compliance' );

	ob_start();
	?>
	<p class="ayudawp-euw-annex-b__intro">
		<em><?php esc_html_e( 'Complete and return this form only if you wish to withdraw from the contract.', 'eu-withdrawal-compliance' ); ?></em>
	</p>

	<address class="ayudawp-euw-annex-b__trader">
		<strong><?php esc_html_e( 'To:', 'eu-withdrawal-compliance' ); ?></strong><br>
		<?php echo esc_html( $name ); ?><br>
		<?php
		if ( '' !== $trader['address'] ) {
			echo wp_kses( nl2br( esc_html( $trader['address'] ) ), array( 'br' => array() ) );
		} else {
			esc_html_e( '[Postal address]', 'eu-withdrawal-compliance' );
		}
		?><br>
		<?php
		printf(
			/* translators: %s: trader email address. */
			esc_html__( 'Email: %s', 'eu-withdrawal-compliance' ),
			esc_html( $email )
		);
		?>
		<?php
		// Phone is optional: only add its line when the trader configured one, so
		// the printable model form never shows a placeholder for an empty phone.
		if ( '' !== $trader['phone'] ) {
			echo '<br>';
			printf(
				/* translators: %s: trader phone number. */
				esc_html__( 'Phone: %s', 'eu-withdrawal-compliance' ),
				esc_html( $trader['phone'] )
			);
		}
		?>
	</address>

	<p><?php esc_html_e( 'I/We hereby give notice that I/we withdraw from my/our contract of sale of the following goods / for the supply of the following service:', 'eu-withdrawal-compliance' ); ?></p>
	<p class="ayudawp-euw-annex-b__field">______________________________________________________________</p>

	<p><?php esc_html_e( 'Ordered on / received on:', 'eu-withdrawal-compliance' ); ?></p>
	<p class="ayudawp-euw-annex-b__field">______________________________________________________________</p>

	<p><?php esc_html_e( 'Name of consumer(s):', 'eu-withdrawal-compliance' ); ?></p>
	<p class="ayudawp-euw-annex-b__field">______________________________________________________________</p>

	<p><?php esc_html_e( 'Address of consumer(s):', 'eu-withdrawal-compliance' ); ?></p>
	<p class="ayudawp-euw-annex-b__field">______________________________________________________________</p>

	<p><?php esc_html_e( 'Signature of consumer(s) (only if this form is notified on paper):', 'eu-withdrawal-compliance' ); ?></p>
	<p class="ayudawp-euw-annex-b__field">______________________________________________________________</p>

	<p><?php esc_html_e( 'Date:', 'eu-withdrawal-compliance' ); ?></p>
	<p class="ayudawp-euw-annex-b__field">______________________________________________________________</p>

	<p class="ayudawp-euw-annex-b__source">
		<small>
			<?php
			echo wp_kses(
				__( 'Source: Annex I, Part B of <a href="https://eur-lex.europa.eu/eli/dir/2011/83/oj" target="_blank" rel="noopener nofollow">Directive 2011/83/EU</a> of the European Parliament and of the Council on consumer rights.', 'eu-withdrawal-compliance' ),
				array(
					'a' => array(
						'href'   => array(),
						'target' => array(),
						'rel'    => array(),
					),
				)
			);
			?>
		</small>
	</p>
	<?php

	return (string) ob_get_clean();
}

/**
 * Build the printable URL of the Annex I.B model form.
 *
 * Anchors on the configured withdrawal page so the print view shares the
 * site theme (header, footer) and uses one of WordPress' standard routes.
 *
 * @return string Permalink or empty string if no page is configured.
 */
function ayudawp_euw_annex_b_get_print_url() {

	$page_id = ayudawp_euw_get_page_id();

	if ( ! $page_id || ! get_post( $page_id ) ) {
		return '';
	}

	$base_url = get_permalink( $page_id );

	if ( ! $base_url ) {
		return '';
	}

	return add_query_arg( 'ayudawp_euw_annex_b', '1', $base_url );
}

/**
 * Render the collapsible Annex I.B block below the public withdrawal form.
 *
 * Hooks the action exposed by ayudawp_euw_render_form() so the block stays
 * decoupled from the form module.
 */
function ayudawp_euw_render_annex_b_collapsible() {

	if ( ! ayudawp_euw_annex_b_is_enabled() ) {
		return;
	}

	$print_url = ayudawp_euw_annex_b_get_print_url();

	?>
	<details class="ayudawp-euw-annex-b" id="ayudawp-euw-annex-b">
		<summary>
			<?php esc_html_e( 'Model withdrawal form (Annex I.B of Directive 2011/83/EU)', 'eu-withdrawal-compliance' ); ?>
		</summary>
		<article class="ayudawp-euw-annex-b__article">
			<?php
			// Inner HTML is fully escaped inside the builder, but contains markup tags
			// (address, strong, p, em, etc.) so wp_kses_post() is the right wrapper
			// here.
			echo wp_kses_post( ayudawp_euw_annex_b_get_inner_html() );
			?>
			<?php if ( '' !== $print_url ) : ?>
				<p class="ayudawp-euw-annex-b__print">
					<a href="<?php echo esc_url( $print_url ); ?>" target="_blank" rel="noopener nofollow">
						<?php esc_html_e( 'Open printable version', 'eu-withdrawal-compliance' ); ?>
					</a>
				</p>
			<?php endif; ?>
		</article>
	</details>
	<?php
}
add_action( 'ayudawp_euw_after_form', 'ayudawp_euw_render_annex_b_collapsible', 20 );

/**
 * Register the public query var that triggers the printable view.
 *
 * @param array $vars Existing public query vars.
 * @return array
 */
function ayudawp_euw_annex_b_register_query_var( $vars ) {

	$vars[] = 'ayudawp_euw_annex_b';

	return $vars;
}
add_filter( 'query_vars', 'ayudawp_euw_annex_b_register_query_var' );

/**
 * Serve a standalone printable view of the Annex I.B model.
 *
 * Intercepts the request as soon as the query var is set on the configured
 * withdrawal page and renders a self-contained HTML document — no header,
 * footer or sidebar from the active theme. The output uses the bundled
 * `annex-b-print.css` (CSS sized for an A4 sheet with `@page` rules and a
 * `@media print` block that hides the toolbar) and a small JS snippet that
 * wires the toolbar button to `window.print()`.
 *
 * Modeled after the printable certificate served by the Terms & Conditions
 * Consent Log plugin so the visual identity stays consistent across the
 * AyudaWP plugin family.
 */
function ayudawp_euw_annex_b_render_standalone() {

	if ( is_admin() ) {
		return;
	}

	if ( ! is_singular() ) {
		return;
	}

	if ( ! get_query_var( 'ayudawp_euw_annex_b' ) ) {
		return;
	}

	$page_id = ayudawp_euw_get_page_id();

	if ( ! $page_id || get_queried_object_id() !== $page_id ) {
		return;
	}

	// Register and enqueue the standalone-page assets manually, since
	// `wp_head()` / `wp_footer()` never run on this view.
	wp_register_style(
		'ayudawp-euw-annex-b-print',
		AYUDAWP_EUW_URL . 'assets/css/annex-b-print.css',
		array(),
		AYUDAWP_EUW_VERSION
	);
	wp_register_script(
		'ayudawp-euw-annex-b-print',
		AYUDAWP_EUW_URL . 'assets/js/annex-b-print.js',
		array(),
		AYUDAWP_EUW_VERSION,
		true
	);

	$site_name      = ayudawp_euw_get_site_name();
	$site_url       = (string) home_url( '/' );
	$generated_at   = gmdate( 'Y-m-d H:i:s' );
	$back_url       = (string) get_permalink( $page_id );
	$document_title = __( 'Model withdrawal form (Annex I.B)', 'eu-withdrawal-compliance' );

	nocache_headers();
	header( 'Content-Type: text/html; charset=utf-8' );
	?><!doctype html>
<html lang="<?php echo esc_attr( (string) get_bloginfo( 'language' ) ); ?>">
<head>
	<meta charset="utf-8">
	<title><?php echo esc_html( $document_title ); ?></title>
	<meta name="viewport" content="width=device-width, initial-scale=1">
	<meta name="robots" content="noindex,nofollow">
	<?php wp_print_styles( array( 'ayudawp-euw-annex-b-print' ) ); ?>
</head>
<body>
	<div class="toolbar">
		<?php if ( '' !== $back_url ) : ?>
			<a href="<?php echo esc_url( $back_url ); ?>" class="secondary" rel="noopener nofollow">← <?php esc_html_e( 'Back to withdrawal page', 'eu-withdrawal-compliance' ); ?></a>
		<?php endif; ?>
		<button type="button" class="ayudawp-euw-annex-b-print"><?php esc_html_e( 'Print / Save as PDF', 'eu-withdrawal-compliance' ); ?></button>
	</div>

	<div class="page">
		<header class="annex-b-header">
			<p class="site-name"><?php echo esc_html( $site_name ); ?></p>
		</header>

		<h1><?php echo esc_html( $document_title ); ?></h1>
		<p class="subtitle"><?php esc_html_e( 'Annex I, Part B of Directive 2011/83/EU on consumer rights', 'eu-withdrawal-compliance' ); ?></p>

		<article class="ayudawp-euw-annex-b ayudawp-euw-annex-b--print">
			<?php echo wp_kses_post( ayudawp_euw_annex_b_get_inner_html() ); ?>
		</article>

		<footer class="annex-b-footer">
			<?php
			printf(
				/* translators: 1: site URL, 2: generation timestamp UTC. */
				esc_html__( 'Generated by %1$s on %2$s UTC.', 'eu-withdrawal-compliance' ),
				esc_html( $site_url ),
				esc_html( $generated_at )
			);
			?>
		</footer>
	</div>
	<?php wp_print_scripts( array( 'ayudawp-euw-annex-b-print' ) ); ?>
</body>
</html>
	<?php
	exit;
}
add_action( 'template_redirect', 'ayudawp_euw_annex_b_render_standalone' );
