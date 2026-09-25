<?php
/**
 * Admin-configured custom quick links.
 *
 * @package NanoBar
 */

declare( strict_types=1 );

namespace NanoBar\Frontend;

defined( 'ABSPATH' ) || exit;

/**
 * Resolves the stored `quick_links` option into the list the current user is
 * actually allowed to see.
 */
final class QuickLinks {

	/**
	 * Filters the stored quick links down to the ones whose role restriction
	 * (if any) matches the current user, and defensively re-validates each
	 * entry's shape — quick links can also arrive unnormalized via the
	 * `nanobar_options` filter or a hand-edited DB row, not just through the
	 * settings form (see Settings\Sanitizer::sanitize()).
	 *
	 * @param mixed $quick_links Stored `quick_links` option value.
	 * @return array<int, array{label: string, url: string, icon: string, mobile_visible: bool}>
	 */
	public static function get_visible( mixed $quick_links ): array {
		if ( ! is_array( $quick_links ) || empty( $quick_links ) ) {
			return array();
		}

		$user_roles = (array) wp_get_current_user()->roles;
		$items      = array();

		foreach ( $quick_links as $link ) {
			if ( ! is_array( $link ) || empty( $link['label'] ) || ! is_string( $link['label'] ) || empty( $link['url'] ) || ! is_string( $link['url'] ) ) {
				continue;
			}

			// Re-checked here too, not just in Settings\Sanitizer::sanitize_quick_links()
			// — same dual-validation reasoning as the rest of this method's
			// docblock: a link must stay on-site even if it arrived through the
			// `nanobar_options` filter or a hand-edited DB row rather than the
			// settings form.
			$url = wp_validate_redirect( $link['url'], '' );
			if ( '' === $url ) {
				continue;
			}

			// Empty/missing roles means "visible to anyone who can see the
			// panel at all" — same convention as the rest of the plugin, which
			// has no other per-item role restriction to mirror.
			$roles = isset( $link['roles'] ) && is_array( $link['roles'] ) ? $link['roles'] : array();
			if ( ! empty( $roles ) && ! array_intersect( $roles, $user_roles ) ) {
				continue;
			}

			// Matches the same pattern Settings\Sanitizer::sanitize_quick_links()
			// validates against on save — re-checked here for the same
			// dual-validation reasoning as the URL/roles checks above, so a
			// malformed icon value can't reach the panel's markup even when
			// it arrives via the `nanobar_options` filter or a hand-edited
			// DB row rather than the settings form.
			$icon = ( ! empty( $link['icon'] ) && is_string( $link['icon'] ) && preg_match( '/^dashicons-[a-z0-9-]+$/', $link['icon'] ) ) ? $link['icon'] : 'dashicons-admin-links';

			// Missing/non-bool means "visible" — same default-on convention
			// Settings\Sanitizer::sanitize_quick_links() and
			// Settings\Page::render_quick_link_row() already use for a link
			// saved before this field existed or arriving unnormalized via
			// the `nanobar_options` filter or a hand-edited DB row.
			$mobile_visible = ! isset( $link['mobile_visible'] ) || ! empty( $link['mobile_visible'] );

			$items[] = array(
				'label'          => $link['label'],
				'url'            => $url,
				'icon'           => $icon,
				'mobile_visible' => $mobile_visible,
			);
		}

		return $items;
	}
}
