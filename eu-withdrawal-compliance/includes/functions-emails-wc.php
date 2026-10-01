<?php
/**
 * Inject the withdrawal notice into WooCommerce transactional emails.
 *
 * The directive obliges traders to inform consumers about the existence
 * and placement of the withdrawal function. We add a short notice with
 * a link to the form in the customer-facing emails covering the period
 * during which the right of withdrawal is exercisable.
 *
 * @package AyudaWP_EU_Withdrawal
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * WooCommerce emails that carry the withdrawal notice.
 *
 * Follows the order statuses ticked in "Show withdrawal option for", so that
 * one setting decides every place the option shows up. The list used to be
 * fixed to the processing and completed emails, and ticking "On hold" brought
 * the button to My Account but never the notice to the on-hold email, which is
 * the first one a customer paying by bank transfer receives.
 *
 * @param object|null $order Optional WC_Order, passed on to the status filter.
 * @return array<int, string> Email IDs.
 */
function ayudawp_euw_get_notice_email_ids( $order = null ) {

	// The customer email WooCommerce sends when an order reaches each status.
	// The statuses missing here (pending, cancelled, failed) have none to carry it.
	$by_status = array(
		'on-hold'    => 'customer_on_hold_order',
		'processing' => 'customer_processing_order',
		'completed'  => 'customer_completed_order',
		'refunded'   => 'customer_refunded_order',
	);

	$email_ids = array();

	foreach ( ayudawp_euw_get_allowed_statuses( $order ) as $status ) {
		if ( isset( $by_status[ $status ] ) ) {
			$email_ids[] = $by_status[ $status ];
		}
	}

	// Sent by hand from the order screen, whatever the status: the order itself
	// is checked by ayudawp_euw_should_show_withdrawal() before printing.
	$email_ids[] = 'customer_invoice';

	/**
	 * Filter the list of WooCommerce email IDs where the notice is included.
	 *
	 * @param array $email_ids Email IDs derived from the eligible order statuses.
	 */
	return (array) apply_filters( 'ayudawp_euw_email_ids', $email_ids );
}

/**
 * Print the withdrawal notice inside WooCommerce emails.
 *
 * Hooked to woocommerce_email_after_order_table for selected email types.
 *
 * @param object $order         WC_Order instance.
 * @param bool   $sent_to_admin Whether the email is going to the admin.
 * @param bool   $plain_text    Whether the email is plain text.
 * @param object $email         WC_Email instance.
 */
function ayudawp_euw_inject_email_notice( $order, $sent_to_admin, $plain_text, $email ) {

	// Never include the notice in admin emails.
	if ( $sent_to_admin ) {
		return;
	}

	$email_id = isset( $email->id ) ? $email->id : '';

	if ( ! in_array( $email_id, ayudawp_euw_get_notice_email_ids( $order ), true ) ) {
		return;
	}

	// Order-level eligibility: the configured order statuses, and the order not
	// holding only excluded products.
	if ( ! ayudawp_euw_should_show_withdrawal( $order ) ) {
		return;
	}

	// The withdrawal page (translation-aware for multilingual sites), with the
	// order number appended so the form can be pre-filled.
	$page_url = ayudawp_euw_get_public_form_url( $order );

	if ( '' === $page_url ) {
		return;
	}

	// On an order that mixes both, the products the right does not apply to.
	$excluded_names = ayudawp_euw_get_mixed_order_excluded_names( $order );

	if ( $plain_text ) {

		// Not esc_html(): WooCommerce strips the tags of the plain-text body and
		// decodes the entities in it, but only the ones in its own list, and the
		// &#039; that esc_html() writes for an apostrophe is not there. It is
		// deleted (woocommerce/includes/emails/class-wc-email.php, $plain_search),
		// so "l'ordine" arrived as "lordine". wp_kses() with no allowed tags still
		// strips markup and leaves quotes alone.
		echo "\n\n----------\n";
		echo wp_kses( __( 'Right of withdrawal', 'eu-withdrawal-compliance' ), array() ) . "\n";
		echo wp_kses( __( 'You have 14 days from receipt to exercise your withdrawal right without giving any reason. To do so, use our online withdrawal function:', 'eu-withdrawal-compliance' ), array() ) . "\n";
		echo esc_url( $page_url ) . "\n";

		if ( ! empty( $excluded_names ) ) {
			echo wp_kses(
				sprintf(
					/* translators: %s: comma-separated names of the products in the order that are excluded from the right of withdrawal. */
					__( 'The right of withdrawal does not apply to these items in your order: %s.', 'eu-withdrawal-compliance' ),
					implode( ', ', $excluded_names )
				),
				array()
			) . "\n";
		}

		return;
	}

	?>
	<h3><?php esc_html_e( 'Right of withdrawal', 'eu-withdrawal-compliance' ); ?></h3>
	<p><?php esc_html_e( 'You have 14 days from receipt to exercise your withdrawal right without giving any reason.', 'eu-withdrawal-compliance' ); ?></p>
	<?php if ( ! empty( $excluded_names ) ) : ?>
		<p>
			<?php
			printf(
				/* translators: %s: comma-separated names of the products in the order that are excluded from the right of withdrawal. */
				esc_html__( 'The right of withdrawal does not apply to these items in your order: %s.', 'eu-withdrawal-compliance' ),
				esc_html( implode( ', ', $excluded_names ) )
			);
			?>
		</p>
	<?php endif; ?>
	<p>
		<a href="<?php echo esc_url( $page_url ); ?>" rel="noopener nofollow" style="display: inline-block; padding: 10px 18px; background-color: #2271b1; color: #ffffff; text-decoration: none; border-radius: 4px; font-weight: 600;">
			<?php esc_html_e( 'Withdraw from contract here', 'eu-withdrawal-compliance' ); ?>
		</a>
	</p>
	<?php
}
add_action( 'woocommerce_email_after_order_table', 'ayudawp_euw_inject_email_notice', 20, 4 );
