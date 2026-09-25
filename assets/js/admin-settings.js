/**
 * Settings → NanoBar admin page — entry point.
 *
 * The page script is split into modules (admin-core.js, admin-layout.js,
 * admin-preview.js, admin-items.js, admin-quick-links.js, admin-save.js,
 * admin-defaults.js), each registered on `window.NanoBarAdmin`. This file
 * loads last: once the DOM is ready it initialises every module, then wires
 * the one function that spans all of them (refreshAll) and paints the
 * initial state.
 *
 * Reads its defaults/config/i18n strings from the `nanobarSettings` object
 * localized by Settings\Page::enqueue_assets(), the same single source of
 * truth used by the sanitizer and the frontend renderer, so the live preview
 * can never drift from what gets saved.
 *
 * @package NanoBar
 */

(function () {
	'use strict';

	if (
		!window.jQuery ||
		!window.nanobarSettings ||
		!window.NanoBarAdminHelpers ||
		!window.NanoBarAdmin
	) {
		return;
	}

	jQuery(function ($) {
		var app = window.NanoBarAdmin.app;
		var $colorField = $('#nanobar-color-field');

		app.modules.forEach(function (init) {
			init(app, $);
		});

		// Re-syncs every derived piece of UI after values were set from code
		// (restore defaults, undo, a save).
		app.refreshAll = function () {
			app.syncSizeUnit();
			app.syncPositionGrid();
			app.syncColorLabel($colorField.val());
			app.updateAddQuickLinkState();
			app.updateMenuItemsCount();
			app.updateElementorSuboptionState();
			app.updatePreview($colorField.val());
		};

		app.refreshAll();
		app.updateMockScheme();
	});
})();
