/**
 * Settings → NanoBar admin page — sticky header offsets, section navigation and dismissible notices.
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

		var prefersReducedMotion = app.prefersReducedMotion;
		// Keeps the sticky header correctly offset under the actual admin bar
		// height instead of a hardcoded guess, which would silently drift if
		// the bar's height changes (WP core tweak, responsive breakpoint, a
		// user with the bar hidden).
		function updateStickyOffsets() {
			var $adminBar = $('#wpadminbar');
			var adminBarHeight =
				$adminBar.length && 'fixed' === $adminBar.css('position')
					? $adminBar.outerHeight()
					: 0;
			document.documentElement.style.setProperty(
				'--nanobar-admin-bar-h',
				adminBarHeight + 'px'
			);
			// Admin bar + the sticky header's own height (it changes when it
			// compacts) + a gap: where anchor targets and the sticky preview
			// must sit to clear both.
			var $stickyHeader = $('.nanobar-header');
			if ($stickyHeader.length) {
				document.documentElement.style.setProperty(
					'--nanobar-sticky-top',
					adminBarHeight + $stickyHeader.outerHeight() + 16 + 'px'
				);
			}
		}

		// Toggles a compact ".is-stuck" style (see _header.scss: drops the
		// subtitle, shrinks the padding) once the sticky header actually
		// reaches the top of the viewport and stays there — not just "the
		// page has been scrolled some amount", which position: sticky's own
		// "top" offset already varies (admin bar height, viewport width). A
		// zero-height sentinel placed right before the header is the standard
		// way to detect this: position: sticky has no matching event or
		// pseudo-class of its own, but the moment the header is stuck is
		// exactly the moment scrolling has carried this sentinel out of view.
		var $header = $('.nanobar-header');
		if (window.ResizeObserver && $header.length) {
			new window.ResizeObserver(updateStickyOffsets).observe($header[0]);
		}

		// Section nav: highlight the link of the section currently under the
		// sticky header (cheap scroll handler, throttled to one frame).
		var $navLinks = $('.nanobar-nav a');
		var navTicking = false;

		function updateNavCurrent() {
			navTicking = false;
			// A section counts as current once its top is within reach below
			// the sticky header (a generous band, so a card that is clearly
			// on screen is highlighted before its top reaches the header).
			var offset = $header[0].getBoundingClientRect().bottom + 120;
			var currentId = null;
			$navLinks.each(function () {
				var target = document.getElementById(
					$(this).attr('href').slice(1)
				);
				if (target && target.getBoundingClientRect().top <= offset) {
					currentId = target.id;
				}
			});
			$navLinks.each(function () {
				var isCurrent = $(this).attr('href') === '#' + currentId;
				$(this).toggleClass('is-current', isCurrent);
				if (isCurrent) {
					$(this).attr('aria-current', 'true');
					// The nav scrolls horizontally when it doesn't fit:
					// keep the current link in view (without moving the page).
					var nav = this.parentNode;
					if (nav.scrollWidth > nav.clientWidth) {
						nav.scrollLeft =
							this.offsetLeft -
							nav.offsetLeft -
							(nav.clientWidth - this.offsetWidth) / 2;
					}
				} else {
					$(this).removeAttr('aria-current');
				}
			});
		}

		if ($navLinks.length) {
			$(window).on('scroll', function () {
				if (!navTicking) {
					navTicking = true;
					window.requestAnimationFrame(updateNavCurrent);
				}
			});
		}

		var headerSentinel = document.getElementById('nanobar-header-sentinel');
		if (headerSentinel && window.IntersectionObserver) {
			var stuckObserver = new IntersectionObserver(function (entries) {
				$header.toggleClass('is-stuck', !entries[0].isIntersecting);
				updateStickyOffsets();
			});
			stuckObserver.observe(headerSentinel);
		}

		$(window).on('resize', updateStickyOffsets);
		updateStickyOffsets();
		if ($navLinks.length) {
			updateNavCurrent();
		}

		// Server-rendered settings notices (a non-AJAX save, or the errors
		// WordPress prints after one): give them an icon and a close button.
		// Success is announced politely, errors/warnings assertively, and none
		// of them disappear on their own.
		$('.nanobar-notices-container .notice').each(function () {
			var $notice = $(this);

			$notice.attr(
				'role',
				$notice.hasClass('notice-success') ? 'status' : 'alert'
			);

			if (!$notice.find('.notice-icon').length) {
				$notice.prepend('<span class="notice-icon"></span>');
			}

			if (!$notice.find('.notice-dismiss').length) {
				$notice.append(
					$(
						'<button type="button" class="notice-dismiss">×</button>'
					).attr('aria-label', i18n.closeNotice || '')
				);
			}

			$notice.on('click.dismiss', '.notice-dismiss', function (e) {
				e.preventDefault();
				$notice.addClass('is-dismissing');
				setTimeout(
					function () {
						$notice.fadeOut(function () {
							$notice.remove();
						});
					},
					prefersReducedMotion.matches ? 0 : 400
				);
			});
		});
	});
})();
