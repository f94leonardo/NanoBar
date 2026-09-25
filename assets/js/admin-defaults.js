/**
 * Settings → NanoBar admin page — "Restore defaults" with undo: applies the defaults, snapshots the form.
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
		var i18n = app.i18n;
		function markDirty() {
			return app.markDirty.apply(app, arguments);
		}
		function clearDirty() {
			return app.clearDirty.apply(app, arguments);
		}
		function showToast() {
			return app.showToast.apply(app, arguments);
		}
		function refreshAll() {
			return app.refreshAll.apply(app, arguments);
		}
		function scheduleMockPanel() {
			return app.scheduleMockPanel.apply(app, arguments);
		}
		function syncSizeUnit() {
			return app.syncSizeUnit.apply(app, arguments);
		}
		function getMenuItemCheckboxes() {
			return app.getMenuItemCheckboxes.apply(app, arguments);
		}

		var state = app.state;
		var $form = $('.nanobar-form');
		var $posH = $('#nanobar-pos-h');
		var $posV = $('#nanobar-pos-v');
		var $sizeInput = $('#nanobar-toggle-size-input');
		var $sizeUnits = $('input[name="nanobar_options[toggle_size_unit]"]');
		var $colorField = $('#nanobar-color-field');
		var $quickLinksList = $('#nanobar-quick-links');

		function applyDefaults() {
			$('input[name="nanobar_options[enabled]"]').prop(
				'checked',
				!!defaults.enabled
			);
			$('input[name="nanobar_options[roles][]"]').prop(
				'checked',
				function () {
					return defaults.roles.indexOf($(this).val()) !== -1;
				}
			);
			$posH.val(defaults.position_horizontal);
			$posV.val(defaults.position_vertical);
			$sizeUnits.each(function () {
				$(this).prop(
					'checked',
					$(this).val() === (defaults.toggle_size_unit || 'px')
				);
			});
			syncSizeUnit();
			$sizeInput.val(defaults.toggle_size);
			$colorField.wpColorPicker('color', defaults.toggle_bg_color);
			$('input[name="nanobar_options[icons_only]"]').prop(
				'checked',
				!!defaults.icons_only
			);
			$('input[name="nanobar_options[force_admin_bar_for_qm]"]').prop(
				'checked',
				!!defaults.force_admin_bar_for_qm
			);
			getMenuItemCheckboxes().each(function () {
				var itemKey = $(this).data('item-key');
				$(this).prop(
					'checked',
					!!(
						defaults.visible_items &&
						defaults.visible_items[itemKey]
					)
				);
			});
			$('input[data-mobile-key]').each(function () {
				var mobileKey = $(this).data('mobile-key');
				$(this).prop(
					'checked',
					!(
						defaults.mobile_items &&
						false === defaults.mobile_items[mobileKey]
					)
				);
			});
			$(
				'input[name="nanobar_options[use_elementor_when_available]"]'
			).prop('checked', !!defaults.use_elementor_when_available);
			$('input[name="nanobar_options[command_palette_enabled]"]').prop(
				'checked',
				!!defaults.command_palette_enabled
			);
			$('#nanobar-color-scheme').val(defaults.color_scheme || 'auto');
		}

		// Snapshot of every saved field, so "Restore defaults" can be undone.
		// Quick link rows are detached (not destroyed) and put back as they
		// were; their own fields are covered that way, not by the field list.
		function captureSnapshot() {
			var fields = [];
			$form
				.find('[name^="nanobar_options["]')
				.not('[name^="nanobar_options[quick_links]"]')
				.each(function () {
					fields.push({
						el: this,
						checked: this.checked,
						value: this.value,
					});
				});
			return {
				fields: fields,
				rows: $quickLinksList
					.children('.nanobar-quick-link-row')
					.detach(),
				dirty: state.dirty,
				generation: state.generation,
			};
		}

		function restoreSnapshot(snapshot) {
			if (snapshot.generation !== state.generation) {
				return;
			}
			snapshot.fields.forEach(function (field) {
				field.el.checked = field.checked;
				field.el.value = field.value;
			});
			$quickLinksList.children('.nanobar-quick-link-row').remove();
			$quickLinksList.append(snapshot.rows);
			$colorField.wpColorPicker('color', $colorField.val());
			refreshAll();
			scheduleMockPanel();
			if (snapshot.dirty) {
				markDirty();
			} else {
				clearDirty();
			}
		}

		// "Restore defaults": asks inline (a toast with Restore / Cancel), and
		// once done offers Undo — nothing is saved either way until Save.
		$('.nanobar-reset-defaults-trigger').on('click', function (event) {
			event.preventDefault();
			var $trigger = $(this);
			showToast({
				message: i18n.confirmRestoreDefaults,
				key: 'restore',
				focus: true,
				onEscape: function () {
					$trigger.trigger('focus');
				},
				actions: [
					{
						label: i18n.restore,
						primary: true,
						onClick: function () {
							var snapshot = captureSnapshot();
							applyDefaults();
							refreshAll();
							markDirty();
							showToast({
								message: i18n.restored,
								key: 'restore',
								timeout: 12000,
								actions: [
									{
										label: i18n.undo,
										primary: true,
										onClick: function () {
											restoreSnapshot(snapshot);
										},
									},
								],
							});
						},
					},
					{
						label: i18n.cancel,
						onClick: function () {
							$trigger.trigger('focus');
						},
					},
				],
			});
		});
	});
})();
