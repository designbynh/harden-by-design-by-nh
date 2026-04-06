<?php
/**
 * Feature: Suppress WordPress update notifications, nags, and email alerts.
 *
 * This is a multi-option feature that reads several notification options;
 * the registry always registers it (it is not gated behind a single boolean).
 *
 * @package HardenByDesignByNH
 */

declare(strict_types=1);

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

final class Harden_Feature_Suppress_Update_Notifications implements Harden_Feature {

	public function id(): string {
		return 'suppress_update_notifications';
	}

	public function register(): void {
		$o = Harden_Options::get();

		$any_active = ! empty( $o['hide_notice_wp_core_nag'] )
			|| ! empty( $o['hide_notice_updates_admin_bar'] )
			|| ! empty( $o['hide_notice_core_update_emails'] )
			|| ! empty( $o['hide_notice_core_auto_update_result_emails'] )
			|| ! empty( $o['hide_notice_plugin_auto_update_emails'] )
			|| ! empty( $o['hide_notice_theme_auto_update_emails'] )
			|| ! empty( $o['hide_notice_auto_updates_debug_emails'] )
			|| ! empty( $o['hide_notice_plugins_menu_count'] )
			|| ! empty( $o['hide_notice_themes_menu_count'] )
			|| ! empty( $o['hide_notice_dashboard_updates_submenu'] );

		if ( ! $any_active ) {
			return;
		}

		if ( ! empty( $o['hide_notice_wp_core_nag'] ) ) {
			add_action( 'admin_init', array( $this, 'replace_core_update_nag' ), 1 );
		}

		if ( ! empty( $o['hide_notice_updates_admin_bar'] ) ) {
			add_action( 'admin_bar_menu', array( $this, 'maybe_remove_updates_admin_bar' ), 999 );
		}

		if ( ! empty( $o['hide_notice_core_update_emails'] ) ) {
			add_filter( 'send_core_update_notification_email', '__return_false' );
		}
		if ( ! empty( $o['hide_notice_core_auto_update_result_emails'] ) ) {
			add_filter( 'auto_core_update_send_email', '__return_false' );
		}
		if ( ! empty( $o['hide_notice_plugin_auto_update_emails'] ) ) {
			add_filter( 'auto_plugin_update_send_email', '__return_false' );
		}
		if ( ! empty( $o['hide_notice_theme_auto_update_emails'] ) ) {
			add_filter( 'auto_theme_update_send_email', '__return_false' );
		}
		if ( ! empty( $o['hide_notice_auto_updates_debug_emails'] ) ) {
			add_filter( 'automatic_updates_send_debug_email', '__return_false' );
		}

		if ( ! empty( $o['hide_notice_plugins_menu_count'] )
			|| ! empty( $o['hide_notice_themes_menu_count'] )
			|| ! empty( $o['hide_notice_dashboard_updates_submenu'] ) ) {
			add_action( 'admin_menu', array( $this, 'strip_admin_menu_badges' ), 9999 );
		}
	}

	/**
	 * Replace the core update nag so only administrators see it.
	 */
	public function replace_core_update_nag(): void {
		remove_action( 'admin_notices', 'update_nag', 3 );
		remove_action( 'network_admin_notices', 'update_nag', 3 );
		add_action( 'admin_notices', array( $this, 'output_nag_for_privileged' ), 3 );
		add_action( 'network_admin_notices', array( $this, 'output_nag_for_privileged' ), 3 );
	}

	public function output_nag_for_privileged(): void {
		if ( $this->user_keeps_update_ui() ) {
			update_nag();
		}
	}

	/**
	 * Remove the Updates node from the admin bar for non-privileged users.
	 *
	 * @param \WP_Admin_Bar $wp_admin_bar Admin bar instance.
	 */
	public function maybe_remove_updates_admin_bar( $wp_admin_bar ): void {
		if ( ! is_object( $wp_admin_bar ) || ! method_exists( $wp_admin_bar, 'remove_node' ) ) {
			return;
		}
		if ( $this->user_keeps_update_ui() ) {
			return;
		}
		$wp_admin_bar->remove_node( 'updates' );
	}

	/**
	 * Strip update count bubbles from admin menu labels.
	 */
	public function strip_admin_menu_badges(): void {
		if ( ! is_admin() ) {
			return;
		}
		if ( $this->user_keeps_update_ui() ) {
			return;
		}

		$o = Harden_Options::get();
		global $menu, $submenu;
		if ( ! is_array( $menu ) || ! is_array( $submenu ) ) {
			return;
		}

		if ( ! empty( $o['hide_notice_plugins_menu_count'] ) ) {
			foreach ( $menu as $idx => $item ) {
				if ( ! is_array( $item ) || ! isset( $item[2], $item[0] ) || 'plugins.php' !== $item[2] ) {
					continue;
				}
				$menu[ $idx ][0] = $this->strip_badge( (string) $item[0] );
			}
		}

		if ( ! empty( $o['hide_notice_themes_menu_count'] ) ) {
			foreach ( $menu as $idx => $item ) {
				if ( ! is_array( $item ) || ! isset( $item[2], $item[0] ) || 'themes.php' !== $item[2] ) {
					continue;
				}
				$menu[ $idx ][0] = $this->strip_badge( (string) $item[0] );
			}
			if ( isset( $submenu['themes.php'] ) && is_array( $submenu['themes.php'] ) ) {
				foreach ( $submenu['themes.php'] as $idx => $item ) {
					if ( ! is_array( $item ) || ! isset( $item[0] ) ) {
						continue;
					}
					$submenu['themes.php'][ $idx ][0] = $this->strip_badge( (string) $item[0] );
				}
			}
		}

		if ( ! empty( $o['hide_notice_dashboard_updates_submenu'] ) && isset( $submenu['index.php'] ) && is_array( $submenu['index.php'] ) ) {
			foreach ( $submenu['index.php'] as $idx => $item ) {
				if ( ! is_array( $item ) || ! isset( $item[2], $item[0] ) || 'update-core.php' !== $item[2] ) {
					continue;
				}
				$submenu['index.php'][ $idx ][0] = $this->strip_badge( (string) $item[0] );
			}
		}
	}

	/**
	 * Whether the current user should still see update UI when suppression is on.
	 *
	 * Default: single-site Administrators (manage_options); network: super admins.
	 */
	private function user_keeps_update_ui(): bool {
		if ( ! is_user_logged_in() ) {
			return false;
		}
		if ( is_multisite() && is_network_admin() ) {
			$keep = is_super_admin();
		} else {
			$keep = current_user_can( 'manage_options' );
		}

		/**
		 * Whether the current user still sees update banners, admin bar Updates, and menu counts.
		 *
		 * @param bool $keep Default from manage_options / super admin checks.
		 */
		return (bool) apply_filters( 'harden_by_nh_user_keeps_update_ui', $keep );
	}

	/**
	 * Remove the update-count <span> badge from a menu title string.
	 *
	 * @param string $title Menu or submenu title HTML.
	 */
	private function strip_badge( string $title ): string {
		$out = preg_replace(
			'/<span class="update-plugins count-\d+"><span class="(?:update-count|plugin-count|theme-count)">[^<]*<\/span><\/span>/',
			'',
			$title
		);
		if ( ! is_string( $out ) ) {
			return trim( $title );
		}
		return trim( preg_replace( '/\s{2,}/u', ' ', $out ) );
	}
}
