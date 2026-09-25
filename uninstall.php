<?php
/**
 * Uninstall routine: removes NanoBar's stored option and cached data.
 *
 * @package NanoBar
 */

declare( strict_types=1 );

defined( 'WP_UNINSTALL_PLUGIN' ) || exit;

/**
 * Removes NanoBar's data from the current site's tables.
 */
function nanobar_uninstall_site(): void {
	global $wpdb;

	delete_option( 'nanobar_options' );

	// The dashicons list transient is keyed by WordPress version
	// (Settings\Page::get_all_dashicons()), so it is removed by prefix.
	// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- one-off cleanup on uninstall.
	$wpdb->query(
		$wpdb->prepare(
			"DELETE FROM {$wpdb->options} WHERE option_name LIKE %s OR option_name LIKE %s",
			$wpdb->esc_like( '_transient_nanobar_dashicons_' ) . '%',
			$wpdb->esc_like( '_transient_timeout_nanobar_dashicons_' ) . '%'
		)
	);
}

if ( is_multisite() ) {
	// Uninstalling a network-activated plugin runs this file once for the
	// whole network, so every site's own options table has to be cleaned.
	foreach ( get_sites(
		array(
			'fields' => 'ids',
			'number' => 0,
		)
	) as $nanobar_site_id ) {
		switch_to_blog( (int) $nanobar_site_id );
		nanobar_uninstall_site();
		restore_current_blog();
	}
} else {
	nanobar_uninstall_site();
}
