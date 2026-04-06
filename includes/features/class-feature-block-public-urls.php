<?php
/**
 * Feature: Block front-end URLs (author pages, date archives, taxonomies, etc.)
 * and hide related admin UI when those URLs are disabled.
 *
 * This is a multi-option feature that reads several options; the registry
 * always registers it (it is not gated behind a single boolean).
 *
 * @package HardenByDesignByNH
 */

declare(strict_types=1);

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

final class Harden_Feature_Block_Public_URLs implements Harden_Feature {

	public function id(): string {
		return 'block_public_urls';
	}

	public function register(): void {
		add_action( 'template_redirect', array( $this, 'maybe_block_query' ), 1 );

		add_action( 'admin_menu', array( $this, 'maybe_remove_taxonomy_menus' ), 999 );
		add_action( 'admin_init', array( $this, 'maybe_block_taxonomy_admin_screen' ) );

		add_action( 'admin_menu', array( $this, 'maybe_remove_posts_pages_menus' ), 99 );
		add_action( 'admin_init', array( $this, 'maybe_block_posts_pages_admin' ) );
		add_action( 'admin_bar_menu', array( $this, 'maybe_remove_admin_bar_nodes' ), 999 );
	}

	/**
	 * @return array<string, mixed>
	 */
	private function opts(): array {
		return Harden_Options::get();
	}

	/**
	 * Return 404 for front-end URLs blocked by plugin options.
	 */
	public function maybe_block_query(): void {
		if ( is_admin() || wp_doing_ajax() || wp_doing_cron() ) {
			return;
		}
		if ( function_exists( 'is_customize_preview' ) && is_customize_preview() ) {
			return;
		}

		$o = $this->opts();

		if ( ! empty( $o['disable_author_pages'] ) && is_author() ) {
			$this->force_404();
			return;
		}

		if ( ! empty( $o['disable_date_archives'] ) && is_date() ) {
			$this->force_404();
			return;
		}

		if ( ! empty( $o['disable_all_taxonomy_archives'] ) ) {
			if ( is_category() || is_tag() || is_tax() ) {
				$this->force_404();
				return;
			}
		} else {
			$tax_list = isset( $o['disabled_taxonomy_archives'] ) && is_array( $o['disabled_taxonomy_archives'] ) ? $o['disabled_taxonomy_archives'] : array();
			if ( ! empty( $tax_list ) ) {
				if ( is_category() && in_array( 'category', $tax_list, true ) ) {
					$this->force_404();
					return;
				}
				if ( is_tag() && in_array( 'post_tag', $tax_list, true ) ) {
					$this->force_404();
					return;
				}
				if ( is_tax() ) {
					$tx = get_queried_object();
					if ( $tx && isset( $tx->taxonomy ) && in_array( (string) $tx->taxonomy, $tax_list, true ) ) {
						$this->force_404();
						return;
					}
				}
			}
		}

		if ( ! empty( $o['disable_all_post_type_archives'] ) ) {
			if ( is_post_type_archive() ) {
				$this->force_404();
				return;
			}
		} else {
			$pta = isset( $o['disabled_post_type_archives'] ) && is_array( $o['disabled_post_type_archives'] ) ? $o['disabled_post_type_archives'] : array();
			if ( ! empty( $pta ) && is_post_type_archive() ) {
				$pt = get_post_type();
				if ( $pt && in_array( $pt, $pta, true ) ) {
					$this->force_404();
					return;
				}
			}
		}

		if ( ! empty( $o['disable_blog_index'] ) && is_home() ) {
			$internal_skip = $this->internal_skip_disable_blog_index_block();
			$context       = $this->blog_index_block_filter_context();
			/**
			 * Skip the disable_blog_index 404 when the request is not the real posts listing.
			 *
			 * WordPress can set is_home() true for core XML sitemap URLs; those are skipped
			 * internally. Use this filter for third-party SEO sitemap routes that still collide.
			 *
			 * @param bool  $skip    When true, the blog index block is not applied.
			 * @param array $context Keys: is_home (bool), request_uri (string), sitemap (string), sitemap_stylesheet (string).
			 */
			$skip = (bool) apply_filters( 'harden_by_nh_skip_disable_blog_index_block', $internal_skip, $context );
			if ( ! $skip ) {
				$this->force_404();
				return;
			}
		}

		$singles = isset( $o['disabled_post_type_singles'] ) && is_array( $o['disabled_post_type_singles'] ) ? $o['disabled_post_type_singles'] : array();
		if ( ! empty( $singles ) && is_singular() && ! is_preview() ) {
			$pt = get_post_type();
			if ( $pt && in_array( $pt, $singles, true ) ) {
				$this->force_404();
				return;
			}
		}
	}

	/**
	 * Core detection: wp-sitemap.xml and related routes must not be treated as the blog index.
	 *
	 * @return bool True when this request should not receive the disable_blog_index 404.
	 */
	private function internal_skip_disable_blog_index_block(): bool {
		if ( function_exists( 'wp_is_sitemap_request' ) && wp_is_sitemap_request() ) {
			return true;
		}
		$sitemap = get_query_var( 'sitemap', '' );
		if ( is_string( $sitemap ) && '' !== $sitemap ) {
			return true;
		}
		$stylesheet = get_query_var( 'sitemap-stylesheet', '' );
		return is_string( $stylesheet ) && '' !== $stylesheet;
	}

	/**
	 * @return array{is_home: bool, request_uri: string, sitemap: string, sitemap_stylesheet: string}
	 */
	private function blog_index_block_filter_context(): array {
		$uri = '';
		if ( isset( $_SERVER['REQUEST_URI'] ) ) {
			$uri = sanitize_text_field( wp_unslash( (string) $_SERVER['REQUEST_URI'] ) );
		}
		$sm  = get_query_var( 'sitemap', '' );
		$xsl = get_query_var( 'sitemap-stylesheet', '' );

		return array(
			'is_home'              => is_home(),
			'request_uri'          => $uri,
			'sitemap'              => is_string( $sm ) ? sanitize_text_field( $sm ) : '',
			'sitemap_stylesheet'   => is_string( $xsl ) ? sanitize_text_field( $xsl ) : '',
		);
	}

	/**
	 * Remove taxonomy sub-menus when front-end archives are blocked.
	 */
	public function maybe_remove_taxonomy_menus(): void {
		if ( ! is_admin() ) {
			return;
		}
		/**
		 * Keep taxonomy admin screens even when front-end archives are blocked.
		 *
		 * @param bool $hide Whether to remove matching admin submenu items (default true).
		 */
		if ( ! (bool) apply_filters( 'harden_by_nh_hide_blocked_taxonomy_admin_menus', true ) ) {
			return;
		}

		global $submenu;
		if ( ! isset( $submenu ) || ! is_array( $submenu ) ) {
			return;
		}

		$removals = array();
		foreach ( $submenu as $parent_slug => $items ) {
			if ( ! is_string( $parent_slug ) || ! is_array( $items ) ) {
				continue;
			}
			$posts_root = ( 'edit.php' === $parent_slug );
			$cpt_root   = ( 0 === strpos( $parent_slug, 'edit.php?post_type=' ) );
			if ( ! $posts_root && ! $cpt_root ) {
				continue;
			}
			foreach ( $items as $item ) {
				if ( empty( $item[2] ) || ! is_string( $item[2] ) ) {
					continue;
				}
				$child_slug = $item[2];
				if ( 0 !== strpos( $child_slug, 'edit-tags.php' ) ) {
					continue;
				}
				if ( ! preg_match( '/[?&]taxonomy=([^&]+)/', $child_slug, $m ) ) {
					continue;
				}
				$tax = sanitize_key( rawurldecode( $m[1] ) );
				if ( '' === $tax ) {
					continue;
				}
				if ( $this->taxonomy_archive_blocked( $tax ) ) {
					$removals[] = array( $parent_slug, $child_slug );
				}
			}
		}

		foreach ( $removals as $pair ) {
			remove_submenu_page( $pair[0], $pair[1] );
		}
	}

	/**
	 * Redirect blocked taxonomy edit-tags screens to the dashboard.
	 */
	public function maybe_block_taxonomy_admin_screen(): void {
		if ( ! is_admin() ) {
			return;
		}
		if ( ! (bool) apply_filters( 'harden_by_nh_hide_blocked_taxonomy_admin_menus', true ) ) {
			return;
		}
		global $pagenow;
		if ( 'edit-tags.php' !== $pagenow || empty( $_GET['taxonomy'] ) ) { // phpcs:ignore WordPress.Security.NonceVerification.Recommended
			return;
		}
		$tax = sanitize_key( wp_unslash( (string) $_GET['taxonomy'] ) ); // phpcs:ignore WordPress.Security.NonceVerification.Recommended
		if ( ! $this->taxonomy_archive_blocked( $tax ) ) {
			return;
		}
		wp_safe_redirect( admin_url() );
		exit;
	}

	/**
	 * Remove Posts / Pages menus when those types' singles are blocked.
	 */
	public function maybe_remove_posts_pages_menus(): void {
		if ( ! is_admin() ) {
			return;
		}
		if ( $this->should_hide_builtin_type_admin_ui( 'post' ) ) {
			remove_menu_page( 'edit.php' );
		}
		if ( $this->should_hide_builtin_type_admin_ui( 'page' ) ) {
			remove_menu_page( 'edit.php?post_type=page' );
		}
	}

	/**
	 * Block admin access to post/page edit screens when singles are blocked.
	 */
	public function maybe_block_posts_pages_admin(): void {
		if ( ! is_admin() ) {
			return;
		}

		global $pagenow;

		if ( 'edit.php' === $pagenow ) {
			$pt = isset( $_GET['post_type'] ) ? sanitize_key( wp_unslash( (string) $_GET['post_type'] ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification.Recommended
			if ( ( '' === $pt || 'post' === $pt ) && $this->should_hide_builtin_type_admin_ui( 'post' ) ) {
				wp_safe_redirect( admin_url() );
				exit;
			}
			if ( 'page' === $pt && $this->should_hide_builtin_type_admin_ui( 'page' ) ) {
				wp_safe_redirect( admin_url() );
				exit;
			}
		}

		if ( 'post-new.php' === $pagenow ) {
			$pt = isset( $_GET['post_type'] ) ? sanitize_key( wp_unslash( (string) $_GET['post_type'] ) ) : 'post'; // phpcs:ignore WordPress.Security.NonceVerification.Recommended
			if ( in_array( $pt, array( 'post', 'page' ), true ) && $this->should_hide_builtin_type_admin_ui( $pt ) ) {
				wp_safe_redirect( admin_url() );
				exit;
			}
		}

		if ( 'post.php' === $pagenow && isset( $_GET['post'] ) ) { // phpcs:ignore WordPress.Security.NonceVerification.Recommended
			$post_id = (int) $_GET['post'];
			$type    = $post_id > 0 ? get_post_type( $post_id ) : '';
			if ( $type && in_array( $type, array( 'post', 'page' ), true ) && $this->should_hide_builtin_type_admin_ui( $type ) ) {
				wp_safe_redirect( admin_url() );
				exit;
			}
		}
	}

	/**
	 * Remove new-post / new-page admin-bar nodes when those types are blocked.
	 *
	 * @param \WP_Admin_Bar $wp_admin_bar Admin bar instance.
	 */
	public function maybe_remove_admin_bar_nodes( $wp_admin_bar ): void {
		if ( ! is_object( $wp_admin_bar ) || ! method_exists( $wp_admin_bar, 'remove_node' ) ) {
			return;
		}
		if ( $this->should_hide_builtin_type_admin_ui( 'post' ) ) {
			$wp_admin_bar->remove_node( 'new-post' );
		}
		if ( $this->should_hide_builtin_type_admin_ui( 'page' ) ) {
			$wp_admin_bar->remove_node( 'new-page' );
		}
	}

	/**
	 * Whether a taxonomy's front-end archive is blocked.
	 */
	private function taxonomy_archive_blocked( string $taxonomy ): bool {
		if ( '' === $taxonomy || ! taxonomy_exists( $taxonomy ) ) {
			return false;
		}
		$o = $this->opts();
		if ( ! empty( $o['disable_all_taxonomy_archives'] ) ) {
			return true;
		}
		$list = isset( $o['disabled_taxonomy_archives'] ) && is_array( $o['disabled_taxonomy_archives'] ) ? $o['disabled_taxonomy_archives'] : array();
		return in_array( $taxonomy, $list, true );
	}

	/**
	 * Whether a built-in post type's singles are blocked.
	 */
	private function post_type_singles_blocked( string $post_type ): bool {
		$o       = $this->opts();
		$singles = isset( $o['disabled_post_type_singles'] ) && is_array( $o['disabled_post_type_singles'] ) ? $o['disabled_post_type_singles'] : array();
		return in_array( $post_type, $singles, true );
	}

	/**
	 * Whether the admin UI for a built-in type (post/page) should be hidden.
	 */
	private function should_hide_builtin_type_admin_ui( string $post_type ): bool {
		if ( ! in_array( $post_type, array( 'post', 'page' ), true ) ) {
			return false;
		}
		if ( ! $this->post_type_singles_blocked( $post_type ) ) {
			return false;
		}
		if ( 'post' === $post_type ) {
			/**
			 * Keep the Posts menu and edit screens even when single post URLs are blocked.
			 *
			 * @param bool $hide Whether to hide Posts in admin (default true when singles are blocked).
			 */
			return (bool) apply_filters( 'harden_by_nh_hide_posts_admin_when_singles_blocked', true );
		}
		/**
		 * Keep the Pages menu and edit screens even when single page URLs are blocked.
		 *
		 * @param bool $hide Whether to hide Pages in admin (default true when singles are blocked).
		 */
		return (bool) apply_filters( 'harden_by_nh_hide_pages_admin_when_singles_blocked', true );
	}

	/**
	 * Mark the main query as 404 so the theme's 404 template loads.
	 */
	private function force_404(): void {
		global $wp_query;
		if ( is_object( $wp_query ) && method_exists( $wp_query, 'set_404' ) ) {
			$wp_query->set_404();
		}
		status_header( 404 );
		nocache_headers();
	}
}
