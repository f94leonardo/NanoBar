# NanoBar

**NanoBar** is a WordPress mu-plugin that replaces the classic admin bar on the frontend with a compact, role-aware floating panel. Configuration lives in a single global settings page: **Settings → NanoBar**.

## Features

- **Floating toggle button** — a small circular button, positioned in a corner of the viewport, that opens a compact quick-access menu.
- **Role-aware** — choose which roles (all except Subscriber) see NanoBar on the frontend.
- **Configurable position** — left, center, or right, on the top or bottom edge (center is only available on the bottom edge).
- **Configurable appearance** — toggle size (32–72px) and background color, with the icon color automatically adjusted for contrast.
- **Icons-only mode** — hides labels for a more compact panel; narrow screens (≤400px) switch to icons-only automatically.
- **Context-aware "Edit" link** — links straight to editing whatever is currently being viewed: a post/page, a taxonomy term, an author archive, a date archive, or a post type archive.
- **Site Editor deep link** — on block themes, opens the Site Editor directly on the block template that is rendering the current page; on classic themes, links to Appearance → Themes.
- **Quick "New" menu** — create a new post, page, media item, or any public custom post type the current user can create.
- **Dashboard submenu** — quick links to Media, Posts, Pages, Plugins, Settings (and Contact, if Contact Form 7 is active), shown to administrators.
- **Query Monitor support** — keeps Query Monitor's toggle working even with the native admin bar hidden.
- **Log out** — always available, redirecting back to the current page by default.

## Requirements

- WordPress 6.4+
- PHP 7.4+

## Installation

As a mu-plugin, drop `nanobar.php` into your site's `wp-content/mu-plugins/` directory. It will be active automatically — mu-plugins can't be deactivated from the Plugins screen and have no activation/deactivation hooks.

## Configuration

Go to **Settings → NanoBar** to configure:

- Enable/disable NanoBar and choose which roles can see it.
- Whether the native admin bar should be kept available for Query Monitor.
- Horizontal/vertical position, with a live preview.
- Toggle size and background color.
- Icons-only mode.

## Filters

- `nanobar_options` — filter the resolved options array (e.g. `add_filter( 'nanobar_options', function ( $options ) { ... } )`).
- `nanobar_logout_redirect` — filter the URL the user is sent to after logging out from NanoBar (defaults to the current page).
