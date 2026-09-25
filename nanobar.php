<?php
/**
 * Plugin Name: NanoBar Compact Admin Toolbar
 * Plugin URI:  https://github.com/f94leonardo/NanoBar
 * Description: Replaces WordPress's classic admin bar, on the frontend, with a
 *              compact, role-aware floating panel, configurable from a single
 *              global settings page (Settings → NanoBar).
 * Version:     1.0.0
 * Requires at least: 6.8
 * Requires PHP: 8.0
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

/**
 * Lightweight PSR-4 autoloader for the `NanoBar\` namespace, mapped to /src.
 * The plugin has no runtime dependencies (Composer is only used in
 * development, for coding-standards and static-analysis tooling), so it
 * needs no vendor/autoload.php and works as soon as it's activated.
 */
spl_autoload_register(
	static function ( string $class_name ): void {
		$prefix = 'NanoBar\\';
		if ( ! str_starts_with( $class_name, $prefix ) ) {
			return;
		}

		$relative_path = str_replace( '\\', '/', substr( $class_name, strlen( $prefix ) ) );
		$file          = __DIR__ . '/src/' . $relative_path . '.php';

		if ( is_file( $file ) ) {
			require $file;
		}
	}
);

NanoBar\Plugin::instance()->boot();
