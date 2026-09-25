/**
 * Settings → NanoBar admin page — menu item and role checkboxes: counters, "Select all", the Elementor sub-option.
 *
 * One module of the page script: registered on `window.NanoBarAdmin` (see
 * admin-core.js) and initialised by admin-settings.js once the DOM is ready.
 * Functions other modules need are published on the shared `app` object;
 * the small local wrappers below call them lazily, so load order between
 * modules does not matter.
 *
 * @package NanoBar
 */

(function () {
	'use strict';

	if (!window.NanoBarAdmin) {
		return;
	}

	window.NanoBarAdmin.register(function (app, $) {
		var i18n = app.i18n;
		function markDirty() {
			return app.markDirty.apply(app, arguments);
		}
		function scheduleMockPanel() {
			return app.scheduleMockPanel.apply(app, arguments);
		}

		var $menuItemsGrid = $('#nanobar-menu-items');
		var $menuItemsCount = $('#nanobar-menu-items-count');
		var $elementorSuboption = $('#nanobar-elementor-suboption');
		var $siteEditorItem = $('#nanobar-item-site_editor');
		var $rolesGrid = $('#nanobar-roles');

		// Scoped to "[data-item-key]" rather than every checkbox in the grid:
		// the "Prefer Elementor" sub-toggle now lives nested inside the "Edit
		// site" tile (same grid), but it is not itself one of the seven
		// togglable items, so it must stay out of the count and out of
		// "Select all"/"Select none".
		function getMenuItemCheckboxes() {
			return $menuItemsGrid.find('input[data-item-key]');
		}

		function updateMenuItemsCount() {
			var $checkboxes = getMenuItemCheckboxes();
			var activeCount = $checkboxes.filter(':checked').length;
			var text = (i18n.items_active || '%1$d of %2$d active')
				.replace('%1$d', activeCount)
				.replace('%2$d', $checkboxes.length);
			$menuItemsCount.text(text);
		}

		// The "Prefer Elementor" toggle only does anything while "Edit site"
		// itself is on: this is a purely visual hint (dimming, not `disabled`)
		// so the checkbox's own saved value is never silently dropped from the
		// submitted form just because "Edit site" happens to be off right now.
		function updateElementorSuboptionState() {
			$elementorSuboption.toggleClass(
				'is-inactive',
				!$siteEditorItem.is(':checked')
			);
		}

		function getRoleCheckboxes() {
			return $rolesGrid.find('input[type="checkbox"]');
		}

		// "Select all" / "Select none" above the roles and menu-items grids.
		// Setting `checked` from code fires no change event, so the counter,
		// the Elementor hint, the preview and the unsaved-changes flag are
		// refreshed explicitly here.
		$('[data-bulk-toggle]').on('click', function (event) {
			event.preventDefault();
			var isItems = 'items' === $(this).data('bulk-target');
			var checked = 'all' === $(this).data('bulk-toggle');
			(isItems ? getMenuItemCheckboxes() : getRoleCheckboxes()).prop(
				'checked',
				checked
			);
			if (isItems) {
				updateMenuItemsCount();
				updateElementorSuboptionState();
			}
			scheduleMockPanel();
			markDirty();
		});

		$menuItemsGrid.on('change', 'input', function () {
			if ($(this).is('[data-item-key]')) {
				updateMenuItemsCount();
				if ('site_editor' === $(this).data('item-key')) {
					updateElementorSuboptionState();
				}
			}
			scheduleMockPanel();
		});

		app.getMenuItemCheckboxes = getMenuItemCheckboxes;
		app.updateMenuItemsCount = updateMenuItemsCount;
		app.updateElementorSuboptionState = updateElementorSuboptionState;
	});
})();
