<?php
/**
 * Feature: Remove REST API discovery links from head and headers.
 *
 * @package HardenByDesignByNH
 */

declare(strict_types=1);

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

final class Harden_Feature_Remove_REST_API_Links implements Harden_Feature {

	public function id(): string {
		return 'remove_rest_api_links';
	}

	public function register(): void {
		remove_action( 'xmlrpc_rsd_apis', 'rest_output_rsd' );
		remove_action( 'wp_head', 'rest_output_link_wp_head', 10 );
		remove_action( 'template_redirect', 'rest_output_link_header', 11 );
	}
}
