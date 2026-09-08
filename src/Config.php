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
	 *     size: array{min: int, max: int},
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
	 *     }
	 * }
	 */
	public static function get(): array {
		return array(
			'size'      => array(
				'min' => 32,
				'max' => 72,
			),
			'contrast'  => array(
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
			'positions' => array(
				'horizontal'      => array( 'left', 'center', 'right' ),
				'vertical'        => array( 'top', 'bottom' ),
				// The dropdown is only laid out for a centered toggle sitting on
				// the bottom edge, so "center" is restricted to that side and
				// falls back to "left" anywhere else.
				'center_vertical' => 'bottom',
				'center_fallback' => 'left',
			),
		);
	}
}
