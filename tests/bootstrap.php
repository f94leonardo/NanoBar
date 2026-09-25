<?php
/**
 * PHPUnit bootstrap: loads the real WordPress runtime of the site the plugin
 * is installed in (the tests only exercise pure sanitizing/normalizing code,
 * they never write to the database).
 *
 * Override the location with the WP_LOAD_PATH environment variable.
 *
 * @package NanoBar
 */

declare( strict_types=1 );

$nanobar_wp_load = getenv( 'WP_LOAD_PATH' );
if ( ! is_string( $nanobar_wp_load ) || '' === $nanobar_wp_load ) {
	$nanobar_wp_load = dirname( __DIR__, 4 ) . '/wp-load.php';
}

if ( ! is_readable( $nanobar_wp_load ) ) {
	fwrite( STDERR, "wp-load.php not found at {$nanobar_wp_load}. Set WP_LOAD_PATH.\n" );
	exit( 1 );
}

// wp-load.php exits without this when run from the CLI outside a web request.
$_SERVER['HTTP_HOST']   = $_SERVER['HTTP_HOST'] ?? 'localhost';
$_SERVER['REQUEST_URI'] = $_SERVER['REQUEST_URI'] ?? '/';

require_once $nanobar_wp_load;
