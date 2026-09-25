<?php
/**
 * Frontend rendering: render gate, assets and the floating panel markup.
 *
 * @package NanoBar
 */

declare( strict_types=1 );

namespace NanoBar\Frontend;

use NanoBar\Plugin;
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
	 * it's actually needed to make the Query Monitor toggle or the Cache buttons
	 * work — even if the
	 * user has disabled the bar from their own profile. It still stays visually
	 * hidden by NanoBar's CSS; can be disabled from the settings.
	 *
	 * @param bool $show Current value of the show_admin_bar filter.
	 */
	public function maybe_force_admin_bar( bool $show ): bool {
		if ( $show || ! self::should_render() || ! current_user_can( 'manage_options' ) ) {
			return $show;
		}

		$options = Options::get();
		if ( defined( 'QM_VERSION' ) && ! empty( $options['force_admin_bar_for_qm'] ) ) {
			return true;
		}

		// The Cache buttons click a node of the native admin bar (kept in the
		// DOM, only hidden), so it has to be rendered for them to work.
		$visible = Options::get_visible_items( $options );
		if ( ! empty( $visible['cache'] ) && null !== Menus::get_active_cache_plugin() ) {
			return true;
		}

		return $show;
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
			Plugin::asset_version( 'assets/css/frontend.css' )
		);

		wp_add_inline_style(
			'nanobar-frontend',
			'#wpadminbar{display:none!important}html{margin-top:0!important}'
		);

		wp_enqueue_script(
			'nanobar-frontend',
			plugins_url( 'assets/js/frontend.js', NANOBAR_FILE ),
			array(),
			Plugin::asset_version( 'assets/js/frontend.js' ),
			true
		);

		// Strings for the command palette (assets/js/frontend.js) — static
		// regardless of options, so localized once here rather than recomputed
		// per-request in render_panel().
		wp_localize_script(
			'nanobar-frontend',
			'nanobarL10n',
			array(
				'commandPlaceholder' => __( 'Type a command or search…', 'nanobar' ),
				'commandEmpty'       => __( 'No matching commands.', 'nanobar' ),
				'commandDialogLabel' => __( 'Quick commands', 'nanobar' ),
			)
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
		$visible  = Options::get_visible_items( $options );
		$mobile   = Options::get_mobile_items( $options );

		// `nanobar__item--hide-mobile` only hides an item in the ≤580px tab-bar
		// layout (see _panel.scss) — it stays in the DOM either way, so it's
		// still reachable from the Cmd/Ctrl+K command palette.
		$hide_mobile = static fn ( string $key ): string => empty( $mobile[ $key ] ) ? ' nanobar__item--hide-mobile' : '';

		$wrapper_classes = sprintf(
			'nanobar nanobar--%1$s nanobar--%2$s%3$s',
			$pos_h,
			$pos_v,
			! empty( $options['icons_only'] ) ? ' nanobar--icons-only' : ''
		);

		$toggle_unit = Options::normalize_size_unit( $options['toggle_size_unit'] ?? null );
		$toggle_size = Options::clamp_toggle_size( $options['toggle_size'], $toggle_unit );
		// Re-validated here (not just at save time) for the same reason
		// normalize_position()/clamp_toggle_size() are: options can also arrive
		// unnormalized via the `nanobar_options` filter or a hand-edited DB row.
		$toggle_color  = ! empty( $options['toggle_bg_color'] ) ? sanitize_hex_color( $options['toggle_bg_color'] ) : '';
		$toggle_color  = $toggle_color ? $toggle_color : $defaults['toggle_bg_color'];
		$toggle_fg     = Color::get_contrast_color( $toggle_color );
		$wrapper_style = sprintf( '--nanobar-toggle-size:%1$s%2$s;--nanobar-toggle-bg:%3$s;--nanobar-toggle-fg:%4$s;', $toggle_size, $toggle_unit, $toggle_color, $toggle_fg );

		$color_scheme    = Options::normalize_color_scheme( $options['color_scheme'] ?? null );
		$quick_links     = QuickLinks::get_visible( $options['quick_links'] ?? array() );
		$command_palette = ! empty( $options['command_palette_enabled'] );
		$commands_extra  = $command_palette ? Commands::get_extra_items() : array();

		$is_site_manager = current_user_can( 'edit_theme_options' );
		$is_super_admin  = current_user_can( 'manage_options' );
		$use_elementor   = ! empty( $options['use_elementor_when_available'] );

		$context_edit      = ! empty( $visible['edit_content'] ) ? ContextLinks::get_context_edit_link() : null;
		$site_editor       = ( $is_site_manager && ! empty( $visible['site_editor'] ) ) ? ContextLinks::get_site_editor_link( $use_elementor ) : null;
		$qm_active         = defined( 'QM_VERSION' );
		$new_content_items = ! empty( $visible['new_content'] ) ? Menus::get_new_content_items( $use_elementor ) : array();
		$dashboard_submenu = ( $is_super_admin && ! empty( $visible['dashboard'] ) ) ? Menus::get_dashboard_submenu_items() : array();
		$cache_plugin      = ( $is_super_admin && ! empty( $visible['cache'] ) ) ? Menus::get_active_cache_plugin() : null;
		$dashboard_link    = admin_url();
		$logout            = Menus::get_logout_link();

		// A plugin isn't guaranteed to expose both purge scopes from its
		// frontend admin bar — WP Super Cache, for one, only has a "this page"
		// node there (its site-wide purge only lives in its is_admin() admin
		// bar variant, never rendered on the frontend NanoBar itself runs on).
		// The main split button's action is whichever scope is actually
		// available, preferring the broader one.
		$cache_purge_all_node  = $cache_plugin['purge_all_node_id'] ?? null;
		$cache_purge_page_node = $cache_plugin['purge_page_node_id'] ?? null;
		$cache_main_node       = $cache_purge_all_node ? $cache_purge_all_node : $cache_purge_page_node;

		$cache_main_title = '';
		if ( $cache_plugin && $cache_main_node ) {
			if ( $cache_purge_all_node ) {
				/* translators: %s: cache plugin name (e.g. WP Rocket). */
				$cache_main_title = sprintf( __( 'Clear cache (%s)', 'nanobar' ), $cache_plugin['label'] );
			} else {
				/* translators: %s: cache plugin name (e.g. WP Rocket). */
				$cache_main_title = sprintf( __( 'Clear cache for the current page (%s)', 'nanobar' ), $cache_plugin['label'] );
			}
		}

		$show_qm_button = false;
		if ( ! empty( $visible['query_monitor'] ) && $qm_active && $is_super_admin ) {
			if ( ! empty( $options['force_admin_bar_for_qm'] ) ) {
				$show_qm_button = true;
			} else {
				$bar_pref       = get_user_meta( get_current_user_id(), 'show_admin_bar_front', true );
				$show_qm_button = ( '' === $bar_pref ) ? true : ( 'true' === $bar_pref );
			}
		}
		?>
		<div id="nanobar" class="<?php echo esc_attr( $wrapper_classes ); ?>" style="<?php echo esc_attr( $wrapper_style ); ?>" data-scheme="<?php echo esc_attr( $color_scheme ); ?>" data-command-palette="<?php echo esc_attr( $command_palette ? '1' : '0' ); ?>">
			<button type="button" class="nanobar__toggle" aria-expanded="false" aria-controls="nanobar-menu" aria-label="<?php esc_attr_e( 'Open/close quick admin menu', 'nanobar' ); ?>" title="<?php esc_attr_e( 'Open/close quick admin menu', 'nanobar' ); ?>">
				<span class="dashicons dashicons-admin-generic nanobar__toggle-icon" aria-hidden="true"></span>
			</button>

			<div class="nanobar__menu" id="nanobar-menu">
				<?php if ( ! empty( $visible['dashboard'] ) ) : ?>
					<?php if ( ! empty( $dashboard_submenu ) ) : ?>
						<div class="nanobar__item-group<?php echo esc_attr( $hide_mobile( 'dashboard' ) ); ?>" data-submenu="dashboard">
							<a class="nanobar__item nanobar__item--split" href="<?php echo esc_url( $dashboard_link ); ?>" title="<?php esc_attr_e( 'Dashboard', 'nanobar' ); ?>">
								<span class="dashicons dashicons-dashboard nanobar__icon" aria-hidden="true"></span>
								<span class="nanobar__item-label"><?php esc_html_e( 'Dashboard', 'nanobar' ); ?></span>
							</a>
							<button type="button" class="nanobar__submenu-toggle" aria-expanded="false" aria-controls="nanobar-dashboard-submenu" aria-label="<?php esc_attr_e( 'Open Dashboard submenu', 'nanobar' ); ?>" title="<?php esc_attr_e( 'Open Dashboard submenu', 'nanobar' ); ?>">
								<span class="dashicons dashicons-arrow-right-alt2" aria-hidden="true"></span>
							</button>
							<div class="nanobar__submenu" id="nanobar-dashboard-submenu" aria-hidden="true">
								<?php foreach ( $dashboard_submenu as $item ) : ?>
									<?php
									/*
									 * `is_self` (see Frontend\Menus::get_dashboard_submenu_items())
									 * duplicates the group's own top-level link — only shown
									 * ≤666px, where that top-level link stops navigating
									 * directly and this becomes the only way to reach it.
									 * Above that width it would just be a redundant entry
									 * next to a tile that already links straight there.
									 */
									?>
									<a<?php echo ! empty( $item['is_self'] ) ? ' class="nanobar__submenu-item--self"' : ''; ?> href="<?php echo esc_url( $item['url'] ); ?>" title="<?php echo esc_attr( $item['label'] ); ?>">
										<span class="dashicons <?php echo esc_attr( $item['icon'] ); ?>" aria-hidden="true"></span>
										<span class="nanobar__submenu-label"><?php echo esc_html( $item['label'] ); ?></span>
									</a>
								<?php endforeach; ?>
							</div>
						</div>
					<?php else : ?>
						<a class="nanobar__item<?php echo esc_attr( $hide_mobile( 'dashboard' ) ); ?>" href="<?php echo esc_url( $dashboard_link ); ?>" title="<?php esc_attr_e( 'Dashboard', 'nanobar' ); ?>">
							<span class="dashicons dashicons-dashboard nanobar__icon" aria-hidden="true"></span>
							<span class="nanobar__item-label"><?php esc_html_e( 'Dashboard', 'nanobar' ); ?></span>
						</a>
					<?php endif; ?>
				<?php endif; ?>

				<?php if ( ! empty( $new_content_items ) ) : ?>
					<div class="nanobar__item-group<?php echo esc_attr( $hide_mobile( 'new_content' ) ); ?>" data-submenu="new">
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

				<?php if ( $context_edit ) : ?>
					<a class="nanobar__item<?php echo esc_attr( $hide_mobile( 'edit_content' ) ); ?>" href="<?php echo esc_url( $context_edit['url'] ); ?>" title="<?php echo esc_attr( $context_edit['label'] ); ?>">
						<span class="dashicons dashicons-edit nanobar__icon" aria-hidden="true"></span>
						<span class="nanobar__item-label"><?php echo esc_html( $context_edit['label'] ); ?></span>
					</a>
				<?php endif; ?>

				<?php if ( $site_editor ) : ?>
					<a class="nanobar__item<?php echo esc_attr( $hide_mobile( 'site_editor' ) ); ?>" href="<?php echo esc_url( $site_editor['url'] ); ?>" title="<?php echo esc_attr( $site_editor['title'] ); ?>">
						<span class="dashicons <?php echo esc_attr( $site_editor['icon'] ); ?> nanobar__icon" aria-hidden="true"></span>
						<span class="nanobar__item-label"><?php echo esc_html( $site_editor['label'] ); ?></span>
					</a>
				<?php endif; ?>

				<?php if ( $cache_plugin && $cache_main_node ) : ?>
					<div class="nanobar__item-group<?php echo esc_attr( $hide_mobile( 'cache' ) ); ?>" data-submenu="cache">
						<button type="button" class="nanobar__item nanobar__item--split nanobar__item--proxy" data-proxy-node="wp-admin-bar-<?php echo esc_attr( $cache_main_node ); ?>" title="<?php echo esc_attr( $cache_main_title ); ?>">
							<span class="dashicons dashicons-database nanobar__icon" aria-hidden="true"></span>
							<span class="nanobar__item-label"><?php esc_html_e( 'Cache', 'nanobar' ); ?></span>
						</button>
						<button type="button" class="nanobar__submenu-toggle" aria-expanded="false" aria-controls="nanobar-cache-submenu" aria-label="<?php esc_attr_e( 'Open Cache submenu', 'nanobar' ); ?>" title="<?php esc_attr_e( 'Open Cache submenu', 'nanobar' ); ?>">
							<span class="dashicons dashicons-arrow-right-alt2" aria-hidden="true"></span>
						</button>
						<div class="nanobar__submenu" id="nanobar-cache-submenu" aria-hidden="true">
							<a href="<?php echo esc_url( $cache_plugin['settings_url'] ); ?>" title="<?php echo esc_attr( sprintf( /* translators: %s: cache plugin name (e.g. WP Rocket). */ __( '%s settings', 'nanobar' ), $cache_plugin['label'] ) ); ?>">
								<span class="dashicons dashicons-admin-generic" aria-hidden="true"></span>
								<span class="nanobar__submenu-label"><?php echo esc_html( sprintf( /* translators: %s: cache plugin name (e.g. WP Rocket). */ __( '%s settings', 'nanobar' ), $cache_plugin['label'] ) ); ?></span>
							</a>
							<?php if ( $cache_purge_all_node ) : ?>
								<button type="button" data-proxy-node="wp-admin-bar-<?php echo esc_attr( $cache_purge_all_node ); ?>" title="<?php esc_attr_e( 'Clear all cache', 'nanobar' ); ?>">
									<span class="dashicons dashicons-trash" aria-hidden="true"></span>
									<span class="nanobar__submenu-label"><?php esc_html_e( 'Clear all cache', 'nanobar' ); ?></span>
								</button>
							<?php endif; ?>
							<?php if ( $cache_purge_page_node ) : ?>
								<button type="button" data-proxy-node="wp-admin-bar-<?php echo esc_attr( $cache_purge_page_node ); ?>" title="<?php esc_attr_e( 'Clear cache for the current page', 'nanobar' ); ?>">
									<span class="dashicons dashicons-update" aria-hidden="true"></span>
									<span class="nanobar__submenu-label"><?php esc_html_e( 'Clear cache for the current page', 'nanobar' ); ?></span>
								</button>
							<?php endif; ?>
						</div>
					</div>
				<?php elseif ( $cache_plugin ) : ?>
					<?php
					/*
					 * No known admin bar node performs a purge for this plugin
					 * (e.g. it only exposes one from its own wp-admin settings
					 * screen) — a plain link to that screen, instead of a
					 * split button/submenu that would have nothing but
					 * "Settings" in it.
					 */
					?>
					<a class="nanobar__item<?php echo esc_attr( $hide_mobile( 'cache' ) ); ?>" href="<?php echo esc_url( $cache_plugin['settings_url'] ); ?>" title="<?php echo esc_attr( sprintf( /* translators: %s: cache plugin name (e.g. WP Rocket). */ __( '%s settings', 'nanobar' ), $cache_plugin['label'] ) ); ?>">
						<span class="dashicons dashicons-database nanobar__icon" aria-hidden="true"></span>
						<span class="nanobar__item-label"><?php esc_html_e( 'Cache', 'nanobar' ); ?></span>
					</a>
				<?php endif; ?>

				<?php if ( $show_qm_button ) : ?>
					<button type="button" class="nanobar__item nanobar__item--button nanobar__item--proxy<?php echo esc_attr( $hide_mobile( 'query_monitor' ) ); ?>" data-proxy-node="wp-admin-bar-query-monitor" title="Query Monitor">
						<span class="dashicons dashicons-code-standards nanobar__icon" aria-hidden="true"></span>
						<span class="nanobar__item-label">Query Monitor</span>
					</button>
				<?php endif; ?>

				<?php foreach ( $quick_links as $quick_link ) : ?>
					<?php
					/*
					 * `nanobar__item--hide-mobile` only hides the tile in the
					 * ≤580px tab-bar layout (see _panel.scss) — the link stays
					 * in the DOM either way, so it's still reachable from the
					 * Cmd/Ctrl+K command palette, which scans this same markup.
					 */
					$quick_link_class = 'nanobar__item nanobar__item--quick-link';
					if ( empty( $quick_link['mobile_visible'] ) ) {
						$quick_link_class .= ' nanobar__item--hide-mobile';
					}
					?>
					<a class="<?php echo esc_attr( $quick_link_class ); ?>" href="<?php echo esc_url( $quick_link['url'] ); ?>" title="<?php echo esc_attr( $quick_link['label'] ); ?>">
						<span class="dashicons <?php echo esc_attr( $quick_link['icon'] ); ?> nanobar__icon" aria-hidden="true"></span>
						<span class="nanobar__item-label"><?php echo esc_html( $quick_link['label'] ); ?></span>
					</a>
				<?php endforeach; ?>

				<?php if ( ! empty( $visible['logout'] ) ) : ?>
					<a class="nanobar__item nanobar__item--logout<?php echo esc_attr( $hide_mobile( 'logout' ) ); ?>" href="<?php echo esc_url( $logout['url'] ); ?>" title="<?php echo esc_attr( $logout['label'] ); ?>">
						<span class="dashicons dashicons-exit nanobar__icon" aria-hidden="true"></span>
						<span class="nanobar__item-label"><?php echo esc_html( $logout['label'] ); ?></span>
					</a>
				<?php endif; ?>
			</div>
		</div>

		<?php if ( $command_palette && ! empty( $commands_extra ) ) : ?>
			<script type="application/json" id="nanobar-commands-extra">
				<?php
				// Extra command palette destinations not already present as panel
				// markup (see Frontend\Commands) — frontend.js merges these with
				// the commands it reads straight out of the panel's own DOM.
				echo wp_json_encode( $commands_extra, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT );
				?>
			</script>
		<?php endif; ?>
		<?php
	}
}
