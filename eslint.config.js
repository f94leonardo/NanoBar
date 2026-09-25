// ESLint v9 flat config per NanoBar.
// Gli script del plugin sono IIFE "classici" (nessun bundler), quindi
// sourceType "script" e solo le regole ESLint built-in.

module.exports = [
	{
		files: ['assets/js/**/*.js'],
		languageOptions: {
			ecmaVersion: 2022,
			sourceType: 'script',
			globals: {
				window: 'readonly',
				document: 'readonly',
				console: 'readonly',
				setTimeout: 'readonly',
				clearTimeout: 'readonly',
				IntersectionObserver: 'readonly',
				MutationObserver: 'readonly',
				URL: 'readonly',
				localStorage: 'readonly',
				sessionStorage: 'readonly',
				requestAnimationFrame: 'readonly',
				// Globali WordPress.
				wp: 'readonly',
				jQuery: 'readonly',
				ajaxurl: 'readonly',
			},
		},
		rules: {
			eqeqeq: ['error', 'always'],
			'no-unused-vars': ['warn', { argsIgnorePattern: '^_' }],
			'no-undef': 'error',
			'no-console': 'off',
		},
	},
	{
		ignores: [
			'node_modules/**',
			'vendor/**',
			'assets/css/**',
			'languages/**',
			'dist/**',
			'**/*.min.js',
		],
	},
];
