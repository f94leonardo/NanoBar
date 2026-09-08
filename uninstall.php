<?php
/**
 * Uninstall routine: removes NanoBar's stored option.
 *
 * @package NanoBar
 */

declare( strict_types=1 );

defined( 'WP_UNINSTALL_PLUGIN' ) || exit;

delete_option( 'nanobar_options' );
