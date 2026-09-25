/**
 * Frontend behavior for the NanoBar floating panel: open/close, submenus,
 * persisted open state, automatic bottom tab-bar mode on phones, the Query
 * Monitor/cache button proxies, and the Cmd/Ctrl+K command palette.
 *
 * @package NanoBar
 */

(function () {
	'use strict';

	/**
	 * Builds and drives the Cmd/Ctrl+K command palette. Its command list is
	 * read straight out of the panel's own already-rendered, already
	 * capability-gated markup (so it can never surface a destination the
	 * current user isn't actually allowed to reach), plus a handful of extra
	 * destinations printed separately as JSON — see Frontend\Commands and
	 * Frontend\Renderer::render_panel(). Only called when the
	 * "command_palette_enabled" setting is on (see init() below).
	 *
	 * @param {HTMLElement} wrap The #nanobar wrapper element.
	 */
	function initCommandPalette(wrap) {
		var l10n = window.nanobarL10n || {};
		var palette = null;
		var listEl = null;
		var inputEl = null;
		var emptyEl = null;
		var commands = [];
		var filtered = [];
		var activeIndex = -1;
		var lastFocused = null;

		// Only http(s) (or relative) destinations: the extra commands come
		// from a filterable registry, so never navigate to e.g. javascript:.
		function isSafeUrl(url) {
			try {
				var parsed = new URL(url, window.location.href);
				return (
					'http:' === parsed.protocol || 'https:' === parsed.protocol
				);
			} catch {
				return false;
			}
		}

		function getLabel(el) {
			var labelEl = el.querySelector(
				'.nanobar__item-label, .nanobar__submenu-label'
			);
			if (labelEl && labelEl.textContent.trim()) {
				return labelEl.textContent.trim();
			}
			if (el.title) {
				return el.title;
			}
			return el.textContent.trim();
		}

		function getIcon(el) {
			var iconEl = el.querySelector('.dashicons');
			if (!iconEl) {
				return '';
			}
			var match = Array.prototype.filter.call(
				iconEl.classList,
				function (cls) {
					return /^dashicons-/.test(cls);
				}
			);
			return match.length ? match[0] : '';
		}

		function collectCommands() {
			var items = [];
			var seen = {};

			wrap.querySelectorAll(
				'a.nanobar__item[href], .nanobar__submenu a[href], [data-proxy-node]'
			).forEach(function (el) {
				// Proxy buttons whose target admin bar node wasn't found hide
				// themselves (see the proxy-node handling below) — skip those
				// rather than offering a dead command.
				if (el.hidden) {
					return;
				}
				// The same destination can appear twice (e.g. "Dashboard" as
				// tile and as submenu entry, or a cache action as main button
				// and submenu entry): list it once.
				var proxyId = el.getAttribute('data-proxy-node');
				var dedupeKey = proxyId ? 'node:' + proxyId : 'url:' + el.href;
				if (seen[dedupeKey]) {
					return;
				}
				seen[dedupeKey] = true;
				items.push({
					label: getLabel(el),
					icon: getIcon(el),
					run: function () {
						if (proxyId) {
							// Bypass the tile's own click handler, which may
							// only toggle its submenu (narrow / icons-only).
							el.dispatchEvent(
								new window.CustomEvent('nanobar:run')
							);
						} else if (el.href && isSafeUrl(el.href)) {
							window.location.href = el.href;
						} else {
							el.click();
						}
					},
				});
			});

			var extraScript = document.getElementById('nanobar-commands-extra');
			if (extraScript) {
				try {
					var extra = JSON.parse(extraScript.textContent);
					extra.forEach(function (item) {
						items.push({
							label: item.label,
							icon: item.icon,
							run: function () {
								if (isSafeUrl(item.url)) {
									window.location.href = item.url;
								}
							},
						});
					});
				} catch {
					// Malformed/missing JSON: extra commands are optional, skip them.
				}
			}

			return items;
		}

		function buildPalette() {
			palette = document.createElement('div');
			palette.className = 'nanobar-command-palette';
			palette.hidden = true;
			// Translated strings (commandDialogLabel/commandPlaceholder) are
			// deliberately not concatenated into this markup: they're set via
			// setAttribute()/the .placeholder property below instead, so a
			// translation containing a stray '"' can't corrupt the markup.
			palette.innerHTML =
				'<div class="nanobar-command-palette__backdrop"></div>' +
				'<div class="nanobar-command-palette__dialog" role="dialog" aria-modal="true">' +
				'<div class="nanobar-command-palette__search">' +
				'<span class="dashicons dashicons-search" aria-hidden="true"></span>' +
				'<input type="text" class="nanobar-command-palette__input" autocomplete="off" role="combobox" aria-expanded="true" aria-autocomplete="list" aria-controls="nanobar-command-palette-list" />' +
				'</div>' +
				'<ul class="nanobar-command-palette__list" id="nanobar-command-palette-list" role="listbox"></ul>' +
				'<p class="nanobar-command-palette__empty" hidden></p>' +
				'</div>';
			document.body.appendChild(palette);

			listEl = palette.querySelector('.nanobar-command-palette__list');
			inputEl = palette.querySelector('.nanobar-command-palette__input');
			emptyEl = palette.querySelector('.nanobar-command-palette__empty');

			palette
				.querySelector('.nanobar-command-palette__dialog')
				.setAttribute(
					'aria-label',
					l10n.commandDialogLabel || 'Quick commands'
				);
			inputEl.placeholder = l10n.commandPlaceholder || '';
			inputEl.setAttribute(
				'aria-label',
				l10n.commandPlaceholder ||
					l10n.commandDialogLabel ||
					'Quick commands'
			);

			palette
				.querySelector('.nanobar-command-palette__backdrop')
				.addEventListener('click', closePalette);

			inputEl.addEventListener('input', function () {
				renderList(inputEl.value);
			});

			inputEl.addEventListener('keydown', function (event) {
				if ('Escape' === event.key) {
					event.preventDefault();
					event.stopPropagation();
					closePalette();
				} else if ('ArrowDown' === event.key) {
					event.preventDefault();
					moveActive(1);
				} else if ('ArrowUp' === event.key) {
					event.preventDefault();
					moveActive(-1);
				} else if ('Enter' === event.key) {
					event.preventDefault();
					runActive();
				} else if ('Tab' === event.key) {
					// Focus trap: the palette is modal, keep focus on its input.
					event.preventDefault();
				}
			});
		}

		function moveActive(delta) {
			if (!filtered.length) {
				return;
			}
			activeIndex =
				(activeIndex + delta + filtered.length) % filtered.length;
			updateActive();
		}

		function updateActive() {
			var rows = listEl.querySelectorAll(
				'.nanobar-command-palette__item'
			);
			rows.forEach(function (row, index) {
				row.classList.toggle('is-active', index === activeIndex);
				row.setAttribute(
					'aria-selected',
					index === activeIndex ? 'true' : 'false'
				);
			});
			if (rows[activeIndex]) {
				inputEl.setAttribute(
					'aria-activedescendant',
					rows[activeIndex].id
				);
				rows[activeIndex].scrollIntoView({ block: 'nearest' });
			} else {
				inputEl.removeAttribute('aria-activedescendant');
			}
		}

		function runActive() {
			if (filtered[activeIndex]) {
				var cmd = filtered[activeIndex];
				closePalette();
				cmd.run();
			}
		}

		function renderList(query) {
			var needle = query.trim().toLowerCase();
			filtered = !needle
				? commands
				: commands.filter(function (cmd) {
						return cmd.label.toLowerCase().indexOf(needle) !== -1;
					});

			listEl.innerHTML = '';
			activeIndex = filtered.length ? 0 : -1;

			filtered.forEach(function (cmd, index) {
				var row = document.createElement('li');
				row.className =
					'nanobar-command-palette__item' +
					(0 === index ? ' is-active' : '');
				row.setAttribute('role', 'option');
				row.id = 'nanobar-command-' + index;
				row.setAttribute(
					'aria-selected',
					0 === index ? 'true' : 'false'
				);
				if (cmd.icon) {
					var iconSpan = document.createElement('span');
					iconSpan.className = 'dashicons ' + cmd.icon;
					iconSpan.setAttribute('aria-hidden', 'true');
					row.appendChild(iconSpan);
				}
				var labelSpan = document.createElement('span');
				labelSpan.className = 'nanobar-command-palette__label';
				labelSpan.textContent = cmd.label;
				row.appendChild(labelSpan);

				row.addEventListener('click', function () {
					closePalette();
					cmd.run();
				});
				row.addEventListener('mouseenter', function () {
					activeIndex = index;
					updateActive();
				});
				listEl.appendChild(row);
			});

			if (filtered.length) {
				inputEl.setAttribute(
					'aria-activedescendant',
					'nanobar-command-0'
				);
			} else {
				inputEl.removeAttribute('aria-activedescendant');
			}
			emptyEl.hidden = filtered.length > 0;
			if (!filtered.length) {
				emptyEl.textContent = l10n.commandEmpty || '';
			}
		}

		function openPalette() {
			if (!palette) {
				buildPalette();
			}
			commands = collectCommands();
			lastFocused = document.activeElement;
			palette.setAttribute(
				'data-scheme',
				wrap.getAttribute('data-scheme') || 'auto'
			);
			palette.hidden = false;
			inputEl.value = '';
			renderList('');
			inputEl.focus();
		}

		function closePalette() {
			if (!palette || palette.hidden) {
				return;
			}
			palette.hidden = true;
			if (lastFocused && lastFocused.focus) {
				lastFocused.focus();
			}
		}

		document.addEventListener('keydown', function (event) {
			var key = event.key ? event.key.toLowerCase() : '';
			if ('k' !== key || !(event.metaKey || event.ctrlKey)) {
				return;
			}
			var paletteOpen = palette && !palette.hidden;
			// Leave the shortcut alone while typing in another field.
			var target = event.target;
			if (
				!paletteOpen &&
				target &&
				target.closest &&
				target.closest(
					'input, textarea, select, [contenteditable]:not([contenteditable="false"])'
				)
			) {
				return;
			}
			event.preventDefault();
			if (paletteOpen) {
				closePalette();
			} else {
				openPalette();
			}
		});
	}

	function init() {
		var wrap = document.getElementById('nanobar');
		if (!wrap) {
			return;
		}

		var toggle = wrap.querySelector('.nanobar__toggle');
		var groups = wrap.querySelectorAll('.nanobar__item-group');
		var STORAGE_KEY = 'nanobar_open';

		function setMenuState(open) {
			wrap.classList.toggle('is-open', open);
			toggle.setAttribute('aria-expanded', open ? 'true' : 'false');
			try {
				localStorage.setItem(STORAGE_KEY, open ? '1' : '0');
			} catch {
				// Persistence not available: not a blocking error.
			}
		}

		function closeSubmenus(except) {
			groups.forEach(function (group) {
				if (group === except) {
					return;
				}
				group.classList.remove('is-open');
				var button = group.querySelector(
					':scope > .nanobar__submenu-toggle'
				);
				var trigger = group.querySelector(
					':scope > .nanobar__item--submenu-trigger'
				);
				var submenu = group.querySelector(':scope > .nanobar__submenu');
				if (button) {
					button.setAttribute('aria-expanded', 'false');
				}
				if (trigger) {
					trigger.setAttribute('aria-expanded', 'false');
				}
				if (submenu) {
					submenu.setAttribute('aria-hidden', 'true');
				}
			});
		}

		function toggleSubmenu(group) {
			var open = !group.classList.contains('is-open');
			closeSubmenus(group);
			group.classList.toggle('is-open', open);
			var button = group.querySelector(
				':scope > .nanobar__submenu-toggle'
			);
			var trigger = group.querySelector(
				':scope > .nanobar__item--submenu-trigger'
			);
			var submenu = group.querySelector(':scope > .nanobar__submenu');
			if (button) {
				button.setAttribute('aria-expanded', open ? 'true' : 'false');
			}
			if (trigger) {
				trigger.setAttribute('aria-expanded', open ? 'true' : 'false');
			}
			var tile = group.querySelector(
				':scope > a.nanobar__item--split[href]'
			);
			if (tile) {
				if (tileOpensSubmenu()) {
					tile.setAttribute('aria-expanded', open ? 'true' : 'false');
				} else {
					tile.removeAttribute('aria-expanded');
				}
			}
			if (submenu) {
				submenu.setAttribute('aria-hidden', open ? 'false' : 'true');
				if (open) {
					keepSubmenuInViewport(submenu);
				}
			}
		}

		// The flyout is anchored to its group (growing downward from it in the
		// top position, upward in the bottom one); on a short viewport that
		// can push it off screen, so nudge it back inside with a transform
		// (works for either anchor, unlike overriding top/bottom). Not needed below 666px, where the
		// submenu is laid out inline instead of as a flyout.
		function keepSubmenuInViewport(submenu) {
			submenu.style.transform = '';
			if (window.matchMedia('(max-width: 666px)').matches) {
				return;
			}
			var margin = 8;
			var rect = submenu.getBoundingClientRect();
			var shift = 0;
			if (rect.bottom > window.innerHeight - margin) {
				shift = window.innerHeight - margin - rect.bottom;
			}
			if (rect.top + shift < margin) {
				shift = margin - rect.top;
			}
			if (0 !== shift) {
				submenu.style.transform = 'translateY(' + shift + 'px)';
			}
		}

		// Below 666px — the same breakpoint the dropdown's own layout switches
		// at (see the "@media (max-width: 666px)" block in _panel.scss) — a
		// splitLink tile (below) opens its submenu on tap instead of
		// navigating immediately. Just a live width check at click time, not
		// a persisted class: nothing else needs to react to it changing.
		var narrowDropdownQuery = window.matchMedia('(max-width: 666px)');

		// True when a splitLink tile must open its submenu rather than
		// navigate: narrow screens, or icons-only mode (no chevron there).
		function tileOpensSubmenu() {
			return (
				narrowDropdownQuery.matches ||
				wrap.classList.contains('nanobar--icons-only')
			);
		}

		groups.forEach(function (group) {
			var button = group.querySelector(
				':scope > .nanobar__submenu-toggle'
			);
			var trigger = group.querySelector(
				':scope > .nanobar__item--submenu-trigger'
			);
			var splitLink = group.querySelector(
				':scope > a.nanobar__item--split[href]'
			);
			if (button) {
				button.addEventListener('click', function (event) {
					event.preventDefault();
					event.stopPropagation();
					toggleSubmenu(group);
				});
			}
			if (trigger) {
				trigger.addEventListener('click', function (event) {
					event.preventDefault();
					event.stopPropagation();
					toggleSubmenu(group);
				});
			}
			if (splitLink) {
				splitLink.addEventListener('click', function (event) {
					if (tileOpensSubmenu()) {
						event.preventDefault();
						event.stopPropagation();
						toggleSubmenu(group);
					}
				});
			}
		});

		toggle.addEventListener('click', function (event) {
			event.stopPropagation();
			if (wrap.classList.contains('is-open')) {
				setMenuState(false);
				closeSubmenus();
			} else {
				setMenuState(true);
			}
		});

		document.addEventListener('click', function (event) {
			if (!wrap.contains(event.target)) {
				setMenuState(false);
				closeSubmenus();
			}
		});

		// Keyboard support: Escape closes the open submenu first (returning
		// focus to its tile), then the whole menu; arrow keys move through the
		// entries (Up/Down/Home/End), Right opens a group's submenu and Left
		// closes it — the menu behaves like a regular menu widget.
		function isShown(el) {
			return !!(el.offsetWidth || el.offsetHeight);
		}

		function topLevelEntries() {
			var entries = [];
			wrap.querySelectorAll('.nanobar__menu > *').forEach(
				function (child) {
					var entry = child.classList.contains('nanobar__item-group')
						? child.querySelector(':scope > .nanobar__item')
						: child;
					if (entry && isShown(entry)) {
						entries.push(entry);
					}
				}
			);
			return entries;
		}

		function submenuEntries(group) {
			return Array.prototype.filter.call(
				group.querySelectorAll(
					':scope > .nanobar__submenu a, :scope > .nanobar__submenu button'
				),
				isShown
			);
		}

		function openGroupFocusFirst(group) {
			if (!group.classList.contains('is-open')) {
				toggleSubmenu(group);
			}
			var entries = submenuEntries(group);
			if (entries.length) {
				entries[0].focus();
			}
		}

		document.addEventListener('keydown', function (event) {
			var key = event.key;
			var active = document.activeElement;
			var openGroup = wrap.querySelector('.nanobar__item-group.is-open');

			// The phone tab bar is always visible, whatever the open state.
			var tabbar = wrap.classList.contains('nanobar--tabbar-auto');
			var menuActive = tabbar || wrap.classList.contains('is-open');

			if ('Escape' === key) {
				if (!menuActive) {
					return;
				}
				if (openGroup) {
					var tile = openGroup.querySelector(
						':scope > .nanobar__item'
					);
					closeSubmenus();
					if (tile && wrap.contains(active)) {
						tile.focus();
					}
					return;
				}
				if (tabbar) {
					return;
				}
				setMenuState(false);
				toggle.focus();
				return;
			}

			if (!wrap.contains(active)) {
				return;
			}

			if (active === toggle && 'ArrowDown' === key) {
				event.preventDefault();
				setMenuState(true);
				var first = topLevelEntries()[0];
				if (first) {
					first.focus();
				}
				return;
			}

			var group = active.closest('.nanobar__item-group');
			var inSubmenu = !!active.closest('.nanobar__submenu');
			var list = inSubmenu ? submenuEntries(group) : topLevelEntries();
			var index = list.indexOf(active);

			if (-1 === index || !menuActive) {
				return;
			}

			if ('ArrowDown' === key) {
				list[(index + 1) % list.length].focus();
			} else if ('ArrowUp' === key) {
				list[(index - 1 + list.length) % list.length].focus();
			} else if ('Home' === key) {
				list[0].focus();
			} else if ('End' === key) {
				list[list.length - 1].focus();
			} else if ('ArrowRight' === key && group && !inSubmenu) {
				openGroupFocusFirst(group);
			} else if ('ArrowLeft' === key && inSubmenu) {
				var owner = group.querySelector(':scope > .nanobar__item');
				closeSubmenus();
				if (owner) {
					owner.focus();
				}
			} else {
				return;
			}
			event.preventDefault();
		});

		try {
			if ('1' === localStorage.getItem(STORAGE_KEY)) {
				setMenuState(true);
			}
		} catch {
			// Persistence not available: starts closed.
		}

		// Phones get an always-visible bottom tab bar instead of the toggle +
		// dropdown menu — see the "--tabbar-auto" modifier in the SCSS. The
		// breakpoint matches the plugin's own existing phone-tweaks media
		// query (assets/scss/components/_panel.scss).
		var mobileQuery = window.matchMedia('(max-width: 580px)');
		function updateAutoTabbar(mediaQuery) {
			wrap.classList.toggle('nanobar--tabbar-auto', mediaQuery.matches);
		}
		updateAutoTabbar(mobileQuery);
		if (mobileQuery.addEventListener) {
			mobileQuery.addEventListener('change', updateAutoTabbar);
		} else if (mobileQuery.addListener) {
			mobileQuery.addListener(updateAutoTabbar);
		}

		// Below 480px the tab-bar drops its text labels too (icons only) —
		// see the "--tabbar-compact" modifier in the SCSS.
		var compactQuery = window.matchMedia('(max-width: 480px)');
		function updateTabbarCompact(mediaQuery) {
			wrap.classList.toggle(
				'nanobar--tabbar-compact',
				mediaQuery.matches
			);
		}
		updateTabbarCompact(compactQuery);
		if (compactQuery.addEventListener) {
			compactQuery.addEventListener('change', updateTabbarCompact);
		} else if (compactQuery.addListener) {
			compactQuery.addListener(updateTabbarCompact);
		}

		// Buttons that work by proxying a click to a specific node inside the
		// real (visually hidden) native admin bar, instead of having a href of
		// their own — used for the Query Monitor and Cache items, since both
		// just want to trigger whatever click handler the actual plugin
		// already wired to its own admin bar node.
		wrap.querySelectorAll('[data-proxy-node]').forEach(function (proxyBtn) {
			var nodeId = proxyBtn.getAttribute('data-proxy-node');
			var findProxyNode = function () {
				// getElementById, not a '#id' selector: the id comes from a
				// filterable registry and may not be a valid CSS identifier.
				var nodeEl = document.getElementById(nodeId);
				return nodeEl
					? nodeEl.querySelector(':scope > a') ||
							nodeEl.querySelector('a')
					: null;
			};

			// init() itself only runs once parsing is done (see bottom of file), so
			// the native admin bar (wp_admin_bar_render, wp_footer priority 1000,
			// after this panel's 999) is already in the DOM by now: if the target
			// node is nowhere to be found, hide our button instead of leaving a
			// dead control in the menu.
			if (!findProxyNode()) {
				proxyBtn.hidden = true;
				return;
			}

			function runProxy() {
				var targetNode = findProxyNode();
				if (targetNode) {
					targetNode.click();
					return;
				}
				proxyBtn.hidden = true;
				if (window.console && window.console.warn) {
					console.warn(
						'NanoBar: admin bar node "#' +
							nodeId +
							'" not found. Is it being removed by the theme or another plugin?'
					);
				}
			}

			// Fired by the command palette to run the action itself, even when
			// a click would only toggle the tile's submenu.
			proxyBtn.addEventListener('nanobar:run', runProxy);

			proxyBtn.addEventListener('click', function () {
				// Icons-only mode has no chevron, so a proxy tile that heads a
				// submenu (Cache) opens that submenu instead of firing its own
				// action, which the submenu offers as entries anyway.
				var group = proxyBtn.closest('.nanobar__item-group');
				if (
					group &&
					proxyBtn.classList.contains('nanobar__item--split') &&
					wrap.classList.contains('nanobar--icons-only')
				) {
					toggleSubmenu(group);
					proxyBtn.setAttribute(
						'aria-expanded',
						group.classList.contains('is-open') ? 'true' : 'false'
					);
					return;
				}
				runProxy();
			});
		});

		if ('1' === wrap.dataset.commandPalette) {
			initCommandPalette(wrap);
		}
	}

	// The panel markup is printed on wp_footer at priority 999 — this script
	// (enqueued in the footer too, but printed by core's default priority-20
	// wp_print_footer_scripts) can run before that markup exists in the DOM,
	// so #nanobar wouldn't be found yet. Defer init() until parsing is done.
	if ('loading' === document.readyState) {
		document.addEventListener('DOMContentLoaded', init);
	} else {
		init();
	}
})();
