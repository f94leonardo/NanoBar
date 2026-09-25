/**
 * Settings → NanoBar admin page — saving in place over fetch, with the native submit as fallback and Cmd/Ctrl+S.
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
		function setStatus() {
			return app.setStatus.apply(app, arguments);
		}
		function clearDirty() {
			return app.clearDirty.apply(app, arguments);
		}
		function showToast() {
			return app.showToast.apply(app, arguments);
		}
		function validateAllQuickLinks() {
			return app.validateAllQuickLinks.apply(app, arguments);
		}
		function replaceQuickLinks() {
			return app.replaceQuickLinks.apply(app, arguments);
		}
		function refreshAll() {
			return app.refreshAll.apply(app, arguments);
		}

		var state = app.state;
		var $form = $('.nanobar-form');
		var $saveButton = $('#nanobar-save-header');
		var $colorField = $('#nanobar-color-field');

		// Saving happens in place (fetch to options.php, the same endpoint and
		// nonce as a normal submit), so the scroll position and open state are
		// kept. Anything unexpected falls back to a regular form submit, which
		// is also what runs with JS unavailable.
		function nativeSubmit() {
			state.submitting = true;
			window.HTMLFormElement.prototype.submit.call($form[0]);
		}

		function setSaving(saving) {
			state.saving = saving;
			$saveButton.prop('disabled', saving);
			$form.attr('aria-busy', saving ? 'true' : 'false');
			setStatus(saving ? i18n.saving : state.dirty ? i18n.unsaved : '');
		}

		// Values the sanitizer clamped or normalized (toggle size, unit, ...)
		// would otherwise stay as typed while the form reads "saved".
		function syncFieldsFromServer(doc) {
			$(doc)
				.find('[name^="nanobar_options["]')
				.not('[name^="nanobar_options[quick_links]"]')
				.each(function () {
					var fresh = this;
					var $local = $form
						.find('[name]')
						.filter(function () {
							return this.name === fresh.name;
						})
						// Several inputs can share a name (the roles[] checkboxes,
						// the unit radios): match the same kind of control, and for
						// checkboxes/radios the one with the same value, so each
						// server-side state lands on its own input.
						.filter(function () {
							return (
								this.type === fresh.type &&
								('radio' !== this.type &&
								'checkbox' !== this.type
									? true
									: this.value === fresh.value)
							);
						})
						.first();
					if (!$local.length) {
						return;
					}
					if ('checkbox' === fresh.type || 'radio' === fresh.type) {
						$local.prop('checked', fresh.checked);
					} else if ($local.val() !== fresh.value) {
						$local.val(fresh.value);
						if ($local.is($colorField)) {
							$colorField.wpColorPicker('color', fresh.value);
						}
					}
				});
			refreshAll();
		}

		function saveViaAjax() {
			setSaving(true);
			window
				.fetch($form.attr('action'), {
					method: 'POST',
					body: new window.FormData($form[0]),
					credentials: 'same-origin',
				})
				.then(function (response) {
					return response.text().then(function (html) {
						return {
							ok: response.ok,
							url: response.url,
							html: html,
						};
					});
				})
				.then(function (result) {
					if (
						!result.ok ||
						!/settings-updated=true/.test(result.url)
					) {
						setSaving(false);
						nativeSubmit();
						return;
					}
					var doc = new window.DOMParser().parseFromString(
						result.html,
						'text/html'
					);
					replaceQuickLinks(
						$(doc)
							.find(
								'#nanobar-quick-links > .nanobar-quick-link-row'
							)
							.map(function () {
								return document.importNode(this, true);
							})
					);
					syncFieldsFromServer(doc);
					state.generation++;
					// A pending "Undo restore defaults" can no longer apply.
					$('[data-toast-key="restore"]').remove();
					clearDirty();
					setSaving(false);
					showToast({
						message: i18n.saved,
						type: 'success',
						timeout: 5000,
						key: 'saved',
					});
					// Notices raised by the sanitizer (e.g. a value it had to
					// correct) ride along in the response page.
					$(doc)
						.find('div.notice[id^="setting-error-"]')
						.not('#setting-error-settings_updated')
						.each(function () {
							var $notice = $(this);
							showToast({
								message: $notice.text().trim(),
								type: $notice.hasClass('notice-error')
									? 'error'
									: '',
							});
						});
				})
				.catch(function () {
					setSaving(false);
					showToast({
						message: i18n.saveFailed,
						type: 'error',
						key: 'save-failed',
					});
				});
		}

		$form.on('submit', function (event) {
			event.preventDefault();
			if (state.saving) {
				return;
			}
			if (!validateAllQuickLinks()) {
				return;
			}
			if (!window.fetch || !window.FormData || !window.DOMParser) {
				nativeSubmit();
				return;
			}
			saveViaAjax();
		});

		// Cmd/Ctrl+S saves, like in any editor.
		$(document).on('keydown', function (event) {
			if (
				(event.metaKey || event.ctrlKey) &&
				'string' === typeof event.key &&
				's' === event.key.toLowerCase()
			) {
				event.preventDefault();
				if ($('.nanobar-icon-picker:not([hidden])').length) {
					return;
				}
				$form.trigger('submit');
			}
		});
	});
})();
