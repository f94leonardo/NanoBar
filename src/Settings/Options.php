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
	 *     toggle_size: int|float,
	 *     toggle_size_unit: string,
	 *     toggle_bg_color: string,
	 *     force_admin_bar_for_qm: bool,
	 *     visible_items: array<string, bool>,
	 *     mobile_items: array<string, bool>,
	 *     use_elementor_when_available: bool,
	 *     command_palette_enabled: bool,
	 *     color_scheme: string,
	 *     quick_links: array<int, array{label: string, url: string, icon: string, roles: array<int, string>, mobile_visible: bool}>
	 * }
	 */
	public static function get_defaults(): array {
		return array(
			'enabled'                      => true,
			'roles'                        => array( 'administrator', 'editor' ),
			'position_horizontal'          => 'left',
			'position_vertical'            => 'bottom',
			'icons_only'                   => false,
			'toggle_size'                  => 32,
			'toggle_size_unit'             => 'px',
			'toggle_bg_color'              => '#1d2327',
			'force_admin_bar_for_qm'       => false,
			// Query Monitor and Cache off by default — both are developer-facing
			// tools most visitors with NanoBar access shouldn't be prompted with
			// out of the box; everything else defaults to visible.
			'visible_items'                => array_merge(
				array_fill_keys( Config::get()['menu_items'], true ),
				array(
					'query_monitor' => false,
					'cache'         => false,
				)
			),
			// Which items also show in the compact bottom tab bar on phones
			// (≤580px) — all of them unless an admin opts one out.
			'mobile_items'                 => array_fill_keys( Config::get()['menu_items'], true ),
			'use_elementor_when_available' => false,
			'command_palette_enabled'      => true,
			'color_scheme'                 => 'auto',
			'quick_links'                  => array(),
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
		$filtered = apply_filters( 'nanobar_options', $options );

		return is_array( $filtered ) ? $filtered : $options;
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
	 * Resolves which top-level panel items are enabled, against the canonical
	 * key list in Config::get()['menu_items'].
	 *
	 * Merges key-by-key (unlike the top-level defaults merge in self::get(),
	 * which only fills in a missing `visible_items` array as a whole) so that
	 * an item added by a future plugin update is not silently treated as
	 * disabled for sites that already have a `visible_items` row saved from an
	 * older version. A missing key falls back to *that item's own* default
	 * (from get_defaults()) rather than a blanket `true` — since
	 * get_defaults()['visible_items'] already covers every key in
	 * Config::get()['menu_items'] (including one added in a future version,
	 * the moment its own default is added alongside it), this keeps the
	 * forward-compatibility behavior for a genuinely new item while not
	 * defaulting an *existing* item (e.g. Cache/Query Monitor, off by
	 * default) back to visible just because a `nanobar_options` filter or a
	 * hand-edited DB row happens to omit it. Used both by the sanitizer (on
	 * save) and by the renderer (on output), same as
	 * normalize_position()/clamp_toggle_size() above.
	 *
	 * @param array<string, mixed> $options Resolved options, as returned by self::get().
	 * @return array<string, bool>
	 */
	public static function get_visible_items( array $options ): array {
		$stored           = isset( $options['visible_items'] ) && is_array( $options['visible_items'] ) ? $options['visible_items'] : array();
		$defaults_visible = self::get_defaults()['visible_items'];

		$items = array();
		foreach ( Config::get()['menu_items'] as $key ) {
			$items[ $key ] = array_key_exists( $key, $stored ) ? ! empty( $stored[ $key ] ) : ! empty( $defaults_visible[ $key ] );
		}

		return $items;
	}

	/**
	 * Resolves which top-level items also appear in the phone tab bar.
	 *
	 * Same key-by-key merge as get_visible_items(); a missing key means
	 * "shown", so an item added by a later version isn't hidden on phones for
	 * sites that already have a `mobile_items` row saved.
	 *
	 * @param array<string, mixed> $options Resolved options, as returned by self::get().
	 * @return array<string, bool>
	 */
	public static function get_mobile_items( array $options ): array {
		$stored = isset( $options['mobile_items'] ) && is_array( $options['mobile_items'] ) ? $options['mobile_items'] : array();

		$items = array();
		foreach ( Config::get()['menu_items'] as $key ) {
			$items[ $key ] = ! array_key_exists( $key, $stored ) || ! empty( $stored[ $key ] );
		}

		return $items;
	}

	/**
	 * Normalizes the toggle size unit against Config::get()['size']['units'].
	 *
	 * @param mixed $unit Requested unit.
	 * @return string
	 */
	public static function normalize_size_unit( mixed $unit ): string {
		$allowed = Config::get()['size']['units'];

		return ( is_string( $unit ) && in_array( $unit, $allowed, true ) ) ? $unit : 'px';
	}

	/**
	 * Clamps the toggle diameter to the supported range for its unit: whole
	 * pixels for "px", up to two decimals for "rem".
	 *
	 * @param mixed $size Requested size, in $unit.
	 * @param mixed $unit Requested unit ("px" or "rem").
	 * @return int|float
	 */
	public static function clamp_toggle_size( mixed $size, mixed $unit = 'px' ): int|float {
		$size = ( is_int( $size ) || is_float( $size ) || is_string( $size ) ) && is_numeric( $size ) ? (float) $size : 0.0;

		if ( 'rem' === self::normalize_size_unit( $unit ) ) {
			$rem = Config::get()['size']['rem'];

			return round( max( (float) $rem['min'], min( (float) $rem['max'], $size ) ), 2 );
		}

		$px = Config::get()['size'];

		// Clamp before casting: (int) of INF/huge floats is 0, which would turn
		// an oversized value into the minimum instead of the maximum.
		return (int) round( max( (float) $px['min'], min( (float) $px['max'], $size ) ) );
	}

	/**
	 * Normalizes the panel's color scheme setting against the allowed values in
	 * Config::get()['color_schemes'], falling back to "auto".
	 *
	 * Used both by the sanitizer (on save) and by the renderer (on output), same
	 * as normalize_position()/clamp_toggle_size() above.
	 *
	 * @param mixed $scheme Requested color scheme.
	 * @return string
	 */
	public static function normalize_color_scheme( mixed $scheme ): string {
		$allowed = Config::get()['color_schemes'];

		return ( is_string( $scheme ) && in_array( $scheme, $allowed, true ) ) ? $scheme : 'auto';
	}
}
