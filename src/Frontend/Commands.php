<?php
/**
 * Extra command palette destinations.
 *
 * @package NanoBar
 */

declare( strict_types=1 );

namespace NanoBar\Frontend;

defined( 'ABSPATH' ) || exit;

/**
 * A small, capability-gated set of common wp-admin destinations offered by
 * the command palette in addition to whatever the panel itself already shows.
 *
 * Deliberately does not replicate the full dynamic wp-admin menu (building
 * that outside wp-admin would mean running every admin_menu callback on the
 * frontend, which is both expensive and can have side effects) — the panel's
 * own already-rendered, already capability-gated markup (Dashboard submenu,
 * "New" submenu, cache, logout, …) covers most of it; frontend.js reads that
 * DOM directly to build the rest of the command list.
 */
final class Commands {

	/**
	 * Registry of extra command palette destinations, filterable via
	 * `nanobar_command_palette_items` — add, remove, or correct entries here
	 * instead of patching this plugin. Each entry needs `label`, `url`,
	 * `icon`, and `is_visible` (a callable returning bool, evaluated lazily in
	 * get_extra_items() below, same shape/reasoning as
	 * Menus::get_cache_plugin_registry()'s `is_active`) — a plain
	 * `current_user_can()` check for the built-in entries, but a filter can
	 * use anything (multiple capabilities, a post type existing, …).
	 *
	 * @return array<int, array{label: string, url: string, icon: string, is_visible: callable(): bool}>
	 */
	private static function get_registry(): array {
		$registry = array(
			array(
				'label'      => __( 'Your profile', 'nanobar' ),
				'url'        => admin_url( 'profile.php' ),
				'icon'       => 'dashicons-businessman',
				'is_visible' => static fn (): bool => true,
			),
			array(
				'label'      => __( 'Comments', 'nanobar' ),
				'url'        => admin_url( 'edit-comments.php' ),
				'icon'       => 'dashicons-admin-comments',
				'is_visible' => static fn (): bool => current_user_can( 'moderate_comments' ),
			),
			array(
				'label'      => __( 'Users', 'nanobar' ),
				'url'        => admin_url( 'users.php' ),
				'icon'       => 'dashicons-admin-users',
				'is_visible' => static fn (): bool => current_user_can( 'list_users' ),
			),
			array(
				'label'      => __( 'Themes', 'nanobar' ),
				'url'        => admin_url( 'themes.php' ),
				'icon'       => 'dashicons-admin-appearance',
				'is_visible' => static fn (): bool => current_user_can( 'edit_theme_options' ),
			),
			array(
				'label'      => __( 'Tools', 'nanobar' ),
				'url'        => admin_url( 'tools.php' ),
				'icon'       => 'dashicons-admin-tools',
				'is_visible' => static fn (): bool => current_user_can( 'import' ),
			),
		);

		/**
		 * Filters the registry of extra command palette destinations. Add,
		 * remove, or correct entries here instead of patching this plugin —
		 * each entry needs `label`, `url`, `icon`, and `is_visible` (a
		 * callable returning bool).
		 *
		 * @param array<int, array{label: string, url: string, icon: string, is_visible: callable(): bool}> $registry Command palette destination definitions.
		 */
		$registry = apply_filters( 'nanobar_command_palette_items', $registry );

		return is_array( $registry ) ? $registry : array();
	}

	/**
	 * Builds the extra destinations list: every registry entry whose
	 * `is_visible` callable returns true for the current user.
	 *
	 * Defensively validates each entry's shape before using it — the
	 * `nanobar_command_palette_items` filter is this plugin's own documented
	 * extension point, but nothing enforces at runtime that a filter
	 * callback's entry actually has a callable `is_visible` or non-empty
	 * `label`/`url`; a filter appending a malformed entry would otherwise
	 * either throw a fatal error out of call_user_func() or print a broken
	 * command into the palette.
	 *
	 * @return array<int, array{label: string, url: string, icon: string}>
	 */
	public static function get_extra_items(): array {
		$items = array();

		foreach ( self::get_registry() as $entry ) {
			if ( ! is_array( $entry ) || ! is_callable( $entry['is_visible'] ?? null ) ) {
				continue;
			}

			$label = isset( $entry['label'] ) && is_string( $entry['label'] ) ? $entry['label'] : '';
			// esc_url_raw() drops any scheme outside WordPress's allowed
			// protocols (notably javascript:), since frontend.js navigates to
			// this value directly.
			$url = isset( $entry['url'] ) && is_string( $entry['url'] ) ? esc_url_raw( $entry['url'] ) : '';
			if ( '' === $label || '' === $url ) {
				continue;
			}

			if ( ! call_user_func( $entry['is_visible'] ) ) {
				continue;
			}

			$items[] = array(
				'label' => $label,
				'url'   => $url,
				'icon'  => ( isset( $entry['icon'] ) && is_string( $entry['icon'] ) && 1 === preg_match( '/^dashicons-[a-z0-9-]+$/', $entry['icon'] ) ) ? $entry['icon'] : 'dashicons-admin-generic',
			);
		}

		return $items;
	}
}
