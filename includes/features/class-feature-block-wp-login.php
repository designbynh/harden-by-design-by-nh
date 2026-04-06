<?php
/**
 * Feature: Block the public wp-login.php screen for guests.
 *
 * Logged-in users pass through (core may redirect). `logout` and `postpass`
 * stay allowed by default. Host or SSO flows can use the
 * `harden_by_nh_allow_wp_login_request` filter, and extra allowed actions can
 * be added via `harden_by_nh_disabled_login_allowed_actions`.
 *
 * @package HardenByDesignByNH
 */

declare(strict_types=1);

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

final class Harden_Feature_Block_WP_Login implements Harden_Feature {

	public function id(): string {
		return 'disable_wp_login_page';
	}

	public function register(): void {
		add_action( 'login_init', array( $this, 'block_login' ), 1 );
	}

	public function block_login(): void {
		if ( is_user_logged_in() ) {
			return;
		}

		/** @var bool */
		if ( (bool) apply_filters( 'harden_by_nh_allow_wp_login_request', false ) ) {
			return;
		}

		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Route only; guests have no nonce.
		$action = isset( $_REQUEST['action'] ) ? sanitize_key( wp_unslash( (string) $_REQUEST['action'] ) ) : 'login';
		if ( '' === $action ) {
			$action = 'login';
		}

		/** @var array<int, string> */
		$allowed_actions = apply_filters(
			'harden_by_nh_disabled_login_allowed_actions',
			array( 'logout', 'postpass' )
		);
		if ( ! is_array( $allowed_actions ) ) {
			$allowed_actions = array( 'logout', 'postpass' );
		}
		$allowed_actions = array_map(
			static function ( $a ): string {
				return sanitize_key( is_string( $a ) ? $a : (string) $a );
			},
			$allowed_actions
		);

		if ( in_array( $action, $allowed_actions, true ) ) {
			return;
		}

		status_header( 403 );
		wp_die(
			esc_html__( 'The login page is disabled on this site.', 'harden-by-design-by-nh' ),
			esc_html__( 'Forbidden', 'harden-by-design-by-nh' ),
			array( 'response' => 403 )
		);
	}
}
