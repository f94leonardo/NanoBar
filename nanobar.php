<?php
/**
 * Plugin Name: NanoBar Compact Admin Toolbar
 * Description: Replaces WordPress's classic admin bar, on the frontend, with a
 *              compact, role-aware floating panel, configurable from a single
 *              global settings page (Settings → NanoBar).
 * Version:     1.0.0
 * Requires at least: 6.4
 * Requires PHP: 7.4
 * Author:      f94leonardo
 * Text Domain: nanobar
 *
 * @package NanoBar
 */

defined( 'ABSPATH' ) || exit;

// Shared configuration.

/**
 * Hard constraints shared by every layer of the plugin: allowed positions,
 * toggle size bounds and the contrast rule that picks the icon color.
 *
 * This is the single source of truth. The sanitizer, the settings page, the
 * frontend renderer and the JavaScript live preview all read these values, so
 * a change made here propagates everywhere instead of having to be mirrored by
 * hand in several places.
 *
 * @return array
 */
function nanobar_get_config() {
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

// Settings.

/**
 * Default option values.
 *
 * @return array
 */
function nanobar_get_default_options() {
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
 * @return array
 */
function nanobar_get_options() {
	$options = wp_parse_args( get_option( 'nanobar_options', array() ), nanobar_get_default_options() );
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
 * @return array{horizontal:string, vertical:string}
 */
function nanobar_normalize_position( $horizontal, $vertical ) {
	$config   = nanobar_get_config();
	$defaults = nanobar_get_default_options();

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
function nanobar_clamp_toggle_size( $size ) {
	$config = nanobar_get_config();
	$size   = is_scalar( $size ) ? absint( $size ) : 0;

	return (int) max( $config['size']['min'], min( $config['size']['max'], $size ) );
}

/**
 * Lowercases a string with multibyte support when available.
 *
 * Falls back to strtolower() on installs without the mbstring extension, where
 * a bare mb_strtolower() call would be a fatal error.
 *
 * @param string $text Text to lowercase.
 * @return string
 */
function nanobar_strtolower( $text ) {
	$text = (string) $text;

	return function_exists( 'mb_strtolower' ) ? mb_strtolower( $text ) : strtolower( $text );
}

/**
 * Registers the option in the Settings API.
 */
function nanobar_register_settings() {
	register_setting( 'nanobar_settings_group', 'nanobar_options', 'nanobar_sanitize_options' );
}
add_action( 'admin_init', 'nanobar_register_settings' );

/**
 * Registers the Settings → NanoBar menu entry.
 */
function nanobar_register_settings_page() {
	add_options_page(
		__( 'NanoBar', 'nanobar' ),
		__( 'NanoBar', 'nanobar' ),
		'manage_options',
		'nanobar',
		'nanobar_render_settings_page'
	);
}
add_action( 'admin_menu', 'nanobar_register_settings_page' );

/**
 * Loads WordPress's native color picker only on the NanoBar settings page.
 *
 * @param string $hook_suffix Identifier of the current admin screen.
 */
function nanobar_admin_enqueue_assets( $hook_suffix ) {
	if ( 'settings_page_nanobar' !== $hook_suffix ) {
		return;
	}

	wp_enqueue_style( 'wp-color-picker' );
	wp_enqueue_script( 'wp-color-picker' );
}
add_action( 'admin_enqueue_scripts', 'nanobar_admin_enqueue_assets' );

/**
 * Sanitizes the settings form input.
 *
 * @param array $input Raw data coming from the form (already extracted from $_POST by the Settings API).
 * @return array
 */
function nanobar_sanitize_options( $input ) {
	$defaults = nanobar_get_default_options();
	$output   = array();

	$output['enabled']    = ! empty( $input['enabled'] );
	$output['icons_only'] = ! empty( $input['icons_only'] );

	$valid_roles = array_diff( array_keys( wp_roles()->roles ), array( 'subscriber' ) );
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

	$position = nanobar_normalize_position(
		isset( $input['position_horizontal'] ) && is_string( $input['position_horizontal'] ) ? sanitize_key( $input['position_horizontal'] ) : $defaults['position_horizontal'],
		isset( $input['position_vertical'] ) && is_string( $input['position_vertical'] ) ? sanitize_key( $input['position_vertical'] ) : $defaults['position_vertical']
	);

	$output['position_horizontal'] = $position['horizontal'];
	$output['position_vertical']   = $position['vertical'];

	$output['toggle_size'] = nanobar_clamp_toggle_size( isset( $input['toggle_size'] ) ? $input['toggle_size'] : $defaults['toggle_size'] );

	$color                     = isset( $input['toggle_bg_color'] ) ? sanitize_hex_color( $input['toggle_bg_color'] ) : '';
	$output['toggle_bg_color'] = $color ? $color : $defaults['toggle_bg_color'];

	$output['force_admin_bar_for_qm'] = ! empty( $input['force_admin_bar_for_qm'] );

	return $output;
}

/**
 * Renders the Settings → NanoBar page.
 */
function nanobar_render_settings_page() {
	if ( ! current_user_can( 'manage_options' ) ) {
		return;
	}

	$options   = nanobar_get_options();
	$defaults  = nanobar_get_default_options();
	$config    = nanobar_get_config();
	$all_roles = array_diff_key( wp_roles()->roles, array( 'subscriber' => true ) );
	$position  = nanobar_normalize_position( $options['position_horizontal'], $options['position_vertical'] );
	$pos_h     = $position['horizontal'];
	$pos_v     = $position['vertical'];
	?>
	<div class="wrap nanobar-settings-wrap">
		<form method="post" action="options.php" class="nanobar-form">
			<div class="nanobar-header">
				<div class="nanobar-header__content">
					<div class="nanobar-header__icon" aria-hidden="true"><span class="dashicons dashicons-admin-generic"></span></div>
					<div>
						<h1><?php esc_html_e( 'NanoBar', 'nanobar' ); ?></h1>
						<p><?php esc_html_e( 'Configure the floating panel that replaces the classic admin bar on the frontend.', 'nanobar' ); ?></p>
					</div>
				</div>
				<div class="nanobar-header__actions">
					<?php submit_button( __( 'Save changes', 'nanobar' ), 'primary large', 'submit', false ); ?>
				</div>
			</div>

			<?php settings_fields( 'nanobar_settings_group' ); ?>

			<div class="nanobar-bento-grid">
				<div class="nanobar-bento-card nanobar-bento-card--general">
					<div class="nanobar-bento-card__header">
						<span class="dashicons dashicons-admin-generic" aria-hidden="true"></span>
						<h2><?php esc_html_e( 'General', 'nanobar' ); ?></h2>
					</div>
					<div class="nanobar-bento-card__body">
						<div class="nanobar-form-group">
							<label class="nanobar-switch-row">
								<input type="checkbox" name="nanobar_options[enabled]" value="1" <?php checked( ! empty( $options['enabled'] ) ); ?> />
								<span class="nanobar-switch" aria-hidden="true"></span>
								<span><strong><?php esc_html_e( 'Enable NanoBar', 'nanobar' ); ?></strong><small><?php esc_html_e( 'Replaces the classic admin bar on the frontend.', 'nanobar' ); ?></small></span>
							</label>
						</div>

						<div class="nanobar-form-group">
							<label class="nanobar-label" for="nanobar-roles"><?php esc_html_e( 'Enabled roles', 'nanobar' ); ?></label>
							<p class="nanobar-help"><?php esc_html_e( 'Choose which roles can see NanoBar. The Subscriber role is not available.', 'nanobar' ); ?></p>
							<div class="nanobar-roles-grid" id="nanobar-roles">
								<?php foreach ( $all_roles as $role_key => $role_data ) : ?>
									<label class="nanobar-role-option">
										<input type="checkbox" name="nanobar_options[roles][]" value="<?php echo esc_attr( $role_key ); ?>" <?php checked( in_array( $role_key, (array) $options['roles'], true ) ); ?> />
										<span><?php echo esc_html( translate_user_role( $role_data['name'] ) ); ?></span>
									</label>
								<?php endforeach; ?>
							</div>
						</div>

						<div class="nanobar-form-group nanobar-form-group--last">
							<label class="nanobar-switch-row">
								<input type="checkbox" name="nanobar_options[force_admin_bar_for_qm]" value="1" <?php checked( ! empty( $options['force_admin_bar_for_qm'] ) ); ?> />
								<span class="nanobar-switch" aria-hidden="true"></span>
								<span><strong><?php esc_html_e( 'Force admin bar for Query Monitor', 'nanobar' ); ?></strong><small><?php esc_html_e( 'Keeps the native bar available, which Query Monitor needs in order to work.', 'nanobar' ); ?></small></span>
							</label>
						</div>
					</div>
				</div>

				<div class="nanobar-bento-card nanobar-bento-card--position">
					<div class="nanobar-bento-card__header">
						<span class="dashicons dashicons-move" aria-hidden="true"></span>
						<h2><?php esc_html_e( 'Position', 'nanobar' ); ?></h2>
					</div>
					<div class="nanobar-bento-card__body">
						<div class="nanobar-form-group">
							<label class="nanobar-label" for="nanobar-pos-h"><?php esc_html_e( 'Horizontal', 'nanobar' ); ?></label>
							<select name="nanobar_options[position_horizontal]" id="nanobar-pos-h" class="nanobar-select">
								<option value="left" <?php selected( $pos_h, 'left' ); ?>><?php esc_html_e( 'Left', 'nanobar' ); ?></option>
								<option value="center" <?php selected( $pos_h, 'center' ); ?> class="nanobar-center-option" <?php disabled( $config['positions']['center_vertical'] !== $pos_v ); ?>><?php esc_html_e( 'Center', 'nanobar' ); ?></option>
								<option value="right" <?php selected( $pos_h, 'right' ); ?>><?php esc_html_e( 'Right', 'nanobar' ); ?></option>
							</select>
						</div>

						<div class="nanobar-form-group nanobar-form-group--last">
							<label class="nanobar-label" for="nanobar-pos-v"><?php esc_html_e( 'Vertical', 'nanobar' ); ?></label>
							<select name="nanobar_options[position_vertical]" id="nanobar-pos-v" class="nanobar-select">
								<option value="bottom" <?php selected( $pos_v, 'bottom' ); ?>><?php esc_html_e( 'Bottom', 'nanobar' ); ?></option>
								<option value="top" <?php selected( $pos_v, 'top' ); ?>><?php esc_html_e( 'Top', 'nanobar' ); ?></option>
							</select>
						</div>
					</div>
				</div>

				<div class="nanobar-bento-card nanobar-bento-card--appearance">
					<div class="nanobar-bento-card__header">
						<span class="dashicons dashicons-admin-customizer" aria-hidden="true"></span>
						<h2><?php esc_html_e( 'Appearance', 'nanobar' ); ?></h2>
					</div>
					<div class="nanobar-bento-card__body">
						<div class="nanobar-form-group">
							<label class="nanobar-label" for="nanobar-toggle-size-input"><?php esc_html_e( 'Size', 'nanobar' ); ?></label>
							<div class="nanobar-input-group">
								<input type="number" id="nanobar-toggle-size-input" name="nanobar_options[toggle_size]" value="<?php echo esc_attr( $options['toggle_size'] ); ?>" min="<?php echo esc_attr( $config['size']['min'] ); ?>" max="<?php echo esc_attr( $config['size']['max'] ); ?>" step="1" inputmode="numeric" class="nanobar-input" aria-describedby="nanobar-size-help" />
								<span class="nanobar-input-suffix">px</span>
							</div>
							<p class="nanobar-help" id="nanobar-size-help">
								<?php
								printf(
									/* translators: 1: minimum size in pixels, 2: maximum size in pixels. */
									esc_html__( 'From %1$dpx to %2$dpx.', 'nanobar' ),
									(int) $config['size']['min'],
									(int) $config['size']['max']
								);
								?>
							</p>
						</div>

						<div class="nanobar-form-group">
							<label class="nanobar-label" for="nanobar-color-field"><?php esc_html_e( 'Background color', 'nanobar' ); ?></label>
							<input type="text" id="nanobar-color-field" class="nanobar-color-field nanobar-input" name="nanobar_options[toggle_bg_color]" value="<?php echo esc_attr( $options['toggle_bg_color'] ); ?>" data-default-color="<?php echo esc_attr( $defaults['toggle_bg_color'] ); ?>" />
							<p class="nanobar-help"><?php esc_html_e( 'The icon color is automatically adjusted to keep sufficient contrast.', 'nanobar' ); ?></p>
						</div>

						<div class="nanobar-form-group nanobar-form-group--last">
							<label class="nanobar-switch-row">
								<input type="checkbox" name="nanobar_options[icons_only]" value="1" <?php checked( ! empty( $options['icons_only'] ) ); ?> />
								<span class="nanobar-switch" aria-hidden="true"></span>
								<span><strong><?php esc_html_e( 'Icons only', 'nanobar' ); ?></strong><small><?php esc_html_e( 'Hides labels and automatically keeps a compact mode on narrow screens.', 'nanobar' ); ?></small></span>
							</label>
						</div>
					</div>
				</div>

				<div class="nanobar-bento-card nanobar-bento-card--preview">
					<div class="nanobar-bento-card__header">
						<span class="dashicons dashicons-visibility" aria-hidden="true"></span>
						<h2><?php esc_html_e( 'Preview', 'nanobar' ); ?></h2>
					</div>
					<div class="nanobar-bento-card__body nanobar-preview-container">
						<div class="nanobar-viewport" id="nanobar-viewport" aria-label="<?php esc_attr_e( 'Preview of the NanoBar position', 'nanobar' ); ?>">
							<div class="nanobar-viewport__label" id="nanobar-viewport-label"></div>
							<div class="nanobar-preview-button" id="nanobar-preview-button" aria-hidden="true">
								<span class="dashicons dashicons-admin-generic"></span>
							</div>
						</div>
					</div>
				</div>
			</div>

			<div class="nanobar-actions">
				<div class="nanobar-actions__buttons">
					<?php submit_button( __( 'Save changes', 'nanobar' ), 'primary', 'submit', false ); ?>
					<button type="button" id="nanobar-reset-defaults" class="button"><?php esc_html_e( 'Restore defaults', 'nanobar' ); ?></button>
				</div>
				<p class="nanobar-actions__hint"><span class="dashicons dashicons-saved" aria-hidden="true"></span><?php esc_html_e( 'Changes are applied on save.', 'nanobar' ); ?></p>
			</div>
		</form>

		<?php
		/*
		 * For child pages of "options-general.php" (like this one), WordPress
		 * already automatically prints settings_errors() in the admin header
		 * (wp-admin/options-head.php), so it should not be called again here,
		 * or the notice would show up duplicated. However, wp-admin/js/common.js
		 * moves that notice right after the page's first <h1>, unless it finds a
		 * ".wp-header-end" marker: we place it here so the notice lands right
		 * inside this container.
		 */
		?>
		<div class="nanobar-notices-container">
			<hr class="wp-header-end" />
		</div>
	</div>

	<style>
		.nanobar-settings-wrap { max-width: none; margin-top: 20px; background: transparent; }
		.nanobar-header { display: flex; align-items: center; justify-content: space-between; gap: 20px; margin: 0 0 18px; padding: 20px 26px; border-radius: 12px; background: linear-gradient( 135deg, #667eea 0%, #764ba2 100% ); color: #fff; box-shadow: 0 8px 24px rgba( 31, 41, 55, .12 ); flex-wrap: wrap; }
		.nanobar-header__content { display: flex; align-items: center; gap: 14px; flex: 1 1 auto; min-width: 0; }
		.nanobar-header__actions { flex: 0 0 auto; }
		.nanobar-header__icon { width: 42px; height: 42px; flex: 0 0 42px; display: flex; align-items: center; justify-content: center; border-radius: 10px; background: rgba( 255, 255, 255, .16 ); }
		.nanobar-header__icon .dashicons { font-size: 22px; width: 22px; height: 22px; }
		.nanobar-header h1 { margin: 0 0 3px; color: #fff; font-size: 28px; line-height: 1.15; font-weight: 700; }
		.nanobar-header p { margin: 0; font-size: 14px; line-height: 1.45; opacity: .94; }
		.nanobar-header__actions .button { margin: 0; }
		.nanobar-notices { margin: 0 0 20px; }
		.nanobar-notices-container { margin-top: 24px; padding: 0 2px; }
		.nanobar-settings-wrap .notice {
			display: flex;
			align-items: flex-start;
			gap: 12px;
			margin: 0 0 12px;
			padding: 14px 16px;
			border-left: 4px solid;
			border-radius: 6px;
			background: #fff;
			animation: nanobar-notice-slide-in .3s ease-out;
		}
		.nanobar-settings-wrap .notice.is-dismissing {
			animation: nanobar-notice-fade-out .4s ease-out forwards;
		}
		.nanobar-settings-wrap .notice-icon::before {
			display: inline-block;
			width: 20px;
			height: 20px;
			line-height: 20px;
			flex: 0 0 20px;
			font-size: 18px;
			font-weight: bold;
			text-align: center;
		}
		.nanobar-settings-wrap .notice.notice-success {
			border-left-color: #00a32a;
			background: #f0f6f0;
		}
		.nanobar-settings-wrap .notice.notice-success .notice-icon::before {
			content: '✓';
			color: #00a32a;
		}
		.nanobar-settings-wrap .notice.notice-error {
			border-left-color: #d63638;
			background: #f8f0f1;
		}
		.nanobar-settings-wrap .notice.notice-error .notice-icon::before {
			content: '!';
			color: #d63638;
		}
		.nanobar-settings-wrap .notice.notice-warning {
			border-left-color: #dba617;
			background: #fefaf0;
		}
		.nanobar-settings-wrap .notice.notice-warning .notice-icon::before {
			content: '⚠';
			color: #dba617;
		}
		.nanobar-settings-wrap .notice p {
			margin: 0;
			flex: 1 1 auto;
			font-size: 14px;
		}
		.nanobar-settings-wrap .notice .notice-dismiss {
			flex: 0 0 auto;
			position: relative;
			padding: 0;
			min-width: 24px;
			height: 24px;
			line-height: 24px;
			color: #9ca0a5;
			background: none;
			border: none;
			cursor: pointer;
			font-size: 16px;
			opacity: .6;
			transition: opacity .15s ease;
		}
		.nanobar-settings-wrap .notice .notice-dismiss:hover {
			opacity: 1;
		}
		@keyframes nanobar-notice-slide-in {
			from { opacity: 0; transform: translateY( -8px ); }
			to { opacity: 1; transform: translateY( 0 ); }
		}
		@keyframes nanobar-notice-fade-out {
			from { opacity: 1; }
			to { opacity: 0; }
		}
		.nanobar-bento-grid { display: grid; grid-template-columns: repeat( 12, minmax( 0, 1fr ) ); gap: 18px; margin-bottom: 0; }
		.nanobar-bento-card { min-width: 0; background: #fff; border: 1px solid #dcdcde; border-radius: 12px; overflow: hidden; box-shadow: 0 1px 2px rgba( 0, 0, 0, .04 ); transition: box-shadow .2s ease, border-color .2s ease, transform .2s ease; }
		.nanobar-bento-card:hover { border-color: #c3c4c7; box-shadow: 0 5px 18px rgba( 0, 0, 0, .06 ); }
		.nanobar-bento-card--general { grid-column: span 5; grid-row: span 3; }
		.nanobar-bento-card--position { grid-column: span 3; }
		.nanobar-bento-card--appearance { grid-column: span 4; }
		.nanobar-bento-card--preview { grid-column: span 7; grid-row: span 2; }
		.nanobar-bento-card__header { min-height: 58px; display: flex; align-items: center; gap: 9px; padding: 15px 20px; border-bottom: 1px solid #f0f0f1; background: #fbfbfc; }
		.nanobar-bento-card__header .dashicons { color: #646970; font-size: 18px; width: 18px; height: 18px; }
		.nanobar-bento-card__header h2 { margin: 0; font-size: 16px; line-height: 1.3; font-weight: 600; color: #1d2327; }
		.nanobar-bento-card__body { padding: 20px; }
		.nanobar-form-group { margin-bottom: 22px; }
		.nanobar-form-group--last { margin-bottom: 0; }
		.nanobar-label { display: block; margin-bottom: 8px; font-size: 13px; font-weight: 600; color: #1d2327; }
		.nanobar-help { margin: 7px 0 0; font-size: 12px; line-height: 1.45; color: #646970; }
		.nanobar-switch-row { display: grid; grid-template-columns: 38px minmax( 0, 1fr ); align-items: center; gap: 10px; cursor: pointer; }
		.nanobar-switch-row input { position: absolute; opacity: 0; pointer-events: none; }
		.nanobar-switch { position: relative; width: 36px; height: 20px; border-radius: 999px; background: #8c8f94; transition: background .15s ease; }
		.nanobar-switch::after { content: ''; position: absolute; top: 3px; left: 3px; width: 14px; height: 14px; border-radius: 50%; background: #fff; box-shadow: 0 1px 2px rgba( 0, 0, 0, .2 ); transition: transform .15s ease; }
		.nanobar-switch-row input:checked + .nanobar-switch { background: #2271b1; }
		.nanobar-switch-row input:checked + .nanobar-switch::after { transform: translateX( 16px ); }
		.nanobar-switch-row input:focus-visible + .nanobar-switch { outline: 2px solid #2271b1; outline-offset: 2px; }
		.nanobar-switch-row strong, .nanobar-switch-row small { display: block; }
		.nanobar-switch-row strong { font-size: 13px; line-height: 1.35; color: #1d2327; }
		.nanobar-switch-row small { margin-top: 2px; font-size: 12px; line-height: 1.4; color: #646970; }
		.nanobar-roles-grid { display: grid; grid-template-columns: repeat( 2, minmax( 0, 1fr ) ); gap: 8px; }
		.nanobar-role-option { display: flex; align-items: center; gap: 8px; min-height: 38px; padding: 8px 10px; border: 1px solid #dcdcde; border-radius: 7px; background: #fff; cursor: pointer; transition: border-color .15s ease, background .15s ease; }
		.nanobar-role-option:hover { border-color: #a7aaad; background: #f6f7f7; }
		.nanobar-role-option input { margin: 0; }
		.nanobar-role-option span { font-size: 13px; color: #1d2327; }
		.nanobar-select, .nanobar-input { width: 100%; min-height: 40px; padding: 8px 11px; border: 1px solid #8c8f94; border-radius: 6px; font-size: 13px; background: #fff; box-shadow: none; }
		.nanobar-select:focus, .nanobar-input:focus { border-color: #2271b1; box-shadow: 0 0 0 1px #2271b1; outline: none; }
		.nanobar-input-group { display: flex; align-items: center; gap: 8px; }
		.nanobar-input-group .nanobar-input { flex: 0 0 88px; width: 88px; text-align: center; }
		.nanobar-input-suffix { font-size: 13px; color: #646970; font-weight: 600; }
		.nanobar-preview-container { display: flex; align-items: center; justify-content: center; }
		.nanobar-viewport { position: relative; width: 88%; height: 132px; border: 1px solid #dcdcde; border-radius: 14px; background: linear-gradient( 145deg, #f6f7f7, #fff ); box-shadow: inset 0 1px 2px rgba( 0, 0, 0, .04 ); overflow: hidden; display: flex; padding: 18px; }
		.nanobar-viewport__label { position: absolute; inset: 50% auto auto 50%; transform: translate( -50%, -50% ); width: calc( 100% - 30px ); text-align: center; font-size: 11px; font-weight: 600; color: #646970; text-transform: uppercase; letter-spacing: .45px; pointer-events: none; }
		.nanobar-preview-button { position: relative; z-index: 1; flex: 0 0 auto; border-radius: 50%; display: flex; align-items: center; justify-content: center; box-shadow: 0 3px 10px rgba( 0, 0, 0, .18 ); transition: all .2s ease; }
		.nanobar-actions { display: flex; align-items: center; justify-content: space-between; gap: 16px; margin-top: 18px; padding: 16px 2px 0; border-top: 1px solid #dcdcde; }
		.nanobar-actions__buttons { display: flex; align-items: center; gap: 8px; }
		.nanobar-actions .button { min-height: 38px; padding: 0 14px; }
		.nanobar-actions__hint { display: flex; align-items: center; gap: 6px; margin: 0; color: #646970; font-size: 12px; }
		.nanobar-actions__hint .dashicons { width: 15px; height: 15px; font-size: 15px; }
		@media (max-width: 1100px) {
			.nanobar-bento-card--general { grid-column: span 6; grid-row: auto; }
			.nanobar-bento-card--position, .nanobar-bento-card--appearance { grid-column: span 6; }
			.nanobar-bento-card--preview { grid-column: span 6; grid-row: auto; }
		}
		@media (max-width: 782px) {
			.nanobar-settings-wrap { margin-top: 10px; }
			.nanobar-header { padding: 16px 18px; border-radius: 10px; flex-direction: column; align-items: flex-start; }
			.nanobar-header__content { gap: 12px; }
			.nanobar-header__actions { width: 100%; }
			.nanobar-header__actions .button { width: 100%; }
			.nanobar-header__icon { width: 36px; height: 36px; flex-basis: 36px; }
			.nanobar-header h1 { font-size: 24px; }
			.nanobar-bento-grid { grid-template-columns: 1fr; gap: 14px; }
			.nanobar-bento-card--general, .nanobar-bento-card--position, .nanobar-bento-card--appearance, .nanobar-bento-card--preview { grid-column: auto; grid-row: auto; }
			.nanobar-preview-container { min-height: 290px; }
			.nanobar-actions { align-items: flex-start; flex-direction: column; }
			.nanobar-actions__buttons { width: 100%; flex-wrap: wrap; }
			.nanobar-actions__hint { width: 100%; }
		}
		@media (max-width: 600px) {
			.nanobar-header { padding: 14px 16px; }
			.nanobar-header h1 { font-size: 22px; }
			.nanobar-header p { font-size: 13px; }
			.nanobar-notices-container { margin-top: 20px; padding: 0; }
			.nanobar-settings-wrap .notice { padding: 12px; gap: 10px; }
			.nanobar-settings-wrap .notice p { font-size: 13px; }
			.nanobar-bento-grid { gap: 12px; }
			.nanobar-bento-card__body { padding: 14px; }
			.nanobar-roles-grid { grid-template-columns: 1fr; }
		}
		@media (max-width: 480px) {
			.nanobar-header__icon { width: 32px; height: 32px; flex-basis: 32px; font-size: 16px; }
			.nanobar-header__icon .dashicons { font-size: 18px; }
			.nanobar-header h1 { font-size: 20px; margin-bottom: 2px; }
			.nanobar-bento-card__body { padding: 12px; }
			.nanobar-form-group { margin-bottom: 16px; }
			.nanobar-actions__buttons .button { flex: 1 1 auto; }
		}
	</style>

	<script>
	( function () {
		if ( ! window.jQuery ) {
			return;
		}

		jQuery( function ( $ ) {
			// Defaults and constraints come straight from nanobar_get_default_options()
			// and nanobar_get_config() in PHP: the same single source of truth used by
			// the sanitizer, by the frontend renderer and by the "Restore defaults"
			// button below, so the live preview can never drift from what gets saved.
			var defaults   = <?php echo wp_json_encode( $defaults ); ?>;
			var config     = <?php echo wp_json_encode( $config ); ?>;
			var $posH      = $( '#nanobar-pos-h' );
			var $posV      = $( '#nanobar-pos-v' );
			var $sizeInput = $( '#nanobar-toggle-size-input' );
			var $colorField = $( '#nanobar-color-field' );
			var $viewport  = $( '#nanobar-viewport' );
			var $preview   = $( '#nanobar-preview-button' );
			var $label     = $( '#nanobar-viewport-label' );

			// Client-side mirror of nanobar_get_contrast_color() in PHP, needed because
			// the preview has to react to colors the user has not saved yet. Every
			// number and color it uses comes from config.contrast, so the two stay in
			// sync on their own.
			function contrastColor( hex ) {
				hex = ( hex || '' ).replace( '#', '' );
				if ( 3 === hex.length ) {
					hex = hex[0] + hex[0] + hex[1] + hex[1] + hex[2] + hex[2];
				}
				if ( ! /^[0-9a-fA-F]{6}$/.test( hex ) ) {
					return config.contrast.on_dark;
				}
				var weights = config.contrast.weights;
				var red = parseInt( hex.substr( 0, 2 ), 16 );
				var green = parseInt( hex.substr( 2, 2 ), 16 );
				var blue = parseInt( hex.substr( 4, 2 ), 16 );
				var yiq = ( red * weights.red + green * weights.green + blue * weights.blue ) / 1000;
				return yiq >= config.contrast.threshold ? config.contrast.on_light : config.contrast.on_dark;
			}

			function updatePreview( color ) {
				var size = parseInt( $sizeInput.val(), 10 );
				var posH = $posH.val();
				var posV = $posV.val();
				size = Math.max( config.size.min, Math.min( config.size.max, isNaN( size ) ? defaults.toggle_size : size ) );
				color = color || defaults.toggle_bg_color;

				$preview.css( {
					width: size + 'px',
					height: size + 'px',
					background: color,
					color: contrastColor( color )
				} );

				var alignH = 'left' === posH ? 'flex-start' : ( 'right' === posH ? 'flex-end' : 'center' );
				var alignV = 'bottom' === posV ? 'flex-end' : 'flex-start';
				var labelText = ( 'top' === posV ? '<?php echo esc_js( __( 'Top', 'nanobar' ) ); ?>' : '<?php echo esc_js( __( 'Bottom', 'nanobar' ) ); ?>' );
				labelText += ' · ' + ( 'left' === posH ? '<?php echo esc_js( __( 'Left', 'nanobar' ) ); ?>' : ( 'right' === posH ? '<?php echo esc_js( __( 'Right', 'nanobar' ) ); ?>' : '<?php echo esc_js( __( 'Center', 'nanobar' ) ); ?>' ) );

				$viewport.css( {
					justifyContent: alignH,
					alignItems: alignV
				} );
				$label.text( labelText );
			}

			function updateCenterOption() {
				var allowsCenter = config.positions.center_vertical === $posV.val();
				var $centerOption = $posH.find( 'option[value="center"]' );
				$centerOption.prop( 'disabled', ! allowsCenter );
				if ( ! allowsCenter && 'center' === $posH.val() ) {
					$posH.val( config.positions.center_fallback );
				}
			}

			$colorField.wpColorPicker( {
				change: function ( event, ui ) {
					updatePreview( ui.color.toString() );
				},
				clear: function () {
					updatePreview( $colorField.data( 'default-color' ) );
				}
			} );

			$sizeInput.on( 'input change', function () {
				updatePreview( $colorField.val() );
			} );
			$posH.on( 'change', function () {
				updateCenterOption();
				updatePreview( $colorField.val() );
			} );
			$posV.on( 'change', function () {
				updateCenterOption();
				updatePreview( $colorField.val() );
			} );

			$( '#nanobar-reset-defaults' ).on( 'click', function ( event ) {
				event.preventDefault();
				$( 'input[name="nanobar_options[enabled]"]' ).prop( 'checked', !! defaults.enabled );
				$( 'input[name="nanobar_options[roles][]"]' ).prop( 'checked', function () {
					return defaults.roles.indexOf( $( this ).val() ) !== -1;
				} );
				$posH.val( defaults.position_horizontal );
				$posV.val( defaults.position_vertical );
				$sizeInput.val( defaults.toggle_size );
				$colorField.wpColorPicker( 'color', defaults.toggle_bg_color );
				$( 'input[name="nanobar_options[icons_only]"]' ).prop( 'checked', !! defaults.icons_only );
				$( 'input[name="nanobar_options[force_admin_bar_for_qm]"]' ).prop( 'checked', !! defaults.force_admin_bar_for_qm );
				updateCenterOption();
				updatePreview( defaults.toggle_bg_color );
			} );

			updateCenterOption();
			updatePreview( $colorField.val() );
		} );
	} )();
	</script>
	<script>
	( function () {
		if ( ! window.jQuery ) {
			return;
		}
		jQuery( function ( $ ) {
			var $notices = $( '.nanobar-notices-container .notice' );

			$notices.each( function () {
				var $notice = $( this );

				// Add type icon (success, error, warning)
				if ( ! $notice.find( '.notice-icon' ).length ) {
					$notice.prepend( '<span class="notice-icon"></span>' );
				}

				// Add close button
				if ( ! $notice.find( '.notice-dismiss' ).length ) {
					$notice.append( '<button type="button" class="notice-dismiss" aria-label="Close notification">×</button>' );
				}

				// Auto-dismiss after 5 seconds (success only)
				if ( $notice.hasClass( 'notice-success' ) ) {
					var dismissTimeout = setTimeout( function () {
						$notice.addClass( 'is-dismissing' );
						setTimeout( function () {
							$notice.fadeOut( function () {
								$notice.remove();
							} );
						}, 400 );
					}, 5000 );

					// Clear the timeout if dismissed manually
					$notice.on( 'click.dismiss', '.notice-dismiss', function ( e ) {
						e.preventDefault();
						clearTimeout( dismissTimeout );
						$notice.trigger( 'close.notice-dismiss' );
					} );
				} else {
					// Error and warning: manual close only
					$notice.on( 'click.dismiss', '.notice-dismiss', function ( e ) {
						e.preventDefault();
						$notice.addClass( 'is-dismissing' );
						setTimeout( function () {
							$notice.fadeOut( function () {
								$notice.remove();
							} );
						}, 400 );
					} );
				}
			} );
		} );
	} )();
	</script>
	<?php
}

// Display logic.

/**
 * NanoBar should only be shown if: we're on the frontend, the user is logged
 * in, the plugin is enabled globally, and the user's role is among the
 * enabled ones.
 *
 * @return bool
 */
function nanobar_should_render() {
	if ( is_admin() || ! is_user_logged_in() ) {
		return false;
	}

	$options = nanobar_get_options();
	if ( empty( $options['enabled'] ) ) {
		return false;
	}

	$user = wp_get_current_user();

	return (bool) array_intersect( (array) $options['roles'], (array) $user->roles );
}

/**
 * Technically forces WordPress's native admin bar to stay "active" (the
 * show_admin_bar filter) for Administrators with NanoBar enabled, only when
 * it's actually needed to make the Query Monitor toggle work — even if the
 * user has disabled the bar from their own profile. It still stays visually
 * hidden by NanoBar's CSS; can be disabled from the settings.
 *
 * @param bool $show Current value of the show_admin_bar filter.
 * @return bool
 */
function nanobar_maybe_force_admin_bar( $show ) {
	if ( ! nanobar_should_render() || ! current_user_can( 'manage_options' ) || ! defined( 'QM_VERSION' ) ) {
		return $show;
	}

	$options = nanobar_get_options();
	if ( empty( $options['force_admin_bar_for_qm'] ) ) {
		return $show;
	}

	return true;
}
add_filter( 'show_admin_bar', 'nanobar_maybe_force_admin_bar' );

/**
 * Ensures Dashicons are loaded on the frontend (normally already included
 * automatically by WP together with the admin bar; made explicit here for
 * robustness).
 */
function nanobar_enqueue_assets() {
	if ( ! nanobar_should_render() ) {
		return;
	}
	wp_enqueue_style( 'dashicons' );
}
add_action( 'wp_enqueue_scripts', 'nanobar_enqueue_assets' );

/**
 * Visually hides the admin bar (it stays in the DOM: needed for the Query
 * Monitor toggle) and resets the margin WordPress adds to <html> to make
 * room for it.
 */
function nanobar_hide_admin_bar_css() {
	if ( ! nanobar_should_render() ) {
		return;
	}
	?>
	<style id="nanobar-hide-adminbar">
		#wpadminbar { display: none !important; }
		html { margin-top: 0 !important; }
	</style>
	<?php
}
add_action( 'wp_head', 'nanobar_hide_admin_bar_css', 999 );

/**
 * Determines the "Edit" link for the current context: single content,
 * taxonomy archive (category/tag/custom taxonomy), author archive, date
 * archive, or post type archive.
 *
 * @return array{url:string, label:string}|null
 */
function nanobar_get_context_edit_link() {
	if ( is_singular() ) {
		$queried_id = get_queried_object_id();
		if ( ! current_user_can( 'edit_post', $queried_id ) ) {
			return null;
		}
		$edit_link = get_edit_post_link( $queried_id, '' );
		if ( ! $edit_link ) {
			return null;
		}

		$post_type_obj = get_post_type_object( get_post_type( $queried_id ) );
		$label         = __( 'Edit content', 'nanobar' );
		if ( $post_type_obj && isset( $post_type_obj->labels->singular_name ) ) {
			/* translators: %s: content type name (e.g. Page, Post). */
			$label = sprintf( __( 'Edit %s', 'nanobar' ), nanobar_strtolower( $post_type_obj->labels->singular_name ) );
		}

		return array(
			'url'   => $edit_link,
			'label' => $label,
		);
	}

	if ( is_category() || is_tag() || is_tax() ) {
		$term = get_queried_object();
		if ( ! ( $term instanceof WP_Term ) || ! current_user_can( 'edit_term', $term->term_id ) ) {
			return null;
		}
		$edit_link = get_edit_term_link( $term->term_id, $term->taxonomy );
		if ( ! $edit_link ) {
			return null;
		}

		$tax_obj = get_taxonomy( $term->taxonomy );
		$label   = __( 'Edit term', 'nanobar' );
		if ( $tax_obj && isset( $tax_obj->labels->singular_name ) ) {
			/* translators: %s: taxonomy name (e.g. Category, Tag). */
			$label = sprintf( __( 'Edit %s', 'nanobar' ), nanobar_strtolower( $tax_obj->labels->singular_name ) );
		}

		return array(
			'url'   => $edit_link,
			'label' => $label,
		);
	}

	if ( is_author() ) {
		$author_id = get_queried_object_id();
		if ( ! current_user_can( 'edit_user', $author_id ) ) {
			return null;
		}
		$edit_link = get_edit_user_link( $author_id );
		if ( ! $edit_link ) {
			return null;
		}

		return array(
			'url'   => $edit_link,
			'label' => __( 'Edit user', 'nanobar' ),
		);
	}

	if ( is_date() ) {
		$post_type_obj = get_post_type_object( 'post' );
		if ( ! $post_type_obj || ! current_user_can( $post_type_obj->cap->edit_posts ) ) {
			return null;
		}

		$args  = array( 'post_type' => 'post' );
		$year  = get_query_var( 'year' );
		$month = get_query_var( 'monthnum' );
		if ( $year && $month ) {
			$args['m'] = $year . zeroise( $month, 2 );
		} elseif ( $year ) {
			$args['m'] = $year;
		}

		return array(
			'url'   => add_query_arg( $args, admin_url( 'edit.php' ) ),
			'label' => __( 'Manage posts from this period', 'nanobar' ),
		);
	}

	if ( is_post_type_archive() ) {
		$post_type = get_query_var( 'post_type' );
		if ( is_array( $post_type ) ) {
			$post_type = reset( $post_type );
		}
		$post_type_obj = get_post_type_object( $post_type );
		if ( ! $post_type_obj || ! current_user_can( $post_type_obj->cap->edit_posts ) ) {
			return null;
		}

		/* translators: %s: plural content type name. */
		$label = sprintf( __( 'Manage %s', 'nanobar' ), nanobar_strtolower( $post_type_obj->labels->name ) );

		return array(
			'url'   => admin_url( 'edit.php?post_type=' . $post_type_obj->name ),
			'label' => $label,
		);
	}

	return null;
}

/**
 * Builds, in priority order, the block template hierarchy (following the
 * same naming convention as classic PHP templates) for the current frontend
 * context, including any custom template manually assigned to a single
 * piece of content.
 *
 * @return array<int, string>
 */
function nanobar_get_current_template_hierarchy() {
	$hierarchy = array();

	if ( is_front_page() ) {
		$hierarchy[] = 'front-page';

		if ( is_home() ) {
			$hierarchy[] = 'home';
		} else {
			$queried_id = get_queried_object_id();
			$assigned   = get_page_template_slug( $queried_id );
			if ( $assigned && false === strpos( $assigned, '.php' ) ) {
				$hierarchy[] = $assigned;
			}
			$post_obj = get_post( $queried_id );
			if ( $post_obj ) {
				$hierarchy[] = 'page-' . $post_obj->post_name;
				$hierarchy[] = 'page-' . $post_obj->ID;
			}
			$hierarchy[] = 'page';
			$hierarchy[] = 'singular';
		}

		$hierarchy[] = 'index';
		return $hierarchy;
	}

	if ( is_home() ) {
		$hierarchy[] = 'home';
		$hierarchy[] = 'index';
		return $hierarchy;
	}

	if ( is_singular() ) {
		$queried_id = get_queried_object_id();
		$assigned   = get_page_template_slug( $queried_id );
		if ( $assigned && false === strpos( $assigned, '.php' ) ) {
			$hierarchy[] = $assigned;
		}

		if ( is_page() ) {
			$post_obj = get_post( $queried_id );
			if ( $post_obj ) {
				$hierarchy[] = 'page-' . $post_obj->post_name;
				$hierarchy[] = 'page-' . $post_obj->ID;
			}
			$hierarchy[] = 'page';
		} elseif ( is_attachment() ) {
			$mime_type = get_post_mime_type( $queried_id );
			if ( $mime_type ) {
				$mime_parts = array_pad( explode( '/', $mime_type ), 2, '' );
				if ( ! empty( $mime_parts[1] ) ) {
					$hierarchy[] = 'single-attachment-' . $mime_parts[1];
				}
				$hierarchy[] = 'single-attachment-' . $mime_parts[0];
			}
			$hierarchy[] = 'attachment';
		} else {
			$post_type = get_post_type( $queried_id );
			$post_obj  = get_post( $queried_id );
			if ( $post_obj ) {
				$hierarchy[] = 'single-' . $post_type . '-' . $post_obj->post_name;
			}
			$hierarchy[] = 'single-' . $post_type;
			$hierarchy[] = 'single';
		}

		$hierarchy[] = 'singular';
		$hierarchy[] = 'index';
		return $hierarchy;
	}

	if ( is_category() ) {
		$term = get_queried_object();
		if ( $term instanceof WP_Term ) {
			$hierarchy[] = 'category-' . $term->slug;
			$hierarchy[] = 'category-' . $term->term_id;
		}
		$hierarchy[] = 'category';
		$hierarchy[] = 'archive';
		$hierarchy[] = 'index';
		return $hierarchy;
	}

	if ( is_tag() ) {
		$term = get_queried_object();
		if ( $term instanceof WP_Term ) {
			$hierarchy[] = 'tag-' . $term->slug;
			$hierarchy[] = 'tag-' . $term->term_id;
		}
		$hierarchy[] = 'tag';
		$hierarchy[] = 'archive';
		$hierarchy[] = 'index';
		return $hierarchy;
	}

	if ( is_tax() ) {
		$term = get_queried_object();
		if ( $term instanceof WP_Term ) {
			$hierarchy[] = 'taxonomy-' . $term->taxonomy . '-' . $term->slug;
			$hierarchy[] = 'taxonomy-' . $term->taxonomy;
		}
		$hierarchy[] = 'taxonomy';
		$hierarchy[] = 'archive';
		$hierarchy[] = 'index';
		return $hierarchy;
	}

	if ( is_author() ) {
		$author_obj = get_queried_object();
		if ( $author_obj instanceof WP_User ) {
			$hierarchy[] = 'author-' . $author_obj->user_nicename;
			$hierarchy[] = 'author-' . $author_obj->ID;
		}
		$hierarchy[] = 'author';
		$hierarchy[] = 'archive';
		$hierarchy[] = 'index';
		return $hierarchy;
	}

	if ( is_date() ) {
		$hierarchy[] = 'date';
		$hierarchy[] = 'archive';
		$hierarchy[] = 'index';
		return $hierarchy;
	}

	if ( is_post_type_archive() ) {
		$post_type = get_query_var( 'post_type' );
		if ( is_array( $post_type ) ) {
			$post_type = reset( $post_type );
		}
		if ( $post_type ) {
			$hierarchy[] = 'archive-' . $post_type;
		}
		$hierarchy[] = 'archive';
		$hierarchy[] = 'index';
		return $hierarchy;
	}

	if ( is_search() ) {
		$hierarchy[] = 'search';
		$hierarchy[] = 'index';
		return $hierarchy;
	}

	if ( is_404() ) {
		$hierarchy[] = '404';
		$hierarchy[] = 'index';
		return $hierarchy;
	}

	$hierarchy[] = 'index';
	return $hierarchy;
}

/**
 * Walks up the hierarchy of candidate slugs until it finds a block template
 * that actually exists in the active theme (theme file or DB-saved
 * override).
 *
 * @param array<int, string> $hierarchy Candidate slugs, from most specific to most generic.
 * @return WP_Block_Template|null
 */
function nanobar_resolve_block_template( $hierarchy ) {
	$theme = get_stylesheet();
	foreach ( $hierarchy as $slug ) {
		$template = get_block_template( $theme . '//' . $slug, 'wp_template' );
		if ( $template ) {
			return $template;
		}
	}
	return null;
}

/**
 * Builds the "Edit site" link: if the theme is block-based (FSE), it opens
 * the Site Editor directly on the template that is actually rendering the
 * current page (also respecting custom templates assigned manually); if the
 * theme is classic, it opens Appearance → Themes.
 *
 * @return array{url:string, label:string, title:string}
 */
function nanobar_get_site_editor_link() {
	if ( ! wp_is_block_theme() ) {
		return array(
			'url'   => admin_url( 'themes.php' ),
			'label' => __( 'Appearance: Themes', 'nanobar' ),
			'title' => __( 'Appearance: Themes', 'nanobar' ),
		);
	}

	$template = nanobar_resolve_block_template( nanobar_get_current_template_hierarchy() );

	if ( ! $template ) {
		return array(
			'url'   => admin_url( 'site-editor.php' ),
			'label' => __( 'Edit site', 'nanobar' ),
			'title' => __( 'Edit site', 'nanobar' ),
		);
	}

	$url = add_query_arg(
		array(
			'postId'   => $template->theme . '//' . $template->slug,
			'postType' => 'wp_template',
			'canvas'   => 'edit',
		),
		admin_url( 'site-editor.php' )
	);

	$title = ! empty( $template->title ) ? $template->title : __( 'Current template', 'nanobar' );

	return array(
		'url'   => $url,
		'label' => __( 'Edit site', 'nanobar' ),
		/* translators: %s: block template name. */
		'title' => sprintf( __( 'Template: %s', 'nanobar' ), $title ),
	);
}

/**
 * Builds the list of items for the "Dashboard" submenu, visible only to
 * Administrators: Dashboard, Media, Plugins, Settings, and Contact (if
 * Contact Form 7 is active).
 *
 * @return array<int, array{label:string, url:string, icon:string}>
 */
function nanobar_get_dashboard_submenu_items() {
	$items = array();

	if ( current_user_can( 'upload_files' ) ) {
		$items[] = array(
			'label' => __( 'Media', 'nanobar' ),
			'url'   => admin_url( 'upload.php' ),
			'icon'  => 'dashicons-admin-media',
		);
	}

	if ( current_user_can( 'edit_posts' ) ) {
		$items[] = array(
			'label' => __( 'Posts', 'nanobar' ),
			'url'   => admin_url( 'edit.php' ),
			'icon'  => 'dashicons-admin-post',
		);
	}

	if ( current_user_can( 'edit_pages' ) ) {
		$items[] = array(
			'label' => __( 'Pages', 'nanobar' ),
			'url'   => admin_url( 'edit.php?post_type=page' ),
			'icon'  => 'dashicons-admin-page',
		);
	}

	$items[] = array(
		'label' => __( 'Plugins', 'nanobar' ),
		'url'   => admin_url( 'plugins.php' ),
		'icon'  => 'dashicons-admin-plugins',
	);
	$items[] = array(
		'label' => __( 'Settings', 'nanobar' ),
		'url'   => admin_url( 'options-general.php' ),
		'icon'  => 'dashicons-admin-settings',
	);

	if ( defined( 'WPCF7_VERSION' ) ) {
		$items[] = array(
			'label' => __( 'Contact', 'nanobar' ),
			'url'   => admin_url( 'admin.php?page=wpcf7' ),
			'icon'  => 'dashicons-feedback',
		);
	}

	return $items;
}

/**
 * Builds the list of items for the "New" submenu: Post, Page, Media, and
 * all public CPTs the current user can create.
 *
 * @return array<int, array{label:string, url:string, icon:string}>
 */
function nanobar_get_new_content_items() {
	$items = array();

	$post_type_obj = get_post_type_object( 'post' );
	if ( $post_type_obj && current_user_can( $post_type_obj->cap->create_posts ) ) {
		$items[] = array(
			'label' => $post_type_obj->labels->singular_name,
			'url'   => admin_url( 'post-new.php' ),
			'icon'  => 'dashicons-admin-post',
		);
	}

	$page_type_obj = get_post_type_object( 'page' );
	if ( $page_type_obj && current_user_can( $page_type_obj->cap->create_posts ) ) {
		$items[] = array(
			'label' => $page_type_obj->labels->singular_name,
			'url'   => admin_url( 'post-new.php?post_type=page' ),
			'icon'  => 'dashicons-admin-page',
		);
	}

	if ( current_user_can( 'upload_files' ) ) {
		$items[] = array(
			'label' => __( 'Media', 'nanobar' ),
			'url'   => admin_url( 'media-new.php' ),
			'icon'  => 'dashicons-admin-media',
		);
	}

	$custom_types = get_post_types(
		array(
			'public'   => true,
			'show_ui'  => true,
			'_builtin' => false,
		),
		'objects'
	);

	foreach ( $custom_types as $cpt ) {
		if ( ! current_user_can( $cpt->cap->create_posts ) ) {
			continue;
		}

		$icon = 'dashicons-admin-post';
		if ( ! empty( $cpt->menu_icon ) && is_string( $cpt->menu_icon ) && 0 === strpos( $cpt->menu_icon, 'dashicons-' ) ) {
			$icon = $cpt->menu_icon;
		}

		$items[] = array(
			'label' => $cpt->labels->singular_name,
			'url'   => admin_url( 'post-new.php?post_type=' . $cpt->name ),
			'icon'  => $icon,
		);
	}

	return $items;
}

/**
 * Builds the logout link for the panel.
 *
 * By default the user lands back on the page they were reading, which is the
 * useful behavior for a frontend bar; wp_logout_url() adds the logout nonce and
 * WordPress validates the redirect with wp_safe_redirect(), so it can never
 * leave the site. Use the `nanobar_logout_redirect` filter to send people
 * somewhere else (the home page, the login screen, a "see you soon" page).
 *
 * @return array{url:string, label:string}
 */
function nanobar_get_logout_link() {
	global $wp;

	$redirect = home_url( '/' );
	if ( isset( $wp->request ) ) {
		$redirect = home_url( add_query_arg( array(), $wp->request ) );
	}

	/**
	 * Filters where the user lands after logging out from NanoBar.
	 *
	 * @param string $redirect Absolute URL. Defaults to the current page.
	 */
	$redirect = apply_filters( 'nanobar_logout_redirect', $redirect );

	return array(
		'url'   => wp_logout_url( $redirect ),
		'label' => __( 'Log out', 'nanobar' ),
	);
}

/**
 * Calculates an icon color (white or charcoal) that is readable against the
 * background color chosen for the button, using the perceived-luminance YIQ
 * formula.
 *
 * The settings page mirrors this in JS as contrastColor(), because the live
 * preview must react to colors that have not been saved yet. Both read their
 * weights, threshold and colors from nanobar_get_config(), so tuning the rule
 * there updates both at once.
 *
 * @param string $hex_color Background color in hex format (e.g. #1d2327).
 * @return string Icon color in hex format.
 */
function nanobar_get_contrast_color( $hex_color ) {
	$contrast = nanobar_get_config()['contrast'];

	$hex = ltrim( (string) $hex_color, '#' );
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

/**
 * Markup of the floating panel + CSS + JS, injected in the footer.
 */
function nanobar_render_panel() {
	if ( ! nanobar_should_render() ) {
		return;
	}

	$options  = nanobar_get_options();
	$defaults = nanobar_get_default_options();
	$position = nanobar_normalize_position( $options['position_horizontal'], $options['position_vertical'] );
	$pos_h    = $position['horizontal'];
	$pos_v    = $position['vertical'];

	$wrapper_classes = sprintf(
		'nanobar nanobar--%1$s nanobar--%2$s%3$s',
		$pos_h,
		$pos_v,
		! empty( $options['icons_only'] ) ? ' nanobar--icons-only' : ''
	);

	$toggle_size   = nanobar_clamp_toggle_size( $options['toggle_size'] );
	$toggle_color  = ! empty( $options['toggle_bg_color'] ) ? $options['toggle_bg_color'] : $defaults['toggle_bg_color'];
	$toggle_fg     = nanobar_get_contrast_color( $toggle_color );
	$wrapper_style = sprintf( '--nanobar-toggle-size:%1$dpx;--nanobar-toggle-bg:%2$s;--nanobar-toggle-fg:%3$s;', $toggle_size, $toggle_color, $toggle_fg );

	$is_site_manager = current_user_can( 'edit_theme_options' );
	$is_super_admin  = current_user_can( 'manage_options' );

	$context_edit      = nanobar_get_context_edit_link();
	$site_editor       = $is_site_manager ? nanobar_get_site_editor_link() : null;
	$qm_active         = defined( 'QM_VERSION' );
	$new_content_items = nanobar_get_new_content_items();
	$dashboard_submenu = $is_super_admin ? nanobar_get_dashboard_submenu_items() : array();
	$dashboard_link    = admin_url();
	$logout            = nanobar_get_logout_link();

	$show_qm_button = false;
	if ( $qm_active && $is_super_admin ) {
		if ( ! empty( $options['force_admin_bar_for_qm'] ) ) {
			$show_qm_button = true;
		} else {
			$bar_pref       = get_user_meta( get_current_user_id(), 'show_admin_bar_front', true );
			$show_qm_button = ( '' === $bar_pref ) ? true : ( 'true' === $bar_pref );
		}
	}
	?>
	<div id="nanobar" class="<?php echo esc_attr( $wrapper_classes ); ?>" style="<?php echo esc_attr( $wrapper_style ); ?>">
		<button type="button" class="nanobar__toggle" aria-expanded="false" aria-controls="nanobar-menu" aria-label="<?php esc_attr_e( 'Open/close quick admin menu', 'nanobar' ); ?>" title="<?php esc_attr_e( 'Open/close quick admin menu', 'nanobar' ); ?>">
			<span class="dashicons dashicons-admin-generic nanobar__toggle-icon" aria-hidden="true"></span>
		</button>

		<div class="nanobar__menu" id="nanobar-menu">
			<?php if ( ! empty( $dashboard_submenu ) ) : ?>
				<div class="nanobar__item-group" data-submenu="dashboard">
					<a class="nanobar__item nanobar__item--split" href="<?php echo esc_url( $dashboard_link ); ?>" title="<?php esc_attr_e( 'Dashboard', 'nanobar' ); ?>">
						<span class="dashicons dashicons-dashboard nanobar__icon" aria-hidden="true"></span>
						<span class="nanobar__item-label"><?php esc_html_e( 'Dashboard', 'nanobar' ); ?></span>
					</a>
					<button type="button" class="nanobar__submenu-toggle" aria-expanded="false" aria-controls="nanobar-dashboard-submenu" aria-label="<?php esc_attr_e( 'Open Dashboard submenu', 'nanobar' ); ?>" title="<?php esc_attr_e( 'Open Dashboard submenu', 'nanobar' ); ?>">
						<span class="dashicons dashicons-arrow-right-alt2" aria-hidden="true"></span>
					</button>
					<div class="nanobar__submenu" id="nanobar-dashboard-submenu" aria-hidden="true">
						<?php foreach ( $dashboard_submenu as $item ) : ?>
							<a href="<?php echo esc_url( $item['url'] ); ?>" title="<?php echo esc_attr( $item['label'] ); ?>">
								<span class="dashicons <?php echo esc_attr( $item['icon'] ); ?>" aria-hidden="true"></span>
								<span class="nanobar__submenu-label"><?php echo esc_html( $item['label'] ); ?></span>
							</a>
						<?php endforeach; ?>
					</div>
				</div>
			<?php else : ?>
				<a class="nanobar__item" href="<?php echo esc_url( $dashboard_link ); ?>" title="<?php esc_attr_e( 'Dashboard', 'nanobar' ); ?>">
					<span class="dashicons dashicons-dashboard nanobar__icon" aria-hidden="true"></span>
					<span class="nanobar__item-label"><?php esc_html_e( 'Dashboard', 'nanobar' ); ?></span>
				</a>
			<?php endif; ?>

			<?php if ( $context_edit ) : ?>
				<a class="nanobar__item" href="<?php echo esc_url( $context_edit['url'] ); ?>" title="<?php echo esc_attr( $context_edit['label'] ); ?>">
					<span class="dashicons dashicons-edit nanobar__icon" aria-hidden="true"></span>
					<span class="nanobar__item-label"><?php echo esc_html( $context_edit['label'] ); ?></span>
				</a>
			<?php endif; ?>

			<?php if ( $site_editor ) : ?>
				<a class="nanobar__item" href="<?php echo esc_url( $site_editor['url'] ); ?>" title="<?php echo esc_attr( $site_editor['title'] ); ?>">
					<span class="dashicons dashicons-admin-appearance nanobar__icon" aria-hidden="true"></span>
					<span class="nanobar__item-label"><?php echo esc_html( $site_editor['label'] ); ?></span>
				</a>
			<?php endif; ?>

			<?php if ( ! empty( $new_content_items ) ) : ?>
				<div class="nanobar__item-group" data-submenu="new">
					<button type="button" class="nanobar__item nanobar__item--split nanobar__item--submenu-trigger" aria-expanded="false" aria-controls="nanobar-new-submenu" title="<?php esc_attr_e( 'New', 'nanobar' ); ?>">
						<span class="dashicons dashicons-plus-alt nanobar__icon" aria-hidden="true"></span>
						<span class="nanobar__item-label"><?php esc_html_e( 'New', 'nanobar' ); ?></span>
					</button>
					<button type="button" class="nanobar__submenu-toggle" aria-expanded="false" aria-controls="nanobar-new-submenu" aria-label="<?php esc_attr_e( 'Open New submenu', 'nanobar' ); ?>" title="<?php esc_attr_e( 'Open New submenu', 'nanobar' ); ?>">
						<span class="dashicons dashicons-arrow-right-alt2" aria-hidden="true"></span>
					</button>
					<div class="nanobar__submenu" id="nanobar-new-submenu" aria-hidden="true">
						<?php foreach ( $new_content_items as $item ) : ?>
							<a href="<?php echo esc_url( $item['url'] ); ?>" title="<?php echo esc_attr( $item['label'] ); ?>">
								<span class="dashicons <?php echo esc_attr( $item['icon'] ); ?>" aria-hidden="true"></span>
								<span class="nanobar__submenu-label"><?php echo esc_html( $item['label'] ); ?></span>
							</a>
						<?php endforeach; ?>
					</div>
				</div>
			<?php endif; ?>

			<?php if ( $show_qm_button ) : ?>
				<button type="button" class="nanobar__item nanobar__item--button" id="nanobar-qm-toggle" title="Query Monitor">
					<span class="dashicons dashicons-code-standards nanobar__icon" aria-hidden="true"></span>
					<span class="nanobar__item-label">Query Monitor</span>
				</button>
			<?php endif; ?>

			<a class="nanobar__item nanobar__item--logout" href="<?php echo esc_url( $logout['url'] ); ?>" title="<?php echo esc_attr( $logout['label'] ); ?>">
				<span class="dashicons dashicons-exit nanobar__icon" aria-hidden="true"></span>
				<span class="nanobar__item-label"><?php echo esc_html( $logout['label'] ); ?></span>
			</a>
		</div>
	</div>

	<style>
		.nanobar { --nanobar-font: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, Oxygen-Sans, Ubuntu, Cantarell, "Helvetica Neue", sans-serif; position: fixed; z-index: 999999; font-family: var( --nanobar-font ); font-size: 16px; }
		.nanobar [hidden] { display: none !important; }
		.nanobar--left { left: max( 16px, env( safe-area-inset-left ) ); }
		.nanobar--center { left: 50%; transform: translateX( -50% ); }
		.nanobar--right { right: max( 16px, env( safe-area-inset-right ) ); }
		.nanobar--bottom { bottom: max( 16px, env( safe-area-inset-bottom ) ); }
		.nanobar--top { top: max( 16px, env( safe-area-inset-top ) ); }
		.nanobar__toggle { width: var( --nanobar-toggle-size, 44px ); height: var( --nanobar-toggle-size, 44px ); border-radius: 50%; border: none; background: var( --nanobar-toggle-bg, #1d2327 ); color: var( --nanobar-toggle-fg, #fff ); cursor: pointer; display: flex; align-items: center; justify-content: center; transition: transform .15s ease, box-shadow .15s ease; }
		.nanobar__toggle:hover, .nanobar__toggle:focus-visible { transform: scale( 1.06 ); }
		.nanobar__toggle:focus-visible, .nanobar__item:focus-visible, .nanobar__submenu-toggle:focus-visible, .nanobar__submenu a:focus-visible { outline: 2px solid #72aee6; outline-offset: 2px; }
		.nanobar__toggle-icon { font-size: calc( var( --nanobar-toggle-size, 44px ) * .5 ); width: calc( var( --nanobar-toggle-size, 44px ) * .5 ); height: calc( var( --nanobar-toggle-size, 44px ) * .5 ); line-height: 1; }
		.nanobar__menu { position: absolute; background: #1d2327; border-radius: 9px; padding: 6px; min-width: 210px; max-width: min( calc( 100vw - 32px ), 360px ); box-shadow: 0 8px 24px rgba( 0, 0, 0, .28 ); display: none; flex-direction: column; gap: 2px; }
		.nanobar.is-open .nanobar__menu { display: flex; }
		.nanobar--bottom .nanobar__menu { bottom: calc( var( --nanobar-toggle-size, 44px ) + 8px ); top: auto; }
		.nanobar--top .nanobar__menu { top: calc( var( --nanobar-toggle-size, 44px ) + 8px ); bottom: auto; }
		.nanobar--left .nanobar__menu { left: 0; right: auto; }
		.nanobar--center .nanobar__menu { left: 50%; right: auto; transform: translateX( -50% ); }
		.nanobar--right .nanobar__menu { right: 0; left: auto; }
		.nanobar__item { display: flex; align-items: center; gap: 8px; box-sizing: border-box; color: #f0f0f1 !important; text-decoration: none !important; font-family: var( --nanobar-font ) !important; padding: 9px 10px; min-height: 40px; border-radius: 5px; cursor: pointer; background: none; border: none; font-size: 15px; text-align: left; width: 100%; }
		.nanobar__item:hover, .nanobar__item:focus-visible, .nanobar__submenu-toggle:hover, .nanobar__submenu-toggle:focus-visible { background: rgba( 255, 255, 255, .09 ); color: #f0f0f1 !important; text-decoration: none !important; }
		.nanobar .dashicons.nanobar__icon { font-size: 18px; width: 18px; height: 18px; line-height: 1; flex: 0 0 auto; }
		.nanobar__item-group { position: relative; display: grid; grid-template-columns: minmax( 0, 1fr ) 30px; gap: 2px; }
		.nanobar__item--split { width: auto; }
		.nanobar__submenu-toggle { display: flex; align-items: center; justify-content: center; width: 30px; min-height: 40px; padding: 0; border: 0; border-radius: 5px; background: transparent; color: #f0f0f1; cursor: pointer; }
		.nanobar__submenu-toggle .dashicons { font-size: 15px; width: 15px; height: 15px; transition: transform .15s ease; }
		.nanobar__item-group.is-open > .nanobar__submenu-toggle .dashicons { transform: rotate( 90deg ); }
		.nanobar__item-group.is-open > .nanobar__submenu { display: flex; }
		.nanobar__submenu { display: none; flex-direction: column; position: absolute; top: 0; z-index: 2; background: #2c3338; border-radius: 6px; padding: 4px; min-width: 170px; box-shadow: 0 6px 18px rgba( 0, 0, 0, .3 ); }
		.nanobar--left .nanobar__submenu { left: calc( 100% + 4px ); right: auto; }
		.nanobar--center .nanobar__submenu { left: 100%; right: auto; }
		.nanobar--right .nanobar__submenu { right: calc( 100% + 4px ); left: auto; }
		.nanobar--bottom .nanobar__submenu { top: auto; bottom: 0; }
		.nanobar--top .nanobar__submenu { top: 0; bottom: auto; }
		.nanobar__submenu a { display: flex; align-items: center; gap: 8px; color: #f0f0f1 !important; text-decoration: none !important; font-family: var( --nanobar-font ) !important; padding: 8px 10px; min-height: 34px; border-radius: 4px; font-size: 14px; }
		.nanobar__submenu a:hover, .nanobar__submenu a:focus-visible { background: rgba( 255, 255, 255, .09 ); color: #f0f0f1 !important; }
		.nanobar__submenu .dashicons { flex: 0 0 auto; font-size: 16px; width: 16px; height: 16px; }
		.nanobar__item--submenu-trigger { justify-content: flex-start; }
		.nanobar__item--logout { position: relative; margin-top: 7px; }
		.nanobar__item--logout::before { content: ''; position: absolute; top: -4px; left: 4px; right: 4px; height: 1px; background: rgba( 255, 255, 255, .14 ); }
		.nanobar__item--logout:hover, .nanobar__item--logout:focus-visible { background: rgba( 224, 67, 67, .22 ); }
		.nanobar--icons-only .nanobar__menu { min-width: 73px;}
		.nanobar--icons-only .nanobar__item {     justify-content: left !important;}
		.nanobar--icons-only .nanobar__item-label, .nanobar--icons-only-auto .nanobar__item-label { position: absolute !important; width: 1px; height: 1px; padding: 0; margin: -1px; overflow: hidden; clip: rect( 0, 0, 0, 0 ); white-space: nowrap; border: 0; }
		.nanobar--icons-only .nanobar__item, .nanobar--icons-only-auto .nanobar__item { justify-content: center; gap: 0; padding: 6px; }
		.nanobar--icons-only .nanobar__item-group, .nanobar--icons-only-auto .nanobar__item-group { grid-template-columns: minmax( 0, 1fr ) 24px; }
		.nanobar--icons-only .nanobar__item-group > .nanobar__item--split, .nanobar--icons-only-auto .nanobar__item-group > .nanobar__item--split { min-width: 40px; }
		.nanobar--icons-only .nanobar__submenu-toggle, .nanobar--icons-only-auto .nanobar__submenu-toggle { width: 24px; min-height: 38px; }
		.nanobar--icons-only .nanobar__icon, .nanobar--icons-only-auto .nanobar__icon { font-size: 24px !important; width: 24px !important; height: 24px !important; }
		.nanobar--icons-only .nanobar__submenu, .nanobar--icons-only-auto .nanobar__submenu { min-width: 0; }
		.nanobar--icons-only .nanobar__submenu a, .nanobar--icons-only-auto .nanobar__submenu a { min-width: 48px; justify-content: center; padding: 6px; }
		.nanobar--icons-only .nanobar__submenu-label, .nanobar--icons-only-auto .nanobar__submenu-label { position: absolute !important; width: 1px; height: 1px; padding: 0; margin: -1px; overflow: hidden; clip: rect( 0, 0, 0, 0 ); white-space: nowrap; border: 0; }
		@media ( max-width: 600px ) {
			.nanobar { max-width: calc( 100vw - 16px ); }
			.nanobar--left { left: max( 8px, env( safe-area-inset-left ) ); }
			.nanobar--right { right: max( 8px, env( safe-area-inset-right ) ); }
			.nanobar--bottom { bottom: max( 8px, env( safe-area-inset-bottom ) ); }
			.nanobar--top { top: max( 8px, env( safe-area-inset-top ) ); }
			.nanobar__menu { min-width: min( 230px, calc( 100vw - 16px ) ); max-width: calc( 100vw - 16px ); }
			.nanobar--center .nanobar__menu { width: min( 230px, calc( 100vw - 16px ) ); }
			.nanobar__submenu { position: static; grid-column: 1 / -1; min-width: 0; width: auto !important; margin: 0 0 2px; box-shadow: none; background: rgba( 0, 0, 0, .12 ); }
			.nanobar__item-group.is-open { background: rgba( 255, 255, 255, .04 ); border-radius: 5px; }
		}
		@media print { .nanobar { display: none !important; } }
	</style>

	<script>
	( function () {
		var wrap = document.getElementById( 'nanobar' );
		if ( ! wrap ) {
			return;
		}

		var toggle = wrap.querySelector( '.nanobar__toggle' );
		var groups = wrap.querySelectorAll( '.nanobar__item-group' );
		var STORAGE_KEY = 'nanobar_open';

		function setMenuState( open ) {
			wrap.classList.toggle( 'is-open', open );
			toggle.setAttribute( 'aria-expanded', open ? 'true' : 'false' );
			try {
				localStorage.setItem( STORAGE_KEY, open ? '1' : '0' );
			} catch ( err ) {
				// Persistence not available: not a blocking error.
			}
		}

		function closeSubmenus( except ) {
			groups.forEach( function ( group ) {
				if ( group === except ) {
					return;
				}
				group.classList.remove( 'is-open' );
				var button = group.querySelector( ':scope > .nanobar__submenu-toggle' );
				var trigger = group.querySelector( ':scope > .nanobar__item--submenu-trigger' );
				var submenu = group.querySelector( ':scope > .nanobar__submenu' );
				if ( button ) {
					button.setAttribute( 'aria-expanded', 'false' );
				}
				if ( trigger ) {
					trigger.setAttribute( 'aria-expanded', 'false' );
				}
				if ( submenu ) {
					submenu.setAttribute( 'aria-hidden', 'true' );
				}
			} );
		}

		function toggleSubmenu( group ) {
			var open = ! group.classList.contains( 'is-open' );
			closeSubmenus( group );
			group.classList.toggle( 'is-open', open );
			var button = group.querySelector( ':scope > .nanobar__submenu-toggle' );
			var trigger = group.querySelector( ':scope > .nanobar__item--submenu-trigger' );
			var submenu = group.querySelector( ':scope > .nanobar__submenu' );
			if ( button ) {
				button.setAttribute( 'aria-expanded', open ? 'true' : 'false' );
			}
			if ( trigger ) {
				trigger.setAttribute( 'aria-expanded', open ? 'true' : 'false' );
			}
			if ( submenu ) {
				submenu.setAttribute( 'aria-hidden', open ? 'false' : 'true' );
			}
		}

		groups.forEach( function ( group ) {
			var button = group.querySelector( ':scope > .nanobar__submenu-toggle' );
			var trigger = group.querySelector( ':scope > .nanobar__item--submenu-trigger' );
			if ( button ) {
				button.addEventListener( 'click', function ( event ) {
					event.preventDefault();
					event.stopPropagation();
					toggleSubmenu( group );
				} );
			}
			if ( trigger ) {
				trigger.addEventListener( 'click', function ( event ) {
					event.preventDefault();
					event.stopPropagation();
					toggleSubmenu( group );
				} );
			}
		} );

		toggle.addEventListener( 'click', function ( event ) {
			event.stopPropagation();
			if ( wrap.classList.contains( 'is-open' ) ) {
				setMenuState( false );
				closeSubmenus();
			} else {
				setMenuState( true );
			}
		} );

		document.addEventListener( 'click', function ( event ) {
			if ( ! wrap.contains( event.target ) ) {
				setMenuState( false );
				closeSubmenus();
			}
		} );

		document.addEventListener( 'keydown', function ( event ) {
			if ( 'Escape' !== event.key ) {
				return;
			}
			if ( wrap.classList.contains( 'is-open' ) ) {
				setMenuState( false );
				closeSubmenus();
				toggle.focus();
			}
		} );

		try {
			if ( '1' === localStorage.getItem( STORAGE_KEY ) ) {
				setMenuState( true );
			}
		} catch ( err ) {
			// Persistence not available: starts closed.
		}

		var narrowQuery = window.matchMedia( '(max-width: 400px)' );
		function updateAutoIconsOnly( mediaQuery ) {
			wrap.classList.toggle( 'nanobar--icons-only-auto', mediaQuery.matches );
		}
		updateAutoIconsOnly( narrowQuery );
		if ( narrowQuery.addEventListener ) {
			narrowQuery.addEventListener( 'change', updateAutoIconsOnly );
		} else if ( narrowQuery.addListener ) {
			narrowQuery.addListener( updateAutoIconsOnly );
		}

		var qmBtn = document.getElementById( 'nanobar-qm-toggle' );
		if ( qmBtn ) {
			// The Query Monitor button works by proxying a click to the real toggle
			// inside the (visually hidden) native admin bar.
			var findQmNode = function () {
				return document.querySelector( '#wp-admin-bar-query-monitor > a' ) ||
					document.querySelector( '#wp-admin-bar-query-monitor a' );
			};

			// The native admin bar is printed after this panel (wp_admin_bar_render
			// runs on wp_footer at priority 1000, this markup at 999), so the node
			// cannot be looked up yet while the document is still parsing: if the
			// toggle is nowhere to be found once parsing is done, hide our button
			// instead of leaving a dead control in the menu.
			var syncQmButton = function () {
				if ( ! findQmNode() ) {
					qmBtn.hidden = true;
				}
			};

			if ( 'loading' === document.readyState ) {
				document.addEventListener( 'DOMContentLoaded', syncQmButton );
			} else {
				syncQmButton();
			}

			qmBtn.addEventListener( 'click', function () {
				var qmNode = findQmNode();
				if ( qmNode ) {
					qmNode.click();
					return;
				}
				qmBtn.hidden = true;
				if ( window.console && window.console.warn ) {
					console.warn( 'NanoBar: Query Monitor node not found in the admin bar (#wp-admin-bar-query-monitor). Is the admin bar being removed by the theme or another plugin?' );
				}
			} );
		}
	} )();
	</script>
	<?php
}
add_action( 'wp_footer', 'nanobar_render_panel', 999 );