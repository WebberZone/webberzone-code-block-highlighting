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

	var i18n = ( typeof wzcbhI18n !== 'undefined' ) ? wzcbhI18n : {};
	var strCopy        = i18n.copy        || 'Copy';
	var strCopied      = i18n.copied      || 'Copied!';
	var strCopySuccess = i18n.copySuccess || 'Copied code to clipboard.';
	var strCopyError   = i18n.copyError   || 'Unable to copy code to clipboard.';
	var strExpand      = i18n.expand      || 'Expand';
	var strCollapse    = i18n.collapse    || 'Collapse';
	var strDownloaded  = i18n.downloaded  || 'Downloaded code as %s.';

	function announce( message ) {
		var region = getLiveRegion();
		region.textContent = '';
		setTimeout( function () {
			region.textContent = message;
		}, 50 );
	}

	// ── Plain code text of a <code> element ──────────────────────────────────
	// textContent, never innerText. innerText follows the rendered layout, and
	// hljs-server-mode.css gives .wzcbh-highlighted-line `display: block` — so a
	// highlighted line, whose span already ends in a newline, was copied with a
	// second one after it. The line-numbers gutter also lives inside <code>, so
	// it is dropped from a clone first rather than read as text.
	function getCodeText( code ) {
		var clone = code.cloneNode( true );
		clone.querySelectorAll( '.line-numbers-rows' ).forEach( function ( el ) {
			el.remove();
		} );
		return clone.textContent;
	}

	// ── Copy button (class matches Prism copy-to-clipboard plugin) ───────────
	function handleCopy( btn ) {
		var toolbar = btn.closest( '.code-toolbar' );
		var pre = toolbar ? toolbar.querySelector( 'pre' ) : null;
		var code = pre ? pre.querySelector( 'code' ) : null;
		if ( ! code ) {
			return;
		}
		var text = getCodeText( code );

		function onSuccess() {
			btn.setAttribute( 'data-copy-state', 'copy-success' );
			var inner = btn.querySelector( 'span' );
			if ( inner ) {
				inner.textContent = strCopied;
			}
			announce( strCopySuccess );
			setTimeout( function () {
				btn.setAttribute( 'data-copy-state', 'copy' );
				if ( inner ) {
					inner.textContent = strCopy;
				}
			}, 2000 );
		}

		function onError() {
			btn.setAttribute( 'data-copy-state', 'copy-error' );
			announce( strCopyError );
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

	// ── Download button (mirrors wzcbh-download in frontend.js) ──────────────
	function handleDownload( btn ) {
		var toolbar = btn.closest( '.code-toolbar' );
		var pre = toolbar ? toolbar.querySelector( 'pre' ) : null;
		var code = pre ? pre.querySelector( 'code' ) : null;
		if ( ! code ) {
			return;
		}

		var fileName = btn.getAttribute( 'data-wzcbh-download' ) || 'snippet.txt';
		var blob = new Blob( [ getCodeText( code ) ], {
			type: 'text/plain;charset=utf-8',
		} );
		var url = URL.createObjectURL( blob );
		var link = document.createElement( 'a' );

		link.href = url;
		link.download = fileName;
		link.style.display = 'none';
		document.body.appendChild( link );
		link.click();
		document.body.removeChild( link );
		setTimeout( function () {
			URL.revokeObjectURL( url );
		}, 0 );

		announce( strDownloaded.replace( '%s', fileName ) );
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
				btn.textContent = strCollapse;
			} else {
				pre.style.maxHeight = originalMaxHeight;
				pre.style.overflowY = originalOverflowY;
				btn.setAttribute( 'aria-expanded', 'false' );
				btn.textContent = strExpand;
			}
		} );
	}

	// ── Event delegation for copy and download buttons ───────────────────────
	document.addEventListener( 'click', function ( e ) {
		var btn = e.target.closest( '.copy-to-clipboard-button' );
		if ( btn ) {
			handleCopy( btn );
			return;
		}

		btn = e.target.closest( '.wzcbh-download-button' );
		if ( btn ) {
			handleDownload( btn );
		}
	} );

	// ── Initialise expand buttons on DOMContentLoaded ─────────────────────────
	document.addEventListener( 'DOMContentLoaded', function () {
		document.querySelectorAll( '.wzcbh-expand-button' ).forEach( function ( btn ) {
			setupExpandButton( btn );
		} );
	} );
} )();
