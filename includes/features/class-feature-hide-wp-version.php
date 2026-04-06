<?php
/**
 * Feature: Hide the WordPress version from front-end, login, and admin output.
 *
 * @package HardenByDesignByNH
 */

declare(strict_types=1);

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

final class Harden_Feature_Hide_WP_Version implements Harden_Feature {

	public function id(): string {
		return 'hide_wp_version';
	}

	public function register(): void {
		remove_action( 'wp_head', 'wp_generator' );
		add_filter( 'the_generator', '__return_empty_string' );

		add_filter( 'style_loader_src', array( $this, 'strip_version_query_arg' ), 20 );
		add_filter( 'script_loader_src', array( $this, 'strip_version_query_arg' ), 20 );

		add_action( 'admin_init', array( $this, 'hide_admin_footer_version' ) );
		add_action( 'login_init', array( $this, 'hide_login_version' ) );
	}

	/**
	 * Strip the `ver` query argument from enqueued asset URLs.
	 *
	 * @param string $src Asset URL.
	 * @return string
	 */
	public function strip_version_query_arg( string $src ): string {
		if ( strpos( $src, 'ver=' ) === false ) {
			return $src;
		}
		return remove_query_arg( 'ver', $src );
	}

	public function hide_admin_footer_version(): void {
		add_filter( 'update_footer', '__return_empty_string', 11 );
	}

	public function hide_login_version(): void {
		remove_action( 'login_head', 'wp_generator' );
	}
}
