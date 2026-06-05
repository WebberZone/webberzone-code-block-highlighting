( function () {
	'use strict';

	// ── ARIA live region for copy announcements (mirrors frontend.js) ────────
	function getLiveRegion() {
		var el = document.getElementById( 'wzcbh-copy-live-region' );
		if ( el ) {
			return el;
		}
		el = document.createElement( 'span' );
		el.id = 'wzcbh-copy-live-region';
		el.setAttribute( 'aria-live', 'polite' );
		el.setAttribute( 'aria-atomic', 'true' );
		el.className = 'screen-reader-text';
		document.body.appendChild( el );
		return el;
	}

	function announce( message ) {
		var region = getLiveRegion();
		region.textContent = '';
		setTimeout( function () {
			region.textContent = message;
		}, 50 );
	}

	// ── Copy button (class matches Prism copy-to-clipboard plugin) ───────────
	function handleCopy( btn ) {
		var toolbar = btn.closest( '.code-toolbar' );
		var pre = toolbar ? toolbar.querySelector( 'pre' ) : null;
		var code = pre ? pre.querySelector( 'code' ) : null;
		if ( ! code ) {
			return;
		}
		var text = code.innerText;

		function onSuccess() {
			btn.setAttribute( 'data-copy-state', 'copy-success' );
			var inner = btn.querySelector( 'span' );
			if ( inner ) {
				inner.textContent = 'Copied!';
			}
			announce( 'Copied code to clipboard.' );
			setTimeout( function () {
				btn.setAttribute( 'data-copy-state', 'copy' );
				if ( inner ) {
					inner.textContent = 'Copy';
				}
			}, 2000 );
		}

		function onError() {
			btn.setAttribute( 'data-copy-state', 'copy-error' );
			announce( 'Unable to copy code to clipboard.' );
			setTimeout( function () {
				btn.setAttribute( 'data-copy-state', 'copy' );
			}, 2000 );
		}

		if ( navigator.clipboard && window.isSecureContext ) {
			navigator.clipboard.writeText( text ).then( onSuccess, onError );
		} else {
			var ta = document.createElement( 'textarea' );
			ta.value = text;
			ta.style.cssText = 'position:fixed;opacity:0;pointer-events:none;';
			document.body.appendChild( ta );
			ta.select();
			try {
				document.execCommand( 'copy' );
				onSuccess();
			} catch ( err ) {
				onError();
			}
			document.body.removeChild( ta );
		}
	}

	// ── Expand/collapse button (mirrors wzcbh-expand in frontend.js) ─────────
	function setupExpandButton( btn ) {
		var pre = btn.closest( '.code-toolbar' );
		pre = pre ? pre.querySelector( 'pre' ) : null;
		if ( ! pre || ! pre.style.maxHeight ) {
			return;
		}
		var originalMaxHeight = pre.style.maxHeight;
		var originalOverflowY = pre.style.overflowY;
		var expanded = false;

		btn.addEventListener( 'click', function () {
			expanded = ! expanded;
			if ( expanded ) {
				pre.style.maxHeight = '';
				pre.style.overflowY = '';
				btn.setAttribute( 'aria-expanded', 'true' );
				btn.textContent = 'Collapse';
			} else {
				pre.style.maxHeight = originalMaxHeight;
				pre.style.overflowY = originalOverflowY;
				btn.setAttribute( 'aria-expanded', 'false' );
				btn.textContent = 'Expand';
			}
		} );
	}

	// ── Event delegation for copy buttons ────────────────────────────────────
	document.addEventListener( 'click', function ( e ) {
		var btn = e.target.closest( '.copy-to-clipboard-button' );
		if ( btn ) {
			handleCopy( btn );
		}
	} );

	// ── Initialise expand buttons on DOMContentLoaded ─────────────────────────
	document.addEventListener( 'DOMContentLoaded', function () {
		document.querySelectorAll( '.wzcbh-expand-button' ).forEach( function ( btn ) {
			setupExpandButton( btn );
		} );
	} );
} )();
