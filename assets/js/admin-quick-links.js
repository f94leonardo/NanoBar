/**
 * Settings → NanoBar admin page — the quick links repeater: validation, icon picker overlay, drag-to-reorder.
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
		var config = app.config;
		var i18n = app.i18n;
		var $quickLinksCount = $('#nanobar-quick-links-count');
		function markDirty() {
			return app.markDirty.apply(app, arguments);
		}
		function scheduleMockPanel() {
			return app.scheduleMockPanel.apply(app, arguments);
		}
		function scrollToElement() {
			return app.scrollToElement.apply(app, arguments);
		}

		var helpers = app.helpers;
		function isInternalQuickLinkUrl(value) {
			return helpers.isInternalQuickLinkUrl(value);
		}
		function relativizeQuickLinkUrl(value) {
			return helpers.relativizeQuickLinkUrl(value);
		}

		// "Quick links" repeater: clones the hidden <template>#nanobar-quick-link-template
		// (see Settings\Page::render_quick_link_row()), swapping its "__INDEX__"
		// placeholder for a counter that only ever increases, so a row's array
		// index is never reused within the same page load even after rows in
		// between are removed — see Config::get()['quick_links']['max'] for the
		// cap mirrored below.
		var $quickLinksList = $('#nanobar-quick-links');
		var $addQuickLink = $('#nanobar-add-quick-link');
		var quickLinkTemplate = document.getElementById(
			'nanobar-quick-link-template'
		);
		var quickLinkIndex = $quickLinksList.find(
			'.nanobar-quick-link-row'
		).length;
		var maxQuickLinks =
			(config.quick_links && config.quick_links.max) || 20;

		function updateAddQuickLinkState() {
			var count = $quickLinksList.find('.nanobar-quick-link-row').length;
			$addQuickLink.prop('disabled', count >= maxQuickLinks);
			var text = (i18n.quickLinksCount || '%1$d of %2$d used')
				.replace('%1$d', count)
				.replace('%2$d', maxQuickLinks);
			$quickLinksCount.text(text);
			$('#nanobar-quick-links-empty').prop('hidden', count > 0);
		}

		function updateQuickLinkIconPreview($row) {
			var value = (
				$row.find('[data-quick-link-icon-input]').val() || ''
			).trim();
			var iconClass = /^dashicons-[a-z0-9-]+$/.test(value)
				? value
				: 'dashicons-admin-links';
			$row.find('.nanobar-quick-link-row__icon-preview').attr(
				'class',
				'dashicons ' +
					iconClass +
					' nanobar-quick-link-row__icon-preview'
			);
		}

		// A row nobody has touched yet (blank label AND blank URL, the same
		// pair Settings\Sanitizer::sanitize_quick_links() uses to drop a row
		// silently) is left alone rather than flagged, so a freshly added row
		// is never itself an error. Roles are not validated here: leaving all
		// of them unchecked is a valid, deliberate choice (see the roles-hint
		// text next to the checkboxes) meaning "same roles as NanoBar itself",
		// not an incomplete row.
		function validateQuickLinkRow($row) {
			var label = (
				$row.find('[data-quick-link-field="label"]').val() || ''
			).trim();
			var url = (
				$row.find('[data-quick-link-field="url"]').val() || ''
			).trim();
			var errors = {};

			if ('' === label && '' === url) {
				return errors;
			}

			if ('' === label) {
				errors.label = i18n.quickLinkLabelRequired;
			}
			if ('' === url) {
				errors.url = i18n.quickLinkUrlRequired;
			} else if (!isInternalQuickLinkUrl(url)) {
				errors.url = i18n.quickLinkUrlInvalid;
			}

			return errors;
		}

		function renderQuickLinkRowErrors($row, errors) {
			$row.find('[data-quick-link-error]').each(function () {
				var $error = $(this);
				var message = errors[$error.data('quick-link-error')] || '';
				$error.text(message);
				$error.prop('hidden', !message);
			});
			$row.toggleClass('has-errors', Object.keys(errors).length > 0);
		}

		// Runs on every submit attempt (see the form 'submit' handler below):
		// validates every row, paints each row's own error messages, shows a
		// summary notice if any row failed, and scrolls/focuses the first
		// offending field — then reports whether the form may proceed.
		function validateAllQuickLinks() {
			var $firstInvalidRow = null;
			var firstInvalidField = null;
			var anyErrors = false;

			$quickLinksList.find('.nanobar-quick-link-row').each(function () {
				var $row = $(this);
				var errors = validateQuickLinkRow($row);
				renderQuickLinkRowErrors($row, errors);
				if (Object.keys(errors).length > 0) {
					anyErrors = true;
					if (!$firstInvalidRow) {
						$firstInvalidRow = $row;
						// Priority order for which control gets focus, not
						// object key order (not guaranteed): label, then url.
						firstInvalidField = errors.label ? 'label' : 'url';
					}
				}
			});

			var $summary = $('#nanobar-quick-links-summary-error');
			$summary.text(anyErrors ? i18n.quickLinksHaveErrors : '');
			$summary.prop('hidden', !anyErrors);

			if ($firstInvalidRow) {
				scrollToElement($firstInvalidRow[0], 'center');
				$firstInvalidRow
					.find('[data-quick-link-field="' + firstInvalidField + '"]')
					.first()
					.trigger('focus');
			}

			return !anyErrors;
		}

		// Shared "choose an icon" overlay — a single instance (built lazily, on
		// first use) reused by every row's icon trigger button, instead of
		// stamping the full ~350-icon dashicons grid into the DOM once per row.
		// The grid itself comes from `nanobarSettings.allDashicons`
		// (Settings\Page::get_all_dashicons(), parsed from WordPress core's own
		// dashicons.css so it's never out of sync with the running core).
		var allDashicons = window.nanobarSettings.allDashicons || [];
		var $iconPicker = null;
		var $iconPickerGrid = null;
		var $iconPickerSearch = null;
		var $iconPickerEmpty = null;
		var iconPickerTargetRow = null;
		var iconPickerLastFocused = null;

		function renderIconPickerGrid(query) {
			var needle = (query || '').trim().toLowerCase();
			var matches = !needle
				? allDashicons
				: allDashicons.filter(function (slug) {
						return -1 !== slug.toLowerCase().indexOf(needle);
					});

			$iconPickerGrid.empty();
			matches.forEach(function (slug) {
				var $btn = $(
					'<button type="button" class="nanobar-icon-picker__icon" tabindex="-1"></button>'
				)
					.attr('title', slug)
					.attr('aria-label', slug)
					.data('icon-slug', slug);
				$('<span aria-hidden="true"></span>')
					.addClass('dashicons ' + slug)
					.appendTo($btn);
				$iconPickerGrid.append($btn);
			});

			// Roving tabindex: the grid is one Tab stop (the first icon);
			// arrow keys move within it (see the keydown handler below).
			$iconPickerGrid
				.find('.nanobar-icon-picker__icon')
				.first()
				.attr('tabindex', '0');
			$iconPickerEmpty.prop('hidden', matches.length > 0);
		}

		function closeIconPicker() {
			if (!$iconPicker || $iconPicker.prop('hidden')) {
				return;
			}
			$iconPicker.prop('hidden', true);
			iconPickerTargetRow = null;
			if (iconPickerLastFocused) {
				iconPickerLastFocused.trigger('focus');
			}
		}

		function selectIcon(slug) {
			var $row = iconPickerTargetRow;
			closeIconPicker();
			if (!$row || !slug) {
				return;
			}
			$row.find('[data-quick-link-icon-input]').val(slug);
			$row.find('[data-quick-link-icon-label]').text(slug);
			$row.find('[data-quick-link-icon-trigger]').attr('title', slug);
			updateQuickLinkIconPreview($row);
			scheduleMockPanel();
			markDirty();
		}

		function buildIconPicker() {
			var i18nLocal = window.nanobarSettings.i18n || {};
			$iconPicker = $(
				'<div class="nanobar-icon-picker" hidden>' +
					'<div class="nanobar-icon-picker__backdrop"></div>' +
					'<div class="nanobar-icon-picker__dialog" role="dialog" aria-modal="true">' +
					'<div class="nanobar-icon-picker__search">' +
					'<span class="dashicons dashicons-search" aria-hidden="true"></span>' +
					'<input type="text" class="nanobar-icon-picker__search-input" autocomplete="off" />' +
					'</div>' +
					'<div class="nanobar-icon-picker__grid" role="group"></div>' +
					'<p class="nanobar-icon-picker__empty" hidden></p>' +
					'</div>' +
					'</div>'
			);
			$iconPicker
				.find('.nanobar-icon-picker__dialog')
				.attr('aria-label', i18nLocal.chooseIcon || '');
			$iconPickerSearch = $iconPicker
				.find('.nanobar-icon-picker__search-input')
				.attr('placeholder', i18nLocal.searchIcons || '');
			$iconPickerGrid = $iconPicker.find('.nanobar-icon-picker__grid');
			$iconPickerEmpty = $iconPicker
				.find('.nanobar-icon-picker__empty')
				.text(i18nLocal.noIconsFound || '');

			$('body').append($iconPicker);

			$iconPicker
				.find('.nanobar-icon-picker__backdrop')
				.on('click', closeIconPicker);
			$iconPickerGrid.on(
				'click',
				'.nanobar-icon-picker__icon',
				function () {
					selectIcon($(this).data('icon-slug'));
				}
			);
			$iconPickerSearch.on('input', function () {
				renderIconPickerGrid($iconPickerSearch.val());
			});
			$iconPicker.on('keydown', function (event) {
				if ('Escape' === event.key) {
					event.preventDefault();
					closeIconPicker();
					return;
				}

				var searchEl = $iconPickerSearch[0];
				var $icons = $iconPickerGrid.find('.nanobar-icon-picker__icon');
				var $current = $icons.filter(document.activeElement);

				if ('Tab' === event.key) {
					// Focus trap: the dialog has two stops — the search field
					// and the grid (one roving stop) — and Tab, either way,
					// moves to the other one instead of leaving the dialog.
					event.preventDefault();
					if (document.activeElement === searchEl && $icons.length) {
						$icons
							.filter('[tabindex="0"]')
							.first()
							.trigger('focus');
					} else {
						$iconPickerSearch.trigger('focus');
					}
					return;
				}

				if (
					'Enter' === event.key &&
					document.activeElement === searchEl
				) {
					event.preventDefault();
					var $first = $icons.first();
					if ($first.length) {
						selectIcon($first.data('icon-slug'));
					}
					return;
				}

				var arrows = [
					'ArrowLeft',
					'ArrowRight',
					'ArrowUp',
					'ArrowDown',
				];
				if (-1 === arrows.indexOf(event.key)) {
					return;
				}
				if (document.activeElement === searchEl) {
					if ('ArrowDown' === event.key && $icons.length) {
						event.preventDefault();
						$icons
							.filter('[tabindex="0"]')
							.first()
							.trigger('focus');
					}
					return;
				}
				if (!$current.length) {
					return;
				}
				event.preventDefault();
				var index = $icons.index($current);
				var next = index;
				if ('ArrowLeft' === event.key) {
					next = index - 1;
				} else if ('ArrowRight' === event.key) {
					next = index + 1;
				} else {
					// Up/down: the icon in the same column of the row above/below,
					// found by geometry since the grid wraps by available width.
					var rect = $current[0].getBoundingClientRect();
					var goingDown = 'ArrowDown' === event.key;
					var best = -1;
					var bestDistance = Infinity;
					$icons.each(function (i) {
						var r = this.getBoundingClientRect();
						var vertical = goingDown
							? r.top - rect.top
							: rect.top - r.top;
						if (vertical < rect.height / 2) {
							return;
						}
						var distance =
							Math.abs(r.left - rect.left) * 1000 + vertical;
						if (distance < bestDistance) {
							bestDistance = distance;
							best = i;
						}
					});
					if (-1 === best && !goingDown) {
						$iconPickerSearch.trigger('focus');
						return;
					}
					next = best;
				}
				if (next >= 0 && next < $icons.length) {
					$icons.attr('tabindex', '-1');
					$icons.eq(next).attr('tabindex', '0').trigger('focus');
				}
			});
		}

		function openIconPicker($row, $trigger) {
			if (!$iconPicker) {
				buildIconPicker();
			}
			iconPickerTargetRow = $row;
			iconPickerLastFocused = $trigger;
			renderIconPickerGrid('');
			$iconPickerSearch.val('');
			$iconPicker.prop('hidden', false);
			$iconPickerSearch.trigger('focus');
		}

		function bindQuickLinkRow($row) {
			updateQuickLinkIconPreview($row);
			$row.on('input', '[data-quick-link-icon-input]', function () {
				updateQuickLinkIconPreview($row);
			});
			// Live feedback as the admin types/checks, on top of the
			// submit-time pass in validateAllQuickLinks() below — clears an
			// error as soon as it's fixed instead of leaving it until the next
			// submit attempt.
			$row.on('input change', '[data-quick-link-field]', function () {
				renderQuickLinkRowErrors($row, validateQuickLinkRow($row));
				scheduleMockPanel();
			});
			$row.on(
				'change',
				'input[name$="[mobile_visible]"]',
				scheduleMockPanel
			);
			// 'focusout', not 'blur': jQuery's delegated .on() needs a bubbling
			// event, and plain 'blur' does not bubble.
			$row.on('focusout', '[data-quick-link-field="url"]', function () {
				var $urlInput = $(this);
				var cleaned = relativizeQuickLinkUrl($urlInput.val());
				if (cleaned !== $urlInput.val()) {
					$urlInput.val(cleaned);
					renderQuickLinkRowErrors($row, validateQuickLinkRow($row));
				}
			});
			$row.on('click', '[data-quick-link-remove]', function (event) {
				event.preventDefault();
				$row.remove();
				updateAddQuickLinkState();
				scheduleMockPanel();
				markDirty();
			});
		}

		// Delegated on the always-present list container, not bound per row:
		// covers rows added later by "Add link" automatically, and there is
		// only ever one picker instance regardless of how many rows exist.
		$quickLinksList.on(
			'click',
			'[data-quick-link-icon-trigger]',
			function (event) {
				event.preventDefault();
				openIconPicker(
					$(this).closest('.nanobar-quick-link-row'),
					$(this)
				);
			}
		);

		$quickLinksList.find('.nanobar-quick-link-row').each(function () {
			bindQuickLinkRow($(this));
		});

		if (quickLinkTemplate) {
			$addQuickLink.on('click', function (event) {
				event.preventDefault();
				if (
					$quickLinksList.find('.nanobar-quick-link-row').length >=
					maxQuickLinks
				) {
					return;
				}
				var html = quickLinkTemplate.innerHTML
					.split('__INDEX__')
					.join(quickLinkIndex);
				quickLinkIndex++;
				var $row = $($.parseHTML(html.trim()));
				$quickLinksList.append($row);
				bindQuickLinkRow($row);
				updateAddQuickLinkState();
				markDirty();
				$row.find('input[type="text"]').first().trigger('focus');
			});
		}

		updateAddQuickLinkState();

		// Drag-and-drop reordering by the row handle. The submitted order is
		// the DOM order (Settings\Sanitizer re-indexes rows as it reads them),
		// so nothing else needs to change when a row moves.
		if ($.fn.sortable) {
			$quickLinksList.sortable({
				items: '> .nanobar-quick-link-row',
				handle: '.nanobar-quick-link-row__handle',
				axis: 'y',
				tolerance: 'pointer',
				forcePlaceholderSize: true,
				placeholder:
					'nanobar-quick-link-row nanobar-quick-link-row--placeholder',
				update: markDirty,
			});
		}

		// The server may normalize quick links on save (drop half-filled
		// rows, make same-site URLs relative): swap in its version of the list.
		function replaceQuickLinks($freshRows) {
			$quickLinksList.find('.nanobar-quick-link-row').remove();
			$quickLinksList.append($freshRows);
			$freshRows.each(function () {
				bindQuickLinkRow($(this));
			});
			quickLinkIndex = $freshRows.length;
			updateAddQuickLinkState();
			renderQuickLinkRowsReset();
			scheduleMockPanel();
		}

		function renderQuickLinkRowsReset() {
			$quickLinksList.find('.nanobar-quick-link-row').each(function () {
				renderQuickLinkRowErrors($(this), {});
			});
			$('#nanobar-quick-links-summary-error').prop('hidden', true);
		}

		app.updateAddQuickLinkState = updateAddQuickLinkState;
		app.validateAllQuickLinks = validateAllQuickLinks;
		app.replaceQuickLinks = replaceQuickLinks;
	});
})();
