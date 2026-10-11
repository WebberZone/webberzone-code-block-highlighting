const CODE = 'echo $a;\nreturn 3;';

const block = (attrs, html) =>
	`<!-- wp:code ${attrs ? JSON.stringify(attrs) + ' ' : ''}-->\n${html}\n<!-- /wp:code -->`;

// Markup saved by 1.2.3 and earlier, with the attributes the editor should migrate to.
export const legacy = {
	all: [
		block(
			{
				lineNumbers: true,
				lineNumbersStart: 5,
				wordWrap: true,
				highlightLines: '2,3-4',
				maxHeight: 120,
				downloadButton: 'show',
			},
			`<pre class="wp-block-code language-php line-numbers word-wrap" data-title="demo $0 &quot;x&quot;.php" data-start="5" data-line="2,3-4" style="max-height:120px;overflow-y:auto"><code lang="php" class="language-php">${CODE}</code></pre>`
		),
		{
			language: 'php',
			title: 'demo $0 "x".php',
			lineNumbers: true,
			lineNumbersStart: 5,
			wordWrap: true,
			highlightLines: '2,3-4',
			maxHeight: 120,
			downloadButton: 'show',
		},
	],
	language: [
		block(
			null,
			`<pre class="wp-block-code language-php"><code lang="php" class="language-php">${CODE}</code></pre>`
		),
		{ language: 'php' },
	],
	title: [
		block(
			null,
			`<pre class="wp-block-code" data-title="demo.php"><code>${CODE}</code></pre>`
		),
		{ title: 'demo.php' },
	],
	lineNumbers: [
		block(
			{ lineNumbers: true },
			`<pre class="wp-block-code line-numbers"><code>${CODE}</code></pre>`
		),
		{ lineNumbers: true, className: undefined },
	],
	wordWrap: [
		block(
			{ wordWrap: true },
			`<pre class="wp-block-code word-wrap"><code>${CODE}</code></pre>`
		),
		{ wordWrap: true, className: undefined },
	],
	maxHeight: [
		block(
			{ maxHeight: 80 },
			`<pre class="wp-block-code" style="max-height:80px;overflow-y:auto"><code>${CODE}</code></pre>`
		),
		{ maxHeight: 80 },
	],
	supports: [
		block(
			{
				className: 'my-class',
				style: { spacing: { padding: { top: '10px' } } },
				backgroundColor: 'vivid-red',
				maxHeight: 100,
			},
			`<pre class="wp-block-code language-javascript my-class has-vivid-red-background-color has-background" style="padding-top:10px;max-height:100px;overflow-y:auto"><code lang="javascript" class="language-javascript">${CODE}</code></pre>`
		),
		{ language: 'javascript', className: 'my-class', maxHeight: 100 },
	],
	codeSyntaxBlock: [
		block(
			{ lineNumbers: true },
			`<pre class="wp-block-code" title="old.php"><code lang="php" class="language-php line-numbers">${CODE}</code></pre>`
		),
		{ language: 'php', title: 'old.php', lineNumbers: true },
	],
	codeSyntaxBlockSupports: [
		block(
			{ backgroundColor: 'vivid-red' },
			`<pre class="wp-block-code has-vivid-red-background-color has-background" title="old.php"><code lang="php" class="language-php">${CODE}</code></pre>`
		),
		{ language: 'php', title: 'old.php', backgroundColor: 'vivid-red' },
	],
};

// Markup saved from now on: core's own, with every option in the comment.
export const current = {
	all: block(
		{
			className: 'my-class',
			backgroundColor: 'vivid-red',
			language: 'php',
			title: 'demo.php',
			lineNumbers: true,
			lineNumbersStart: 5,
			wordWrap: true,
			highlightLines: '2',
			maxHeight: 120,
			downloadButton: 'hide',
			colorScheme: 'prism-nord',
		},
		`<pre class="wp-block-code my-class has-vivid-red-background-color has-background"><code>${CODE}</code></pre>`
	),
	colorScheme: block(
		{ colorScheme: 'prism-one-light' },
		`<pre class="wp-block-code"><code>${CODE}</code></pre>`
	),
	plain: block(null, `<pre class="wp-block-code"><code>${CODE}</code></pre>`),
};
