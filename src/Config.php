<?php
/**
 * Shared configuration.
 *
 * @package NanoBar
 */

declare( strict_types=1 );

namespace NanoBar;

defined( 'ABSPATH' ) || exit;

/**
 * Hard constraints shared by every layer of the plugin: allowed positions,
 * toggle size bounds and the contrast rule that picks the icon color.
 *
 * This is the single source of truth. The sanitizer, the settings page, the
 * frontend renderer and the JavaScript live preview all read these values, so
 * a change made here propagates everywhere instead of having to be mirrored by
 * hand in several places.
 */
final class Config {

	/**
	 * Returns the shared configuration array.
	 *
	 * @return array{
	 *     size: array{min: int, max: int, units: array<int, string>, rem: array{min: float|int, max: float|int, step: float}, rem_base: int},
	 *     contrast: array{
	 *         weights: array{red: int, green: int, blue: int},
	 *         threshold: int,
	 *         on_light: string,
	 *         on_dark: string
	 *     },
	 *     positions: array{
	 *         horizontal: array<int, string>,
	 *         vertical: array<int, string>,
	 *         center_vertical: string,
	 *         center_fallback: string
	 *     },
	 *     menu_items: array<int, string>,
	 *     color_schemes: array<int, string>,
	 *     quick_links: array{max: int}
	 * }
	 */
	public static function get(): array {
		return array(
			'size'          => array(
				// Pixel bounds (the historical unit); `min`/`max` stay at the top
				// level of this array so existing readers keep working.
				'min'      => 32,
				'max'      => 72,
				'units'    => array( 'px', 'rem' ),
				// The same range expressed in rem (1rem = `rem_base` px), used when
				// the "rem" unit is selected. The settings page preview has to
				// pick a pixel size for it, so it assumes the browser default.
				'rem'      => array(
					'min'  => 2,
					'max'  => 4.5,
					'step' => 0.25,
				),
				'rem_base' => 16,
			),
			'contrast'      => array(
				// Perceived luminance (YIQ) weights, the threshold above which a
				// background counts as "light", and the two icon colors.
				'weights'   => array(
					'red'   => 299,
					'green' => 587,
					'blue'  => 114,
				),
				'threshold' => 150,
				'on_light'  => '#1d2327',
				'on_dark'   => '#ffffff',
			),
			'positions'     => array(
				'horizontal'      => array( 'left', 'center', 'right' ),
				'vertical'        => array( 'top', 'bottom' ),
				// The dropdown is only laid out for a centered toggle sitting on
				// the bottom edge, so "center" is restricted to that side and
				// falls back to "left" anywhere else.
				'center_vertical' => 'bottom',
				'center_fallback' => 'left',
			),
			// The panel's top-level items, in the order they can be toggled on
			// the settings page — kept in one place so the settings page, the
			// sanitizer (key whitelist) and the renderer (visibility lookup)
			// can't drift out of sync with each other.
			'menu_items'    => array( 'dashboard', 'new_content', 'edit_content', 'site_editor', 'cache', 'query_monitor', 'logout' ),
			// Allowed values for the panel's color scheme setting: "auto"
			// follows the visitor's OS/browser prefers-color-scheme, "light"
			// and "dark" force one regardless of it.
			'color_schemes' => array( 'auto', 'light', 'dark' ),
			'quick_links'   => array(
				// Hard cap on custom quick links, mirrored by the sanitizer,
				// mostly to keep the settings page and the panel usable rather
				// than as a security boundary.
				'max' => 20,
			),
		);
	}
}
