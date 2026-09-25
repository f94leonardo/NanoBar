<?php
/**
 * Settings form sanitization.
 *
 * @package NanoBar
 */

declare( strict_types=1 );

namespace NanoBar\Settings;

use NanoBar\Config;

defined( 'ABSPATH' ) || exit;

/**
 * Sanitizes the settings form input before it is stored.
 */
final class Sanitizer {

	/**
	 * Sanitizes the settings form input.
	 *
	 * Registered as the `sanitize_callback` for the `nanobar_options` setting.
	 *
	 * @param mixed $input Raw data coming from the form (already extracted from $_POST by the Settings API).
	 * @return array<string, mixed>
	 */
	public static function sanitize( mixed $input ): array {
		$input    = is_array( $input ) ? $input : array();
		$defaults = Options::get_defaults();
		$output   = array();

		$output['enabled']    = ! empty( $input['enabled'] );
		$output['icons_only'] = ! empty( $input['icons_only'] );

		$valid_roles     = array_diff( array_keys( wp_roles()->roles ), array( 'subscriber' ) );
		$output['roles'] = array();
		if ( ! empty( $input['roles'] ) && is_array( $input['roles'] ) ) {
			foreach ( $input['roles'] as $role ) {
				$role = is_string( $role ) ? sanitize_key( $role ) : '';
				if ( in_array( $role, $valid_roles, true ) ) {
					$output['roles'][] = $role;
				}
			}
		}
		if ( empty( $output['roles'] ) ) {
			$output['roles'] = $defaults['roles'];
		}

		$position = Options::normalize_position(
			isset( $input['position_horizontal'] ) && is_string( $input['position_horizontal'] ) ? sanitize_key( $input['position_horizontal'] ) : $defaults['position_horizontal'],
			isset( $input['position_vertical'] ) && is_string( $input['position_vertical'] ) ? sanitize_key( $input['position_vertical'] ) : $defaults['position_vertical']
		);

		$output['position_horizontal'] = $position['horizontal'];
		$output['position_vertical']   = $position['vertical'];

		$output['toggle_size_unit'] = Options::normalize_size_unit( $input['toggle_size_unit'] ?? null );
		$output['toggle_size']      = Options::clamp_toggle_size( $input['toggle_size'] ?? $defaults['toggle_size'], $output['toggle_size_unit'] );

		$color                     = isset( $input['toggle_bg_color'] ) && is_string( $input['toggle_bg_color'] ) ? sanitize_hex_color( $input['toggle_bg_color'] ) : '';
		$output['toggle_bg_color'] = $color ? $color : $defaults['toggle_bg_color'];

		$output['force_admin_bar_for_qm'] = ! empty( $input['force_admin_bar_for_qm'] );

		$input_items             = isset( $input['visible_items'] ) && is_array( $input['visible_items'] ) ? $input['visible_items'] : array();
		$output['visible_items'] = array();
		foreach ( Config::get()['menu_items'] as $item_key ) {
			$output['visible_items'][ $item_key ] = ! empty( $input_items[ $item_key ] );
		}

		// Every item's checkbox is always rendered, so a missing key here means
		// the admin unchecked it (same reasoning as the quick links' own
		// "Visible on mobile" checkbox below).
		// If the whole array is absent (a save that didn't come from the form,
		// e.g. WP-CLI), keep every item shown rather than hiding them all.
		$has_mobile             = isset( $input['mobile_items'] ) && is_array( $input['mobile_items'] );
		$input_mobile           = $has_mobile ? $input['mobile_items'] : array();
		$output['mobile_items'] = array();
		foreach ( Config::get()['menu_items'] as $item_key ) {
			$output['mobile_items'][ $item_key ] = $has_mobile ? ! empty( $input_mobile[ $item_key ] ) : true;
		}

		$output['use_elementor_when_available'] = ! empty( $input['use_elementor_when_available'] );

		$output['command_palette_enabled'] = ! empty( $input['command_palette_enabled'] );
		$output['color_scheme']            = Options::normalize_color_scheme( $input['color_scheme'] ?? null );
		$output['quick_links']             = self::sanitize_quick_links( $input['quick_links'] ?? null, $valid_roles );

		return $output;
	}

	/**
	 * Sanitizes the "Quick links" repeater: drops incomplete rows (no label, or
	 * no URL that stays on this site), enforces the
	 * Config::get()['quick_links']['max'] cap, and whitelists each row's roles
	 * the same way the top-level `roles` field is whitelisted above.
	 *
	 * @param mixed              $quick_links Raw quick links input.
	 * @param array<int, string> $valid_roles Role keys allowed anywhere in this plugin (Subscriber excluded).
	 * @return array<int, array{label: string, url: string, icon: string, roles: array<int, string>, mobile_visible: bool}>
	 */
	private static function sanitize_quick_links( mixed $quick_links, array $valid_roles ): array {
		if ( ! is_array( $quick_links ) ) {
			return array();
		}

		$max          = Config::get()['quick_links']['max'];
		$output       = array();
		$default_icon = 'dashicons-admin-links';

		foreach ( $quick_links as $link ) {
			if ( count( $output ) >= $max ) {
				break;
			}

			if ( ! is_array( $link ) ) {
				continue;
			}

			$label = isset( $link['label'] ) && is_string( $link['label'] ) ? sanitize_text_field( $link['label'] ) : '';
			$url   = isset( $link['url'] ) && is_string( $link['url'] ) ? esc_url_raw( $link['url'] ) : '';

			// Quick links may only point at this site — never an external
			// destination, admin-supplied though they are (a panel visible to
			// Editors too is not a safe place to trust arbitrary redirects).
			// wp_validate_redirect() is the same mechanism WordPress itself
			// uses to keep e.g. login redirects on-site: it accepts a relative
			// path or an absolute URL whose host matches this site's, and
			// falls back to '' for anything else, which drops the row below
			// exactly like a missing URL would.
			$url = $url ? wp_validate_redirect( $url, '' ) : '';

			if ( '' === $label || '' === $url ) {
				continue;
			}

			// A validated absolute same-site URL (e.g. pasted straight from the
			// browser's address bar) is stored as just the relative path/query/
			// fragment instead — tidier, and matches what admin-settings.js
			// already does live in the field on blur (relativizeQuickLinkUrl()).
			$url = self::relative_quick_link_url( $url );

			$icon = $default_icon;
			if ( isset( $link['icon'] ) && is_string( $link['icon'] ) && preg_match( '/^dashicons-[a-z0-9-]+$/', $link['icon'] ) ) {
				$icon = $link['icon'];
			}

			// Empty/missing roles means "visible to anyone who can see the
			// panel at all" — same convention as the rest of the plugin, which
			// has no other per-item role restriction to mirror.
			$roles = array();
			if ( ! empty( $link['roles'] ) && is_array( $link['roles'] ) ) {
				foreach ( $link['roles'] as $role ) {
					$role = is_string( $role ) ? sanitize_key( $role ) : '';
					if ( in_array( $role, $valid_roles, true ) ) {
						$roles[] = $role;
					}
				}
			}

			// Checkboxes only appear in $_POST when checked, so a missing key
			// here means the admin unchecked it — not "field never touched" —
			// because the form always re-submits whatever is currently checked
			// in the rendered markup, and the row renders checked by default
			// (see Page::render_quick_link_row()). That keeps existing quick
			// links visible on mobile until an admin deliberately opts one out.
			$mobile_visible = ! empty( $link['mobile_visible'] );

			$output[] = array(
				'label'          => $label,
				'url'            => $url,
				'icon'           => $icon,
				'roles'          => $roles,
				'mobile_visible' => $mobile_visible,
			);
		}

		return $output;
	}

	/**
	 * Strips the scheme/host off an already-validated same-site URL, leaving
	 * just the path (plus query/fragment, when present) — called only after
	 * wp_validate_redirect() has already confirmed the URL stays on this site,
	 * so a URL with a host reaching this point is always this site's own.
	 * A URL that was already relative (no host) is returned unchanged.
	 *
	 * A leading "//" in the resulting path is collapsed to a single "/": for
	 * an input like "https://this-site//evil.example.com/x", that "//" is
	 * just a literal path segment while the URL still carries its own
	 * scheme+host, but once reduced to a bare path and printed on its own as
	 * an href, a browser reinterprets a leading "//" as a protocol-relative
	 * URL to a *different* host — turning an already-validated same-site URL
	 * into an off-site one. Without this, the row would still get rejected
	 * when re-read (see Frontend\QuickLinks::get_visible(), whose own
	 * wp_validate_redirect() call treats a leading "//" as an off-site
	 * destination and drops it), but silently: the settings form would report
	 * success while the link simply never appeared in the panel.
	 *
	 * @param string $url Already-validated, same-site URL.
	 * @return string
	 */
	private static function relative_quick_link_url( string $url ): string {
		$parts = wp_parse_url( $url );
		if ( ! is_array( $parts ) || empty( $parts['host'] ) ) {
			return $url;
		}

		$relative = ! empty( $parts['path'] ) ? $parts['path'] : '/';
		$relative = '/' . ltrim( $relative, '/' );

		if ( ! empty( $parts['query'] ) ) {
			$relative .= '?' . $parts['query'];
		}
		if ( ! empty( $parts['fragment'] ) ) {
			$relative .= '#' . $parts['fragment'];
		}

		return $relative;
	}
}
