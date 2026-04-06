<?php
/**
 * Feature: Dequeue the password strength meter on the front end.
 *
 * @package HardenByDesignByNH
 */

declare(strict_types=1);

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

final class Harden_Feature_Disable_Password_Strength_Meter implements Harden_Feature {

	public function id(): string {
		return 'disable_password_strength_meter';
	}

	public function register(): void {
		add_action( 'wp_print_scripts', array( $this, 'dequeue_scripts' ), 100 );
	}

	public function dequeue_scripts(): void {
		if ( is_admin() ) {
			return;
		}

		global $pagenow;
		if ( isset( $pagenow ) && 'wp-login.php' === $pagenow ) {
			return;
		}

		if ( function_exists( 'is_account_page' ) && is_account_page() ) {
			return;
		}

		wp_dequeue_script( 'zxcvbn-async' );
		wp_dequeue_script( 'password-strength-meter' );
		wp_dequeue_script( 'wc-password-strength-meter' );
	}
}
