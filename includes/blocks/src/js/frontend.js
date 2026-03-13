/**
 * Frontend Prism.js bundle.
 *
 * Imports Prism core, all supported language grammars, the line-numbers plugin,
 * and the line-numbers plugin CSS (extracted to build/frontend.css by webpack).
 *
 * Dependency order matters — each language must be imported after its dependencies.
 */

import Prism from 'prismjs';
import { __ } from '@wordpress/i18n';

// ── Base layers (no dependencies) ────────────────────────────────────────────
import 'prismjs/components/prism-clike';
import 'prismjs/components/prism-markup'; // HTML, XML, SVG
import 'prismjs/components/prism-css';

// ── JavaScript family ─────────────────────────────────────────────────────────
import 'prismjs/components/prism-javascript'; // depends: clike
import 'prismjs/components/prism-typescript'; // depends: javascript
import 'prismjs/components/prism-jsx'; // depends: markup, javascript
import 'prismjs/components/prism-tsx'; // depends: jsx, typescript

// ── C family ──────────────────────────────────────────────────────────────────
import 'prismjs/components/prism-c'; // depends: clike
import 'prismjs/components/prism-cpp'; // depends: c
import 'prismjs/components/prism-csharp'; // depends: clike
import 'prismjs/components/prism-objectivec'; // depends: c

// ── PHP ───────────────────────────────────────────────────────────────────────
import 'prismjs/components/prism-markup-templating'; // depends: markup - required by php
import 'prismjs/components/prism-php'; // depends: markup, clike, markup-templating

// ── CSS extensions ────────────────────────────────────────────────────────────
import 'prismjs/components/prism-sass'; // depends: css

// ── Markdown ──────────────────────────────────────────────────────────────────
import 'prismjs/components/prism-markdown'; // depends: markup

// ── Remaining languages (no inter-plugin dependencies) ────────────────────────
import 'prismjs/components/prism-apacheconf';
import 'prismjs/components/prism-bash';
import 'prismjs/components/prism-dart';
import 'prismjs/components/prism-docker';
import 'prismjs/components/prism-fsharp';
import 'prismjs/components/prism-go';
import 'prismjs/components/prism-graphql';
import 'prismjs/components/prism-haskell';
import 'prismjs/components/prism-java';
import 'prismjs/components/prism-json';
import 'prismjs/components/prism-kotlin';
import 'prismjs/components/prism-nginx';
import 'prismjs/components/prism-powershell';
import 'prismjs/components/prism-python';
import 'prismjs/components/prism-ruby';
import 'prismjs/components/prism-rust';
import 'prismjs/components/prism-sql';
import 'prismjs/components/prism-swift';
import 'prismjs/components/prism-toml';
import 'prismjs/components/prism-vim';
import 'prismjs/components/prism-yaml';

// ── Plugins ───────────────────────────────────────────────────────────────────
import 'prismjs/plugins/line-numbers/prism-line-numbers';
import 'prismjs/plugins/line-numbers/prism-line-numbers.css';
import 'prismjs/plugins/toolbar/prism-toolbar';
import 'prismjs/plugins/toolbar/prism-toolbar.css';
import 'prismjs/plugins/show-language/prism-show-language';
import 'prismjs/plugins/copy-to-clipboard/prism-copy-to-clipboard';

// ── Frontend utilities ────────────────────────────────────────────────────────
import '../css/frontend.css';

const getLiveRegion = function () {
	let liveRegion = document.getElementById('wz-cbh-copy-live-region');

	if (liveRegion) {
		return liveRegion;
	}

	liveRegion = document.createElement('span');
	liveRegion.id = 'wz-cbh-copy-live-region';
	liveRegion.setAttribute('aria-live', 'polite');
	liveRegion.setAttribute('aria-atomic', 'true');
	liveRegion.className = 'screen-reader-text';
	document.body.appendChild(liveRegion);

	return liveRegion;
};

const announceMessage = function (message) {
	const liveRegion = getLiveRegion();

	liveRegion.textContent = '';

	window.setTimeout(function () {
		liveRegion.textContent = message;
	}, 50);
};

const setupCopyAnnouncements = function (codeToolbar) {
	codeToolbar
		.querySelectorAll('.copy-to-clipboard-button')
		.forEach(function (button) {
			if (button.dataset.wzCbhAnnounceBound) {
				return;
			}

			button.dataset.wzCbhAnnounceBound = 'true';

			const observer = new MutationObserver(function () {
				const copyState = button.getAttribute('data-copy-state');

				if ('copy-success' === copyState) {
					announceMessage(
						__('Copied code to clipboard.', 'webberzone-code-block-highlighting')
					);
				} else if ('copy-error' === copyState) {
					announceMessage(
						__('Unable to copy code to clipboard.', 'webberzone-code-block-highlighting')
					);
				}
			});

			observer.observe(button, {
				attributes: true,
				attributeFilter: ['data-copy-state'],
			});
		});
};

// ── Title/filename toolbar button ─────────────────────────────────────────────
Prism.plugins.toolbar.registerButton('wz-cbh-title', function (env) {
	const title =
		env.element.parentElement &&
		(
			env.element.parentElement.getAttribute('data-title') ||
			env.element.parentElement.getAttribute('title')
		);
	if (!title) {
		return;
	}
	const span = document.createElement('span');
	span.className = 'wz-cbh-toolbar-title';
	span.textContent = title;
	return span;
});

// ── Mark show-language label as decorative (aria-hidden) ─────────────────────
// The show-language plugin creates a plain <span> in the toolbar. It is purely
// visual — screen readers get the language from the <code> element's class and
// the highlighted content itself — so we hide it from the AT tree.
Prism.hooks.add('complete', function (env) {
	const codeToolbar = env.element.closest('.code-toolbar');
	if (!codeToolbar) {
		return;
	}
	codeToolbar
		.querySelectorAll('.toolbar-item > span:not(.wz-cbh-toolbar-title)')
		.forEach(function (el) {
			el.setAttribute('aria-hidden', 'true');
		});

	codeToolbar
		.querySelectorAll('.line-numbers-rows, .line-numbers-rows > span')
		.forEach(function (el) {
			el.setAttribute('aria-hidden', 'true');
		});

	setupCopyAnnouncements(codeToolbar);
});

// ── Conditionally remove copy-to-clipboard based on global setting ────────────
// cbhSettings is injected as an inline script before this bundle runs.
if (typeof cbhSettings !== 'undefined' && !cbhSettings.copyToClipboard) {
	Prism.hooks.add('complete', function (env) {
		const codeToolbar = env.element.closest('.code-toolbar');
		if (!codeToolbar) {
			return;
		}
		codeToolbar.querySelectorAll('.copy-to-clipboard-button').forEach(function (btn) {
			const item = btn.closest('.toolbar-item');
			if (item) {
				item.remove();
			}
		});
	});
}

export default Prism;
