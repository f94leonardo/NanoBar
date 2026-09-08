<?php
/**
 * Settings form sanitization.
 *
 * @package NanoBar
 */

declare( strict_types=1 );

namespace NanoBar\Settings;

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
				$role = sanitize_key( $role );
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

		$output['toggle_size'] = Options::clamp_toggle_size( $input['toggle_size'] ?? $defaults['toggle_size'] );

		$color                     = isset( $input['toggle_bg_color'] ) ? sanitize_hex_color( $input['toggle_bg_color'] ) : '';
		$output['toggle_bg_color'] = $color ? $color : $defaults['toggle_bg_color'];

		$output['force_admin_bar_for_qm'] = ! empty( $input['force_admin_bar_for_qm'] );

		return $output;
	}
}
