/**
 * Settings → NanoBar admin page — size, color and position controls, and the live preview (mock panel and phone bar).
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
		var defaults = app.defaults;
		var config = app.config;
		var i18n = app.i18n;
		function markDirty() {
			return app.markDirty.apply(app, arguments);
		}

		var helpers = app.helpers;
		var $posH = $('#nanobar-pos-h');
		var $posV = $('#nanobar-pos-v');
		var $positionGrid = $('#nanobar-position-grid');
		var $sizeInput = $('#nanobar-toggle-size-input');
		var $sizeUnits = $('input[name="nanobar_options[toggle_size_unit]"]');
		var $sizeRange = $('[data-size-range]');
		var $sizeSlider = $('#nanobar-toggle-size-slider');
		var $sizeRemNote = $('[data-size-rem-note]');
		var $colorField = $('#nanobar-color-field');
		var $viewport = $('#nanobar-viewport');
		var $preview = $('#nanobar-preview-button');
		var $mock = $('#nanobar-mock');
		var $mockPanel = $('#nanobar-mock-panel');
		var $mockTabbar = $('#nanobar-mock-tabbar');
		var $menuItemsGrid = $('#nanobar-menu-items');
		var $quickLinksList = $('#nanobar-quick-links');

		function contrastColor(hex) {
			return helpers.contrastColor(hex, config.contrast);
		}

		function getSizeUnit() {
			return $sizeUnits.filter(':checked').val() || 'px';
		}

		// Size bounds for a unit — px is the top-level min/max in
		// Config::get()['size'], rem its own nested range.
		function getSizeBounds(unit) {
			return 'rem' === unit
				? config.size.rem
				: { min: config.size.min, max: config.size.max, step: 1 };
		}

		// Preview pixel size of the current field value: rem is converted with
		// the browser default root size (config.size.rem_base), the best the
		// settings page can do without knowing the site's own root font size.
		function getPreviewSizePx() {
			var unit = getSizeUnit();
			var bounds = getSizeBounds(unit);
			var value = parseFloat($sizeInput.val());
			if (isNaN(value)) {
				value =
					'rem' === unit ? config.size.rem.min : defaults.toggle_size;
			}
			value = Math.max(bounds.min, Math.min(bounds.max, value));
			return 'rem' === unit ? value * config.size.rem_base : value;
		}

		function formatSizeRange(unit) {
			var bounds = getSizeBounds(unit);
			return (i18n.sizeRange || 'From %1$s to %2$s.')
				.replace('%1$s', bounds.min + unit)
				.replace('%2$s', bounds.max + unit);
		}

		// Switching unit converts the current value (16px = 1rem) so the
		// button keeps roughly the same size, then re-applies that unit's
		// bounds and hint.
		function applySizeUnit(previousUnit) {
			var unit = getSizeUnit();
			var bounds = getSizeBounds(unit);
			var value = parseFloat($sizeInput.val());
			if (previousUnit && previousUnit !== unit && !isNaN(value)) {
				value =
					'rem' === unit
						? Math.round((value / config.size.rem_base) * 4) / 4
						: Math.round(value * config.size.rem_base);
				value = Math.max(bounds.min, Math.min(bounds.max, value));
				$sizeInput.val(value);
			}
			$sizeInput.attr({
				min: bounds.min,
				max: bounds.max,
				step: bounds.step,
			});
			$sizeRange.text(formatSizeRange(unit));
			$sizeRemNote.prop('hidden', 'rem' !== unit);
			syncSizeSlider();
		}

		// The slider has no name (it isn't submitted); it just mirrors the
		// number field, which stays the source of truth.
		function syncSizeSlider() {
			var bounds = getSizeBounds(getSizeUnit());
			var value = parseFloat($sizeInput.val());
			$sizeSlider.attr({
				min: bounds.min,
				max: bounds.max,
				step: bounds.step,
			});
			$sizeSlider.val(isNaN(value) ? bounds.min : value);
		}

		$sizeSlider.on('input', function () {
			$sizeInput.val($sizeSlider.val()).trigger('input');
		});
		$sizeInput.on('input change', syncSizeSlider);
		function isDashicon(value) {
			return /^dashicons-[a-z0-9-]+$/.test(value || '');
		}
		// The mock panel/phone bar, rebuilt from the form's current values:
		// ticked menu items (with their icon/label read from the tiles), then
		// the quick links, then Log out set apart — the order the real panel
		// uses. Text goes in via .text(), never as HTML.
		function getPreviewEntries() {
			var main = [];
			var tail = [];
			var quick = [];

			$menuItemsGrid.find('.nanobar-role-option--item').each(function () {
				var $tile = $(this);
				var $check = $tile.find('input[data-item-key]');
				if (!$check.is(':checked')) {
					return;
				}
				var $main = $tile.find('.nanobar-role-option__main');
				var iconClass = ($main.find('.dashicons').attr('class') || '')
					.split(/\s+/)
					.filter(isDashicon)[0];
				var entry = {
					icon: iconClass || 'dashicons-admin-generic',
					label: $main
						.find('span:not(.dashicons, .nanobar-status)')
						.first()
						.text(),
					mobile: $tile.find('input[data-mobile-key]').is(':checked'),
				};
				if ('logout' === $check.data('item-key')) {
					tail.push(entry);
				} else {
					main.push(entry);
				}
			});

			$quickLinksList.find('.nanobar-quick-link-row').each(function () {
				var $row = $(this);
				var label = (
					$row.find('[data-quick-link-field="label"]').val() || ''
				).trim();
				if ('' === label) {
					return;
				}
				var icon = (
					$row.find('[data-quick-link-icon-input]').val() || ''
				).trim();
				quick.push({
					icon: isDashicon(icon) ? icon : 'dashicons-admin-links',
					label: label,
					mobile: $row
						.find('input[name$="[mobile_visible]"]')
						.is(':checked'),
				});
			});

			return { main: main.concat(quick), tail: tail };
		}

		function buildMockItem(entry, extraClass) {
			var $item = $('<div class="nanobar-mock-item"></div>').addClass(
				extraClass || ''
			);
			$('<span class="dashicons"></span>')
				.addClass(entry.icon)
				.appendTo($item);
			$('<span></span>').text(entry.label).appendTo($item);
			return $item;
		}

		function updateMockPanel() {
			var entries = getPreviewEntries();
			var all = entries.main.concat(entries.tail);
			var iconsOnly = $('input[name="nanobar_options[icons_only]"]').is(
				':checked'
			);

			$mockPanel.empty();
			$mockTabbar.empty();

			$mockPanel
				.toggleClass('is-icons-only', iconsOnly)
				.toggleClass('is-empty', 0 === all.length);
			$mockTabbar.toggleClass('is-empty', 0 === all.length);

			if (0 === all.length) {
				$mockPanel.text(i18n.previewEmpty);
				$mockTabbar.text(i18n.previewEmpty);
				return;
			}

			entries.main.forEach(function (entry) {
				$mockPanel.append(buildMockItem(entry));
			});
			entries.tail.forEach(function (entry, index) {
				$mockPanel.append(
					buildMockItem(
						entry,
						0 === index ? 'nanobar-mock-item--sep' : ''
					)
				);
			});

			// Phone bar: only what is switched on for mobile.
			all.filter(function (entry) {
				return entry.mobile;
			}).forEach(function (entry) {
				var $tab = $('<div class="nanobar-mock-tab"></div>');
				$('<span class="dashicons"></span>')
					.addClass(entry.icon)
					.appendTo($tab);
				$('<span></span>').text(entry.label).appendTo($tab);
				$mockTabbar.append($tab);
			});
			if (!$mockTabbar.children().length) {
				$mockTabbar.addClass('is-empty').text(i18n.previewEmpty);
			}
		}

		var mockScheduled = false;

		function scheduleMockPanel() {
			if (mockScheduled) {
				return;
			}
			mockScheduled = true;
			window.requestAnimationFrame(function () {
				mockScheduled = false;
				updateMockPanel();
				updateMockScheme();
			});
		}

		// "auto" follows the visitor's OS setting, like the real panel.
		function updateMockScheme() {
			var scheme = $('#nanobar-color-scheme').val() || 'auto';
			if ('auto' === scheme) {
				scheme =
					window.matchMedia &&
					window.matchMedia('(prefers-color-scheme: light)').matches
						? 'light'
						: 'dark';
			}
			$viewport.attr('data-scheme', scheme);
		}

		function updatePreview(color) {
			var size = getPreviewSizePx();
			var posH = $posH.val();
			var posV = $posV.val();
			color = color || defaults.toggle_bg_color;

			$viewport.css('--nanobar-toggle-size', size + 'px');
			$preview.css({
				width: size + 'px',
				height: size + 'px',
				background: color,
				color: contrastColor(color),
			});
			$preview.find('.dashicons').css({
				fontSize: size * 0.5 + 'px',
				width: size * 0.5 + 'px',
				height: size * 0.5 + 'px',
			});

			var alignH =
				'left' === posH
					? 'flex-start'
					: 'right' === posH
						? 'flex-end'
						: 'center';
			var alignV = 'bottom' === posV ? 'flex-end' : 'flex-start';
			$viewport.css({
				justifyContent: alignH,
				alignItems: alignV,
			});
			// The panel is written before the button in the markup: it sits
			// above the button at the bottom edge, below it at the top.
			$mock.css({
				flexDirection: 'bottom' === posV ? 'column' : 'column-reverse',
				alignItems: alignH,
			});
			$mockTabbar.attr('data-edge', 'top' === posV ? 'top' : 'bottom');
			scheduleMockPanel();
		}
		// Position grid: keeps the pressed cell, the hidden inputs the form
		// submits, and which cells are selectable in sync. "Center" is only
		// allowed on the edge Config names (positions.center_vertical).
		function syncPositionGrid() {
			var allowsCenter = config.positions.center_vertical === $posV.val();
			if (!allowsCenter && 'center' === $posH.val()) {
				$posH.val(config.positions.center_fallback);
			}
			var centerOnly = $positionGrid.data('center-only') || '';
			$positionGrid.find('.nanobar-position-cell').each(function () {
				var $cell = $(this);
				var isCenter = 'center' === $cell.data('pos-h');
				var locked =
					isCenter &&
					config.positions.center_vertical !== $cell.data('pos-v');
				var isCurrent =
					$cell.data('pos-h') === $posH.val() &&
					$cell.data('pos-v') === $posV.val();
				$cell
					.prop('disabled', locked)
					.attr('aria-checked', isCurrent ? 'true' : 'false')
					.attr('tabindex', isCurrent ? '0' : '-1')
					.attr(
						'title',
						locked ? centerOnly : $cell.attr('aria-label')
					);
			});
		}

		function selectPosition($cell) {
			$posH.val($cell.data('pos-h'));
			$posV.val($cell.data('pos-v'));
			syncPositionGrid();
			updatePreview($colorField.val());
			markDirty();
		}

		$positionGrid.on('click', '.nanobar-position-cell', function () {
			selectPosition($(this));
		});

		// Radio-group keyboard model: arrows move the selection (skipping the
		// locked cells), like a native radio group.
		$positionGrid.on('keydown', '.nanobar-position-cell', function (event) {
			var steps = {
				ArrowLeft: -1,
				ArrowRight: 1,
				ArrowUp: -3,
				ArrowDown: 3,
			};
			if (!(event.key in steps)) {
				return;
			}
			event.preventDefault();
			var $cells = $positionGrid.find('.nanobar-position-cell');
			var index = $cells.index(this);
			var step = steps[event.key];
			var next = index + step;
			// Left/right wrap within the current row only.
			if (
				Math.abs(step) === 1 &&
				Math.floor(next / 3) !== Math.floor(index / 3)
			) {
				return;
			}
			var $target = $cells.eq(next);
			if (!$target.length || $target.prop('disabled')) {
				return;
			}
			$target.trigger('focus');
			selectPosition($target);
		});

		// Preview device switch (view-only) and open/close of the mock panel.
		$('input[name="nanobar_preview_device"]').on('change', function () {
			$viewport.attr('data-device', $(this).val());
		});

		$preview.on('click', function () {
			var isOpen = !$mockPanel.hasClass('is-closed');
			$mockPanel.toggleClass('is-closed', isOpen);
			$preview.attr('aria-expanded', isOpen ? 'false' : 'true');
		});
		// Ready-made swatches shown inside the picker, so the common choices
		// are one click away instead of hunting on the color field.
		var colorPalette = [
			'#1d2327',
			'#2271b1',
			'#764ba2',
			'#00a32a',
			'#d63638',
			'#dba617',
			'#ffffff',
		];

		// Shows the current hex on the picker's button ("Select color" tells
		// the admin nothing about what is currently selected).
		function syncColorLabel(color) {
			$colorField
				.closest('.wp-picker-container')
				.find('.wp-color-result-text')
				.text(String(color || '').toUpperCase());
		}

		$colorField.wpColorPicker({
			palettes: colorPalette,
			change: function (event, ui) {
				updatePreview(ui.color.toString());
				syncColorLabel(ui.color.toString());
				markDirty();
			},
			clear: function () {
				updatePreview($colorField.data('default-color'));
				syncColorLabel($colorField.data('default-color'));
			},
		});
		syncColorLabel($colorField.val());
		$sizeInput.on('input change', function () {
			updatePreview($colorField.val());
		});
		var currentSizeUnit = getSizeUnit();
		$sizeUnits.on('change', function () {
			applySizeUnit(currentSizeUnit);
			currentSizeUnit = getSizeUnit();
			updatePreview($colorField.val());
		});
		$(
			'#nanobar-color-scheme, input[name="nanobar_options[icons_only]"]'
		).on('change', scheduleMockPanel);

		// Re-reads the unit radios after values were set from code (restore
		// defaults, undo, a save), re-applying that unit's bounds and hint.
		function syncSizeUnit() {
			currentSizeUnit = getSizeUnit();
			applySizeUnit(null);
		}

		app.getSizeUnit = getSizeUnit;
		app.syncSizeUnit = syncSizeUnit;
		app.syncPositionGrid = syncPositionGrid;
		app.syncColorLabel = syncColorLabel;
		app.updatePreview = updatePreview;
		app.scheduleMockPanel = scheduleMockPanel;
		app.updateMockScheme = updateMockScheme;
	});
})();
