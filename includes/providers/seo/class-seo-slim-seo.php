<?php
declare(strict_types=1);

/**
 * Slim SEO sitemap provider — aligns XML sitemap with Harden public‑URL blocking.
 *
 * @package HardenByDesignByNH
 */

defined( 'ABSPATH' ) || exit;

/**
 * Instance‑based Slim SEO integration implementing {@see Harden_Seo_Provider}.
 */
final class Harden_Seo_Slim_Seo implements Harden_Seo_Provider {

	private const PLUGIN_FILE = 'slim-seo/slim-seo.php';

	public function id(): string {
		return 'slim_seo';
	}

	public function type(): string {
		return 'seo_sitemap';
	}

	public function admin_label(): string {
		return 'Slim SEO';
	}

	public function option_key(): string {
		return 'integrate_slim_seo_sitemap';
	}

	public function is_available(): bool {
		if ( ! function_exists( 'is_plugin_active' ) ) {
			require_once ABSPATH . 'wp-admin/includes/plugin.php';
		}
		return is_plugin_active( self::PLUGIN_FILE );
	}

	public function register(): void {
		add_filter( 'slim_seo_sitemap_post_ignore', array( $this, 'filter_post_ignore' ), 1000, 2 );
		add_filter( 'slim_seo_sitemap_taxonomies', array( $this, 'filter_taxonomies' ), 1000 );
		add_filter( 'slim_seo_user_sitemap', array( $this, 'filter_user_sitemap' ), 1000 );
		add_filter( 'slim_seo_taxonomy_query_args', array( $this, 'filter_taxonomy_query_args' ), 1000, 2 );
	}

	/**
	 * Exclude posts whose post type is in the disabled singles list.
	 *
	 * @param bool    $ignore Prior value.
	 * @param WP_Post $post   Post object.
	 */
	public function filter_post_ignore( $ignore, $post ): bool {
		if ( ! $this->integration_enabled() ) {
			return (bool) $ignore;
		}
		if ( $ignore || ! $post instanceof \WP_Post ) {
			return (bool) $ignore;
		}
		$o       = Harden_Options::get();
		$singles = isset( $o['disabled_post_type_singles'] ) && is_array( $o['disabled_post_type_singles'] ) ? $o['disabled_post_type_singles'] : array();
		if ( empty( $singles ) ) {
			return false;
		}
		$pt = isset( $post->post_type ) ? (string) $post->post_type : '';
		return '' !== $pt && in_array( $pt, $singles, true );
	}

	/**
	 * Remove blocked taxonomies from the sitemap index.
	 *
	 * @param array<int, string> $taxonomies Taxonomy slugs.
	 * @return array<int, string>
	 */
	public function filter_taxonomies( $taxonomies ): array {
		if ( ! $this->integration_enabled() ) {
			return is_array( $taxonomies ) ? array_values( array_map( 'sanitize_key', array_map( 'strval', $taxonomies ) ) ) : array();
		}
		$list = is_array( $taxonomies ) ? array_values( array_unique( array_map( 'sanitize_key', array_map( 'strval', $taxonomies ) ) ) ) : array();
		$o    = Harden_Options::get();

		if ( ! empty( $o['disable_all_taxonomy_archives'] ) ) {
			return array();
		}

		$blocked = isset( $o['disabled_taxonomy_archives'] ) && is_array( $o['disabled_taxonomy_archives'] ) ? $o['disabled_taxonomy_archives'] : array();
		$blocked = array_values( array_unique( array_map( 'sanitize_key', array_map( 'strval', $blocked ) ) ) );
		if ( empty( $blocked ) ) {
			return $list;
		}

		return array_values( array_diff( $list, $blocked ) );
	}

	/**
	 * Disable the user / author sitemap when author pages are blocked.
	 *
	 * @param bool $active Prior value from Slim SEO.
	 */
	public function filter_user_sitemap( $active ): bool {
		if ( ! $this->integration_enabled() ) {
			return (bool) $active;
		}
		$o = Harden_Options::get();
		if ( ! empty( $o['disable_author_pages'] ) ) {
			return false;
		}
		return (bool) $active;
	}

	/**
	 * Force zero terms for blocked taxonomies (sitemap index + taxonomy XML).
	 *
	 * @param array<string, mixed> $query_args Merged query arguments for get_terms / wp_count_terms.
	 * @param array<string, mixed> $request    Original $args passed into Taxonomy::get_query_args().
	 * @return array<string, mixed>
	 */
	public function filter_taxonomy_query_args( array $query_args, array $request ): array {
		unset( $request );
		if ( ! $this->integration_enabled() ) {
			return $query_args;
		}
		$taxonomy = isset( $query_args['taxonomy'] ) ? sanitize_key( (string) $query_args['taxonomy'] ) : '';
		if ( '' === $taxonomy ) {
			return $query_args;
		}
		if ( ! $this->is_taxonomy_blocked_for_sitemap( $taxonomy ) ) {
			return $query_args;
		}
		$query_args['include']    = array( 0 );
		$query_args['hide_empty'] = false;

		return $query_args;
	}

	private function is_taxonomy_blocked_for_sitemap( string $taxonomy ): bool {
		$o = Harden_Options::get();
		if ( ! empty( $o['disable_all_taxonomy_archives'] ) ) {
			return true;
		}
		$blocked = isset( $o['disabled_taxonomy_archives'] ) && is_array( $o['disabled_taxonomy_archives'] ) ? $o['disabled_taxonomy_archives'] : array();
		$blocked = array_map(
			static function ( $slug ): string {
				return sanitize_key( (string) $slug );
			},
			$blocked
		);

		return in_array( $taxonomy, $blocked, true );
	}

	private function integration_enabled(): bool {
		if ( ! $this->is_available() ) {
			return false;
		}
		$o = Harden_Options::get();
		return ! empty( $o[ $this->option_key() ] );
	}
}
