/* EU Withdrawal Compliance — Annex I.B print trigger */
( function () {
	'use strict';

	var btn = document.querySelector( '.ayudawp-euw-annex-b-print' );
	if ( ! btn ) {
		return;
	}
	btn.addEventListener( 'click', function () {
		window.print();
	} );
} )();
