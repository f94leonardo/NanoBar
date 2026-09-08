<?php
/**
 * Contrast color helper.
 *
 * @package NanoBar
 */

declare( strict_types=1 );

namespace NanoBar\Support;

use NanoBar\Config;

defined( 'ABSPATH' ) || exit;

/**
 * Picks a readable icon color against an arbitrary background color.
 */
final class Color {

	/**
	 * Calculates an icon color (white or charcoal) that is readable against the
	 * background color chosen for the button, using the perceived-luminance YIQ
	 * formula.
	 *
	 * The settings page mirrors this in JS, because the live preview must react
	 * to colors that have not been saved yet. Both read their weights,
	 * threshold and colors from Config::get(), so tuning the rule there
	 * updates both at once.
	 *
	 * @param string $hex_color Background color in hex format (e.g. #1d2327).
	 * @return string Icon color in hex format.
	 */
	public static function get_contrast_color( string $hex_color ): string {
		$contrast = Config::get()['contrast'];

		$hex = ltrim( $hex_color, '#' );
		if ( 3 === strlen( $hex ) ) {
			$hex = $hex[0] . $hex[0] . $hex[1] . $hex[1] . $hex[2] . $hex[2];
		}
		if ( 6 !== strlen( $hex ) || ! ctype_xdigit( $hex ) ) {
			return $contrast['on_dark'];
		}

		$red   = hexdec( substr( $hex, 0, 2 ) );
		$green = hexdec( substr( $hex, 2, 2 ) );
		$blue  = hexdec( substr( $hex, 4, 2 ) );

		$yiq = ( ( $red * $contrast['weights']['red'] ) + ( $green * $contrast['weights']['green'] ) + ( $blue * $contrast['weights']['blue'] ) ) / 1000;

		return ( $yiq >= $contrast['threshold'] ) ? $contrast['on_light'] : $contrast['on_dark'];
	}
}
