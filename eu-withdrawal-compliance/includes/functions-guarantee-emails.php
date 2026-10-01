<?php
/**
 * The harmonised guarantee notice inside the WooCommerce order emails.
 *
 * The Commission guidelines ask for the notice in the order confirmation as
 * well as on the site, so the customer keeps it on a durable medium. Two pieces
 * do that job together, because they fail in different situations:
 *
 * - the image, with an absolute URL and linked to the full-size file, which is
 *   what most customers see;
 * - the official PDF as an attachment, which is what survives when the mail
 *   client blocks remote images, and many do by default.
 *
 * The image is sized with an inline `style`: Emogrifier (the inliner bundled
 * with WooCommerce) turns `width:500px` into a `width` attribute, which is what
 * Outlook honours. `max-width:100%` keeps it inside narrow mobile clients.
 *
 * Only customer emails ever carry it: the shop already knows about the legal
 * guarantee, and the admin notification is not a durable medium for anyone.
 *
 * @package AyudaWP_EU_Withdrawal
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * WooCommerce email IDs that carry the notice.
 *
 * Defaults to the two emails that confirm a paid or pending order, which is
 * where the guidelines place it. Shops that also want it on the completed or
 * the manual invoice email tick them in settings.
 *
 * @return array<int, string>
 */
function ayudawp_euw_guarantee_email_ids() {

	$default = array( 'customer_on_hold_order', 'customer_processing_order' );
	$stored  = get_option( 'ayudawp_euw_guarantee_email_ids', $default );

	if ( ! is_array( $stored ) ) {
		$stored = $default;
	}

	$ids = array();

	foreach ( $stored as $id ) {

		$id = sanitize_key( (string) $id );

		if ( '' !== $id ) {
			$ids[ $id ] = $id;
		}
	}

	/**
	 * Filters the WooCommerce email IDs where the guarantee notice is included.
	 *
	 * Return an empty array to keep the notice (and the PDF) out of every email.
	 *
	 * @param array<int, string> $ids Email IDs from settings.
	 */
	return (array) apply_filters( 'ayudawp_euw_guarantee_email_ids', array_values( $ids ) );
}

/**
 * Whether this email should carry the notice.
 *
 * Same three questions for the body and for the attachment, asked in one place
 * so they cannot answer differently and leave a PDF attached to an email with
 * no notice in it, or the other way round.
 *
 * @param string   $email_id      WooCommerce email ID.
 * @param mixed    $order         Object the email is about.
 * @param bool     $sent_to_admin Whether the email goes to the shop.
 * @return bool
 */
function ayudawp_euw_guarantee_email_applies( $email_id, $order, $sent_to_admin = false ) {

	if ( $sent_to_admin || ! ayudawp_euw_guarantee_is_enabled() ) {
		return false;
	}

	if ( ! $order instanceof WC_Order ) {
		return false;
	}

	if ( ! in_array( sanitize_key( (string) $email_id ), ayudawp_euw_guarantee_email_ids(), true ) ) {
		return false;
	}

	return ayudawp_euw_guarantee_order_has_goods( $order );
}

/**
 * Print the notice inside the customer order emails.
 *
 * Priority 25, after the withdrawal notice of `functions-emails-wc.php` at 20,
 * so the order of the two blocks is fixed rather than depending on the order
 * the files happen to be required in.
 *
 * @param WC_Order $order         Order.
 * @param bool     $sent_to_admin Whether the email goes to the shop.
 * @param bool     $plain_text    Whether this is the plain-text version.
 * @param WC_Email $email         Email being sent.
 */
function ayudawp_euw_guarantee_render_email( $order, $sent_to_admin, $plain_text, $email ) {

	$email_id = ( is_object( $email ) && isset( $email->id ) ) ? $email->id : '';

	if ( ! ayudawp_euw_guarantee_email_applies( $email_id, $order, $sent_to_admin ) ) {
		return;
	}

	$lang  = ayudawp_euw_guarantee_lang();
	$image = ayudawp_euw_guarantee_image( $lang );

	if ( '' === $image['url'] ) {
		return;
	}

	$heading    = __( 'Your legal guarantee rights', 'eu-withdrawal-compliance' );
	$link_label = __( 'More about your guarantee rights on the European Commission website', 'eu-withdrawal-compliance' );
	$eu_url     = ayudawp_euw_guarantee_youreurope_url( $lang );

	if ( $plain_text ) {

		// wp_kses() and not esc_html() for the text, so that an apostrophe survives
		// WooCommerce's plain-text clean-up: the explanation is in
		// ayudawp_euw_inject_email_notice(), functions-emails-wc.php.
		echo "\n\n----------\n";
		echo wp_kses( $heading, array() ) . "\n";
		echo wp_kses( __( 'Official EU notice:', 'eu-withdrawal-compliance' ), array() ) . ' ' . esc_url( $image['url'] ) . "\n";
		echo wp_kses( $link_label, array() ) . ': ' . esc_url( $eu_url ) . "\n";

		$note = ayudawp_euw_guarantee_note( false );

		if ( '' !== $note ) {
			echo wp_kses( $note, array() ) . "\n";
		}

		return;
	}

	$html = sprintf(
		'<h2>%s</h2>',
		esc_html( $heading )
	);

	$html .= sprintf(
		'<p><a href="%1$s" target="_blank" rel="noopener nofollow"><img src="%1$s" width="500" alt="%2$s" style="display:block;width:500px;max-width:100%%;height:auto;border:0"></a></p>',
		esc_url( $image['url'] ),
		esc_attr__( 'European Union harmonised notice on the legal guarantee of conformity', 'eu-withdrawal-compliance' )
	);

	$html .= sprintf(
		'<p><a href="%1$s" target="_blank" rel="noopener nofollow">%2$s</a></p>',
		esc_url( $eu_url ),
		esc_html( $link_label )
	);

	$note = ayudawp_euw_guarantee_note();

	if ( '' !== $note ) {
		$html .= '<p>' . $note . '</p>';
	}

	echo wp_kses_post( $html );
}
add_action( 'woocommerce_email_after_order_table', 'ayudawp_euw_guarantee_render_email', 25, 4 );

/**
 * Attach the official PDF to the same customer emails.
 *
 * The path comes from `ayudawp_euw_guarantee_pdf_path()`, which only ever
 * returns a file inside the plugin's own notice directory.
 *
 * @param array    $attachments Attachment paths.
 * @param string   $email_id    WooCommerce email ID.
 * @param mixed    $object      Object the email is about (a WC_Order for order emails).
 * @param WC_Email $email       Email being sent.
 * @return array
 */
function ayudawp_euw_guarantee_attach_pdf( $attachments, $email_id, $object = null, $email = null ) { // phpcs:ignore Generic.CodeAnalysis.UnusedFunctionParameter.Found

	if ( ! is_array( $attachments ) ) {
		$attachments = array();
	}

	if ( 'yes' !== get_option( 'ayudawp_euw_guarantee_pdf_attach', 'yes' ) ) {
		return $attachments;
	}

	// The attachments filter carries no $sent_to_admin flag, so the admin emails
	// are kept out by their IDs: every default is a customer email, and the
	// settings screen only offers customer emails to tick.
	if ( ! ayudawp_euw_guarantee_email_applies( $email_id, $object ) ) {
		return $attachments;
	}

	$path = ayudawp_euw_guarantee_pdf_path( ayudawp_euw_guarantee_lang() );

	if ( '' !== $path && ! in_array( $path, $attachments, true ) ) {
		$attachments[] = $path;
	}

	return $attachments;
}
add_filter( 'woocommerce_email_attachments', 'ayudawp_euw_guarantee_attach_pdf', 10, 4 );
