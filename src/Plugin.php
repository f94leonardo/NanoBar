<?php
/**
 * Plugin bootstrap and hook wiring.
 *
 * @package NanoBar
 */

declare( strict_types=1 );

namespace NanoBar;

use NanoBar\Frontend\Renderer;
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
		add_action( 'init', array( $this, 'load_textdomain' ) );

		add_action( 'admin_init', array( $this->settings_page, 'register_settings' ) );
		add_action( 'admin_menu', array( $this->settings_page, 'register_menu' ) );
		add_action( 'admin_enqueue_scripts', array( $this->settings_page, 'enqueue_assets' ) );

		add_filter( 'show_admin_bar', array( $this->renderer, 'maybe_force_admin_bar' ) );
		add_action( 'wp_enqueue_scripts', array( $this->renderer, 'enqueue_assets' ) );
		add_action( 'wp_footer', array( $this->renderer, 'render_panel' ), 999 );
	}

	/**
	 * Loads the plugin translations from the bundled /languages folder.
	 *
	 * Hooked on `init` rather than `plugins_loaded` so WordPress 6.7+ does not
	 * report a "translation loading triggered too early" notice.
	 */
	public function load_textdomain(): void {
		load_plugin_textdomain( 'nanobar', false, dirname( plugin_basename( NANOBAR_FILE ) ) . '/languages' );
	}
}
