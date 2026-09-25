/**
 * Pure helpers for the Settings → NanoBar page (no DOM, no jQuery), kept apart
 * from admin-settings.js so they can be unit-tested with Node
 * (tests/js/helpers.test.js). Loaded before admin-settings.js, which reads
 * them from `window.NanoBarAdminHelpers`.
 *
 * @package NanoBar
 */

/* global module */

(function (root) {
	'use strict';

	var SCHEME = /^[a-zA-Z][a-zA-Z0-9+.-]*:/;

	function currentLocation(location) {
		return location || root.location;
	}

	// Client-side mirror of Support\Color::get_contrast_color() in PHP,
	// needed because the preview has to react to colors that have not been
	// saved yet. `contrast` is `config.contrast` (weights, threshold and the
	// two result colors), so the two stay in sync on their own.
	function contrastColor(hex, contrast) {
		hex = (hex || '').replace('#', '');
		if (3 === hex.length) {
			hex = hex[0] + hex[0] + hex[1] + hex[1] + hex[2] + hex[2];
		}
		if (!/^[0-9a-fA-F]{6}$/.test(hex)) {
			return contrast.on_dark;
		}
		var weights = contrast.weights;
		var red = parseInt(hex.substr(0, 2), 16);
		var green = parseInt(hex.substr(2, 2), 16);
		var blue = parseInt(hex.substr(4, 2), 16);
		var yiq =
			(red * weights.red + green * weights.green + blue * weights.blue) /
			1000;
		return yiq >= contrast.threshold ? contrast.on_light : contrast.on_dark;
	}

	// Best-effort client-side mirror of what wp_validate_redirect() enforces
	// authoritatively server-side (Settings\Sanitizer::sanitize_quick_links()):
	// a relative path is always fine; an absolute URL is only fine when its
	// scheme is http(s) and its host matches this site's. Protocol-relative
	// ("//host/…") is treated as external, same as wp_validate_redirect().
	function isInternalQuickLinkUrl(value, location) {
		value = (value || '').trim();
		if ('' === value) {
			return false;
		}
		if ('//' === value.slice(0, 2)) {
			return false;
		}
		if (!SCHEME.test(value)) {
			// esc_url_raw() prepends "http://" to anything that is not a
			// path, query or fragment ("contact/", "example.com/x"), which
			// then fails the same-site check, so only accept those forms.
			return /^[/?#]/.test(value);
		}
		var here = currentLocation(location);
		try {
			var parsed = new URL(value, here.origin);
			if ('http:' !== parsed.protocol && 'https:' !== parsed.protocol) {
				return false;
			}
			return (
				parsed.hostname.toLowerCase() === here.hostname.toLowerCase()
			);
		} catch {
			return false;
		}
	}

	// Tidies an absolute same-site URL (e.g. pasted straight from the
	// browser's address bar) down to just its path/query/fragment, live in the
	// field — Settings\Sanitizer::sanitize_quick_links() does the same thing
	// authoritatively on save (relative_quick_link_url()), so this is purely a
	// "let the admin see it happen" convenience. Anything already relative,
	// empty, protocol-relative, or pointing off-site is returned unchanged.
	function relativizeQuickLinkUrl(value, location) {
		value = (value || '').trim();
		if ('' === value || '//' === value.slice(0, 2) || !SCHEME.test(value)) {
			return value;
		}
		var here = currentLocation(location);
		try {
			var parsed = new URL(value, here.origin);
			if (parsed.hostname.toLowerCase() !== here.hostname.toLowerCase()) {
				return value;
			}
			// Collapse a leading "//" the same way
			// Settings\Sanitizer::relative_quick_link_url() does server-side:
			// printed on its own as an href, a leading "//" would otherwise be
			// reinterpreted by the browser as a protocol-relative URL to a
			// different host.
			var path = parsed.pathname.replace(/^\/+/, '');
			return '/' + path + parsed.search + parsed.hash;
		} catch {
			return value;
		}
	}

	var helpers = {
		contrastColor: contrastColor,
		isInternalQuickLinkUrl: isInternalQuickLinkUrl,
		relativizeQuickLinkUrl: relativizeQuickLinkUrl,
	};

	root.NanoBarAdminHelpers = helpers;
	if ('undefined' !== typeof module && module.exports) {
		module.exports = helpers;
	}
})(typeof window !== 'undefined' ? window : globalThis);
