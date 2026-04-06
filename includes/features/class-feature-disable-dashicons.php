<?php
/**
 * Feature: Dequeue Dashicons on the front end for logged-out visitors.
 *
 * @package HardenByDesignByNH
 */

declare(strict_types=1);

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

final class Harden_Feature_Disable_Dashicons implements Harden_Feature {

	public function id(): string {
		return 'disable_dashicons';
	}

	public function register(): void {
		add_action( 'wp_enqueue_scripts', array( $this, 'dequeue_dashicons' ), 100 );
	}

	public function dequeue_dashicons(): void {
		if ( is_admin() || is_user_logged_in() ) {
			return;
		}
		wp_dequeue_style( 'dashicons' );
		wp_deregister_style( 'dashicons' );
	}
}
