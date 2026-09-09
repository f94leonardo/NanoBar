=== NanoBar Compact Admin Toolbar ===
Contributors: f94leonardo
Tags: admin bar, toolbar, admin, dashboard, block editor
Requires at least: 6.8
Tested up to: 7.1
Requires PHP: 8.0
Stable tag: 1.0.1
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

Replaces WordPress's classic admin bar, on the frontend, with a compact, role-aware floating panel.

== Description ==

NanoBar replaces WordPress's classic admin bar, on the frontend, with a compact, role-aware floating panel. Configuration lives in a single global settings page: **Settings → NanoBar**.

= Features =

* **Floating toggle button** — a small circular button, positioned in a corner of the viewport, that opens a compact quick-access menu.
* **Role-aware** — choose which roles (all except Subscriber) see NanoBar on the frontend.
* **Configurable position** — left, center, or right, on the top or bottom edge (center is only available on the bottom edge).
* **Configurable appearance** — toggle size (32–72px) and background color, with the icon color automatically adjusted for contrast.
* **Icons-only mode** — hides labels for a more compact panel.
* **Automatic phone tab bar** — on narrow screens (≤600px) the floating toggle is replaced by an always-visible bottom tab bar (icon + label per item).
* **Context-aware "Edit" link** — links straight to editing whatever is currently being viewed: a post/page, a taxonomy term, an author archive, a date archive, or a post type archive.
* **Site Editor deep link** — on block themes, opens the Site Editor directly on the block template that is rendering the current page; on classic themes, links to Appearance → Themes.
* **Quick "New" menu** — create a new post, page, media item, or any public custom post type the current user can create.
* **Dashboard submenu** — quick links to Media, Posts, Pages, Plugins, Settings (and Contact, if Contact Form 7 is active), shown to administrators.
* **Query Monitor support** — keeps Query Monitor's toggle working even with the native admin bar hidden.
* **Log out** — always available, redirecting back to the current page by default.
* **Translation-ready** — ships with an Italian (it_IT) translation.

NanoBar does not collect, transmit, or store any personal data, does not call any external/third-party service, and adds no tracking of any kind. It only stores its own settings (position, size, color, enabled roles, and similar preferences) in a single row of the WordPress options table, which is removed automatically on uninstall.

= Filters =

* `nanobar_options` — filter the resolved options array.
* `nanobar_logout_redirect` — filter the URL the user is sent to after logging out from NanoBar (defaults to the current page).

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

= 1.0.1 =
* Lowered the minimum required PHP version from 8.4 to 8.0 (the actual minimum the code needs), for much broader hosting compatibility.
* Removed the Composer runtime dependency: the plugin now ships its own lightweight autoloader and works immediately after activation, with no build step required.

= 1.0.0 =
* First public release: floating role-aware panel replacing the classic admin bar on the frontend, with configurable position/size/color, context-aware Edit and Site Editor links, quick "New" and Dashboard submenus, Query Monitor support, and an automatic phone tab bar.

== Upgrade Notice ==

= 1.0.1 =
Broader PHP compatibility (8.0+) and no more Composer dependency at runtime.

= 1.0.0 =
First public release.
