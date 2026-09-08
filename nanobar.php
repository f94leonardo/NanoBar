<?php
/**
 * Plugin Name: NanoBar Compact Admin Toolbar
 * Plugin URI:  https://github.com/f94leonardo/NanoBar
 * Description: Replaces WordPress's classic admin bar, on the frontend, with a
 *              compact, role-aware floating panel, configurable from a single
 *              global settings page (Settings → NanoBar).
 * Version:     1.0.0
 * Requires at least: 6.8
 * Requires PHP: 8.4
 * Author:      f94leonardo
 * Author URI:  https://github.com/f94leonardo
 * Text Domain: nanobar
 * Domain Path: /languages
 * License:     GPL v2 or later
 * License URI: https://www.gnu.org/licenses/gpl-2.0.html
 *
 * @package NanoBar
 */

declare( strict_types=1 );

defined( 'ABSPATH' ) || exit;

define( 'NANOBAR_FILE', __FILE__ );
define( 'NANOBAR_VERSION', '1.0.0' );

if ( ! file_exists( __DIR__ . '/vendor/autoload.php' ) ) {
	add_action(
		'admin_notices',
		static function (): void {
			printf(
				'<div class="notice notice-error"><p>%s</p></div>',
				esc_html__( 'NanoBar is missing its Composer autoloader. Run "composer install" in the plugin directory.', 'nanobar' )
			);
		}
	);
	return;
}

require_once __DIR__ . '/vendor/autoload.php';

NanoBar\Plugin::instance()->boot();
