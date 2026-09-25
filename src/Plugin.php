<?php
/**
 * Plugin bootstrap and hook wiring.
 *
 * @package NanoBar
 */

declare( strict_types=1 );

namespace NanoBar;

use NanoBar\Frontend\Renderer;
use NanoBar\Settings\Backup;
use NanoBar\Settings\Page;

defined( 'ABSPATH' ) || exit;

/**
 * Wires every collaborator to its WordPress hook. Kept deliberately free of
 * side effects beyond hook registration, so simply loading this class never
 * does any work until WordPress fires the hooks.
 */
final class Plugin {

	/**
	 * Singleton instance.
	 *
	 * @var self|null
	 */
	private static ?self $instance = null;

	/**
	 * Settings page collaborator.
	 *
	 * @var Page
	 */
	private Page $settings_page;

	/**
	 * Frontend renderer collaborator.
	 *
	 * @var Renderer
	 */
	private Renderer $renderer;

	/**
	 * Returns the shared plugin instance, creating it on first call.
	 */
	public static function instance(): self {
		if ( null === self::$instance ) {
			self::$instance = new self();
		}

		return self::$instance;
	}

	/**
	 * Cache-busting version for a bundled asset.
	 *
	 * The plugin version in production; in a local/development environment
	 * (or with SCRIPT_DEBUG on) the file's modification time, so a rebuilt
	 * asset is never served from the browser cache.
	 *
	 * @param string $relative_path Path relative to the plugin root (e.g. assets/css/frontend.css).
	 */
	public static function asset_version( string $relative_path ): string {
		$is_dev = ( defined( 'SCRIPT_DEBUG' ) && SCRIPT_DEBUG )
			|| in_array( wp_get_environment_type(), array( 'local', 'development' ), true );

		if ( $is_dev ) {
			$mtime = @filemtime( plugin_dir_path( NANOBAR_FILE ) . $relative_path ); // phpcs:ignore WordPress.PHP.NoSilencedErrors.Discouraged -- A missing file just falls back to the plugin version.
			if ( false !== $mtime ) {
				return (string) $mtime;
			}
		}

		return NANOBAR_VERSION;
	}

	/**
	 * Constructs the collaborators. Hooks are registered separately, in boot().
	 */
	private function __construct() {
		$this->settings_page = new Page();
		$this->renderer      = new Renderer();
	}

	/**
	 * Registers every WordPress hook the plugin relies on.
	 */
	public function boot(): void {
		( new Backup() )->register();

		add_action( 'admin_init', array( $this->settings_page, 'register_settings' ) );
		add_action( 'admin_menu', array( $this->settings_page, 'register_menu' ) );
		add_action( 'admin_enqueue_scripts', array( $this->settings_page, 'enqueue_assets' ) );

		add_filter( 'show_admin_bar', array( $this->renderer, 'maybe_force_admin_bar' ) );
		add_action( 'wp_enqueue_scripts', array( $this->renderer, 'enqueue_assets' ) );
		add_action( 'wp_footer', array( $this->renderer, 'render_panel' ), 999 );
	}
}
