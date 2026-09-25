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
	 * @return array<int, array{label: string, url: string, icon: string, is_self?: bool}>
	 */
	public static function get_dashboard_submenu_items(): array {
		// Also duplicated as the group's own top-level link (Frontend\Renderer's
		// $dashboard_link) — listed here too so the destination stays reachable
		// once that top-level link stops navigating directly below 666px (see
		// the ":scope > a.nanobar__item--split[href]" handling in frontend.js).
		// `is_self` marks it so Frontend\Renderer can hide this one entry above
		// that breakpoint, where the top-level link still works on its own and
		// listing it here too would just be a redundant duplicate.
		$items = array(
			array(
				'label'   => __( 'Dashboard', 'nanobar' ),
				'url'     => admin_url(),
				'icon'    => 'dashicons-dashboard',
				'is_self' => true,
			),
		);

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
	 * Post types Elementor registers for its own internal content (saved
	 * templates, Floating Elements, Elementor Pro's landing pages) — public
	 * and creatable like any other CPT, but not something a "Prefer Elementor"
	 * setting turned off should be pointing people at from the "New" submenu.
	 * Filterable in case Elementor (or Elementor Pro) adds another one this
	 * list doesn't know about yet.
	 *
	 * @return array<int, string>
	 */
	private static function get_elementor_post_types(): array {
		/**
		 * Filters the list of post type slugs treated as "belonging to
		 * Elementor" for the purpose of hiding them from NanoBar's "New"
		 * submenu when "Prefer Elementor when available" is off.
		 *
		 * @param array<int, string> $post_types Elementor's own post type slugs.
		 */
		$post_types = apply_filters(
			'nanobar_elementor_post_types',
			array( 'elementor_library', 'e-floating-buttons', 'e-landing-page' )
		);

		return is_array( $post_types ) ? $post_types : array();
	}

	/**
	 * Builds the list of items for the "New" submenu: Post, Page, Media, and
	 * all public CPTs the current user can create.
	 *
	 * @param bool $use_elementor Whether Elementor's own post types (templates, Floating Elements, …) may be included. Comes from the "use_elementor_when_available" option (default off — no default value here either, to avoid this signature silently drifting out of sync with that option's own default again; the Renderer always passes this explicitly).
	 * @return array<int, array{label: string, url: string, icon: string}>
	 */
	public static function get_new_content_items( bool $use_elementor ): array {
		$items                = array();
		$elementor_post_types = $use_elementor ? array() : self::get_elementor_post_types();

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

			if ( in_array( $cpt->name, $elementor_post_types, true ) ) {
				continue;
			}

			$icon = 'dashicons-admin-post';
			if ( ! empty( $cpt->menu_icon ) && is_string( $cpt->menu_icon ) && str_starts_with( $cpt->menu_icon, 'dashicons-' ) ) {
				$icon = $cpt->menu_icon;
			}

			$items[] = array(
				'label' => $cpt->labels->singular_name,
				'url'   => add_query_arg( 'post_type', $cpt->name, admin_url( 'post-new.php' ) ),
				'icon'  => $icon,
			);
		}

		return $items;
	}

	/**
	 * Registry of cache plugins NanoBar knows how to detect and link to.
	 *
	 * Best-effort: `purge_all_node_id`/`purge_page_node_id` are admin bar
	 * nodes belonging to the plugin itself (the panel's Cache buttons proxy a
	 * click to them — see the `data-proxy-node` handling in frontend.js and
	 * Renderer::render_panel()), and `settings_url` a guess at where that
	 * plugin's own settings screen lives.
	 *
	 * A top-level admin bar item is not necessarily itself a working "purge"
	 * link: for a plugin whose item is only a dropdown parent (its own click
	 * just opens the submenu, or even just links to its settings page — this
	 * is what LiteSpeed Cache's top-level node turned out to do, verified
	 * live, and its entry below points at its actual `litespeed-purge-all`
	 * child node instead), `purge_all_node_id` needs to name the *child* node
	 * that actually performs the purge. `purge_page_node_id` (clears only the
	 * current page/URL, not the whole site) is left `null` unless verified —
	 * a plugin's admin bar does not always expose an equivalent node for it
	 * at all. None of this has been verified against a live install of every
	 * plugin listed here except LiteSpeed Cache, WP Fastest Cache, WP Super
	 * Cache and FastCache (this plugin has no test suite — see this
	 * component's CLAUDE.md); getting a node id wrong just means that button
	 * quietly hides itself (see frontend.js), so a wrong
	 * guess is safe, merely useless — always worth checking against a real
	 * install when a site actually settles on one of the unverified entries.
	 * Add, remove or correct entries with the `nanobar_cache_plugins` filter
	 * instead of editing this method.
	 *
	 * @return array<int, array{id: string, label: string, is_active: callable(): bool, purge_all_node_id: string|null, purge_page_node_id: string|null, settings_url: string}>
	 */
	private static function get_cache_plugin_registry(): array {
		$registry = array(
			array(
				'id'                 => 'wp_rocket',
				'label'              => 'WP Rocket',
				'is_active'          => static fn (): bool => defined( 'WP_ROCKET_VERSION' ),
				'purge_all_node_id'  => 'wp-rocket',
				'purge_page_node_id' => null,
				'settings_url'       => admin_url( 'options-general.php?page=wprocket' ),
			),
			array(
				'id'                 => 'w3_total_cache',
				'label'              => 'W3 Total Cache',
				'is_active'          => static fn (): bool => defined( 'W3TC_VERSION' ),
				'purge_all_node_id'  => 'w3tc',
				'purge_page_node_id' => null,
				'settings_url'       => admin_url( 'admin.php?page=w3tc_dashboard' ),
			),
			array(
				'id'                 => 'wp_super_cache',
				'label'              => 'WP Super Cache',
				'is_active'          => static fn (): bool => function_exists( 'wp_cache_clear_cache' ),
				// Verified live against WP Super Cache 2.x: unlike the other
				// plugins above, it has no site-wide purge node on the
				// frontend admin bar at all — only a single "delete-cache"
				// node that clears the *current page's* cache (its site-wide
				// purge only exists in the is_admin() variant of the same
				// node, never rendered on the frontend NanoBar itself runs
				// on). Its href is a real, already-nonced link (not a bare
				// "#" needing separate JS), so the proxy click just follows
				// it — confirmed with a real click: the resulting round trip
				// through wp-admin (it deletes the cache, then redirects back
				// to the referring page) completed successfully.
				'purge_all_node_id'  => null,
				'purge_page_node_id' => 'delete-cache',
				'settings_url'       => admin_url( 'options-general.php?page=wpsupercache' ),
			),
			array(
				'id'                 => 'litespeed_cache',
				'label'              => 'LiteSpeed Cache',
				'is_active'          => static fn (): bool => defined( 'LSCWP_V' ),
				// Verified live against LiteSpeed Cache 7.x: the top-level
				// "litespeed-menu" node only links to the settings page (its
				// child nodes, below, are where the actual purge actions are).
				'purge_all_node_id'  => 'litespeed-purge-all',
				'purge_page_node_id' => 'litespeed-purge-single',
				'settings_url'       => admin_url( 'admin.php?page=litespeed' ),
			),
			array(
				'id'                 => 'wp_fastest_cache',
				'label'              => 'WP Fastest Cache',
				'is_active'          => static fn (): bool => class_exists( 'WpFastestCache' ),
				// Verified live against WP Fastest Cache 1.x: like LiteSpeed
				// Cache, the top-level "wpfc-toolbar-parent" node only links to
				// the settings page — the purge actions are its child nodes.
				'purge_all_node_id'  => 'wpfc-toolbar-parent-delete-cache',
				'purge_page_node_id' => 'wpfc-toolbar-parent-clear-cache-of-this-page',
				'settings_url'       => admin_url( 'admin.php?page=wpfastestcacheoptions' ),
			),
			array(
				'id'                 => 'fastcache',
				'label'              => 'FastCache',
				'is_active'          => static fn (): bool => defined( 'FASTCACHE_VERSION' ),
				// Verified live (FastCache 1.7.0): its admin bar "CDN Purge"
				// nodes only exist once its separate CDN feature is enabled
				// (`fastcache-enable`, not the local page cache this plugin
				// otherwise runs) — absent here, so there's nothing to proxy a
				// click to. Its local page cache can only be cleared from its
				// own settings screen, not the admin bar, and even the CDN
				// nodes look to have a bug in the plugin's own JS (the
				// frontend markup and the click handler's selector don't
				// match) that would need re-checking on a site with the CDN
				// active before ever setting these to something non-null.
				'purge_all_node_id'  => null,
				'purge_page_node_id' => null,
				'settings_url'       => admin_url( 'options-general.php?page=fastcache' ),
			),
		);

		/**
		 * Filters the registry of cache plugins NanoBar can show a "Cache" item
		 * for. Add or correct entries here instead of patching this plugin —
		 * each entry needs `id`, `label`, `is_active` (a callable returning
		 * bool), `purge_all_node_id` (the admin bar node whose click clears the
		 * whole site's cache — `null` if the plugin has no admin bar purge
		 * mechanism at all, which still gets it a "Cache" item linking to its
		 * settings, just without a purge button), `purge_page_node_id` (same,
		 * for the current page/URL only — `null` if the plugin has no such
		 * node) and `settings_url`.
		 *
		 * @param array<int, array{id: string, label: string, is_active: callable(): bool, purge_all_node_id: string|null, purge_page_node_id: string|null, settings_url: string}> $registry Cache plugin definitions.
		 */
		$registry = apply_filters( 'nanobar_cache_plugins', $registry );

		return is_array( $registry ) ? $registry : array();
	}

	/**
	 * Returns the first active cache plugin found in the registry, or null if
	 * none of the known ones are active.
	 *
	 * Assumes at most one cache plugin is active at a time: running two
	 * full-page cache plugins together is a well-known source of conflicts,
	 * so in practice sites essentially never do it.
	 *
	 * @return array{id: string, label: string, purge_all_node_id: string|null, purge_page_node_id: string|null, settings_url: string}|null
	 */
	public static function get_active_cache_plugin(): ?array {
		foreach ( self::get_cache_plugin_registry() as $plugin ) {
			// Same defensive reasoning as get_elementor_post_types() below:
			// the `nanobar_cache_plugins` filter is this plugin's own
			// documented extension point (see README.md), but nothing
			// enforces at runtime that a filter callback's entry actually has
			// a callable `is_active` — call_user_func() on anything else
			// (missing key, a typo, null) throws a fatal TypeError in PHP 8,
			// which would otherwise take down the whole panel for every user
			// who can see the Cache item.
			if ( ! is_array( $plugin ) || ! is_callable( $plugin['is_active'] ?? null ) ) {
				continue;
			}
			if ( call_user_func( $plugin['is_active'] ) ) {
				// Normalized like the entry's callable above: a filter can add
				// a partial or oddly-typed entry, and the panel prints these
				// values into markup (node ids also end up in a DOM id lookup
				// in frontend.js, so they're restricted to id-safe characters).
				$label        = isset( $plugin['label'] ) && is_string( $plugin['label'] ) ? $plugin['label'] : '';
				$settings_url = isset( $plugin['settings_url'] ) && is_string( $plugin['settings_url'] ) ? $plugin['settings_url'] : '';
				if ( '' === $label || '' === $settings_url ) {
					continue;
				}

				return array(
					'id'                 => isset( $plugin['id'] ) && is_string( $plugin['id'] ) ? $plugin['id'] : '',
					'label'              => $label,
					'purge_all_node_id'  => self::sanitize_node_id( $plugin['purge_all_node_id'] ?? null ),
					'purge_page_node_id' => self::sanitize_node_id( $plugin['purge_page_node_id'] ?? null ),
					'settings_url'       => $settings_url,
				);
			}
		}

		return null;
	}

	/**
	 * Restricts an admin bar node id to characters valid in a DOM id, or null.
	 *
	 * @param mixed $node_id Node id from a cache plugin registry entry.
	 * @return string|null
	 */
	private static function sanitize_node_id( mixed $node_id ): ?string {
		if ( ! is_string( $node_id ) || 1 !== preg_match( '/^[A-Za-z0-9_-]+$/', $node_id ) ) {
			return null;
		}

		return $node_id;
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
			$redirect = home_url( $wp->request );
			// Keep the query string (search, pagination, …) so the user lands on the same page.
			// phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- Sanitized right below by esc_url_raw().
			$query = isset( $_SERVER['QUERY_STRING'] ) && is_string( $_SERVER['QUERY_STRING'] ) ? wp_unslash( $_SERVER['QUERY_STRING'] ) : '';
			if ( '' !== $query ) {
				$redirect = esc_url_raw( $redirect . '?' . $query );
			}
		}

		/**
		 * Filters where the user lands after logging out from NanoBar.
		 *
		 * @param string $redirect Absolute URL. Defaults to the current page.
		 */
		$filtered = apply_filters( 'nanobar_logout_redirect', $redirect );
		$redirect = is_string( $filtered ) ? $filtered : $redirect;

		return array(
			'url'   => wp_logout_url( $redirect ),
			'label' => __( 'Log out', 'nanobar' ),
		);
	}
}
