<?php
/**
 * Withdrawal form rendering.
 *
 * @package AyudaWP_EU_Withdrawal
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Canonical reminder that a withdrawal request is subject to the statutory
 * deadlines and conditions and is not accepted merely by being submitted.
 *
 * Reused across the public form, the confirmation screen and the customer
 * acknowledgement email so the message stays consistent everywhere. Returned
 * as a plain translatable string; callers escape it for their own context.
 *
 * @return string
 */
function ayudawp_euw_legal_conditions_text() {

	return __( 'Your withdrawal is subject to the legal deadlines and conditions: the 14-day period (for goods, counted from delivery; for digital content, from the start of the download) and the statutory exceptions to the right of withdrawal. We verify these before confirming, so submitting a request does not by itself guarantee its acceptance.', 'eu-withdrawal-compliance' );
}

/**
 * Default intro paragraph shown above the public withdrawal form.
 *
 * Single source of truth so the settings editor pre-fills the textarea with the
 * same copy used when the merchant has not customised it.
 *
 * @return string
 */
function ayudawp_euw_form_intro_default() {

	return __( 'Use this form to exercise your withdrawal right under applicable EU consumer protection law. Submitting it registers your request and we will confirm by email; it does not by itself mean the withdrawal is accepted.', 'eu-withdrawal-compliance' );
}

/**
 * Default text of the optional "bought as a consumer" self-declaration.
 *
 * @return string
 */
function ayudawp_euw_consumer_check_default_text() {

	return __( 'I confirm that I made this purchase as a consumer, that is, as a natural person acting for purposes outside my trade, business or profession.', 'eu-withdrawal-compliance' );
}

/**
 * Whether a form field was flagged invalid on the previous submission.
 *
 * @param string             $field  Field key.
 * @param array<int, string> $errors Field keys that failed validation.
 * @return string The error CSS class (with a leading space) or an empty string.
 */
function ayudawp_euw_field_error_class( $field, $errors ) {

	return in_array( $field, (array) $errors, true ) ? ' ayudawp-euw-field--error' : '';
}

/**
 * Value for a field's aria-invalid attribute on the previous submission.
 *
 * @param string             $field  Field key.
 * @param array<int, string> $errors Field keys that failed validation.
 * @return string 'true' or 'false'.
 */
function ayudawp_euw_field_aria_invalid( $field, $errors ) {

	return in_array( $field, (array) $errors, true ) ? 'true' : 'false';
}

/**
 * Print the inline, per-field error note when a field failed validation.
 *
 * Keeps the feedback next to the field that needs fixing (a red highlight plus a
 * short reason) instead of stacking every message at the top of the form.
 *
 * @param string             $field      Field key.
 * @param array<int, string> $errors     Field keys that failed validation.
 * @param string             $error_code The active error code ('fields', 'email', ...).
 */
function ayudawp_euw_print_field_error( $field, $errors, $error_code ) {

	if ( ! in_array( $field, (array) $errors, true ) ) {
		return;
	}

	if ( 'email' === $error_code ) {
		$message = ( 'email' === $field ) ? __( 'Enter a valid email address.', 'eu-withdrawal-compliance' ) : '';
	} elseif ( 'fields' === $error_code ) {
		$message = in_array( $field, array( 'privacy', 'consumer' ), true )
			? __( 'Please tick this box to continue.', 'eu-withdrawal-compliance' )
			: __( 'This field is required.', 'eu-withdrawal-compliance' );
	} else {
		// order / status / expired: the notice above the form already explains it.
		$message = '';
	}

	if ( '' === $message ) {
		return;
	}

	printf(
		'<span class="ayudawp-euw-field-error" role="alert">%s</span>',
		esc_html( $message )
	);
}

/**
 * Render the withdrawal form HTML directly to output.
 *
 * This function echoes the form markup. Callers that need a string (e.g. the
 * shortcode) should use ayudawp_euw_get_form_html() instead.
 *
 * @param array $atts Optional atts. Currently supports:
 *                    - order_id: pre-fill order number.
 *                    - email: pre-fill email.
 *                    - name: pre-fill full name.
 *                    - date: pre-fill order date (Y-m-d).
 *                    - scope: pre-select scope (full|partial).
 *                    - details: pre-fill the free-text details.
 *                    - lock: when true, render the order-derived fields (name,
 *                      email, order, date) read-only. Only set by the My
 *                      Account button, where the values come from an order the
 *                      logged-in customer owns, so they match what we validate.
 */
function ayudawp_euw_render_form( $atts = array() ) {

	$atts = wp_parse_args(
		$atts,
		array(
			'order_id'       => '',
			'email'          => '',
			'name'           => '',
			'date'           => '',
			'scope'          => '',
			'details'        => '',
			'lock'           => false,
			'consumer_check' => '',
		)
	);

	// Field values to render. Start from whatever the caller pre-filled; the
	// edit/recover tokens handled below may override these. The privacy consent
	// is never pre-filled or restored.
	$prefill = array(
		'name'    => $atts['name'],
		'email'   => $atts['email'],
		'order'   => $atts['order_id'],
		'date'    => $atts['date'],
		'scope'   => $atts['scope'],
		'details' => $atts['details'],
	);

	$lock = ! empty( $atts['lock'] );

	// Show success/error message if the form was just submitted. Both flags
	// only render after a redirect from functions-handler.php that adds an
	// 'ayudawp_euw_form_feedback' nonce to the URL.
	$success = false;
	$error   = '';
	$errors  = array();

	if ( isset( $_GET['_wpnonce'] )
		&& wp_verify_nonce(
			sanitize_text_field( wp_unslash( $_GET['_wpnonce'] ) ),
			'ayudawp_euw_form_feedback'
		)
	) {
		$success = isset( $_GET['ayudawp_euw_sent'] )
			&& '1' === sanitize_text_field( wp_unslash( $_GET['ayudawp_euw_sent'] ) );
		$error   = isset( $_GET['ayudawp_euw_error'] )
			? sanitize_key( wp_unslash( $_GET['ayudawp_euw_error'] ) )
			: '';
	}

	if ( $success ) {
		?>
		<div class="ayudawp-euw-wrapper ayudawp-euw-wrapper--feedback" id="ayudawp-euw-form">
			<div class="ayudawp-euw-notice ayudawp-euw-notice--success" role="status">
				<p><?php esc_html_e( 'We have received your withdrawal request and sent a confirmation email. Your request will be reviewed against the legal deadlines and conditions before it is accepted.', 'eu-withdrawal-compliance' ); ?></p>
			</div>
		</div>
		<?php
		return;
	}

	// Step 2: a validated declaration is waiting to be confirmed. Article
	// 11a(3) requires the consumer to confirm through a dedicated confirmation
	// function before the request is submitted. The single-use token is the
	// secret, so this does not depend on the feedback nonce checked above.
	// phpcs:ignore WordPress.Security.NonceVerification.Recommended
	$confirm_token = isset( $_GET['ayudawp_euw_confirm'] ) ? sanitize_text_field( wp_unslash( $_GET['ayudawp_euw_confirm'] ) ) : '';

	if ( '' !== $confirm_token ) {
		$pending = get_transient( 'ayudawp_euw_pending_' . $confirm_token );

		if ( is_array( $pending ) ) {
			ayudawp_euw_render_confirmation( $confirm_token, $pending );
			return;
		}

		// Expired or already used: fall back to the form with a notice.
		$error = 'session';
	}

	// "Edit data" on the confirmation screen sends the customer back here with
	// the pending token so the form is repopulated and editable. The transient
	// is kept (resubmitting mints a fresh token) and expires on its own.
	// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- the single-use token is the secret.
	$edit_token = isset( $_GET['ayudawp_euw_edit'] ) ? sanitize_text_field( wp_unslash( $_GET['ayudawp_euw_edit'] ) ) : '';

	if ( '' !== $edit_token ) {
		$editing = get_transient( 'ayudawp_euw_pending_' . $edit_token );

		if ( is_array( $editing ) ) {
			foreach ( array( 'name', 'email', 'order', 'date', 'scope', 'details' ) as $field ) {
				if ( isset( $editing[ $field ] ) ) {
					$prefill[ $field ] = $editing[ $field ];
				}
			}

			$lock = false;
		}
	}

	// Restore the values from a failed validation attempt so the customer does
	// not have to retype everything (see ayudawp_euw_redirect_with_error).
	// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- the single-use token is the secret.
	$recover_token = isset( $_GET['ayudawp_euw_recover'] ) ? sanitize_text_field( wp_unslash( $_GET['ayudawp_euw_recover'] ) ) : '';

	if ( '' !== $recover_token ) {
		$recovered = get_transient( 'ayudawp_euw_recover_' . $recover_token );

		if ( is_array( $recovered ) ) {
			delete_transient( 'ayudawp_euw_recover_' . $recover_token );

			foreach ( array( 'name', 'email', 'order', 'date', 'scope', 'details' ) as $field ) {
				if ( isset( $recovered[ $field ] ) ) {
					$prefill[ $field ] = $recovered[ $field ];
				}
			}

			if ( isset( $recovered['_invalid'] ) && is_array( $recovered['_invalid'] ) ) {
				$errors = $recovered['_invalid'];
			}

			$lock = false;
		}
	}

	$lock_attr = $lock ? 'readonly' : '';
	?>
	<div class="ayudawp-euw-wrapper" id="ayudawp-euw-form">

		<?php if ( $error ) : ?>
			<div class="ayudawp-euw-notice ayudawp-euw-notice--error" role="alert">
				<p><?php echo esc_html( ayudawp_euw_get_error_message( $error ) ); ?></p>
			</div>
		<?php endif; ?>

		<?php
		// Intro paragraph: editable in Settings and hideable via its toggle. Empty
		// text falls back to the bundled default; unticking the toggle hides the
		// paragraph entirely (e.g. when the page already explains the withdrawal
		// above the shortcode). The legal note below stays fixed.
		$intro_enabled = 'yes' === get_option( 'ayudawp_euw_form_intro_enabled', 'yes' );
		$intro_text    = (string) get_option( 'ayudawp_euw_form_intro_text', '' );

		if ( '' === trim( $intro_text ) ) {
			$intro_text = ayudawp_euw_form_intro_default();
		}

		if ( $intro_enabled && '' !== trim( $intro_text ) ) :
			?>
			<p class="ayudawp-euw-intro"><?php echo esc_html( $intro_text ); ?></p>
		<?php endif; ?>

		<p class="ayudawp-euw-legal-note">
			<?php echo esc_html( ayudawp_euw_legal_conditions_text() ); ?>
		</p>

		<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" class="ayudawp-euw-form" novalidate>

			<input type="hidden" name="action" value="ayudawp_euw_review">

			<?php
			wp_nonce_field( 'ayudawp_euw_review_action', 'ayudawp_euw_nonce' );

			// Honeypot field for basic spam protection.
			?>
			<div class="ayudawp-euw-hp" aria-hidden="true">
				<label for="ayudawp_euw_website"><?php esc_html_e( 'Leave this field empty', 'eu-withdrawal-compliance' ); ?></label>
				<input type="text" id="ayudawp_euw_website" name="ayudawp_euw_website" tabindex="-1" autocomplete="off">
			</div>

			<div class="ayudawp-euw-field<?php echo esc_attr( ayudawp_euw_field_error_class( 'name', $errors ) ); ?>">
				<label for="ayudawp_euw_name"><?php esc_html_e( 'Full name', 'eu-withdrawal-compliance' ); ?> <span class="ayudawp-euw-required">*</span></label>
				<input type="text" id="ayudawp_euw_name" name="ayudawp_euw_name" value="<?php echo esc_attr( $prefill['name'] ); ?>" aria-invalid="<?php echo esc_attr( ayudawp_euw_field_aria_invalid( 'name', $errors ) ); ?>" <?php echo esc_attr( $lock_attr ); ?> required>
				<?php ayudawp_euw_print_field_error( 'name', $errors, $error ); ?>
			</div>

			<div class="ayudawp-euw-field<?php echo esc_attr( ayudawp_euw_field_error_class( 'email', $errors ) ); ?>">
				<label for="ayudawp_euw_email"><?php esc_html_e( 'Email used in the order', 'eu-withdrawal-compliance' ); ?> <span class="ayudawp-euw-required">*</span></label>
				<input type="email" id="ayudawp_euw_email" name="ayudawp_euw_email" value="<?php echo esc_attr( $prefill['email'] ); ?>" aria-invalid="<?php echo esc_attr( ayudawp_euw_field_aria_invalid( 'email', $errors ) ); ?>" <?php echo esc_attr( $lock_attr ); ?> required>
				<small class="ayudawp-euw-help"><?php esc_html_e( 'The withdrawal acknowledgement will be sent to this address.', 'eu-withdrawal-compliance' ); ?></small>
				<?php ayudawp_euw_print_field_error( 'email', $errors, $error ); ?>
			</div>

			<div class="ayudawp-euw-field<?php echo esc_attr( ayudawp_euw_field_error_class( 'order', $errors ) ); ?>">
				<label for="ayudawp_euw_order"><?php esc_html_e( 'Order number', 'eu-withdrawal-compliance' ); ?> <span class="ayudawp-euw-required">*</span></label>
				<input type="text" id="ayudawp_euw_order" name="ayudawp_euw_order" value="<?php echo esc_attr( $prefill['order'] ); ?>" aria-invalid="<?php echo esc_attr( ayudawp_euw_field_aria_invalid( 'order', $errors ) ); ?>" <?php echo esc_attr( $lock_attr ); ?> required>
				<small class="ayudawp-euw-help"><?php esc_html_e( 'You can find it in the confirmation email we sent you.', 'eu-withdrawal-compliance' ); ?></small>
				<?php ayudawp_euw_print_field_error( 'order', $errors, $error ); ?>
			</div>

			<div class="ayudawp-euw-field">
				<label for="ayudawp_euw_date"><?php esc_html_e( 'Order date', 'eu-withdrawal-compliance' ); ?></label>
				<input type="date" id="ayudawp_euw_date" name="ayudawp_euw_date" value="<?php echo esc_attr( $prefill['date'] ); ?>" max="<?php echo esc_attr( gmdate( 'Y-m-d' ) ); ?>" <?php echo esc_attr( $lock_attr ); ?>>
			</div>

			<div class="ayudawp-euw-field">
				<label for="ayudawp_euw_scope"><?php esc_html_e( 'Scope of the withdrawal', 'eu-withdrawal-compliance' ); ?> <span class="ayudawp-euw-required">*</span></label>
				<select id="ayudawp_euw_scope" name="ayudawp_euw_scope" required>
					<option value="full" <?php selected( $prefill['scope'], 'full' ); ?>><?php esc_html_e( 'Full order', 'eu-withdrawal-compliance' ); ?></option>
					<option value="partial" <?php selected( $prefill['scope'], 'partial' ); ?>><?php esc_html_e( 'Specific products only', 'eu-withdrawal-compliance' ); ?></option>
				</select>
			</div>

			<div class="ayudawp-euw-field">
				<label for="ayudawp_euw_details"><?php esc_html_e( 'Affected products / additional information', 'eu-withdrawal-compliance' ); ?></label>
				<textarea id="ayudawp_euw_details" name="ayudawp_euw_details" rows="5"><?php echo esc_textarea( $prefill['details'] ); ?></textarea>
				<small class="ayudawp-euw-help"><?php esc_html_e( 'If you have selected partial withdrawal, list the products affected here.', 'eu-withdrawal-compliance' ); ?></small>
			</div>

			<?php
			// Optional B2B self-declaration. Shown when enabled globally or forced
			// on via the shortcode attribute; integrators can override per request
			// (e.g. only for VIES-verified buyers) through the filter.
			$consumer_att = isset( $atts['consumer_check'] ) ? sanitize_key( (string) $atts['consumer_check'] ) : '';

			if ( 'yes' === $consumer_att ) {
				$show_consumer = true;
			} elseif ( 'no' === $consumer_att ) {
				$show_consumer = false;
			} else {
				$show_consumer = ( 'yes' === get_option( 'ayudawp_euw_consumer_check_enabled', 'no' ) );
			}

			$show_consumer = (bool) apply_filters( 'ayudawp_euw_show_consumer_check', $show_consumer, $prefill );

			if ( $show_consumer ) :
				$consumer_text = (string) get_option( 'ayudawp_euw_consumer_check_text', '' );

				if ( '' === trim( $consumer_text ) ) {
					$consumer_text = ayudawp_euw_consumer_check_default_text();
				}
				?>
				<div class="ayudawp-euw-field ayudawp-euw-field--checkbox<?php echo esc_attr( ayudawp_euw_field_error_class( 'consumer', $errors ) ); ?>">
					<label for="ayudawp_euw_consumer">
						<input type="checkbox" id="ayudawp_euw_consumer" name="ayudawp_euw_consumer" value="1" aria-invalid="<?php echo esc_attr( ayudawp_euw_field_aria_invalid( 'consumer', $errors ) ); ?>" required>
						<span><?php echo esc_html( $consumer_text ); ?></span>
					</label>
					<?php ayudawp_euw_print_field_error( 'consumer', $errors, $error ); ?>
				</div>
				<?php
			endif;
			?>

			<?php
			$privacy_policy = get_option( 'wp_page_for_privacy_policy' );
			$privacy_url    = $privacy_policy ? get_permalink( $privacy_policy ) : '';
			?>
			<div class="ayudawp-euw-field ayudawp-euw-field--checkbox<?php echo esc_attr( ayudawp_euw_field_error_class( 'privacy', $errors ) ); ?>">
				<label for="ayudawp_euw_privacy">
					<input type="checkbox" id="ayudawp_euw_privacy" name="ayudawp_euw_privacy" value="1" aria-invalid="<?php echo esc_attr( ayudawp_euw_field_aria_invalid( 'privacy', $errors ) ); ?>" required>
					<span>
						<?php
						if ( $privacy_url ) {
							printf(
								wp_kses(
									/* translators: %s: privacy policy URL. */
									__( 'I have read and accept the <a href="%s" target="_blank" rel="noopener nofollow">privacy policy</a>.', 'eu-withdrawal-compliance' ),
									array(
										'a' => array(
											'href'   => array(),
											'target' => array(),
											'rel'    => array(),
										),
									)
								),
								esc_url( $privacy_url )
							);
						} else {
							esc_html_e( 'I confirm that the information provided is correct.', 'eu-withdrawal-compliance' );
						}
						?>
					</span>
				</label>
				<?php ayudawp_euw_print_field_error( 'privacy', $errors, $error ); ?>
			</div>

			<?php
			/**
			 * Fires inside the form, right before the submit button.
			 *
			 * Use it to render an anti-spam / captcha widget or an extra
			 * informational field: anything echoed here is posted with the form
			 * and can be rejected through the `ayudawp_euw_validation_result`
			 * filter. The required legal fields above cannot be removed.
			 *
			 * @param array $prefill Current field values being rendered.
			 * @param bool  $lock    Whether order-derived fields are read-only.
			 */
			do_action( 'ayudawp_euw_form_before_submit', $prefill, $lock );
			?>

			<div class="ayudawp-euw-submit">
				<button type="submit" class="ayudawp-euw-button"><?php esc_html_e( 'Continue', 'eu-withdrawal-compliance' ); ?></button>
			</div>
		</form>

		<?php
		/**
		 * Fires inside the withdrawal-form wrapper, right after the form.
		 *
		 * Used by the Annex I.B module to render the collapsible model
		 * withdrawal form. Hook with a higher priority to inject content
		 * below the form without coupling either module to the other.
		 */
		do_action( 'ayudawp_euw_after_form' );
		?>
	</div>
	<?php
}

/**
 * Return the withdrawal form HTML as a string.
 *
 * Thin wrapper around ayudawp_euw_render_form() for callers (like the
 * shortcode) that need a return value rather than direct output.
 *
 * @param array $atts Optional atts. See ayudawp_euw_render_form().
 * @return string HTML markup of the form.
 */
function ayudawp_euw_get_form_html( $atts = array() ) {

	ob_start();
	ayudawp_euw_render_form( $atts );
	return ob_get_clean();
}

/**
 * Render the confirmation screen (step 2).
 *
 * Echoes a read-only summary of the declaration plus the dedicated confirmation
 * function required by Article 11a(3) of Directive 2011/83/EU: a button
 * labelled only with "Confirm withdrawal". The request is registered only when
 * this button is pressed, which prevents the unintended exercise of the right.
 *
 * @param string $token Single-use token identifying the pending declaration.
 * @param array  $data  Validated declaration recovered from the transient.
 */
function ayudawp_euw_render_confirmation( $token, $data ) {

	$scope_label = ( 'partial' === $data['scope'] )
		? __( 'Specific products only', 'eu-withdrawal-compliance' )
		: __( 'Full order', 'eu-withdrawal-compliance' );

	?>
	<div class="ayudawp-euw-wrapper" id="ayudawp-euw-form">

		<h2 class="ayudawp-euw-confirm-title"><?php esc_html_e( 'Review and confirm your withdrawal', 'eu-withdrawal-compliance' ); ?></h2>

		<p class="ayudawp-euw-intro">
			<?php esc_html_e( 'Please review the details below. Your withdrawal will only be submitted when you press the “Confirm withdrawal” button.', 'eu-withdrawal-compliance' ); ?>
		</p>

		<p class="ayudawp-euw-legal-note">
			<?php echo esc_html( ayudawp_euw_legal_conditions_text() ); ?>
		</p>

		<table class="ayudawp-euw-summary">
			<tbody>
				<tr>
					<th scope="row"><?php esc_html_e( 'Full name', 'eu-withdrawal-compliance' ); ?></th>
					<td><?php echo esc_html( $data['name'] ); ?></td>
				</tr>
				<tr>
					<th scope="row"><?php esc_html_e( 'Order number', 'eu-withdrawal-compliance' ); ?></th>
					<td><?php echo esc_html( $data['order'] ); ?></td>
				</tr>
				<?php if ( ! empty( $data['date'] ) ) : ?>
					<tr>
						<th scope="row"><?php esc_html_e( 'Order date', 'eu-withdrawal-compliance' ); ?></th>
						<td><?php echo esc_html( $data['date'] ); ?></td>
					</tr>
				<?php endif; ?>
				<tr>
					<th scope="row"><?php esc_html_e( 'Scope of the withdrawal', 'eu-withdrawal-compliance' ); ?></th>
					<td><?php echo esc_html( $scope_label ); ?></td>
				</tr>
				<?php if ( ! empty( $data['details'] ) ) : ?>
					<tr>
						<th scope="row"><?php esc_html_e( 'Affected products / additional information', 'eu-withdrawal-compliance' ); ?></th>
						<td><?php echo wp_kses( nl2br( esc_html( $data['details'] ) ), array( 'br' => array() ) ); ?></td>
					</tr>
				<?php endif; ?>
			</tbody>
		</table>

		<p class="ayudawp-euw-confirm-channel">
			<?php esc_html_e( 'You will receive the acknowledgement of receipt at this address:', 'eu-withdrawal-compliance' ); ?>
			<strong><?php echo esc_html( $data['email'] ); ?></strong>
		</p>

		<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" class="ayudawp-euw-form ayudawp-euw-confirm-form">

			<input type="hidden" name="action" value="ayudawp_euw_confirm">
			<input type="hidden" name="ayudawp_euw_token" value="<?php echo esc_attr( $token ); ?>">
			<?php wp_nonce_field( 'ayudawp_euw_confirm_action', 'ayudawp_euw_confirm_nonce' ); ?>

			<div class="ayudawp-euw-submit ayudawp-euw-confirm-actions">
				<a class="ayudawp-euw-button ayudawp-euw-button--secondary" href="<?php echo esc_url( add_query_arg( 'ayudawp_euw_edit', $token, remove_query_arg( 'ayudawp_euw_confirm' ) ) . '#ayudawp-euw-form' ); ?>"><?php esc_html_e( 'Edit data', 'eu-withdrawal-compliance' ); ?></a>
				<button type="submit" class="ayudawp-euw-button"><?php esc_html_e( 'Confirm withdrawal', 'eu-withdrawal-compliance' ); ?></button>
			</div>
		</form>
	</div>
	<?php
}

/**
 * Translate an error code into a human-readable message.
 *
 * @param string $code Error code.
 * @return string Translated message.
 */
function ayudawp_euw_get_error_message( $code ) {

	$messages = array(
		'nonce'   => __( 'Security check failed. Please refresh the page and try again.', 'eu-withdrawal-compliance' ),
		'spam'    => __( 'Your request looks like spam and was rejected.', 'eu-withdrawal-compliance' ),
		'fields'  => __( 'Please complete the fields highlighted below.', 'eu-withdrawal-compliance' ),
		'email'   => __( 'The email address is not valid.', 'eu-withdrawal-compliance' ),
		'order'   => __( 'We could not match this email with the order number provided.', 'eu-withdrawal-compliance' ),
		'status'  => __( 'This order is not eligible for withdrawal because of its current order status. If you believe this is a mistake, please contact us.', 'eu-withdrawal-compliance' ),
		'expired' => __( 'The withdrawal period for this order has passed. If you believe this is a mistake, please contact us.', 'eu-withdrawal-compliance' ),
		'session' => __( 'Your confirmation link has expired or was already used. Please fill in the form again.', 'eu-withdrawal-compliance' ),
		'general' => __( 'An error occurred. Please try again later.', 'eu-withdrawal-compliance' ),
	);

	return isset( $messages[ $code ] ) ? $messages[ $code ] : $messages['general'];
}
