<?php
/**
 * Dashboard submenu, "New" submenu and logout link.
 *
 * @package NanoBar
 */

declare( strict_types=1 );

namespace NanoBar\Frontend;

defined( 'ABSPATH' ) || exit;

/**
 * Builds the panel's secondary menus.
 */
final class Menus {

	/**
	 * Builds the list of items for the "Dashboard" submenu, visible only to
	 * Administrators: Dashboard, Media, Plugins, Settings, and Contact (if
	 * Contact Form 7 is active).
	 *
	 * @return array<int, array{label: string, url: string, icon: string}>
	 */
	public static function get_dashboard_submenu_items(): array {
		$items = array();

		if ( current_user_can( 'upload_files' ) ) {
			$items[] = array(
				'label' => __( 'Media', 'nanobar' ),
				'url'   => admin_url( 'upload.php' ),
				'icon'  => 'dashicons-admin-media',
			);
		}

		if ( current_user_can( 'edit_posts' ) ) {
			$items[] = array(
				'label' => __( 'Posts', 'nanobar' ),
				'url'   => admin_url( 'edit.php' ),
				'icon'  => 'dashicons-admin-post',
			);
		}

		if ( current_user_can( 'edit_pages' ) ) {
			$items[] = array(
				'label' => __( 'Pages', 'nanobar' ),
				'url'   => admin_url( 'edit.php?post_type=page' ),
				'icon'  => 'dashicons-admin-page',
			);
		}

		$items[] = array(
			'label' => __( 'Plugins', 'nanobar' ),
			'url'   => admin_url( 'plugins.php' ),
			'icon'  => 'dashicons-admin-plugins',
		);
		$items[] = array(
			'label' => __( 'Settings', 'nanobar' ),
			'url'   => admin_url( 'options-general.php' ),
			'icon'  => 'dashicons-admin-settings',
		);

		if ( defined( 'WPCF7_VERSION' ) ) {
			$items[] = array(
				'label' => __( 'Contact', 'nanobar' ),
				'url'   => admin_url( 'admin.php?page=wpcf7' ),
				'icon'  => 'dashicons-feedback',
			);
		}

		return $items;
	}

	/**
	 * Builds the list of items for the "New" submenu: Post, Page, Media, and
	 * all public CPTs the current user can create.
	 *
	 * @return array<int, array{label: string, url: string, icon: string}>
	 */
	public static function get_new_content_items(): array {
		$items = array();

		$post_type_obj = get_post_type_object( 'post' );
		if ( $post_type_obj && current_user_can( $post_type_obj->cap->create_posts ) ) {
			$items[] = array(
				'label' => $post_type_obj->labels->singular_name,
				'url'   => admin_url( 'post-new.php' ),
				'icon'  => 'dashicons-admin-post',
			);
		}

		$page_type_obj = get_post_type_object( 'page' );
		if ( $page_type_obj && current_user_can( $page_type_obj->cap->create_posts ) ) {
			$items[] = array(
				'label' => $page_type_obj->labels->singular_name,
				'url'   => admin_url( 'post-new.php?post_type=page' ),
				'icon'  => 'dashicons-admin-page',
			);
		}

		if ( current_user_can( 'upload_files' ) ) {
			$items[] = array(
				'label' => __( 'Media', 'nanobar' ),
				'url'   => admin_url( 'media-new.php' ),
				'icon'  => 'dashicons-admin-media',
			);
		}

		$custom_types = get_post_types(
			array(
				'public'   => true,
				'show_ui'  => true,
				'_builtin' => false,
			),
			'objects'
		);

		foreach ( $custom_types as $cpt ) {
			if ( ! current_user_can( $cpt->cap->create_posts ) ) {
				continue;
			}

			$icon = 'dashicons-admin-post';
			if ( ! empty( $cpt->menu_icon ) && is_string( $cpt->menu_icon ) && str_starts_with( $cpt->menu_icon, 'dashicons-' ) ) {
				$icon = $cpt->menu_icon;
			}

			$items[] = array(
				'label' => $cpt->labels->singular_name,
				'url'   => admin_url( 'post-new.php?post_type=' . $cpt->name ),
				'icon'  => $icon,
			);
		}

		return $items;
	}

	/**
	 * Builds the logout link for the panel.
	 *
	 * By default the user lands back on the page they were reading, which is the
	 * useful behavior for a frontend bar; wp_logout_url() adds the logout nonce and
	 * WordPress validates the redirect with wp_safe_redirect(), so it can never
	 * leave the site. Use the `nanobar_logout_redirect` filter to send people
	 * somewhere else (the home page, the login screen, a "see you soon" page).
	 *
	 * @return array{url: string, label: string}
	 */
	public static function get_logout_link(): array {
		global $wp;

		$redirect = home_url( '/' );
		if ( isset( $wp->request ) ) {
			$redirect = home_url( add_query_arg( array(), $wp->request ) );
		}

		/**
		 * Filters where the user lands after logging out from NanoBar.
		 *
		 * @param string $redirect Absolute URL. Defaults to the current page.
		 */
		$redirect = apply_filters( 'nanobar_logout_redirect', $redirect );

		return array(
			'url'   => wp_logout_url( $redirect ),
			'label' => __( 'Log out', 'nanobar' ),
		);
	}
}
