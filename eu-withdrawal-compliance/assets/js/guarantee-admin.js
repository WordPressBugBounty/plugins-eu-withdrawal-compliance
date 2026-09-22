/**
 * EU Withdrawal and Legal Guarantee Compliance — media picker for the guarantee notice files.
 *
 * Lets the shop replace the bundled official notice of a language with a file
 * from the media library. Nothing here builds HTML: the chosen attachment ID
 * goes into a hidden input with .val() and its file name into a <span> with
 * .text(), so no value ever reaches an attribute or gets parsed as markup.
 */
( function ( $ ) {
	'use strict';

	var strings = window.ayudawpEuwGuarantee || {};

	function fileRow( element ) {
		return $( element ).closest( '.ayudawp-euw-guarantee-file' );
	}

	$( document ).on( 'click', '.ayudawp-euw-guarantee-file__choose', function ( event ) {
		event.preventDefault();

		if ( ! window.wp || ! window.wp.media ) {
			return;
		}

		var $row = fileRow( this );
		var frame = window.wp.media( {
			title: strings.chooseTitle || '',
			button: { text: strings.chooseButton || '' },
			library: { type: 'image' },
			multiple: false
		} );

		frame.on( 'select', function () {
			var attachment = frame.state().get( 'selection' ).first().toJSON();

			$row.find( '.ayudawp-euw-guarantee-file__id' ).val( parseInt( attachment.id, 10 ) || 0 );
			$row.find( '.ayudawp-euw-guarantee-file__name' ).text( attachment.filename || '' );
			$row.find( '.ayudawp-euw-guarantee-file__clear' ).show();
		} );

		frame.open();
	} );

	$( document ).on( 'click', '.ayudawp-euw-guarantee-file__clear', function ( event ) {
		event.preventDefault();

		var $row = fileRow( this );

		$row.find( '.ayudawp-euw-guarantee-file__id' ).val( 0 );
		$row.find( '.ayudawp-euw-guarantee-file__name' ).text( strings.bundled || '' );
		$( this ).hide();
	} );
} )( jQuery );
