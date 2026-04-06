<?php
/**
 * Feature: Completely disable comments and trackbacks site-wide.
 *
 * @package HardenByDesignByNH
 */

declare(strict_types=1);

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

final class Harden_Feature_Disable_Comments implements Harden_Feature {

	public function id(): string {
		return 'disable_comments';
	}

	public function register(): void {
		add_filter( 'comments_open', '__return_false', 20, 2 );
		add_filter( 'pings_open', '__return_false', 20, 2 );
		add_filter( 'comments_array', '__return_empty_array', 10, 2 );

		foreach ( get_post_types( '', 'names' ) as $post_type ) {
			remove_post_type_support( $post_type, 'comments' );
			remove_post_type_support( $post_type, 'trackbacks' );
		}

		add_action( 'registered_post_type', array( $this, 'strip_comments_support' ), 10, 2 );
		add_action( 'admin_menu', array( $this, 'remove_menu' ), 999 );
		add_action( 'admin_init', array( $this, 'block_admin_screen' ) );
		add_action( 'admin_bar_menu', array( $this, 'remove_admin_bar_node' ), 999 );
		add_action( 'wp_dashboard_setup', array( $this, 'remove_dashboard_widget' ) );
	}

	/**
	 * Strip comment/trackback support from post types registered after init.
	 *
	 * @param string        $post_type       Post type slug.
	 * @param \WP_Post_Type $post_type_object Registered type object.
	 */
	public function strip_comments_support( string $post_type, $post_type_object ): void {
		unset( $post_type_object );
		remove_post_type_support( $post_type, 'comments' );
		remove_post_type_support( $post_type, 'trackbacks' );
	}

	public function remove_menu(): void {
		remove_menu_page( 'edit-comments.php' );
	}

	public function block_admin_screen(): void {
		global $pagenow;
		if ( 'edit-comments.php' === $pagenow ) {
			wp_safe_redirect( admin_url() );
			exit;
		}
	}

	/**
	 * @param \WP_Admin_Bar $wp_admin_bar Admin bar instance.
	 */
	public function remove_admin_bar_node( $wp_admin_bar ): void {
		if ( is_object( $wp_admin_bar ) && method_exists( $wp_admin_bar, 'remove_node' ) ) {
			$wp_admin_bar->remove_node( 'comments' );
		}
	}

	public function remove_dashboard_widget(): void {
		remove_meta_box( 'dashboard_recent_comments', 'dashboard', 'normal' );
	}
}
