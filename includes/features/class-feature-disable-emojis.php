<?php
/**
 * Feature: Disable WordPress emoji detection and assets.
 *
 * @package HardenByDesignByNH
 */

declare(strict_types=1);

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

final class Harden_Feature_Disable_Emojis implements Harden_Feature {

	public function id(): string {
		return 'disable_emojis';
	}

	public function register(): void {
		remove_action( 'wp_head', 'print_emoji_detection_script', 7 );
		remove_action( 'admin_print_scripts', 'print_emoji_detection_script' );
		remove_action( 'wp_print_styles', 'print_emoji_styles' );
		remove_action( 'admin_print_styles', 'print_emoji_styles' );

		remove_filter( 'the_content_feed', 'wp_staticize_emoji' );
		remove_filter( 'comment_text_rss', 'wp_staticize_emoji' );
		remove_filter( 'wp_mail', 'wp_staticize_emoji_for_email' );

		add_filter( 'tiny_mce_plugins', array( $this, 'filter_tiny_mce_plugins' ) );
		add_filter( 'emoji_svg_url', '__return_false' );
	}

	/**
	 * Remove the wpemoji TinyMCE plugin.
	 *
	 * @param array<int, string> $plugins TinyMCE plugins.
	 * @return array<int, string>
	 */
	public function filter_tiny_mce_plugins( array $plugins ): array {
		return array_diff( $plugins, array( 'wpemoji' ) );
	}
}
