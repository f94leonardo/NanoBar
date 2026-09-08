<?php
/**
 * String helpers.
 *
 * @package NanoBar
 */

declare( strict_types=1 );

namespace NanoBar\Support;

defined( 'ABSPATH' ) || exit;

/**
 * Small string utilities shared across the plugin.
 */
final class Str {

	/**
	 * Multibyte-safe lowercase conversion, with a fallback for environments
	 * where the mbstring extension is not available.
	 *
	 * @param string $text Text to lowercase.
	 * @return string
	 */
	public static function lower( string $text ): string {
		return function_exists( 'mb_strtolower' ) ? mb_strtolower( $text ) : strtolower( $text );
	}
}
