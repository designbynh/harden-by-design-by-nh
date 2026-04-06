<?php
/**
 * Feature: Disable the Appearance → Site Editor (block-theme editor).
 *
 * @package HardenByDesignByNH
 */

declare(strict_types=1);

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

final class Harden_Feature_Disable_Site_Editor implements Harden_Feature {

	public function id(): string {
		return 'disable_appearance_site_editor';
	}

	public function register(): void {
		/**
		 * Keep the Site Editor available despite the Harden option (return false).
		 *
		 * @param bool $disable Whether to remove menu and block site-editor.php (default true).
		 */
		if ( ! (bool) apply_filters( 'harden_by_nh_disable_appearance_site_editor', true ) ) {
			return;
		}

		add_action( 'admin_menu', array( $this, 'remove_menu' ), 999 );
		add_action( 'admin_init', array( $this, 'block_screen' ) );
		add_action( 'admin_bar_menu', array( $this, 'remove_admin_bar_node' ), 999 );
	}

	public function remove_menu(): void {
		remove_submenu_page( 'themes.php', 'site-editor.php' );
	}

	public function block_screen(): void {
		global $pagenow;
		if ( 'site-editor.php' === $pagenow ) {
			wp_safe_redirect( admin_url() );
			exit;
		}
	}

	/**
	 * @param \WP_Admin_Bar $wp_admin_bar Admin bar instance.
	 */
	public function remove_admin_bar_node( $wp_admin_bar ): void {
		if ( is_object( $wp_admin_bar ) && method_exists( $wp_admin_bar, 'remove_node' ) ) {
			$wp_admin_bar->remove_node( 'site-editor' );
		}
	}
}
