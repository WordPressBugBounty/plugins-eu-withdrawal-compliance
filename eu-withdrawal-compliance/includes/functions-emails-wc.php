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

	// Only on customer-facing emails relevant to the withdrawal period. The
	// manual `customer_invoice` email can fire on any status, so its order is
	// validated by `ayudawp_euw_should_show_withdrawal()` below.
	$allowed_emails = array(
		'customer_processing_order',
		'customer_completed_order',
		'customer_invoice',
	);

	$email_id = isset( $email->id ) ? $email->id : '';

	/**
	 * Filter the list of WooCommerce email IDs where the notice is included.
	 *
	 * @param array $allowed_emails Default list of email IDs.
	 */
	$allowed_emails = apply_filters( 'ayudawp_euw_email_ids', $allowed_emails );

	if ( ! in_array( $email_id, $allowed_emails, true ) ) {
		return;
	}

	// Order-level eligibility: the configured order statuses.
	if ( ! ayudawp_euw_should_show_withdrawal( $order ) ) {
		return;
	}

	// Resolve the URL of the withdrawal page (translation-aware for multilingual sites).
	$page_id = ayudawp_euw_get_page_id();

	if ( ! $page_id || ! get_post( $page_id ) ) {
		return;
	}

	$page_url = get_permalink( $page_id );

	// Append order_id so the form can be pre-filled. Use the displayed order
	// number when available (Sequential Order Numbers and similar plugins) so
	// the customer sees in the form the same reference shown in their receipt.
	if ( $order ) {
		$order_ref = method_exists( $order, 'get_order_number' ) ? $order->get_order_number() : $order->get_id();
		$page_url  = add_query_arg( 'order_id', $order_ref, $page_url );
	}

	if ( $plain_text ) {
		echo "\n\n----------\n";
		echo esc_html__( 'Right of withdrawal', 'eu-withdrawal-compliance' ) . "\n";
		echo esc_html__( 'You have 14 days from receipt to exercise your withdrawal right without giving any reason. To do so, use our online withdrawal function:', 'eu-withdrawal-compliance' ) . "\n";
		echo esc_url( $page_url ) . "\n";
		return;
	}

	?>
	<h3><?php esc_html_e( 'Right of withdrawal', 'eu-withdrawal-compliance' ); ?></h3>
	<p><?php esc_html_e( 'You have 14 days from receipt to exercise your withdrawal right without giving any reason.', 'eu-withdrawal-compliance' ); ?></p>
	<p>
		<a href="<?php echo esc_url( $page_url ); ?>" rel="noopener nofollow" style="display: inline-block; padding: 10px 18px; background-color: #2271b1; color: #ffffff; text-decoration: none; border-radius: 4px; font-weight: 600;">
			<?php esc_html_e( 'Withdraw from contract here', 'eu-withdrawal-compliance' ); ?>
		</a>
	</p>
	<?php
}
add_action( 'woocommerce_email_after_order_table', 'ayudawp_euw_inject_email_notice', 20, 4 );
