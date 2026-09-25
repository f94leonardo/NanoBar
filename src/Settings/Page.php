<?php
/**
 * Settings → NanoBar admin page.
 *
 * @package NanoBar
 */

declare( strict_types=1 );

namespace NanoBar\Settings;

use NanoBar\Config;
use NanoBar\Frontend\Menus;
use NanoBar\Plugin;

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
			Plugin::asset_version( 'assets/css/admin.css' )
		);

		// The page script is split into modules (each registers itself on
		// window.NanoBarAdmin); admin-settings.js is the entry point that
		// initialises them all, so it depends on every other file.
		$modules = array(
			'nanobar-admin-helpers'     => array( 'assets/js/admin-helpers.js', array() ),
			'nanobar-admin-core'        => array( 'assets/js/admin-core.js', array( 'jquery', 'nanobar-admin-helpers' ) ),
			'nanobar-admin-layout'      => array( 'assets/js/admin-layout.js', array( 'nanobar-admin-core' ) ),
			'nanobar-admin-preview'     => array( 'assets/js/admin-preview.js', array( 'nanobar-admin-core', 'wp-color-picker' ) ),
			'nanobar-admin-items'       => array( 'assets/js/admin-items.js', array( 'nanobar-admin-core' ) ),
			'nanobar-admin-quick-links' => array( 'assets/js/admin-quick-links.js', array( 'nanobar-admin-core', 'jquery-ui-sortable' ) ),
			'nanobar-admin-save'        => array( 'assets/js/admin-save.js', array( 'nanobar-admin-core' ) ),
			'nanobar-admin-defaults'    => array( 'assets/js/admin-defaults.js', array( 'nanobar-admin-core', 'wp-color-picker' ) ),
			'nanobar-admin-backup'      => array( 'assets/js/admin-backup.js', array( 'nanobar-admin-core' ) ),
		);
		foreach ( $modules as $handle => $module ) {
			wp_enqueue_script(
				$handle,
				plugins_url( $module[0], NANOBAR_FILE ),
				$module[1],
				Plugin::asset_version( $module[0] ),
				true
			);
		}

		wp_enqueue_script(
			'nanobar-admin',
			plugins_url( 'assets/js/admin-settings.js', NANOBAR_FILE ),
			array_keys( $modules ),
			Plugin::asset_version( 'assets/js/admin-settings.js' ),
			true
		);

		// Defaults and constraints come straight from Options::get_defaults()
		// and Config::get(): the same single source of truth used by the
		// sanitizer, the frontend renderer and the "Restore defaults" button,
		// so the live preview can never drift from what gets saved.
		// Attached to the first module (not the entry point) because
		// admin-core.js reads nanobarSettings as soon as it loads.
		wp_localize_script(
			'nanobar-admin-helpers',
			'nanobarSettings',
			array(
				'defaults'     => Options::get_defaults(),
				'config'       => Config::get(),
				// The "Quick links" icon picker's full grid is built entirely
				// client-side from this list — see get_all_dashicons() below.
				'allDashicons' => $this->get_all_dashicons(),
				'i18n'         => array(
					// Reuses the exact string/placeholders printed server-side
					// below, so a translator only ever has to translate it once.
					/* translators: 1: number of currently visible items, 2: total number of items. */
					'items_active'           => __( '%1$d of %2$d active', 'nanobar' ),
					// Client-side validation for the "Quick links" repeater
					// (validateQuickLinkRow() in admin-settings.js) — a
					// best-effort mirror of the authoritative checks that
					// still run server-side in Settings\Sanitizer::sanitize_quick_links(),
					// so the admin gets feedback before submitting instead of
					// the row silently being dropped on save.
					'quickLinkLabelRequired' => __( 'Enter a label.', 'nanobar' ),
					'quickLinkUrlRequired'   => __( 'Enter a link.', 'nanobar' ),
					'quickLinkUrlInvalid'    => __( 'Only links to this site are allowed (e.g. /page/).', 'nanobar' ),
					'quickLinksHaveErrors'   => __( 'Fix the highlighted quick links before saving.', 'nanobar' ),
					// The icon picker overlay (initIconPicker() in admin-settings.js).
					'chooseIcon'             => __( 'Choose an icon', 'nanobar' ),
					'searchIcons'            => __( 'Search icons…', 'nanobar' ),
					'noIconsFound'           => __( 'No icons found.', 'nanobar' ),
					/* translators: 1: minimum size with its unit (e.g. 32px), 2: maximum size with its unit (e.g. 72px). */
					'sizeRange'              => __( 'From %1$s to %2$s.', 'nanobar' ),
					// Accessible name of the dismiss button added to settings notices.
					'closeNotice'            => __( 'Close notification', 'nanobar' ),
					// "Restore defaults" confirmation (both the header and footer
					// buttons — see the shared .nanobar-reset-defaults-trigger
					// class) and the "Quick links" count badge, updated live as
					// rows are added/removed the same way "Menu items" already is.
					'confirmRestoreDefaults' => __( 'Restore every NanoBar setting to its default value? Nothing is saved until you click Save changes.', 'nanobar' ),
					'restore'                => __( 'Restore', 'nanobar' ),
					'cancel'                 => __( 'Cancel', 'nanobar' ),
					'backupConfirmImport'    => __( 'Replace your current NanoBar settings with the ones in this file? This cannot be undone.', 'nanobar' ),
					'backupImportAction'     => __( 'Import', 'nanobar' ),
					'backupInvalidFile'      => __( 'That file is not a valid NanoBar settings export.', 'nanobar' ),
					'backupTooLarge'         => __( 'The file is too large to be a NanoBar settings export.', 'nanobar' ),
					/* translators: 1: NanoBar version that produced the export, 2: number of quick links in it. */
					'backupSummary'          => __( 'Export from NanoBar %1$s · %2$d quick links', 'nanobar' ),
					'restored'               => __( 'Defaults restored. Save changes to keep them.', 'nanobar' ),
					'undo'                   => __( 'Undo', 'nanobar' ),
					// Save status (AJAX save in admin-settings.js) and the unsaved-changes label next to Save.
					'unsaved'                => __( 'Unsaved changes', 'nanobar' ),
					'saving'                 => __( 'Saving…', 'nanobar' ),
					'saved'                  => __( 'Settings saved.', 'nanobar' ),
					'saveFailed'             => __( 'Could not save the settings. Please try again.', 'nanobar' ),
					'previewEmpty'           => __( 'No items selected.', 'nanobar' ),
					/* translators: 1: number of quick links currently configured, 2: maximum number allowed. */
					'quickLinksCount'        => __( '%1$d of %2$d used', 'nanobar' ),
				),
			)
		);
	}

	/**
	 * The full list of dashicon classes shipped with this install of
	 * WordPress core, parsed straight from its own dashicons.css — used to
	 * build the "Quick links" icon picker's grid client-side (see
	 * `allDashicons` in enqueue_assets() above). Parsing the stylesheet
	 * instead of hand-maintaining a list means it can never drift out of sync
	 * with whatever dashicons version actually ships with the running core,
	 * across a WordPress upgrade in either direction.
	 *
	 * Cached in a transient keyed by the core version, so normal page loads
	 * never re-parse the file — only the first settings-page view after a
	 * WordPress core update does.
	 *
	 * @return array<int, string>
	 */
	private function get_all_dashicons(): array {
		$cache_key = 'nanobar_dashicons_' . get_bloginfo( 'version' );
		$cached    = get_transient( $cache_key );
		if ( is_array( $cached ) && ! empty( $cached ) ) {
			return $cached;
		}

		// A hardcoded fallback, in case the stylesheet can't be read (a
		// non-standard core install layout, file permissions, …) — better a
		// single working icon than a broken/empty picker.
		// 'wp-includes' (not the WPINC constant, which the WordPress stub
		// package PHPStan reads doesn't declare) — its value is fixed by
		// WordPress core itself in wp-settings.php, never customizable.
		$icons    = array( 'dashicons-admin-links' );
		$css_file = ABSPATH . 'wp-includes/css/dashicons.css';

		if ( is_readable( $css_file ) ) {
			// phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents -- local core file, not a remote URL; wp_remote_get() does not apply.
			$css = (string) file_get_contents( $css_file );
			if ( preg_match_all( '/\.(dashicons-[a-z0-9-]+):before/', $css, $matches ) ) {
				// `.dashicons-before:before` is the helper class, not an icon.
				$icons = array_values( array_diff( array_unique( $matches[1] ), array( 'dashicons-before' ) ) );
				sort( $icons );
			}
		}

		set_transient( $cache_key, $icons, WEEK_IN_SECONDS );

		return $icons;
	}

	/**
	 * Renders the Settings → NanoBar page.
	 */
	public function render(): void {
		if ( ! current_user_can( 'manage_options' ) ) {
			return;
		}

		$view = new View();
		?>
		<div class="wrap nanobar-settings-wrap">
			<form method="post" action="options.php" class="nanobar-form">
				<?php
				$view->header();
				settings_fields( 'nanobar_settings_group' );
				?>

				<div class="nanobar-bento-grid">
					<?php
					$view->general();
					$view->roles();
					?>

					<?php
					/*
					 * Appearance + Preview share a wrapper: their own two-column
					 * row, so the two cards stretch to the same height.
					 */
					?>
					<div class="nanobar-bento-pair">
						<?php
						$view->appearance();
						$view->preview();
						?>
					</div>

					<?php
					$view->menu_items();
					$view->quick_links();
					?>
				</div>

				<?php $view->quick_link_template(); ?>
			</form>

			<?php
			// Backup lives after the settings form (forms cannot nest).
			Backup::render_card();
			?>

			<?php
			/*
			 * Toast stack (admin-settings.js): save results, the "Restore
			 * defaults" confirmation and its undo. A polite live region so
			 * screen readers announce each message.
			 */
			?>
			<div class="nanobar-toasts" id="nanobar-toasts" role="status" aria-live="polite"></div>

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
				<?php Backup::render_result_notice(); ?>
			</div>
		</div>
		<?php
	}
}
