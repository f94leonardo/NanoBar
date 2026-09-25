=== NanoBar Compact Admin Toolbar ===
Contributors: f94leonardo
Tags: admin bar, toolbar, admin, dashboard, quick links
Requires at least: 6.8
Tested up to: 7.1
Requires PHP: 8.0
Stable tag: 1.0.0
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

Replaces WordPress's classic admin bar, on the frontend, with a compact, role-aware floating panel.

== Description ==

NanoBar replaces WordPress's classic admin bar, on the frontend, with a compact, role-aware floating panel. Configuration lives in a single global settings page: **Settings → NanoBar**.

= Features =

* **Floating toggle button** — a small circular button, positioned in a corner of the viewport, that opens a compact quick-access menu.
* **Role-aware** — choose which roles (all except Subscriber) see NanoBar on the frontend.
* **Configurable position** — left, center, or right, on the top or bottom edge (center is only available on the bottom edge).
* **Configurable appearance** — toggle size (32–72px, or 2–4.5rem) and background color (with quick swatches), with the icon color automatically adjusted for contrast.
* **Per-item phone visibility** — choose which panel items also appear in the compact bottom bar on phones (≤580px), for the built-in items and for each quick link.
* **Icons-only mode** — hides labels for a more compact panel.
* **Automatic phone tab bar** — on narrow screens (≤580px) the floating toggle is replaced by an always-visible bottom tab bar (icon + label per item).
* **Context-aware "Edit" link** — links straight to editing whatever is currently being viewed: a post/page, a taxonomy term, an author archive, a date archive, or a post type archive.
* **Site Editor deep link** — on block themes, opens the Site Editor directly on the block template that is rendering the current page; on classic themes, links to Appearance → Themes.
* **Quick "New" menu** — create a new post, page, media item, or any public custom post type the current user can create.
* **Dashboard submenu** — quick links to Media, Posts, Pages, Plugins, Settings (and Contact, if Contact Form 7 is active), shown to administrators.
* **Cache plugin support** — a "Cache" button that clears the active cache plugin (WP Rocket, W3 Total Cache, WP Super Cache, LiteSpeed Cache, WP Fastest Cache, FastCache) or, for others, links to its settings screen.
* **Elementor integration** — when Elementor is active and applies to the current view, "Edit site" links straight into Elementor's own editor or Theme Builder instead of the native Site Editor.
* **Command palette** — press Cmd/Ctrl+K to quickly jump to anything already in the panel, plus a few common wp-admin destinations.
* **Custom quick links** — add your own shortcuts to the panel (label, URL, icon picked from every WordPress dashicon, optional per-role visibility, optional mobile visibility); links are restricted to this site, and a pasted absolute URL is automatically cleaned up to a relative one.
* **Color scheme** — Auto (follows the visitor's system), Light, or Dark for the panel's dropdown menu.
* **Menu items toggle** — choose exactly which top-level items appear in the panel, independent of role/permission checks.
* **Query Monitor support** — keeps Query Monitor's toggle working even with the native admin bar hidden.
* **Settings backup** — export your settings as a JSON file and import them back (or on another site) from Settings → NanoBar → Backup; imports are validated exactly like a normal save.
* **Log out** — always available, redirecting back to the current page by default.
* **Translation-ready** — ships with an Italian (it_IT) translation.

NanoBar does not collect, transmit, or store any personal data, does not call any external/third-party service, and adds no tracking of any kind. It only stores its own settings (position, size, color, enabled roles, and similar preferences) in a single row of the WordPress options table, which is removed automatically on uninstall.

= Source code =

The compiled stylesheets in `assets/css/` are built from the SCSS files in `assets/scss/` (included in this plugin) with `npm run build`; the full development repository is at https://github.com/f94leonardo/NanoBar.

= Filters =

* `nanobar_options` — filter the resolved options array.
* `nanobar_logout_redirect` — filter the URL the user is sent to after logging out from NanoBar (defaults to the current page).
* `nanobar_cache_plugins` — add, remove, or correct entries in the registry of cache plugins the "Cache" button knows how to detect and purge.
* `nanobar_elementor_post_types` — filter the list of post type slugs treated as belonging to Elementor, hidden from the "New" menu when Elementor integration is off.
* `nanobar_command_palette_items` — filter the registry of extra command palette destinations (profile, comments, users, themes, tools by default).

== Installation ==

1. In your WordPress admin, go to **Plugins → Add New**, search for "NanoBar", and click **Install Now**, then **Activate**. Alternatively, upload the plugin ZIP via **Plugins → Add New → Upload Plugin**, or copy the `nanobar` folder into `wp-content/plugins/`.
2. Activate **NanoBar Compact Admin Toolbar** from the Plugins screen.
3. Go to **Settings → NanoBar** to configure it.

== Frequently Asked Questions ==

= Does NanoBar remove the native admin bar entirely? =

It hides it visually on the frontend for the roles you enable it for, but keeps it in the page so integrations that depend on it (such as Query Monitor) keep working. It has no effect in wp-admin.

= Can I choose who sees NanoBar? =

Yes. Go to Settings → NanoBar and choose which roles see the panel (every role except Subscriber is available).

= Does NanoBar send any data outside my site? =

No. It has no external API calls, no tracking, and no analytics. It only reads and writes its own settings in your site's database.

= What happens to NanoBar's settings if I uninstall the plugin? =

Its single settings option is deleted automatically when the plugin is uninstalled from the Plugins screen.

== Screenshots ==

1. The floating toggle button on the frontend, opening the quick-access menu.
2. The Settings → NanoBar configuration page, with the live position/size/color preview.
3. The automatic bottom tab bar shown on narrow (phone-width) screens.

== Changelog ==

= 1.0.0 =
* Changed: readme, package and translation housekeeping ahead of the WordPress.org submission, after a Plugin Check run. The release ZIP no longer contains hidden or development-only files and now includes the SCSS sources of the minified CSS.
* Changed: importing a backup file reads the upload more defensively (input is unslashed and sanitized); no change in behavior.
* Fixed: quick link URLs without a leading `/`, `?` or `#` (e.g. `contact/`) are now flagged in the form instead of being silently dropped on save.
* Fixed: the icon picker no longer offers `dashicons-before`, which is not an icon.
* Fixed: the Cache buttons now work for administrators who turned off the toolbar in their profile.
* Fixed: the command palette runs "Dashboard" and "Cache" actions on small screens and in icons-only mode, lists each destination once, and is better exposed to screen readers (accessible name, active option, focus kept inside); Cmd/Ctrl+K is no longer captured while typing in another field.
* Fixed: arrow keys and Escape now work on the phone tab bar.
* Fixed: logging out keeps the query string of the current page; an oversized backup file gets the right error, and a backup missing some keys no longer turns those settings off.
* Fixed: Cmd/Ctrl+S error on browser autofill events, and a stale "Undo" toast after saving.
* Removed: the manual `load_plugin_textdomain()` call. Translations of plugins hosted on WordPress.org are loaded by WordPress itself, so the bundled translations in `languages/` are no longer loaded on manual installs outside the directory.

= 0.0.5 =
* Changed: the "Select all" checkbox on the "Enabled roles" and "Panel items" cards is back to the two "Select all" / "Select none" buttons.
* Changed: Settings → NanoBar → Backup is now two side-by-side cards, Export and Import. Import has a drop zone that checks the chosen file before sending it, keeps the Import button disabled until the file is a valid NanoBar export, and asks for confirmation before replacing your settings. Without JavaScript it stays a plain file input.

= 0.0.4 =
* Fixed: after saving the settings page in place, the "Enabled roles" checkboxes could show the wrong state (e.g. Administrator unchecked) even though the saved settings were correct; a second save would then have stored it. The form now shows exactly what was saved.
* Changed: internal refactoring of the settings page (PHP markup and JavaScript split into smaller parts) — no visible change.

= 0.0.3 =
* Security: the command palette extra items are now encoded so a label or URL coming from the `nanobar_command_palette_items` filter cannot break out of its `<script>` tag (XSS), and `javascript:` URLs are no longer followed.
* Security: cache plugin admin bar node ids (`nanobar_cache_plugins` filter) are restricted to valid DOM id characters, and option values and filter results of an unexpected type are handled instead of causing errors.
* New: export and import your settings as a JSON file (Settings → NanoBar → Backup). Imported files are validated exactly like a normal save.
* New: full keyboard support on the panel — arrow keys move through the entries, Right/Left open and close a submenu, Escape closes the submenu first and then the menu; transitions are turned off when the visitor prefers reduced motion.
* New: a "Unit" setting for the toggle size: `px` (32–72) or `rem` (2–4.5), with a slider next to the number field.
* New: every panel item has a phone button to hide it from the compact tab bar on phones; the item stays reachable from the command palette.
* New: quick links can be reordered by drag and drop, and the background color picker shows a swatch, the hex code and a quick palette.
* Changed: the settings page has a new layout — sticky header with Save / Restore buttons, section navigation and an unsaved-changes indicator, an "Appearance" card split into "Icon" and "Panel" sub-cards, a live preview of the open panel with a Desktop / Mobile switch, a position picker directly on the preview, and "Detected / Not detected" badges for the cache plugin, Query Monitor and Elementor.
* Changed: saving the settings page happens in place (no reload) and confirmations, including "Restore defaults" with an undo, are shown as toasts instead of browser dialogs. Cmd/Ctrl+S saves.
* Changed: in icons-only mode the panel and its submenus are sized by their icons alone, and the Dashboard/New/Cache tiles open their submenu directly (no arrow), with Dashboard listed inside its submenu. The Cache item now uses a database icon.
* Changed: accessibility improvements on the settings page (icon picker keyboard navigation, live regions, notices that no longer disappear on their own, reduced motion).
* Fixed: the toggle button could turn into an ellipse with themes that style bare buttons.
* Fixed: after saving over AJAX the form now shows the values the server actually stored (e.g. a size that was clamped), and undoing "Restore defaults" after a save no longer brings back stale values.
* Fixed: a very large icon size in px was stored as the minimum instead of the maximum.
* Fixed: submenus could extend past the top or bottom of a short viewport.
* Fixed: on multisite, uninstalling the plugin network-wide now cleans up every site.
* Fixed: on the settings page, the sticky header could jitter while scrolling, checkboxes showed two check marks, and Cmd/Ctrl+S saved while the icon picker was open.
* Fixed: if `mobile_items` is missing entirely (e.g. after a WP-CLI update), items stay visible on the phone bar instead of all being hidden.

= 0.0.2 =
* New: "Menu items" card on the settings page to choose exactly which top-level items appear in the panel (Dashboard, Edit, Edit site, New, Query Monitor, Cache, Log out). Disabled items stay hidden; enabled ones still respect capabilities, page context and active third-party plugins.
* New: Elementor integration — with "Prefer Elementor when available" on, "Edit site" links straight to Elementor (the current content if built with Elementor, or the Elementor Pro Theme Builder for the template in use).
* New: a "Cache" panel item, visible to administrators only, with a purge button and a submenu (settings, purge all, purge current page where the plugin supports it). Supports WP Rocket, W3 Total Cache, WP Super Cache, LiteSpeed Cache and WP Fastest Cache; FastCache by host.it is detected and links to its settings. Extend or correct it with the `nanobar_cache_plugins` filter.
* New: command palette (Cmd/Ctrl+K) to quickly jump to anything already in the panel, plus a few common wp-admin destinations. Extend it with the `nanobar_command_palette_items` filter.
* New: custom quick links — add up to 20 of your own shortcuts to the panel, restricted to this site (a pasted absolute URL is cleaned up automatically), with an optional per-role visibility restriction, an icon picker covering every dashicon, and per-link inline validation.
* New: each quick link can be set to stay hidden on the ≤580px phone tab bar independently of its role visibility; newly added links default to hidden there, already-saved links stay visible unless changed.
* New: panel color scheme setting (Auto/Light/Dark).
* New: bulk select for "Enabled roles" and a usage counter for quick links; unsaved-changes indicator, restore-defaults confirmation and a warning when leaving the page with unsaved changes.
* Changed: on new installs, "Force admin bar for Query Monitor", the "Query Monitor"/"Cache" panel items, and "Prefer Elementor when available" now default to off — already-configured sites are unaffected.
* Changed: on ≤666px viewports, the "Dashboard" panel item no longer navigates on tap — it opens its submenu instead (which now also lists "Dashboard" itself), avoiding accidental taps next to the small submenu-open arrow.
* Changed: settings page redesigned (larger text, roomier spacing, "Position" and "Appearance" merged, more comfortable layout at tablet widths) and the Italian translation regenerated.
* Fixed: a rare fatal error building the "New" menu when a third-party plugin registers a custom post type with a non-string `menu_icon`, or when a filter callback returns an unexpected type for `nanobar_elementor_post_types`.
* Fixed: the "New" submenu listed Elementor's internal post types even with "Prefer Elementor when available" off.
* Fixed: wrong admin bar nodes for LiteSpeed Cache, WP Fastest Cache and WP Super Cache, so the Cache button did not purge anything; corrected and verified on real installs.
* Fixed: the automatic phone tab bar's breakpoint was inconsistent between CSS and JavaScript.
* Fixed: a same-site quick link URL containing a doubled slash (e.g. `https://yoursite.example//other.example/x`) could be stored in a form that a browser would treat as pointing off-site once printed as a link. Such a link was never rendered, so it was not exploitable, but it was silently saved as unusable — now stored correctly.
* Fixed: the Cmd/Ctrl+K command palette could fail to visually close after pressing Escape or clicking outside it, blocking the page.
* Fixed: a malformed entry from a `nanobar_cache_plugins` filter callback could cause a fatal error on every frontend page load for users who can see the Cache item; such an entry is now skipped instead.
* Fixed: quick link icons and the "Menu items" visibility settings are re-validated when read, so a filtered or hand-edited options row can no longer produce an invalid state; a missing item in a partial `visible_items` array now uses that item's own default.
* Fixed: settings page layout issues at tablet and phone widths, where cards could become uncomfortably narrow or squeezed.

= 0.0.1 =
* Changed: lowered the minimum required PHP version from 8.4 to 8.0 (the actual minimum the code needs), for much broader hosting compatibility.
* Changed: removed the Composer runtime dependency: the plugin now ships its own lightweight autoloader and works immediately after activation, with no build step required.
* New: readme in the WordPress.org format, GPLv2 license file, and icon, banner and screenshots for the plugin directory.

= 0.0.0 =
* First release: floating role-aware panel replacing the classic admin bar on the frontend, with configurable position/size/color, context-aware Edit and Site Editor links, quick "New" and Dashboard submenus, Query Monitor support, and an automatic phone tab bar.
* Fixed: the panel did not open on click/tap because the script ran before the panel markup was printed.

== Upgrade Notice ==

= 1.0.0 =
Release prepared for WordPress.org. Translations are now loaded by WordPress itself. No configuration changes are needed.

= 0.0.5 =
Redesigned Backup (export/import) cards and the return of the Select all / Select none buttons. No configuration changes are needed.

= 0.0.4 =
Fixes the roles checkboxes showing a wrong state after saving the settings page. Recommended for everyone on 0.0.3.

= 0.0.3 =
Security hardening of the command palette and cache integration, settings export/import, keyboard navigation and several fixes to the icons-only mode and the settings page. No configuration changes are needed.

= 0.0.2 =
Command palette, custom quick links, cache plugin/Elementor support, a panel color scheme, and a redesigned settings page. A few settings default differently on new installs only; your existing configuration is unaffected.

= 0.0.1 =
Broader PHP compatibility (8.0+) and no more Composer dependency at runtime.

= 0.0.0 =
First release.
