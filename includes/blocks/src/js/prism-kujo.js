import Prism from 'prismjs';

Prism.languages.kujo = {
	comment: [
		{
			pattern: /\/\*[\s\S]*?(?:\*\/|$)/,
			greedy: true,
		},
		{
			pattern: /(?:\/\/|#).*/,
			greedy: true,
		},
	],
	string: {
		pattern: /"(?:\\[\s\S]|[^\\"\r\n])*"/,
		greedy: true,
	},
	function: [
		{
			pattern: /(\bfunc\s+)[a-z_]\w*/i,
			lookbehind: true,
		},
		/\b[a-z_]\w*(?=\s*\()/i,
	],
	keyword: /\b(?:async|await|case|const|else|for|from|func|if|import|in|let|match|mut|return|struct|while)\b/,
	boolean: /\b(?:false|true)\b/,
	number: /\b\d+(?:\.\d+)?\b/,
	operator: /:=|[=!<>]=|[-+*/<>=]/,
	punctuation: /[{}[\](),.:]/,
};
