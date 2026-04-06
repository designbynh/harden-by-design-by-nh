<?php
/**
 * Feature: Send security-related HTTP headers on every response.
 *
 * @package HardenByDesignByNH
 */

declare(strict_types=1);

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

final class Harden_Feature_Security_Headers implements Harden_Feature {

	public function id(): string {
		return 'enable_security_headers';
	}

	public function register(): void {
		add_action( 'send_headers', array( $this, 'send_front_end_headers' ), 0 );
		add_action( 'admin_init', array( $this, 'send_admin_headers' ), 0 );
	}

	public function send_front_end_headers(): void {
		if ( is_admin() ) {
			return;
		}
		$this->send_headers();
	}

	public function send_admin_headers(): void {
		if ( ! is_admin() ) {
			return;
		}
		$this->send_headers();
	}

	private function send_headers(): void {
		if ( headers_sent() ) {
			return;
		}
		header( 'X-Content-Type-Options: nosniff' );
		header( 'X-Frame-Options: SAMEORIGIN' );
		header( 'Referrer-Policy: strict-origin-when-cross-origin' );
		header( 'Permissions-Policy: camera=(), microphone=(), geolocation=()' );
		header_remove( 'X-Powered-By' );
	}
}
