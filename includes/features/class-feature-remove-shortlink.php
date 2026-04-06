<?php
/**
 * Feature: Remove shortlink from wp_head and HTTP headers.
 *
 * @package HardenByDesignByNH
 */

declare(strict_types=1);

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

final class Harden_Feature_Remove_Shortlink implements Harden_Feature {

	public function id(): string {
		return 'remove_shortlink';
	}

	public function register(): void {
		remove_action( 'wp_head', 'wp_shortlink_wp_head', 10 );
		remove_action( 'template_redirect', 'wp_shortlink_header', 11 );
	}
}
