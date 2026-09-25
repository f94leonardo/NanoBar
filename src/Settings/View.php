<?php
/**
 * Markup of the Settings → NanoBar page, one method per card.
 *
 * @package NanoBar
 */

declare( strict_types=1 );

namespace NanoBar\Settings;

use NanoBar\Config;
use NanoBar\Frontend\Menus;

defined( 'ABSPATH' ) || exit;

/**
 * Prints the page sections. Page::render() decides the order and the
 * surrounding markup; this class only knows how to print each card, from a
 * context computed once (current options, defaults, config, detected plugins).
 */
final class View {

	/**
	 * Values shared by the cards, computed once in the constructor.
	 *
	 * @var array<string, mixed>
	 */
	private array $ctx;

	/**
	 * Builds the shared context every card reads from.
	 */
	public function __construct() {
		$options       = Options::get();
		$defaults      = Options::get_defaults();
		$config        = Config::get();
		$all_roles     = array_diff_key( wp_roles()->roles, array( 'subscriber' => true ) );
		$position      = Options::normalize_position( $options['position_horizontal'], $options['position_vertical'] );
		$pos_h         = $position['horizontal'];
		$pos_v         = $position['vertical'];
		$visible_items = Options::get_visible_items( $options );

		// Reuses the exact strings already shown on the frontend panel for each
		// item (see Frontend\Renderer/Menus/ContextLinks), so a translator only
		// ever has to translate "Dashboard", "Edit site", etc. once. Icons match
		// the dashicon the item actually uses in the panel, so this grid reads
		// as a small preview of the panel rather than a plain settings list.
		//
		// Keyed by, but not built from, Config::get()['menu_items'] — see the
		// loop below, which iterates Config's list (not this array's keys)
		// precisely so a key present in Config but missing here still gets a
		// checkbox (with a generic fallback label/icon) instead of silently
		// having no way to be configured from this page at all.
		$menu_items_meta = array(
			'dashboard'     => array(
				'label' => __( 'Dashboard', 'nanobar' ),
				'icon'  => 'dashicons-dashboard',
			),
			'new_content'   => array(
				'label' => __( 'New', 'nanobar' ),
				'icon'  => 'dashicons-plus-alt',
			),
			'edit_content'  => array(
				'label' => __( 'Edit content', 'nanobar' ),
				'icon'  => 'dashicons-edit',
			),
			'site_editor'   => array(
				'label' => __( 'Edit site', 'nanobar' ),
				'icon'  => 'dashicons-admin-appearance',
			),
			'cache'         => array(
				'label' => __( 'Cache', 'nanobar' ),
				'icon'  => 'dashicons-database',
			),
			'query_monitor' => array(
				'label' => 'Query Monitor',
				'icon'  => 'dashicons-code-standards',
			),
			'logout'        => array(
				'label' => __( 'Log out', 'nanobar' ),
				'icon'  => 'dashicons-exit',
			),
		);

		$quick_links     = is_array( $options['quick_links'] ) ? $options['quick_links'] : array();
		$mobile_items    = Options::get_mobile_items( $options );
		$size_unit       = Options::normalize_size_unit( $options['toggle_size_unit'] ?? null );
		$size_bounds     = self::get_size_bounds( $size_unit );
		$size_range_text = self::format_size_range( $size_bounds['min'], $size_bounds['max'], $size_unit );

		$visible_items_count = count( array_filter( $visible_items ) );
		$visible_items_total = count( $visible_items );

		// Detection badges for the items/options that only do something when a
		// third-party plugin is around, so an admin can tell at a glance why
		// one of them might have no effect. Same checks the frontend uses
		// (Renderer: QM_VERSION; ContextLinks: ELEMENTOR_VERSION; Menus:
		// the cache plugin registry).
		$elementor_active = defined( 'ELEMENTOR_VERSION' );
		$cache_plugin     = Menus::get_active_cache_plugin();
		$item_status      = array(
			'cache'         => array(
				'ok'   => null !== $cache_plugin,
				'text' => null !== $cache_plugin
					/* translators: %s: name of the detected cache plugin (e.g. WP Rocket). */
					? sprintf( __( 'Detected: %s', 'nanobar' ), $cache_plugin['label'] )
					: __( 'Not detected', 'nanobar' ),
			),
			'query_monitor' => array(
				'ok'   => defined( 'QM_VERSION' ),
				'text' => defined( 'QM_VERSION' ) ? __( 'Detected', 'nanobar' ) : __( 'Not detected', 'nanobar' ),
			),
		);

		$this->ctx = compact( 'options', 'defaults', 'config', 'all_roles', 'position', 'pos_h', 'pos_v', 'visible_items', 'menu_items_meta', 'quick_links', 'mobile_items', 'size_unit', 'size_bounds', 'size_range_text', 'visible_items_count', 'visible_items_total', 'elementor_active', 'cache_plugin', 'item_status' );
	}

	/**
	 * Renders the sticky header: title, status, buttons and section navigation.
	 */
	public function header(): void {
				/*
				 * Zero-height marker admin-settings.js watches with an
				 * IntersectionObserver: once scrolling carries it out of view,
				 * the sticky header below has necessarily reached the top of
				 * the viewport and become "stuck" there — CSS position:sticky
				 * has no matching event or pseudo-class of its own to detect
				 * that state directly, so a sentinel is the standard technique.
				 * Adds the ".is-stuck" class read by the collapsed-header rules
				 * in _header.scss (hides the subtitle, shrinks the padding).
				 */
		?>
				<div id="nanobar-header-sentinel"></div>
				<div class="nanobar-header">
					<div class="nanobar-header__top">
						<div class="nanobar-header__content">
							<div class="nanobar-header__icon" aria-hidden="true"><span class="dashicons dashicons-admin-generic"></span></div>
							<div>
								<h1><?php esc_html_e( 'NanoBar', 'nanobar' ); ?></h1>
								<p><?php esc_html_e( 'Configure the floating panel that replaces the classic admin bar on the frontend.', 'nanobar' ); ?></p>
							</div>
						</div>
						<div class="nanobar-header__actions">
							<?php
							/*
							 * Live region: admin-settings.js writes "Unsaved changes"
							 * (and "Saving…") here, so the state is announced and
							 * visible as text, not only as the dot on Save. Empty
							 * (and hidden by :empty) while there is nothing to say.
							 */
							?>
							<span class="nanobar-header__status" id="nanobar-save-status" role="status"></span>
							<button type="button" id="nanobar-reset-defaults-header" class="nanobar-header__btn nanobar-reset-defaults-trigger" title="<?php esc_attr_e( 'Restore defaults', 'nanobar' ); ?>">
								<span class="dashicons dashicons-image-rotate" aria-hidden="true"></span>
								<span class="nanobar-header__btn-label"><?php esc_html_e( 'Restore defaults', 'nanobar' ); ?></span>
							</button>
							<button type="submit" name="submit" id="nanobar-save-header" class="nanobar-header__btn nanobar-header__btn--primary" title="<?php esc_attr_e( 'Save changes', 'nanobar' ); ?>">
								<span class="dashicons dashicons-yes" aria-hidden="true"></span>
								<span class="nanobar-header__btn-label"><?php esc_html_e( 'Save changes', 'nanobar' ); ?></span>
							</button>
						</div>
					</div>
					<nav class="nanobar-nav" aria-label="<?php esc_attr_e( 'Settings sections', 'nanobar' ); ?>">
						<a href="#nanobar-section-general"><?php esc_html_e( 'General', 'nanobar' ); ?></a>
						<a href="#nanobar-section-roles"><?php esc_html_e( 'Enabled roles', 'nanobar' ); ?></a>
						<a href="#nanobar-section-appearance"><?php esc_html_e( 'Appearance', 'nanobar' ); ?></a>
						<a href="#nanobar-section-menu-items"><?php esc_html_e( 'Menu items', 'nanobar' ); ?></a>
						<a href="#nanobar-section-quick-links"><?php esc_html_e( 'Quick links', 'nanobar' ); ?></a>
						<a href="#nanobar-section-backup"><?php esc_html_e( 'Backup', 'nanobar' ); ?></a>
					</nav>
				</div>
		<?php
	}

	/**
	 * Renders the General card.
	 */
	public function general(): void {
		$options = $this->ctx['options'];
		?>
					<div class="nanobar-bento-card nanobar-bento-card--general" id="nanobar-section-general">
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
								<label class="nanobar-switch-row">
									<input type="checkbox" name="nanobar_options[command_palette_enabled]" value="1" <?php checked( ! empty( $options['command_palette_enabled'] ) ); ?> />
									<span class="nanobar-switch" aria-hidden="true"></span>
									<span><strong><?php esc_html_e( 'Command palette', 'nanobar' ); ?></strong><small><?php esc_html_e( 'Press Cmd/Ctrl+K to jump to any panel item or common wp-admin page.', 'nanobar' ); ?></small></span>
								</label>
							</div>

							<div class="nanobar-form-group nanobar-form-group--last">
								<label class="nanobar-switch-row">
									<input type="checkbox" name="nanobar_options[force_admin_bar_for_qm]" value="1" <?php checked( ! empty( $options['force_admin_bar_for_qm'] ) ); ?> />
									<span class="nanobar-switch" aria-hidden="true"></span>
									<span><strong><?php esc_html_e( 'Force admin bar for Query Monitor', 'nanobar' ); ?></strong><small><?php esc_html_e( 'Keeps the native bar available, as Query Monitor needs it.', 'nanobar' ); ?></small></span>
								</label>
							</div>
						</div>
					</div>
		<?php
	}

	/**
	 * Renders the Enabled roles card.
	 */
	public function roles(): void {
		$options   = $this->ctx['options'];
		$all_roles = $this->ctx['all_roles'];
		?>
					<div class="nanobar-bento-card nanobar-bento-card--roles" id="nanobar-section-roles">
						<div class="nanobar-bento-card__header">
							<span class="dashicons dashicons-groups" aria-hidden="true"></span>
							<h2><?php esc_html_e( 'Enabled roles', 'nanobar' ); ?></h2>
						</div>
						<div class="nanobar-bento-card__body">
							<p class="nanobar-help nanobar-help--lead"><?php esc_html_e( 'Choose which roles can see NanoBar. The Subscriber role is not available.', 'nanobar' ); ?></p>
							<div class="nanobar-menu-items-actions" role="group" aria-label="<?php esc_attr_e( 'Bulk select', 'nanobar' ); ?>">
								<button type="button" class="nanobar-chip-button" data-bulk-target="roles" data-bulk-toggle="all">
									<span class="dashicons dashicons-yes" aria-hidden="true"></span>
									<?php esc_html_e( 'Select all', 'nanobar' ); ?>
								</button>
								<button type="button" class="nanobar-chip-button" data-bulk-target="roles" data-bulk-toggle="none">
									<span class="dashicons dashicons-no-alt" aria-hidden="true"></span>
									<?php esc_html_e( 'Select none', 'nanobar' ); ?>
								</button>
							</div>
							<div class="nanobar-roles-grid" id="nanobar-roles">
								<?php foreach ( $all_roles as $role_key => $role_data ) : ?>
									<label class="nanobar-role-option">
										<input type="checkbox" class="nanobar-checkbox" name="nanobar_options[roles][]" value="<?php echo esc_attr( $role_key ); ?>" <?php checked( in_array( $role_key, (array) $options['roles'], true ) ); ?> />
										<span><?php echo esc_html( translate_user_role( $role_data['name'] ) ); ?></span>
									</label>
								<?php endforeach; ?>
							</div>
						</div>
					</div>
		<?php
	}

	/**
	 * Renders the Appearance card (Icon and Panel groups).
	 */
	public function appearance(): void {
		$options         = $this->ctx['options'];
		$defaults        = $this->ctx['defaults'];
		$config          = $this->ctx['config'];
		$menu_items_meta = $this->ctx['menu_items_meta'];
		$size_unit       = $this->ctx['size_unit'];
		$size_bounds     = $this->ctx['size_bounds'];
		$size_range_text = $this->ctx['size_range_text'];
		?>
					<div class="nanobar-bento-card nanobar-bento-card--appearance" id="nanobar-section-appearance">
						<div class="nanobar-bento-card__header">
							<span class="dashicons dashicons-admin-customizer" aria-hidden="true"></span>
							<h2><?php esc_html_e( 'Appearance', 'nanobar' ); ?></h2>
						</div>
						<div class="nanobar-bento-card__body">
							<div class="nanobar-group" role="group" aria-labelledby="nanobar-group-icon">
							<h3 class="nanobar-group__title" id="nanobar-group-icon"><?php esc_html_e( 'Icon', 'nanobar' ); ?></h3>
							<div class="nanobar-form-row">
							<div class="nanobar-form-group">
								<label class="nanobar-label" for="nanobar-toggle-size-input"><?php esc_html_e( 'Icon size', 'nanobar' ); ?></label>
								<div class="nanobar-size-control">
									<input type="number" id="nanobar-toggle-size-input" name="nanobar_options[toggle_size]" value="<?php echo esc_attr( (string) $options['toggle_size'] ); ?>" min="<?php echo esc_attr( (string) $size_bounds['min'] ); ?>" max="<?php echo esc_attr( (string) $size_bounds['max'] ); ?>" step="<?php echo esc_attr( (string) $size_bounds['step'] ); ?>" inputmode="decimal" class="nanobar-input" aria-describedby="nanobar-size-range" />
									<div class="nanobar-segmented" role="radiogroup" aria-label="<?php esc_attr_e( 'Size unit', 'nanobar' ); ?>">
										<?php foreach ( $config['size']['units'] as $unit_value ) : ?>
											<label class="nanobar-segmented__option">
												<input type="radio" name="nanobar_options[toggle_size_unit]" value="<?php echo esc_attr( $unit_value ); ?>" <?php checked( $size_unit, $unit_value ); ?> />
												<span><?php echo esc_html( $unit_value ); ?></span>
											</label>
										<?php endforeach; ?>
									</div>
								</div>
								<input type="range" id="nanobar-toggle-size-slider" class="nanobar-size-slider" min="<?php echo esc_attr( (string) $size_bounds['min'] ); ?>" max="<?php echo esc_attr( (string) $size_bounds['max'] ); ?>" step="<?php echo esc_attr( (string) $size_bounds['step'] ); ?>" value="<?php echo esc_attr( (string) $options['toggle_size'] ); ?>" aria-label="<?php esc_attr_e( 'Icon size', 'nanobar' ); ?>" data-no-dirty />
								<p class="nanobar-help" id="nanobar-size-range" data-size-range><?php echo esc_html( $size_range_text ); ?></p>
								<p class="nanobar-help" data-size-rem-note <?php echo 'rem' === $size_unit ? '' : 'hidden'; ?>><?php esc_html_e( 'rem follows your site\'s root font size (16px by default), so the button scales with it.', 'nanobar' ); ?></p>
							</div>

							<div class="nanobar-form-group">
								<label class="nanobar-label" for="nanobar-color-field"><?php esc_html_e( 'Icon color', 'nanobar' ); ?></label>
								<input type="text" id="nanobar-color-field" class="nanobar-color-field nanobar-input" name="nanobar_options[toggle_bg_color]" value="<?php echo esc_attr( $options['toggle_bg_color'] ); ?>" data-default-color="<?php echo esc_attr( $defaults['toggle_bg_color'] ); ?>" />
							</div>
							</div>
							</div>

							<?php
							// Labels for the values in Config::get()['color_schemes'] — keyed
							// by, but (like $menu_items_meta above) not built from, that list:
							// the <option> loop below iterates Config's list directly, so a
							// value added there but missing a label here still gets a
							// selectable option (falling back to its raw slug) instead of
							// being silently unreachable from this page.
							$color_scheme_labels = array(
								'auto'  => __( 'Auto (match system)', 'nanobar' ),
								'light' => __( 'Light', 'nanobar' ),
								'dark'  => __( 'Dark', 'nanobar' ),
							);
							?>
							<div class="nanobar-group" role="group" aria-labelledby="nanobar-group-panel">
							<h3 class="nanobar-group__title" id="nanobar-group-panel"><?php esc_html_e( 'Panel', 'nanobar' ); ?></h3>
							<div class="nanobar-form-row">
							<div class="nanobar-form-group">
								<label class="nanobar-switch-row">
									<input type="checkbox" name="nanobar_options[icons_only]" value="1" <?php checked( ! empty( $options['icons_only'] ) ); ?> />
									<span class="nanobar-switch" aria-hidden="true"></span>
									<span><strong><?php esc_html_e( 'Icons only', 'nanobar' ); ?></strong><small><?php esc_html_e( 'Hides labels; compact mode is automatic on narrow screens.', 'nanobar' ); ?></small></span>
								</label>
							</div>

							<div class="nanobar-form-group">
								<label class="nanobar-label" for="nanobar-color-scheme"><?php esc_html_e( 'Color scheme', 'nanobar' ); ?></label>
								<select name="nanobar_options[color_scheme]" id="nanobar-color-scheme" class="nanobar-select">
									<?php foreach ( $config['color_schemes'] as $scheme_value ) : ?>
										<option value="<?php echo esc_attr( $scheme_value ); ?>" <?php selected( $options['color_scheme'], $scheme_value ); ?>><?php echo esc_html( $color_scheme_labels[ $scheme_value ] ?? ucfirst( $scheme_value ) ); ?></option>
									<?php endforeach; ?>
								</select>
								<p class="nanobar-help"><?php esc_html_e( 'Only affects the dropdown menu; the toggle button always keeps the color chosen above.', 'nanobar' ); ?></p>
							</div>
							</div>

							</div>
						</div>
					</div>
		<?php
	}

	/**
	 * Renders the Preview card, which also hosts the position picker.
	 */
	public function preview(): void {
		$config = $this->ctx['config'];
		$pos_h  = $this->ctx['pos_h'];
		$pos_v  = $this->ctx['pos_v'];

					/*
					 * Position is chosen on the preview itself: one spot per
					 * allowed place (3×2, mirroring the screen), a radio group
					 * whose two hidden inputs are what the form submits (same
					 * names as the selects they replaced); admin-settings.js keeps
					 * them in sync with the pressed spot. "Center" only exists on
					 * the edge named by Config::get()['positions']['center_vertical'].
					 */
					$center_edge           = $config['positions']['center_vertical'];
							$position_rows = array( 'top', 'bottom' );
							$edge_labels   = array(
								'top'    => __( 'Top', 'nanobar' ),
								'bottom' => __( 'Bottom', 'nanobar' ),
							);
							$side_labels   = array(
								'left'   => __( 'Left', 'nanobar' ),
								'center' => __( 'Center', 'nanobar' ),
								'right'  => __( 'Right', 'nanobar' ),
							);
							/* translators: %s: screen edge where "Center" is available (e.g. Bottom). */
							$center_only_text = sprintf( __( 'Center is only available with the %s position.', 'nanobar' ), $edge_labels[ $center_edge ] );
							?>
					<div class="nanobar-bento-card nanobar-bento-card--preview">
						<div class="nanobar-bento-card__header">
							<span class="dashicons dashicons-visibility" aria-hidden="true"></span>
							<h2><?php esc_html_e( 'Preview', 'nanobar' ); ?></h2>
							<div class="nanobar-segmented nanobar-segmented--small" role="radiogroup" aria-label="<?php esc_attr_e( 'Preview device', 'nanobar' ); ?>">
								<label class="nanobar-segmented__option">
									<input type="radio" name="nanobar_preview_device" value="desktop" data-no-dirty checked />
									<span><i class="dashicons dashicons-desktop" aria-hidden="true"></i><?php esc_html_e( 'Desktop', 'nanobar' ); ?></span>
								</label>
								<label class="nanobar-segmented__option">
									<input type="radio" name="nanobar_preview_device" value="mobile" data-no-dirty />
									<span><i class="dashicons dashicons-smartphone" aria-hidden="true"></i><?php esc_html_e( 'Mobile', 'nanobar' ); ?></span>
								</label>
							</div>
						</div>
						<div class="nanobar-bento-card__body nanobar-preview-container">
							<div class="nanobar-viewport" id="nanobar-viewport" data-device="desktop" role="group" aria-label="<?php esc_attr_e( 'Preview of the NanoBar panel', 'nanobar' ); ?>">
								<div class="nanobar-mock-site" aria-hidden="true"><span></span><span></span><span></span><span></span><span></span></div>
								<div class="nanobar-mock-spots" id="nanobar-position-grid" role="radiogroup" aria-label="<?php esc_attr_e( 'Position', 'nanobar' ); ?>" data-center-only="<?php echo esc_attr( $center_only_text ); ?>">
									<?php foreach ( $position_rows as $row_v ) : ?>
										<?php foreach ( $config['positions']['horizontal'] as $col_h ) : ?>
											<?php
											$is_current = $pos_h === $col_h && $pos_v === $row_v;
											$is_locked  = 'center' === $col_h && $center_edge !== $row_v;
											$cell_label = $edge_labels[ $row_v ] . ' · ' . ( $side_labels[ $col_h ] ?? $col_h );
											?>
											<button type="button" class="nanobar-position-cell" role="radio" aria-checked="<?php echo $is_current ? 'true' : 'false'; ?>" aria-label="<?php echo esc_attr( $cell_label ); ?>" title="<?php echo esc_attr( $is_locked ? $center_only_text : $cell_label ); ?>" data-pos-h="<?php echo esc_attr( $col_h ); ?>" data-pos-v="<?php echo esc_attr( $row_v ); ?>" tabindex="<?php echo $is_current ? '0' : '-1'; ?>" <?php disabled( $is_locked ); ?>></button>
										<?php endforeach; ?>
									<?php endforeach; ?>
								</div>
								<input type="hidden" id="nanobar-pos-h" name="nanobar_options[position_horizontal]" value="<?php echo esc_attr( $pos_h ); ?>" />
								<input type="hidden" id="nanobar-pos-v" name="nanobar_options[position_vertical]" value="<?php echo esc_attr( $pos_v ); ?>" />
								<div class="nanobar-mock" id="nanobar-mock">
									<div class="nanobar-mock-panel" id="nanobar-mock-panel" aria-hidden="true"></div>
									<button type="button" class="nanobar-preview-button" id="nanobar-preview-button" aria-expanded="true" aria-controls="nanobar-mock-panel" aria-label="<?php esc_attr_e( 'Show or hide the panel in the preview', 'nanobar' ); ?>">
										<span class="dashicons dashicons-admin-generic" aria-hidden="true"></span>
									</button>
								</div>
								<div class="nanobar-mock-tabbar" id="nanobar-mock-tabbar" aria-hidden="true"></div>
							</div>
							<p class="nanobar-help nanobar-preview-help"><?php esc_html_e( 'Click a spot in the preview to move the button.', 'nanobar' ); ?> <?php echo esc_html( $center_only_text ); ?></p>
						</div>
					</div>
		<?php
	}

	/**
	 * Renders the Menu items card.
	 */
	public function menu_items(): void {
		$options             = $this->ctx['options'];
		$config              = $this->ctx['config'];
		$visible_items       = $this->ctx['visible_items'];
		$menu_items_meta     = $this->ctx['menu_items_meta'];
		$mobile_items        = $this->ctx['mobile_items'];
		$visible_items_count = $this->ctx['visible_items_count'];
		$visible_items_total = $this->ctx['visible_items_total'];
		$elementor_active    = $this->ctx['elementor_active'];
		$item_status         = $this->ctx['item_status'];
		?>
					<div class="nanobar-bento-card nanobar-bento-card--menu-items" id="nanobar-section-menu-items">
						<div class="nanobar-bento-card__header">
							<span class="dashicons dashicons-menu" aria-hidden="true"></span>
							<h2><?php esc_html_e( 'Menu items', 'nanobar' ); ?></h2>
							<span class="nanobar-count-badge" id="nanobar-menu-items-count" aria-live="polite">
								<?php
								printf(
									/* translators: 1: number of currently visible items, 2: total number of items. */
									esc_html__( '%1$d of %2$d active', 'nanobar' ),
									(int) $visible_items_count,
									(int) $visible_items_total
								);
								?>
							</span>
						</div>
						<div class="nanobar-bento-card__body">
							<p class="nanobar-help nanobar-help--lead"><?php esc_html_e( 'Choose which items appear in the panel, when the user has permission for them. The phone button adds an item to the compact bottom bar on phones (up to 580px wide).', 'nanobar' ); ?></p>
							<div class="nanobar-menu-items-actions" role="group" aria-label="<?php esc_attr_e( 'Bulk select', 'nanobar' ); ?>">
								<button type="button" class="nanobar-chip-button" data-bulk-target="items" data-bulk-toggle="all">
									<span class="dashicons dashicons-yes" aria-hidden="true"></span>
									<?php esc_html_e( 'Select all', 'nanobar' ); ?>
								</button>
								<button type="button" class="nanobar-chip-button" data-bulk-target="items" data-bulk-toggle="none">
									<span class="dashicons dashicons-no-alt" aria-hidden="true"></span>
									<?php esc_html_e( 'Select none', 'nanobar' ); ?>
								</button>
							</div>
							<div class="nanobar-roles-grid nanobar-roles-grid--menu-items" id="nanobar-menu-items">
								<?php foreach ( $config['menu_items'] as $item_key ) : ?>
									<?php
									// A key present in Config but missing from $menu_items_meta
									// above (e.g. a future item added to Config without a
									// matching entry here yet) still gets a checkbox, just with
									// a generic icon and the raw key as its label, rather than
									// silently having no way to be configured from this page.
									$item   = $menu_items_meta[ $item_key ] ?? array(
										'label' => ucwords( str_replace( '_', ' ', $item_key ) ),
										'icon'  => 'dashicons-admin-generic',
									);
									$status = $item_status[ $item_key ] ?? null;
									?>
									<div class="nanobar-role-option nanobar-role-option--item<?php echo 'site_editor' === $item_key ? ' nanobar-role-option--wide' : ''; ?>">
										<label class="nanobar-role-option__main">
											<input type="checkbox" class="nanobar-checkbox" name="nanobar_options[visible_items][<?php echo esc_attr( $item_key ); ?>]" id="nanobar-item-<?php echo esc_attr( $item_key ); ?>" data-item-key="<?php echo esc_attr( $item_key ); ?>" value="1" <?php checked( ! empty( $visible_items[ $item_key ] ) ); ?> />
											<span class="dashicons <?php echo esc_attr( $item['icon'] ); ?>" aria-hidden="true"></span>
											<span><?php echo esc_html( $item['label'] ); ?></span>
											<?php if ( null !== $status ) : ?>
												<span class="nanobar-status nanobar-status--<?php echo $status['ok'] ? 'ok' : 'off'; ?>"><?php echo esc_html( $status['text'] ); ?></span>
											<?php endif; ?>
										</label>
										<label class="nanobar-mobile-toggle" data-tooltip-align="end" data-tooltip="<?php esc_attr_e( 'Show on mobile', 'nanobar' ); ?>">
											<input type="checkbox" name="nanobar_options[mobile_items][<?php echo esc_attr( $item_key ); ?>]" data-mobile-key="<?php echo esc_attr( $item_key ); ?>" value="1" <?php checked( ! empty( $mobile_items[ $item_key ] ) ); ?> />
											<span class="dashicons dashicons-smartphone" aria-hidden="true"></span>
											<span class="nanobar-mobile-toggle__state" aria-hidden="true"></span>
											<span class="screen-reader-text"><?php echo esc_html( sprintf( /* translators: %s: menu item name (e.g. Dashboard). */ __( 'Show %s on mobile', 'nanobar' ), $item['label'] ) ); ?></span>
										</label>
										<?php if ( 'site_editor' === $item_key ) : ?>
											<label class="nanobar-switch-row nanobar-role-option__sub" id="nanobar-elementor-suboption">
												<input type="checkbox" name="nanobar_options[use_elementor_when_available]" value="1" <?php checked( ! empty( $options['use_elementor_when_available'] ) ); ?> />
												<span class="nanobar-switch" aria-hidden="true"></span>
												<span>
													<strong><?php esc_html_e( 'Prefer Elementor when available', 'nanobar' ); ?> <span class="nanobar-status nanobar-status--<?php echo $elementor_active ? 'ok' : 'off'; ?>"><?php echo $elementor_active ? esc_html__( 'Elementor detected', 'nanobar' ) : esc_html__( 'Elementor not detected', 'nanobar' ); ?></span></strong>
													<small><?php esc_html_e( 'Links to Elementor\'s editor or Theme Builder when they apply to the current view. Needs "Edit site" to be on.', 'nanobar' ); ?></small>
												</span>
											</label>
										<?php endif; ?>
									</div>
								<?php endforeach; ?>
							</div>
						</div>
					</div>
		<?php
	}

	/**
	 * Renders the Quick links card.
	 */
	public function quick_links(): void {
		$config      = $this->ctx['config'];
		$all_roles   = $this->ctx['all_roles'];
		$quick_links = $this->ctx['quick_links'];
		?>
					<div class="nanobar-bento-card nanobar-bento-card--quick-links" id="nanobar-section-quick-links">
						<div class="nanobar-bento-card__header">
							<span class="dashicons dashicons-admin-links" aria-hidden="true"></span>
							<h2><?php esc_html_e( 'Quick links', 'nanobar' ); ?></h2>
							<span class="nanobar-count-badge" id="nanobar-quick-links-count" aria-live="polite">
								<?php
								printf(
									/* translators: 1: number of quick links currently configured, 2: maximum number allowed. */
									esc_html__( '%1$d of %2$d used', 'nanobar' ),
									count( $quick_links ),
									(int) $config['quick_links']['max']
								);
								?>
							</span>
						</div>
						<div class="nanobar-bento-card__body">
							<p class="nanobar-help nanobar-help--lead">
								<?php
								printf(
									/* translators: %d: maximum number of quick links allowed. */
									esc_html__( 'Shortcuts to pages on this site, such as an admin screen or a documentation page. External links are not allowed. Up to %d.', 'nanobar' ),
									(int) $config['quick_links']['max']
								);
								?>
							</p>
							<p class="nanobar-quick-links-summary-error" id="nanobar-quick-links-summary-error" role="alert" hidden></p>
							<div class="nanobar-quick-links" id="nanobar-quick-links">
								<?php foreach ( $quick_links as $index => $link ) : ?>
									<?php echo $this->render_quick_link_row( $index, is_array( $link ) ? $link : array(), $all_roles ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- render_quick_link_row() escapes every value it prints. ?>
								<?php endforeach; ?>
							</div>
							<div class="nanobar-quick-links__empty" id="nanobar-quick-links-empty" <?php echo empty( $quick_links ) ? '' : 'hidden'; ?>>
								<span class="dashicons dashicons-admin-links" aria-hidden="true"></span>
								<strong><?php esc_html_e( 'No shortcuts yet', 'nanobar' ); ?></strong>
								<span><?php esc_html_e( 'Add a link to reach any page of this site in one click from the panel.', 'nanobar' ); ?></span>
							</div>
							<button type="button" class="nanobar-add-link" id="nanobar-add-quick-link">
								<span class="dashicons dashicons-plus-alt2" aria-hidden="true"></span>
								<?php esc_html_e( 'Add link', 'nanobar' ); ?>
							</button>
						</div>
					</div>
		<?php
	}

	/**
	 * Renders the empty row template cloned by the "Add link" button.
	 */
	public function quick_link_template(): void {
		$all_roles = $this->ctx['all_roles'];
		?>
				<template id="nanobar-quick-link-template">
					<?php echo $this->render_quick_link_row( '__INDEX__', array(), $all_roles ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- render_quick_link_row() escapes every value it prints. ?>
				</template>
		<?php
	}

	/**
	 * Min/max/step of the size input for a unit, from Config::get()['size'].
	 *
	 * @param string $unit "px" or "rem".
	 * @return array{min: int|float, max: int|float, step: int|float}
	 */
	private static function get_size_bounds( string $unit ): array {
		$size = Config::get()['size'];

		if ( 'rem' === $unit ) {
			return $size['rem'];
		}

		return array(
			'min'  => $size['min'],
			'max'  => $size['max'],
			'step' => 1,
		);
	}

	/**
	 * The "From 32px to 72px." hint shown next to the size label.
	 *
	 * @param int|float $min  Lower bound.
	 * @param int|float $max  Upper bound.
	 * @param string    $unit Unit suffix.
	 * @return string
	 */
	private static function format_size_range( int|float $min, int|float $max, string $unit ): string {
		/* translators: 1: minimum size with its unit (e.g. 32px), 2: maximum size with its unit (e.g. 72px). */
		return sprintf( __( 'From %1$s to %2$s.', 'nanobar' ), $min . $unit, $max . $unit );
	}

	/**
	 * Renders one row of the "Quick links" repeater — used both for each
	 * already-saved link and, with $index left as the literal placeholder
	 * `__INDEX__`, for the empty <template> the "Add link" button in
	 * admin-settings.js clones (replacing the placeholder with a fresh
	 * position each time, and its icon field text with an escaped, static
	 * template-language string rather than PHP output, since real-index rows
	 * and the template share the exact same markup).
	 *
	 * @param int|string           $index     Row index (an actual array index for a saved row, or "__INDEX__" for the template).
	 * @param array<string, mixed> $link      Row data: label, url, icon, roles, mobile_visible.
	 * @param array<string, mixed> $all_roles Roles eligible for the per-link restriction, as returned by wp_roles() minus Subscriber.
	 * @return string
	 */
	private function render_quick_link_row( int|string $index, array $link, array $all_roles ): string {
		$label = isset( $link['label'] ) && is_string( $link['label'] ) ? $link['label'] : '';
		$url   = isset( $link['url'] ) && is_string( $link['url'] ) ? $link['url'] : '';
		$icon  = ! empty( $link['icon'] ) && is_string( $link['icon'] ) && 1 === preg_match( '/^dashicons-[a-z0-9-]+$/', $link['icon'] ) ? $link['icon'] : 'dashicons-admin-links';
		$roles = isset( $link['roles'] ) && is_array( $link['roles'] ) ? $link['roles'] : array();
		$name  = 'nanobar_options[quick_links][' . $index . ']';

		// Two different defaults for the same missing key, deliberately: a
		// link saved before this field existed still defaults to visible
		// (matching Settings\Sanitizer::sanitize_quick_links()'s own
		// fallback) — changing that retroactively would silently hide
		// shortcuts an admin already configured and never touched again. A
		// brand-new link — this is the "__INDEX__" template
		// admin-settings.js clones for "+ Add link" — defaults the other way:
		// the ≤580px tab bar is already tight on space, so a newly added
		// shortcut has to be deliberately opted in rather than silently
		// showing up there.
		$mobile_visible = '__INDEX__' === $index
			? ! empty( $link['mobile_visible'] )
			: ( ! isset( $link['mobile_visible'] ) || ! empty( $link['mobile_visible'] ) );

		ob_start();
		?>
		<div class="nanobar-quick-link-row" data-quick-link-row>
			<div class="nanobar-quick-link-row__main">
				<span class="nanobar-quick-link-row__handle" title="<?php esc_attr_e( 'Drag to reorder', 'nanobar' ); ?>" aria-hidden="true">
					<span class="dashicons dashicons-menu"></span>
				</span>

				<div class="nanobar-quick-link-row__icon">
					<button type="button" class="nanobar-quick-link-row__icon-trigger" data-quick-link-icon-trigger aria-haspopup="dialog" title="<?php echo esc_attr( $icon ); ?>">
						<span class="dashicons <?php echo esc_attr( $icon ); ?> nanobar-quick-link-row__icon-preview" aria-hidden="true"></span>
						<span class="screen-reader-text"><?php esc_html_e( 'Choose an icon', 'nanobar' ); ?> <span data-quick-link-icon-label><?php echo esc_html( $icon ); ?></span></span>
					</button>
					<input type="hidden" name="<?php echo esc_attr( $name ); ?>[icon]" value="<?php echo esc_attr( $icon ); ?>" data-quick-link-icon-input />
				</div>

				<div class="nanobar-quick-link-row__field nanobar-quick-link-row__field--label">
					<label class="screen-reader-text" for="nanobar-quick-link-<?php echo esc_attr( (string) $index ); ?>-label"><?php esc_html_e( 'Label', 'nanobar' ); ?></label>
					<input type="text" class="nanobar-input" id="nanobar-quick-link-<?php echo esc_attr( (string) $index ); ?>-label" name="<?php echo esc_attr( $name ); ?>[label]" value="<?php echo esc_attr( $label ); ?>" placeholder="<?php esc_attr_e( 'Label', 'nanobar' ); ?>" data-quick-link-field="label" />
					<p class="nanobar-quick-link-row__error" data-quick-link-error="label" hidden></p>
				</div>

				<div class="nanobar-quick-link-row__field nanobar-quick-link-row__field--url">
					<?php
					/*
					 * Deliberately type="text", not type="url": a relative
					 * path like "/wp-admin/tools.php" — a valid, intended
					 * value here, see Settings\Sanitizer::sanitize_quick_links()
					 * — fails a browser's native type="url" validation,
					 * which requires an absolute URL with a scheme.
					 */
					?>
					<label class="screen-reader-text" for="nanobar-quick-link-<?php echo esc_attr( (string) $index ); ?>-url"><?php esc_html_e( 'URL', 'nanobar' ); ?></label>
					<input type="text" class="nanobar-input" id="nanobar-quick-link-<?php echo esc_attr( (string) $index ); ?>-url" name="<?php echo esc_attr( $name ); ?>[url]" value="<?php echo esc_attr( $url ); ?>" placeholder="<?php esc_attr_e( '/internal-page/', 'nanobar' ); ?>" data-quick-link-field="url" />
					<p class="nanobar-quick-link-row__error" data-quick-link-error="url" hidden></p>
				</div>

				<button type="button" class="nanobar-quick-link-row__remove" data-quick-link-remove aria-label="<?php esc_attr_e( 'Remove this link', 'nanobar' ); ?>" data-tooltip="<?php esc_attr_e( 'Remove this link', 'nanobar' ); ?>">
					<span class="dashicons dashicons-trash" aria-hidden="true"></span>
				</button>
			</div>

			<div class="nanobar-quick-link-row__roles">
				<span class="nanobar-quick-link-row__roles-label"><?php esc_html_e( 'Visible to:', 'nanobar' ); ?></span>
				<?php foreach ( $all_roles as $role_key => $role_data ) : ?>
					<label class="nanobar-pill">
						<input type="checkbox" class="nanobar-pill__input" name="<?php echo esc_attr( $name ); ?>[roles][]" value="<?php echo esc_attr( $role_key ); ?>" <?php checked( in_array( $role_key, $roles, true ) ); ?> />
						<span class="nanobar-pill__text"><?php echo esc_html( translate_user_role( $role_data['name'] ) ); ?></span>
					</label>
				<?php endforeach; ?>
				<span class="nanobar-quick-link-row__roles-divider" aria-hidden="true"></span>
				<label class="nanobar-pill nanobar-pill--mobile">
					<input type="checkbox" class="nanobar-pill__input" name="<?php echo esc_attr( $name ); ?>[mobile_visible]" value="1" <?php checked( $mobile_visible ); ?> />
					<span class="nanobar-pill__text"><span class="dashicons dashicons-smartphone" aria-hidden="true"></span><?php esc_html_e( 'Visible on mobile', 'nanobar' ); ?></span>
				</label>
				<span class="nanobar-quick-link-row__roles-hint"><?php esc_html_e( '(none checked = same roles as NanoBar itself)', 'nanobar' ); ?></span>
			</div>
		</div>
		<?php
		return (string) ob_get_clean();
	}
}
