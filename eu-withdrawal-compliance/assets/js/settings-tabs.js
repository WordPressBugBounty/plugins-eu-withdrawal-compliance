/**
 * EU Withdrawal and Legal Guarantee Compliance - settings page tabs.
 *
 * Turns the cards printed by PHP into tabs, and folds the long explanations of
 * each field and each section into <details>. Nothing here builds HTML from a
 * string: elements are created with createElement and the only text set comes
 * from the localized strings, through textContent.
 */
( function () {
	'use strict';

	var strings = window.ayudawpEuwSettings || {};
	var TAB_PREFIX = 'ayudawp-euw-tab-';
	var INTRO_LIMIT = 220;

	function init() {
		var root = document.querySelector( '.ayudawp-euw-settings' );

		if ( ! root ) {
			return;
		}

		// The stylesheet keeps the other tabs and the explanations out of sight
		// until this class arrives, so it has to arrive even if a step fails: in
		// the worst case the page shows everything, as it did before the tabs.
		try {
			setupTabs( root );
			foldFieldHelp( root );
			foldSectionIntros( root );
		} finally {
			root.classList.add( 'ayudawp-euw-ready' );
		}
	}

	/* Tabs ---------------------------------------------------------------- */

	function setupTabs( root ) {
		var nav = root.querySelector( 'nav.ayudawp-euw-tabs' );
		var links = nav ? Array.prototype.slice.call( nav.querySelectorAll( '[data-ayudawp-euw-tab]' ) ) : [];
		var panels = {};
		var form = root.closest( 'form' );
		var referer = form ? form.querySelector( 'input[name="_wp_http_referer"]' ) : null;

		if ( ! nav || ! links.length ) {
			return;
		}

		nav.setAttribute( 'role', 'tablist' );

		links.forEach( function ( link ) {
			var panelId = TAB_PREFIX + link.getAttribute( 'data-ayudawp-euw-tab' );
			var panel = document.getElementById( panelId );

			if ( ! panel ) {
				return;
			}

			panels[ panelId ] = panel;
			link.setAttribute( 'role', 'tab' );
			link.setAttribute( 'aria-controls', panelId );
			panel.setAttribute( 'role', 'tabpanel' );
		} );

		function activate( panelId, focus ) {
			if ( ! panels[ panelId ] ) {
				return;
			}

			links.forEach( function ( link ) {
				var active = ( TAB_PREFIX + link.getAttribute( 'data-ayudawp-euw-tab' ) ) === panelId;

				link.setAttribute( 'aria-selected', active ? 'true' : 'false' );
				link.setAttribute( 'tabindex', active ? '0' : '-1' );
				link.classList.toggle( 'nav-tab-active', active );

				if ( active && focus ) {
					link.focus();
				}
			} );

			Object.keys( panels ).forEach( function ( id ) {
				panels[ id ].hidden = ( id !== panelId );
			} );

			// options.php sends the browser back to this referer with
			// add_query_arg(), which keeps the fragment: saving returns to the
			// same tab.
			if ( referer ) {
				referer.value = referer.value.split( '#' )[ 0 ] + '#' + panelId;
			}
		}

		function select( panelId, focus ) {
			activate( panelId, focus );

			if ( window.history && window.history.replaceState ) {
				window.history.replaceState( null, '', '#' + panelId );
			}
		}

		// Any link to a tab, the nav and the ones inside the page alike.
		document.addEventListener( 'click', function ( event ) {
			var anchor = event.target && event.target.closest ? event.target.closest( 'a[href^="#' + TAB_PREFIX + '"]' ) : null;
			var panelId;

			if ( ! anchor ) {
				return;
			}

			panelId = anchor.getAttribute( 'href' ).slice( 1 );

			if ( ! panels[ panelId ] ) {
				return;
			}

			event.preventDefault();
			select( panelId, false );

			if ( ! nav.contains( anchor ) ) {
				nav.scrollIntoView();
			}
		} );

		nav.addEventListener( 'keydown', function ( event ) {
			var index = links.indexOf( document.activeElement );
			var next = -1;

			if ( index < 0 ) {
				return;
			}

			if ( 'ArrowRight' === event.key ) {
				next = ( index + 1 ) % links.length;
			} else if ( 'ArrowLeft' === event.key ) {
				next = ( index - 1 + links.length ) % links.length;
			} else if ( 'Home' === event.key ) {
				next = 0;
			} else if ( 'End' === event.key ) {
				next = links.length - 1;
			}

			if ( next < 0 ) {
				return;
			}

			event.preventDefault();
			select( TAB_PREFIX + links[ next ].getAttribute( 'data-ayudawp-euw-tab' ), true );
		} );

		// Starting tab: the one in the address, the one that holds the element the
		// address points at (a link from another screen), or the first.
		var first = TAB_PREFIX + links[ 0 ].getAttribute( 'data-ayudawp-euw-tab' );
		var start = first;
		var target = null;
		var id = '';

		if ( window.location.hash.length > 1 ) {
			try {
				id = decodeURIComponent( window.location.hash.slice( 1 ) );
			} catch ( e ) {
				id = '';
			}

			target = id ? document.getElementById( id ) : null;
		}

		if ( target ) {
			if ( panels[ target.id ] ) {
				start = target.id;
				target = null;
			} else {
				Object.keys( panels ).forEach( function ( panelId ) {
					if ( panels[ panelId ].contains( target ) ) {
						start = panelId;
					}
				} );
			}
		}

		activate( start, false );

		if ( target ) {
			target.scrollIntoView();
		}
	}

	/* Folded help ---------------------------------------------------------- */

	function isExplanation( element ) {
		return !! element
			&& 'P' === element.tagName
			&& element.classList.contains( 'description' )
			&& ! element.classList.contains( 'ayudawp-euw-status' );
	}

	function makeDetails( label, modifier ) {
		var details = document.createElement( 'details' );
		var summary = document.createElement( 'summary' );

		details.className = 'ayudawp-euw-help' + ( modifier ? ' ' + modifier : '' );
		summary.textContent = label;
		details.appendChild( summary );

		return details;
	}

	// Each run of consecutive explanations of a field goes into one <details>,
	// placed where the first of them was. Status lines stay in sight.
	function foldFieldHelp( root ) {
		var label = strings.moreInfo || 'More information';
		var fields = root.querySelectorAll( '.ayudawp-euw-field' );

		Array.prototype.forEach.call( fields, function ( field ) {
			var paragraphs = Array.prototype.slice.call( field.querySelectorAll( 'p.description' ) );

			paragraphs.forEach( function ( paragraph ) {
				var details;
				var group;
				var sibling;

				if ( ! isExplanation( paragraph ) || paragraph.closest( 'details.ayudawp-euw-help' ) ) {
					return;
				}

				group = [ paragraph ];
				sibling = paragraph.nextElementSibling;

				while ( isExplanation( sibling ) ) {
					group.push( sibling );
					sibling = sibling.nextElementSibling;
				}

				details = makeDetails( label, '' );
				paragraph.parentNode.insertBefore( details, paragraph );

				group.forEach( function ( item ) {
					details.appendChild( item );
				} );
			} );
		} );
	}

	// A card whose opening paragraphs add up to more than INTRO_LIMIT characters
	// folds them, under the same label as the help of each field. The Help tab is
	// the documentation itself, so it stays open.
	function foldSectionIntros( root ) {
		var label = strings.moreInfo || 'More information';
		var cards = root.querySelectorAll( '.ayudawp-euw-card' );

		Array.prototype.forEach.call( cards, function ( card ) {
			var intro = [];
			var length = 0;
			var children = card.children;
			var i;
			var details;

			if ( card.closest( '#' + TAB_PREFIX + 'help' ) ) {
				return;
			}

			for ( i = 0; i < children.length; i++ ) {

				if ( children[ i ].classList.contains( 'ayudawp-euw-fields' ) || children[ i ].classList.contains( 'ayudawp-euw-split' ) ) {
					break;
				}

				if ( 'P' === children[ i ].tagName && ! children[ i ].classList.contains( 'ayudawp-euw-status' ) ) {
					intro.push( children[ i ] );
					length += children[ i ].textContent.length;
				}
			}

			if ( ! intro.length || length <= INTRO_LIMIT ) {
				return;
			}

			details = makeDetails( label, 'ayudawp-euw-help--intro' );
			card.insertBefore( details, intro[ 0 ] );

			intro.forEach( function ( paragraph ) {
				details.appendChild( paragraph );
			} );
		} );
	}

	if ( 'loading' === document.readyState ) {
		document.addEventListener( 'DOMContentLoaded', init );
	} else {
		init();
	}
}() );
