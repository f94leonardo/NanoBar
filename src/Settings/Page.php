<?php
/**
 * Settings → NanoBar admin page.
 *
 * @package NanoBar
 */

declare( strict_types=1 );

namespace NanoBar\Settings;

use NanoBar\Config;

defined( 'ABSPATH' ) || exit;

/**
 * Registers and renders the Settings → NanoBar admin page.
 */
final class Page {

	/**
	 * Slug of the settings page, as registered with add_options_page().
	 *
	 * @var string
	 */
	private const MENU_SLUG = 'nanobar';

	/**
	 * Registers the option in the Settings API.
	 */
	public function register_settings(): void {
		register_setting( 'nanobar_settings_group', Options::OPTION_NAME, array( Sanitizer::class, 'sanitize' ) );
	}

	/**
	 * Registers the Settings → NanoBar menu entry.
	 */
	public function register_menu(): void {
		add_options_page(
			__( 'NanoBar', 'nanobar' ),
			__( 'NanoBar', 'nanobar' ),
			'manage_options',
			self::MENU_SLUG,
			array( $this, 'render' )
		);
	}

	/**
	 * Loads this page's assets: WordPress's native color picker, plus the
	 * plugin's own compiled admin stylesheet/script for the bento layout and
	 * the live preview, only on the NanoBar settings page.
	 *
	 * @param string $hook_suffix Identifier of the current admin screen.
	 */
	public function enqueue_assets( string $hook_suffix ): void {
		if ( 'settings_page_' . self::MENU_SLUG !== $hook_suffix ) {
			return;
		}

		wp_enqueue_style( 'wp-color-picker' );
		wp_enqueue_script( 'wp-color-picker' );

		wp_enqueue_style(
			'nanobar-admin',
			plugins_url( 'assets/css/admin.css', NANOBAR_FILE ),
			array( 'wp-color-picker' ),
			NANOBAR_VERSION
		);

		wp_enqueue_script(
			'nanobar-admin',
			plugins_url( 'assets/js/admin-settings.js', NANOBAR_FILE ),
			array( 'jquery', 'wp-color-picker' ),
			NANOBAR_VERSION,
			true
		);

		// Defaults and constraints come straight from Options::get_defaults()
		// and Config::get(): the same single source of truth used by the
		// sanitizer, the frontend renderer and the "Restore defaults" button,
		// so the live preview can never drift from what gets saved.
		wp_localize_script(
			'nanobar-admin',
			'nanobarSettings',
			array(
				'defaults' => Options::get_defaults(),
				'config'   => Config::get(),
				'i18n'     => array(
					'top'    => __( 'Top', 'nanobar' ),
					'bottom' => __( 'Bottom', 'nanobar' ),
					'left'   => __( 'Left', 'nanobar' ),
					'right'  => __( 'Right', 'nanobar' ),
					'center' => __( 'Center', 'nanobar' ),
				),
			)
		);
	}

	/**
	 * Renders the Settings → NanoBar page.
	 */
	public function render(): void {
		if ( ! current_user_can( 'manage_options' ) ) {
			return;
		}

		$options   = Options::get();
		$defaults  = Options::get_defaults();
		$config    = Config::get();
		$all_roles = array_diff_key( wp_roles()->roles, array( 'subscriber' => true ) );
		$position  = Options::normalize_position( $options['position_horizontal'], $options['position_vertical'] );
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
									<input type="number" id="nanobar-toggle-size-input" name="nanobar_options[toggle_size]" value="<?php echo esc_attr( (string) $options['toggle_size'] ); ?>" min="<?php echo esc_attr( (string) $config['size']['min'] ); ?>" max="<?php echo esc_attr( (string) $config['size']['max'] ); ?>" step="1" inputmode="numeric" class="nanobar-input" aria-describedby="nanobar-size-help" />
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
		<?php
	}
}
