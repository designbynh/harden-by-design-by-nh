<?php
/**
 * Feature: Disable XML-RPC entirely (endpoint, pingbacks, option).
 *
 * @package HardenByDesignByNH
 */

declare(strict_types=1);

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

final class Harden_Feature_Disable_XMLRPC implements Harden_Feature {

	public function id(): string {
		return 'disable_xmlrpc';
	}

	public function register(): void {
		add_filter( 'xmlrpc_enabled', '__return_false' );
		add_filter( 'wp_headers', array( $this, 'remove_x_pingback_header' ) );
		add_filter( 'pings_open', '__return_false', 9999 );
		add_filter( 'pre_update_option_enable_xmlrpc', '__return_false' );
		add_filter( 'pre_option_enable_xmlrpc', array( $this, 'return_zero_string' ) );
		$this->intercept_xmlrpc_request();
	}

	/**
	 * Strip the X-Pingback header from responses.
	 *
	 * @param array<string, string> $headers Response headers.
	 * @return array<string, string>
	 */
	public function remove_x_pingback_header( array $headers ): array {
		unset( $headers['X-Pingback'], $headers['x-pingback'] );
		return $headers;
	}

	/**
	 * Force the stored XML-RPC option to disabled.
	 */
	public function return_zero_string(): string {
		return '0';
	}

	/**
	 * If the current request targets xmlrpc.php, reject it immediately.
	 */
	private function intercept_xmlrpc_request(): void {
		if ( ! isset( $_SERVER['SCRIPT_FILENAME'] ) ) {
			return;
		}
		$script = wp_basename( wp_unslash( (string) $_SERVER['SCRIPT_FILENAME'] ) );
		if ( 'xmlrpc.php' !== $script ) {
			return;
		}
		status_header( 403 );
		exit( 'Forbidden' );
	}
}
