<?php
/**
 * Feature: Disable WordPress oEmbed / embed functionality.
 *
 * @package HardenByDesignByNH
 */

declare(strict_types=1);

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

final class Harden_Feature_Disable_Embeds implements Harden_Feature {

	public function id(): string {
		return 'disable_embeds';
	}

	public function register(): void {
		global $wp;
		if ( isset( $wp ) && is_object( $wp ) && property_exists( $wp, 'public_query_vars' ) ) {
			$wp->public_query_vars = array_diff( $wp->public_query_vars, array( 'embed' ) );
		}

		add_filter( 'embed_oembed_discover', '__return_false' );

		remove_filter( 'oembed_dataparse', 'wp_filter_oembed_result', 10 );

		remove_action( 'wp_head', 'wp_oembed_add_discovery_links' );
		remove_action( 'wp_head', 'wp_oembed_add_host_js' );

		add_filter( 'tiny_mce_plugins', array( $this, 'filter_tiny_mce_plugins' ) );
		add_filter( 'rewrite_rules_array', array( $this, 'filter_rewrite_rules' ) );

		remove_filter( 'pre_oembed_result', 'wp_filter_pre_oembed_result', 10 );
	}

	/**
	 * Remove the wpembed TinyMCE plugin.
	 *
	 * @param array<int, string> $plugins TinyMCE plugins.
	 * @return array<int, string>
	 */
	public function filter_tiny_mce_plugins( array $plugins ): array {
		return array_diff( $plugins, array( 'wpembed' ) );
	}

	/**
	 * Strip embed rewrite rules.
	 *
	 * @param array<string, string> $rules Rewrite rules.
	 * @return array<string, string>
	 */
	public function filter_rewrite_rules( array $rules ): array {
		foreach ( $rules as $pattern => $rewrite ) {
			if ( false !== strpos( $rewrite, 'embed=true' ) ) {
				unset( $rules[ $pattern ] );
			}
		}
		return $rules;
	}
}
