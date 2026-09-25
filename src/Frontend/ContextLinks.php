<?php
/**
 * Context-aware admin links: edit link, block template hierarchy, Site Editor.
 *
 * @package NanoBar
 */

declare( strict_types=1 );

namespace NanoBar\Frontend;

use NanoBar\Support\Str;
use WP_Block_Template;
use WP_Term;
use WP_User;

defined( 'ABSPATH' ) || exit;

/**
 * Resolves the "Edit" and "Edit site" links for whatever is currently being
 * viewed on the frontend.
 */
final class ContextLinks {

	/**
	 * Determines the "Edit" link for the current context: single content,
	 * taxonomy archive (category/tag/custom taxonomy), author archive, date
	 * archive, or post type archive.
	 *
	 * @return array{url: string, label: string}|null
	 */
	public static function get_context_edit_link(): ?array {
		if ( is_singular() ) {
			$queried_id = get_queried_object_id();
			if ( ! current_user_can( 'edit_post', $queried_id ) ) {
				return null;
			}
			$edit_link = get_edit_post_link( $queried_id, '' );
			if ( ! $edit_link ) {
				return null;
			}

			$post_type_obj = get_post_type_object( (string) get_post_type( $queried_id ) );
			$label         = __( 'Edit content', 'nanobar' );
			if ( $post_type_obj && isset( $post_type_obj->labels->singular_name ) ) {
				/* translators: %s: content type name (e.g. Page, Post). */
				$label = sprintf( __( 'Edit %s', 'nanobar' ), Str::lower( $post_type_obj->labels->singular_name ) );
			}

			return array(
				'url'   => $edit_link,
				'label' => $label,
			);
		}

		if ( is_category() || is_tag() || is_tax() ) {
			$term = get_queried_object();
			if ( ! ( $term instanceof WP_Term ) || ! current_user_can( 'edit_term', $term->term_id ) ) {
				return null;
			}
			$edit_link = get_edit_term_link( $term->term_id, $term->taxonomy );
			if ( ! $edit_link ) {
				return null;
			}

			$tax_obj = get_taxonomy( $term->taxonomy );
			$label   = __( 'Edit term', 'nanobar' );
			if ( $tax_obj && isset( $tax_obj->labels->singular_name ) ) {
				/* translators: %s: taxonomy name (e.g. Category, Tag). */
				$label = sprintf( __( 'Edit %s', 'nanobar' ), Str::lower( $tax_obj->labels->singular_name ) );
			}

			return array(
				'url'   => $edit_link,
				'label' => $label,
			);
		}

		if ( is_author() ) {
			$author_id = get_queried_object_id();
			if ( ! current_user_can( 'edit_user', $author_id ) ) {
				return null;
			}
			$edit_link = get_edit_user_link( $author_id );
			if ( ! $edit_link ) {
				return null;
			}

			return array(
				'url'   => $edit_link,
				'label' => __( 'Edit user', 'nanobar' ),
			);
		}

		if ( is_date() ) {
			$post_type_obj = get_post_type_object( 'post' );
			if ( ! $post_type_obj || ! current_user_can( $post_type_obj->cap->edit_posts ) ) {
				return null;
			}

			$args  = array( 'post_type' => 'post' );
			$year  = get_query_var( 'year' );
			$month = get_query_var( 'monthnum' );
			if ( $year && $month ) {
				$args['m'] = $year . zeroise( $month, 2 );
			} elseif ( $year ) {
				$args['m'] = $year;
			}

			return array(
				'url'   => add_query_arg( $args, admin_url( 'edit.php' ) ),
				'label' => __( 'Manage posts from this period', 'nanobar' ),
			);
		}

		if ( is_post_type_archive() ) {
			$post_type = get_query_var( 'post_type' );
			if ( is_array( $post_type ) ) {
				$post_type = reset( $post_type );
			}
			$post_type_obj = get_post_type_object( (string) $post_type );
			if ( ! $post_type_obj || ! current_user_can( $post_type_obj->cap->edit_posts ) ) {
				return null;
			}

			/* translators: %s: plural content type name. */
			$label = sprintf( __( 'Manage %s', 'nanobar' ), Str::lower( $post_type_obj->labels->name ) );

			return array(
				'url'   => add_query_arg( 'post_type', $post_type_obj->name, admin_url( 'edit.php' ) ),
				'label' => $label,
			);
		}

		return null;
	}

	/**
	 * Builds, in priority order, the block template hierarchy (following the
	 * same naming convention as classic PHP templates) for the current frontend
	 * context, including any custom template manually assigned to a single
	 * piece of content.
	 *
	 * @return array<int, string>
	 */
	public static function get_current_template_hierarchy(): array {
		$hierarchy = array();

		if ( is_front_page() ) {
			$hierarchy[] = 'front-page';

			if ( is_home() ) {
				$hierarchy[] = 'home';
			} else {
				$queried_id = get_queried_object_id();
				$assigned   = get_page_template_slug( $queried_id );
				if ( $assigned && false === strpos( $assigned, '.php' ) ) {
					$hierarchy[] = $assigned;
				}
				$post_obj = get_post( $queried_id );
				if ( $post_obj ) {
					$hierarchy[] = 'page-' . $post_obj->post_name;
					$hierarchy[] = 'page-' . $post_obj->ID;
				}
				$hierarchy[] = 'page';
				$hierarchy[] = 'singular';
			}

			$hierarchy[] = 'index';
			return $hierarchy;
		}

		if ( is_home() ) {
			$hierarchy[] = 'home';
			$hierarchy[] = 'index';
			return $hierarchy;
		}

		if ( is_singular() ) {
			$queried_id = get_queried_object_id();
			$assigned   = get_page_template_slug( $queried_id );
			if ( $assigned && false === strpos( $assigned, '.php' ) ) {
				$hierarchy[] = $assigned;
			}

			if ( is_page() ) {
				$post_obj = get_post( $queried_id );
				if ( $post_obj ) {
					$hierarchy[] = 'page-' . $post_obj->post_name;
					$hierarchy[] = 'page-' . $post_obj->ID;
				}
				$hierarchy[] = 'page';
			} elseif ( is_attachment() ) {
				$mime_type = get_post_mime_type( $queried_id );
				if ( $mime_type ) {
					$mime_parts = array_pad( explode( '/', $mime_type ), 2, '' );
					if ( ! empty( $mime_parts[1] ) ) {
						$hierarchy[] = 'single-attachment-' . $mime_parts[1];
					}
					$hierarchy[] = 'single-attachment-' . $mime_parts[0];
				}
				$hierarchy[] = 'attachment';
			} else {
				$post_type = get_post_type( $queried_id );
				$post_obj  = get_post( $queried_id );
				if ( $post_obj ) {
					$hierarchy[] = 'single-' . $post_type . '-' . $post_obj->post_name;
				}
				$hierarchy[] = 'single-' . $post_type;
				$hierarchy[] = 'single';
			}

			$hierarchy[] = 'singular';
			$hierarchy[] = 'index';
			return $hierarchy;
		}

		if ( is_category() ) {
			$term = get_queried_object();
			if ( $term instanceof WP_Term ) {
				$hierarchy[] = 'category-' . $term->slug;
				$hierarchy[] = 'category-' . $term->term_id;
			}
			$hierarchy[] = 'category';
			$hierarchy[] = 'archive';
			$hierarchy[] = 'index';
			return $hierarchy;
		}

		if ( is_tag() ) {
			$term = get_queried_object();
			if ( $term instanceof WP_Term ) {
				$hierarchy[] = 'tag-' . $term->slug;
				$hierarchy[] = 'tag-' . $term->term_id;
			}
			$hierarchy[] = 'tag';
			$hierarchy[] = 'archive';
			$hierarchy[] = 'index';
			return $hierarchy;
		}

		if ( is_tax() ) {
			$term = get_queried_object();
			if ( $term instanceof WP_Term ) {
				$hierarchy[] = 'taxonomy-' . $term->taxonomy . '-' . $term->slug;
				$hierarchy[] = 'taxonomy-' . $term->taxonomy;
			}
			$hierarchy[] = 'taxonomy';
			$hierarchy[] = 'archive';
			$hierarchy[] = 'index';
			return $hierarchy;
		}

		if ( is_author() ) {
			$author_obj = get_queried_object();
			if ( $author_obj instanceof WP_User ) {
				$hierarchy[] = 'author-' . $author_obj->user_nicename;
				$hierarchy[] = 'author-' . $author_obj->ID;
			}
			$hierarchy[] = 'author';
			$hierarchy[] = 'archive';
			$hierarchy[] = 'index';
			return $hierarchy;
		}

		if ( is_date() ) {
			$hierarchy[] = 'date';
			$hierarchy[] = 'archive';
			$hierarchy[] = 'index';
			return $hierarchy;
		}

		if ( is_post_type_archive() ) {
			$post_type = get_query_var( 'post_type' );
			if ( is_array( $post_type ) ) {
				$post_type = reset( $post_type );
			}
			if ( $post_type ) {
				$hierarchy[] = 'archive-' . $post_type;
			}
			$hierarchy[] = 'archive';
			$hierarchy[] = 'index';
			return $hierarchy;
		}

		if ( is_search() ) {
			$hierarchy[] = 'search';
			$hierarchy[] = 'index';
			return $hierarchy;
		}

		if ( is_404() ) {
			$hierarchy[] = '404';
			$hierarchy[] = 'index';
			return $hierarchy;
		}

		$hierarchy[] = 'index';
		return $hierarchy;
	}

	/**
	 * Walks up the hierarchy of candidate slugs until it finds a block template
	 * that actually exists in the active theme (theme file or DB-saved
	 * override).
	 *
	 * @param array<int, string> $hierarchy Candidate slugs, from most specific to most generic.
	 * @return WP_Block_Template|null
	 */
	public static function resolve_block_template( array $hierarchy ): ?WP_Block_Template {
		$theme = get_stylesheet();
		foreach ( $hierarchy as $slug ) {
			$template = get_block_template( $theme . '//' . $slug, 'wp_template' );
			if ( $template ) {
				return $template;
			}
		}
		return null;
	}

	/**
	 * Builds the "Edit site" link: if Elementor applies to the current view and
	 * the "Prefer Elementor when available" setting is on, links straight into
	 * Elementor instead (see get_elementor_edit_link()); otherwise, if the
	 * theme is block-based (FSE), opens the Site Editor directly on the
	 * template that is actually rendering the current page (also respecting
	 * custom templates assigned manually); if the theme is classic, opens
	 * Appearance → Themes.
	 *
	 * @param bool $use_elementor Whether to prefer an Elementor link over the native Site Editor/Appearance one, when Elementor applies to the current view. Comes from the "use_elementor_when_available" option (default off — no default value here either, to avoid this signature silently drifting out of sync with that option's own default again; the Renderer always passes this explicitly).
	 * @return array{url: string, label: string, title: string, icon: string}
	 */
	public static function get_site_editor_link( bool $use_elementor ): array {
		if ( $use_elementor ) {
			$elementor_link = self::get_elementor_edit_link();
			if ( null !== $elementor_link ) {
				return $elementor_link;
			}
		}

		if ( ! wp_is_block_theme() ) {
			return array(
				'url'   => admin_url( 'themes.php' ),
				'label' => __( 'Appearance: Themes', 'nanobar' ),
				'title' => __( 'Appearance: Themes', 'nanobar' ),
				'icon'  => 'dashicons-admin-appearance',
			);
		}

		$template = self::resolve_block_template( self::get_current_template_hierarchy() );

		if ( ! $template ) {
			return array(
				'url'   => admin_url( 'site-editor.php' ),
				'label' => __( 'Edit site', 'nanobar' ),
				'title' => __( 'Edit site', 'nanobar' ),
				'icon'  => 'dashicons-admin-appearance',
			);
		}

		$url = add_query_arg(
			array(
				'postId'   => $template->theme . '//' . $template->slug,
				'postType' => 'wp_template',
				'canvas'   => 'edit',
			),
			admin_url( 'site-editor.php' )
		);

		$title = ! empty( $template->title ) ? $template->title : __( 'Current template', 'nanobar' );

		return array(
			'url'   => $url,
			'label' => __( 'Edit site', 'nanobar' ),
			/* translators: %s: block template name. */
			'title' => sprintf( __( 'Template: %s', 'nanobar' ), $title ),
			'icon'  => 'dashicons-admin-appearance',
		);
	}

	/**
	 * Builds the "Edit with Elementor" link, when Elementor actually applies to
	 * what's currently being viewed: either the singular content itself was
	 * built with Elementor's page builder, or an Elementor Pro Theme Builder
	 * template (header, footer, single or archive) is controlling part of the
	 * current view. Returns null when Elementor isn't active, or neither
	 * condition is met, so get_site_editor_link() falls back to the native
	 * Site Editor/Appearance link.
	 *
	 * @return array{url: string, label: string, title: string, icon: string}|null
	 */
	private static function get_elementor_edit_link(): ?array {
		if ( ! defined( 'ELEMENTOR_VERSION' ) ) {
			return null;
		}

		if ( is_singular() ) {
			$queried_id = get_queried_object_id();
			if ( current_user_can( 'edit_post', $queried_id ) && 'builder' === get_post_meta( $queried_id, '_elementor_edit_mode', true ) ) {
				return array(
					'url'   => admin_url( 'post.php?post=' . $queried_id . '&action=elementor' ),
					'label' => __( 'Edit with Elementor', 'nanobar' ),
					'title' => __( 'Edit with Elementor', 'nanobar' ),
					'icon'  => 'dashicons-layout',
				);
			}
		}

		if ( self::has_active_elementor_pro_template() ) {
			return array(
				'url'   => admin_url( 'edit.php?post_type=elementor_library&tabs_group=theme' ),
				'label' => __( 'Elementor Theme Builder', 'nanobar' ),
				'title' => __( 'Elementor Theme Builder', 'nanobar' ),
				'icon'  => 'dashicons-layout',
			);
		}

		return null;
	}

	/**
	 * Best-effort check for whether an Elementor Pro Theme Builder template
	 * (header, footer, single or archive) is controlling part of the page
	 * currently being rendered.
	 *
	 * Reads Elementor Pro's own Locations Manager, which only lists a
	 * location's documents once that location has actually been rendered for
	 * the current request — this relies on NanoBar's panel printing on
	 * wp_footer (priority 999), by which point the theme has already rendered.
	 * This reaches into Elementor Pro's internal (non-public, unversioned) API
	 * rather than a documented one, so every step is guarded with
	 * class_exists()/method_exists()/isset() and fails closed (false) the
	 * moment anything doesn't match what's expected — verify against a real
	 * Elementor Pro install if its internals change this in a future release.
	 */
	private static function has_active_elementor_pro_template(): bool {
		if ( ! defined( 'ELEMENTOR_PRO_VERSION' ) || ! class_exists( '\ElementorPro\Plugin' ) ) {
			return false;
		}

		$plugin = \ElementorPro\Plugin::instance();
		if ( ! isset( $plugin->modules_manager ) || ! method_exists( $plugin->modules_manager, 'get_modules' ) ) {
			return false;
		}

		$theme_builder = $plugin->modules_manager->get_modules( 'theme-builder' );
		if ( ! is_object( $theme_builder ) || ! method_exists( $theme_builder, 'get_locations_manager' ) ) {
			return false;
		}

		$locations_manager = $theme_builder->get_locations_manager();
		if ( ! is_object( $locations_manager ) || ! method_exists( $locations_manager, 'get_locations' ) ) {
			return false;
		}

		foreach ( (array) $locations_manager->get_locations() as $location ) {
			if ( ! empty( $location['documents'] ) ) {
				return true;
			}
		}

		return false;
	}
}
