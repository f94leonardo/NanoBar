/**
 * Settings → NanoBar admin page — shared state (unsaved changes / saving), toasts and the scroll helper.
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

	if (!window.jQuery || !window.nanobarSettings) {
		return;
	}

	var registered = [];

	// The shared object every module hangs its public functions on.
	var app = {
		defaults: window.nanobarSettings.defaults,
		config: window.nanobarSettings.config,
		i18n: window.nanobarSettings.i18n,
		helpers: window.NanoBarAdminHelpers,
		modules: registered,
		// "Dirty" (unsaved changes) tracking lives here so every module reads
		// the same flags: dirty (something changed since the last save),
		// saving (a fetch is in flight), submitting (a native submit is on
		// its way, so the leave-site prompt must stay quiet), generation
		// (bumped per successful save, so an undo snapshot can go stale).
		state: {
			dirty: false,
			submitting: false,
			saving: false,
			generation: 0,
		},
	};

	window.NanoBarAdmin = {
		app: app,
		register: function (init) {
			registered.push(init);
		},
	};

	window.NanoBarAdmin.register(function (app, $) {
		var i18n = app.i18n;
		var state = app.state;
		var $toasts = $('#nanobar-toasts');
		var $saveStatus = $('#nanobar-save-status');
		var $saveButton = $('#nanobar-save-header');
		var $form = $('.nanobar-form');
		var prefersReducedMotion = window.matchMedia
			? window.matchMedia('(prefers-reduced-motion: reduce)')
			: { matches: false };

		// A dot on the header's Save button (".is-dirty", see _header.scss),
		// an "Unsaved changes" label next to it (a live region, so it is also
		// announced), plus a native "leave site?" prompt on navigating away,
		// so a change is never silently lost. markDirty() runs both from the
		// delegated listener below (every native input/change; controls
		// marked data-no-dirty, like the preview's device switch, are
		// view-only and skipped) and explicitly at the mutation points that
		// change the DOM without firing one (icon picker, wpColorPicker,
		// position grid, add/remove quick link).
		function setStatus(text) {
			$saveStatus.text(text || '');
		}

		function markDirty() {
			state.dirty = true;
			$saveButton.addClass('is-dirty');
			if (!state.saving) {
				setStatus(i18n.unsaved);
			}
		}

		function clearDirty() {
			state.dirty = false;
			$saveButton.removeClass('is-dirty');
			setStatus('');
		}

		$form.on('input change', 'input, select, textarea', function (event) {
			if ($(event.target).is('[data-no-dirty]')) {
				return;
			}
			markDirty();
		});

		$(window).on('beforeunload', function (event) {
			if (!state.dirty || state.submitting) {
				return undefined;
			}
			event.preventDefault();
			// Legacy requirement for the prompt to actually appear in some
			// browsers; the string itself is never shown — every modern
			// browser displays its own fixed wording instead.
			event.returnValue = '';
			return '';
		});
		// Scrolling helper that honors the OS "reduce motion" preference.
		function scrollToElement(element, block) {
			element.scrollIntoView({
				behavior: prefersReducedMotion.matches ? 'auto' : 'smooth',
				block: block || 'center',
			});
		}
		// Toasts: a small message stack for save results and the "Restore
		// defaults" confirm/undo. `key` makes a toast replace the previous one
		// with the same key; `actions` are buttons; `timeout` (ms) auto-closes.
		function showToast(options) {
			if (options.key) {
				$toasts
					.children('[data-toast-key="' + options.key + '"]')
					.remove();
			}
			var $toast = $('<div class="nanobar-toast"></div>').addClass(
				options.type ? 'nanobar-toast--' + options.type : ''
			);
			if (options.key) {
				$toast.attr('data-toast-key', options.key);
			}
			$('<span class="nanobar-toast__message"></span>')
				.text(options.message)
				.appendTo($toast);

			function close() {
				$toast.remove();
			}

			if (options.actions && options.actions.length) {
				var $actions = $('<div class="nanobar-toast__actions"></div>');
				options.actions.forEach(function (action) {
					$(
						'<button type="button" class="nanobar-toast__btn"></button>'
					)
						.toggleClass(
							'nanobar-toast__btn--primary',
							!!action.primary
						)
						.text(action.label)
						.on('click', function () {
							close();
							if (action.onClick) {
								action.onClick();
							}
						})
						.appendTo($actions);
				});
				$toast.append($actions);
			}
			if (options.onEscape) {
				$toast.on('keydown', function (event) {
					if ('Escape' === event.key) {
						event.preventDefault();
						close();
						options.onEscape();
					}
				});
			}
			$toasts.append($toast);
			if (options.focus) {
				$toast.find('button').first().trigger('focus');
			}
			if (options.timeout) {
				setTimeout(close, options.timeout);
			}
			return $toast;
		}

		app.prefersReducedMotion = prefersReducedMotion;
		app.setStatus = setStatus;
		app.markDirty = markDirty;
		app.clearDirty = clearDirty;
		app.scrollToElement = scrollToElement;
		app.showToast = showToast;
	});
})();
