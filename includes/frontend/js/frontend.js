/**
 * Frontend Prism.js bundle.
 *
 * Imports Prism core, all supported language grammars, the line-numbers plugin,
 * and the line-numbers plugin CSS (extracted to build/frontend.css by webpack).
 *
 * Dependency order matters — each language must be imported after its dependencies.
 */

import Prism from 'prismjs';

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
import 'prismjs/components/prism-php'; // depends: markup, clike

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

export default Prism;
