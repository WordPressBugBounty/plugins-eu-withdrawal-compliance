/**
 * Keeps the checkout consent checkboxes ticked across the order review refresh.
 *
 * WooCommerce re-renders the payment block, where the checkboxes sit by default,
 * whenever the customer changes address, shipping or payment method, and only
 * restores its own terms checkbox afterwards. This remembers what the customer
 * ticked and puts it back once the refresh is done.
 */
( function ( $ ) {
	'use strict';

	var ticked = {};

	$( document.body ).on( 'change', '.ayudawp-euw-checkout-consent input[type="checkbox"]', function () {
		ticked[ this.id ] = this.checked;
	} );

	$( document.body ).on( 'updated_checkout', function () {
		$.each( ticked, function ( id, isTicked ) {
			var box = document.getElementById( id );

			if ( box ) {
				box.checked = isTicked;
			}
		} );
	} );
}( jQuery ) );
