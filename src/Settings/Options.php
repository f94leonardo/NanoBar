<?php
/**
 * Options storage and normalization.
 *
 * @package NanoBar
 */

declare( strict_types=1 );

namespace NanoBar\Settings;

use NanoBar\Config;

defined( 'ABSPATH' ) || exit;

/**
 * Reads, defaults and normalizes the plugin's stored options.
 */
final class Options {

	/**
	 * Name of the option row in wp_options.
	 *
	 * @var string
	 */
	public const OPTION_NAME = 'nanobar_options';

	/**
	 * Default option values.
	 *
	 * @return array{
	 *     enabled: bool,
	 *     roles: array<int, string>,
	 *     position_horizontal: string,
	 *     position_vertical: string,
	 *     icons_only: bool,
	 *     toggle_size: int,
	 *     toggle_bg_color: string,
	 *     force_admin_bar_for_qm: bool
	 * }
	 */
	public static function get_defaults(): array {
		return array(
			'enabled'                => true,
			'roles'                  => array( 'administrator', 'editor' ),
			'position_horizontal'    => 'left',
			'position_vertical'      => 'bottom',
			'icons_only'             => false,
			'toggle_size'            => 44,
			'toggle_bg_color'        => '#1d2327',
			'force_admin_bar_for_qm' => true,
		);
	}

	/**
	 * Current options (with defaults), filterable by other plugins/themes with:
	 * add_filter( 'nanobar_options', ... ).
	 *
	 * @return array<string, mixed>
	 */
	public static function get(): array {
		$options = wp_parse_args( get_option( self::OPTION_NAME, array() ), self::get_defaults() );

		/**
		 * Filters the resolved NanoBar options array.
		 *
		 * @param array<string, mixed> $options Resolved options.
		 */
		return apply_filters( 'nanobar_options', $options );
	}

	/**
	 * Normalizes a position pair against the allowed values and against the rule
	 * that "center" only exists on the bottom edge.
	 *
	 * Used both by the sanitizer (on save) and by the renderers (on output), so
	 * options coming from the `nanobar_options` filter, from a hand-edited row in
	 * the database or from an older version can never produce an unsupported
	 * combination.
	 *
	 * @param mixed $horizontal Requested horizontal position.
	 * @param mixed $vertical   Requested vertical position.
	 * @return array{horizontal: string, vertical: string}
	 */
	public static function normalize_position( mixed $horizontal, mixed $vertical ): array {
		$config   = Config::get();
		$defaults = self::get_defaults();

		if ( ! is_string( $vertical ) || ! in_array( $vertical, $config['positions']['vertical'], true ) ) {
			$vertical = $defaults['position_vertical'];
		}

		if ( ! is_string( $horizontal ) || ! in_array( $horizontal, $config['positions']['horizontal'], true ) ) {
			$horizontal = $defaults['position_horizontal'];
		}

		if ( 'center' === $horizontal && $config['positions']['center_vertical'] !== $vertical ) {
			$horizontal = $config['positions']['center_fallback'];
		}

		return array(
			'horizontal' => $horizontal,
			'vertical'   => $vertical,
		);
	}

	/**
	 * Clamps the toggle diameter to the supported range.
	 *
	 * @param mixed $size Requested size in pixels.
	 * @return int
	 */
	public static function clamp_toggle_size( mixed $size ): int {
		$config = Config::get();
		$size   = is_scalar( $size ) ? absint( $size ) : 0;

		return (int) max( $config['size']['min'], min( $config['size']['max'], $size ) );
	}
}
