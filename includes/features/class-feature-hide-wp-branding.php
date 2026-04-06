<?php
/**
 * Feature: Hide WordPress branding from admin, toolbar, and login screen.
 *
 * @package HardenByDesignByNH
 */

declare(strict_types=1);

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

final class Harden_Feature_Hide_WP_Branding implements Harden_Feature {

	public function id(): string {
		return 'hide_wp_branding';
	}

	public function register(): void {
		add_action( 'admin_bar_menu', array( $this, 'remove_wp_logo' ), 999 );
		add_filter( 'admin_footer_text', '__return_empty_string', 20 );
		add_filter( 'update_footer', '__return_empty_string', 20 );
		add_action( 'login_init', array( $this, 'rebrand_login_screen' ) );
	}

	/**
	 * Remove the WordPress logo node from the admin bar.
	 *
	 * @param \WP_Admin_Bar $wp_admin_bar Admin bar instance.
	 */
	public function remove_wp_logo( $wp_admin_bar ): void {
		if ( is_object( $wp_admin_bar ) && method_exists( $wp_admin_bar, 'remove_node' ) ) {
			$wp_admin_bar->remove_node( 'wp-logo' );
		}
	}

	public function rebrand_login_screen(): void {
		add_filter( 'login_headerurl', array( $this, 'login_header_url' ) );
		add_filter( 'login_headertext', array( $this, 'login_header_text' ) );
		add_filter( 'login_title', array( $this, 'login_document_title' ), 10, 2 );
		add_action( 'login_enqueue_scripts', array( $this, 'enqueue_login_branding_css' ), 20 );
	}

	public function login_header_url(): string {
		return home_url( '/' );
	}

	public function login_header_text(): string {
		return get_bloginfo( 'name', 'display' );
	}

	/**
	 * Remove trailing "— WordPress" from the login screen <title>.
	 *
	 * @param string $login_title Full title after core formatting.
	 * @param string $title       Original screen title (unused but required by filter).
	 */
	public function login_document_title( string $login_title, string $title ): string {
		unset( $title );
		return (string) preg_replace( '/\s*(?:&#8212;|&mdash;|—)\s*WordPress\s*$/iu', '', $login_title );
	}

	public function enqueue_login_branding_css(): void {
		wp_enqueue_style(
			'harden-by-nh-login-branding',
			HARDEN_BY_NH_URL . 'assets/login-branding.css',
			array( 'login' ),
			HARDEN_BY_NH_VERSION
		);
	}
}
