<?php
/**
 * Frontend rendering: render gate, assets and the floating panel markup.
 *
 * @package NanoBar
 */

declare( strict_types=1 );

namespace NanoBar\Frontend;

use NanoBar\Settings\Options;
use NanoBar\Support\Color;

defined( 'ABSPATH' ) || exit;

/**
 * Decides whether NanoBar should appear, loads its assets and prints the
 * floating panel markup in the footer.
 */
final class Renderer {

	/**
	 * NanoBar should only be shown if: we're on the frontend, the user is logged
	 * in, the plugin is enabled globally, and the user's role is among the
	 * enabled ones.
	 */
	public static function should_render(): bool {
		if ( is_admin() || ! is_user_logged_in() ) {
			return false;
		}

		$options = Options::get();
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
	 */
	public function maybe_force_admin_bar( bool $show ): bool {
		if ( ! self::should_render() || ! current_user_can( 'manage_options' ) || ! defined( 'QM_VERSION' ) ) {
			return $show;
		}

		$options = Options::get();
		if ( empty( $options['force_admin_bar_for_qm'] ) ) {
			return $show;
		}

		return true;
	}

	/**
	 * Enqueues the frontend stylesheet/script and Dashicons, and hides the
	 * native admin bar (it stays in the DOM: needed for the Query Monitor
	 * toggle) by resetting the margin WordPress adds to <html> for it.
	 */
	public function enqueue_assets(): void {
		if ( ! self::should_render() ) {
			return;
		}

		wp_enqueue_style( 'dashicons' );

		wp_enqueue_style(
			'nanobar-frontend',
			plugins_url( 'assets/css/frontend.css', NANOBAR_FILE ),
			array( 'dashicons' ),
			NANOBAR_VERSION
		);

		wp_add_inline_style(
			'nanobar-frontend',
			'#wpadminbar{display:none!important}html{margin-top:0!important}'
		);

		wp_enqueue_script(
			'nanobar-frontend',
			plugins_url( 'assets/js/frontend.js', NANOBAR_FILE ),
			array(),
			NANOBAR_VERSION,
			true
		);
	}

	/**
	 * Markup of the floating panel, injected in the footer.
	 */
	public function render_panel(): void {
		if ( ! self::should_render() ) {
			return;
		}

		$options  = Options::get();
		$defaults = Options::get_defaults();
		$position = Options::normalize_position( $options['position_horizontal'], $options['position_vertical'] );
		$pos_h    = $position['horizontal'];
		$pos_v    = $position['vertical'];

		$wrapper_classes = sprintf(
			'nanobar nanobar--%1$s nanobar--%2$s%3$s',
			$pos_h,
			$pos_v,
			! empty( $options['icons_only'] ) ? ' nanobar--icons-only' : ''
		);

		$toggle_size   = Options::clamp_toggle_size( $options['toggle_size'] );
		$toggle_color  = ! empty( $options['toggle_bg_color'] ) ? $options['toggle_bg_color'] : $defaults['toggle_bg_color'];
		$toggle_fg     = Color::get_contrast_color( $toggle_color );
		$wrapper_style = sprintf( '--nanobar-toggle-size:%1$dpx;--nanobar-toggle-bg:%2$s;--nanobar-toggle-fg:%3$s;', $toggle_size, $toggle_color, $toggle_fg );

		$is_site_manager = current_user_can( 'edit_theme_options' );
		$is_super_admin  = current_user_can( 'manage_options' );

		$context_edit      = ContextLinks::get_context_edit_link();
		$site_editor       = $is_site_manager ? ContextLinks::get_site_editor_link() : null;
		$qm_active         = defined( 'QM_VERSION' );
		$new_content_items = Menus::get_new_content_items();
		$dashboard_submenu = $is_super_admin ? Menus::get_dashboard_submenu_items() : array();
		$dashboard_link    = admin_url();
		$logout            = Menus::get_logout_link();

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
		<?php
	}
}
