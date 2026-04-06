<?php
declare(strict_types=1);

/**
 * WordPress core sitemap provider — syncs wp-sitemap.xml with Pages tab URL blocking.
 *
 * @package HardenByDesignByNH
 */

defined( 'ABSPATH' ) || exit;

/**
 * Hooks into WordPress core sitemaps (wp-sitemap.xml, WP 5.5+) to remove
 * content types that the Pages tab blocks with a 404.
 */
final class Harden_Seo_WordPress_Core implements Harden_Seo_Provider {

	public function id(): string {
		return 'wordpress_core';
	}

	public function type(): string {
		return 'seo_sitemap';
	}

	public function admin_label(): string {
		return __( 'WordPress (built-in)', 'harden-by-design-by-nh' );
	}

	public function option_key(): string {
		return 'integrate_wp_core_sitemap';
	}

	public function is_available(): bool {
		return true;
	}

	public function register(): void {
		if ( ! $this->integration_enabled() ) {
			return;
		}
		add_filter( 'wp_sitemaps_post_types', array( $this, 'filter_post_types' ), 100 );
		add_filter( 'wp_sitemaps_taxonomies', array( $this, 'filter_taxonomies' ), 100 );
		add_filter( 'wp_sitemaps_users_query_args', array( $this, 'filter_users_query_args' ), 100 );
	}

	/**
	 * Remove post types whose singles are blocked on the Pages tab.
	 *
	 * @param WP_Post_Type[] $post_types Keyed by post type name.
	 * @return WP_Post_Type[]
	 */
	public function filter_post_types( $post_types ): array {
		if ( ! is_array( $post_types ) ) {
			return array();
		}
		$o       = Harden_Options::get();
		$singles = isset( $o['disabled_post_type_singles'] ) && is_array( $o['disabled_post_type_singles'] ) ? $o['disabled_post_type_singles'] : array();
		if ( empty( $singles ) ) {
			return $post_types;
		}
		foreach ( $singles as $slug ) {
			unset( $post_types[ $slug ] );
		}
		return $post_types;
	}

	/**
	 * Remove blocked taxonomies from the core sitemap.
	 *
	 * @param WP_Taxonomy[] $taxonomies Keyed by taxonomy name.
	 * @return WP_Taxonomy[]
	 */
	public function filter_taxonomies( $taxonomies ): array {
		if ( ! is_array( $taxonomies ) ) {
			return array();
		}
		$o = Harden_Options::get();

		if ( ! empty( $o['disable_all_taxonomy_archives'] ) ) {
			return array();
		}

		$blocked = isset( $o['disabled_taxonomy_archives'] ) && is_array( $o['disabled_taxonomy_archives'] ) ? $o['disabled_taxonomy_archives'] : array();
		if ( empty( $blocked ) ) {
			return $taxonomies;
		}
		foreach ( $blocked as $slug ) {
			unset( $taxonomies[ $slug ] );
		}
		return $taxonomies;
	}

	/**
	 * Return no users when author pages are blocked.
	 *
	 * @param array<string, mixed> $args WP_User_Query arguments.
	 * @return array<string, mixed>
	 */
	public function filter_users_query_args( $args ): array {
		if ( ! is_array( $args ) ) {
			$args = array();
		}
		$o = Harden_Options::get();
		if ( ! empty( $o['disable_author_pages'] ) ) {
			$args['include'] = array( 0 );
		}
		return $args;
	}

	private function integration_enabled(): bool {
		$o = Harden_Options::get();
		return ! empty( $o[ $this->option_key() ] );
	}
}
