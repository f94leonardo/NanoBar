/**
 * Settings → NanoBar admin page — the Import card: a drop zone for the
 * settings file, a client-side look inside it (so a wrong file is caught
 * before it is sent) and an inline confirmation before it replaces the saved
 * settings. Without JS the form is a plain file input + submit button.
 *
 * One module of the page script: registered on `window.NanoBarAdmin` (see
 * admin-core.js) and initialised by admin-settings.js once the DOM is ready.
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
		var $form = $('[data-backup-form]');
		if (!$form.length) {
			return;
		}

		// Matches Settings\Backup::MAX_BYTES; the server enforces it anyway.
		var MAX_BYTES = 262144;

		var $zone = $form.find('[data-dropzone]');
		var input = $zone.find('input[type="file"]')[0];
		var $file = $zone.find('[data-dropzone-file]');
		var $name = $zone.find('[data-dropzone-name]');
		var $meta = $zone.find('[data-dropzone-meta]');
		var $summary = $form.find('[data-backup-summary]');
		var $import = $form.find('[data-backup-import]');
		var confirmed = false;

		function formatBytes(bytes) {
			return bytes < 1024
				? bytes + ' B'
				: (bytes / 1024).toFixed(1) + ' KB';
		}

		function reset() {
			input.value = '';
			$zone.removeClass('has-file is-invalid');
			$file.prop('hidden', true);
			$summary.text('').removeClass('is-error');
			$import.prop('disabled', true);
		}

		function setInvalid(message) {
			$zone.addClass('is-invalid');
			$summary.text(message).addClass('is-error');
			$import.prop('disabled', true);
		}

		function setReady(message) {
			$zone.removeClass('is-invalid');
			$summary.text(message).removeClass('is-error');
			$import.prop('disabled', false);
		}

		function readText(file) {
			if (file.text) {
				return file.text();
			}
			return new Promise(function (resolve, reject) {
				var reader = new window.FileReader();
				reader.onload = function () {
					resolve(String(reader.result));
				};
				reader.onerror = reject;
				reader.readAsText(file);
			});
		}

		// Looks inside the chosen file: a NanoBar export has `plugin: nanobar`
		// and an `options` object. Only a summary is shown; nothing is applied
		// client-side (the server re-validates everything on import).
		function inspect(file) {
			$name.text(file.name);
			$meta.text(formatBytes(file.size));
			$zone.addClass('has-file');
			$file.prop('hidden', false);
			$import.prop('disabled', true);

			if (file.size > MAX_BYTES) {
				setInvalid(i18n.backupTooLarge);
				return;
			}

			readText(file)
				.then(function (text) {
					var data = JSON.parse(text);
					if (
						!data ||
						'nanobar' !== data.plugin ||
						!data.options ||
						'object' !== typeof data.options
					) {
						throw new Error('not a NanoBar export');
					}
					var links = Array.isArray(data.options.quick_links)
						? data.options.quick_links.length
						: 0;
					setReady(
						(i18n.backupSummary || '%1$s %2$d')
							.replace('%1$s', function () {
								return String(data.version || '?').slice(0, 20);
							})
							.replace('%2$d', links)
					);
				})
				.catch(function () {
					setInvalid(i18n.backupInvalidFile);
				});
		}

		function chooseFile(file) {
			if (file) {
				inspect(file);
			} else {
				reset();
			}
		}

		$(input).on('change', function () {
			chooseFile(input.files && input.files[0]);
		});

		// Drag and drop onto the zone: hand the dropped file to the real
		// input (so it is what gets submitted) and inspect it like a pick.
		$zone.on('dragenter dragover', function (event) {
			event.preventDefault();
			$zone.addClass('is-dragover');
		});
		$zone.on('dragleave dragend drop', function () {
			$zone.removeClass('is-dragover');
		});
		$zone.on('drop', function (event) {
			event.preventDefault();
			var files = event.originalEvent.dataTransfer.files;
			if (!files || !files.length) {
				return;
			}
			try {
				input.files = files;
			} catch {
				// A browser that will not let a script set the input's files:
				// the file cannot be submitted, so ask for a regular pick.
				setInvalid(i18n.backupInvalidFile);
				return;
			}
			chooseFile(files[0]);
		});

		$zone.find('[data-dropzone-remove]').on('click', function () {
			reset();
			$zone.find('label').trigger('focus');
		});

		// Replacing the saved settings is destructive: confirm inline (the same
		// toast pattern as "Restore defaults") before actually submitting.
		$form.on('submit', function (event) {
			if (confirmed) {
				return;
			}
			event.preventDefault();
			if ($import.prop('disabled')) {
				return;
			}
			app.showToast({
				message: i18n.backupConfirmImport,
				key: 'backup-import',
				focus: true,
				onEscape: function () {
					$import.trigger('focus');
				},
				actions: [
					{
						label: i18n.backupImportAction,
						primary: true,
						onClick: function () {
							confirmed = true;
							// A leave-site prompt over unsaved edits makes no
							// sense here: the import replaces them anyway.
							app.state.submitting = true;
							window.HTMLFormElement.prototype.submit.call(
								$form[0]
							);
						},
					},
					{
						label: i18n.cancel,
						onClick: function () {
							$import.trigger('focus');
						},
					},
				],
			});
		});

		// Nothing chosen yet → nothing to import (a file kept by the browser
		// across a reload is inspected right away).
		if (input.files && input.files.length) {
			chooseFile(input.files[0]);
		} else {
			$import.prop('disabled', true);
		}
	});
})();
