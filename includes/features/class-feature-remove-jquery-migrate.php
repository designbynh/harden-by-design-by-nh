<?php
/**
 * Feature: Remove jQuery Migrate from the front end.
 *
 * @package HardenByDesignByNH
 */

declare(strict_types=1);

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

final class Harden_Feature_Remove_jQuery_Migrate implements Harden_Feature {

	public function id(): string {
		return 'remove_jquery_migrate';
	}

	public function register(): void {
		add_action( 'wp_default_scripts', array( $this, 'remove_migrate' ), 11 );
	}

	/**
	 * Strip jquery-migrate from jQuery's dependency list on the front end.
	 *
	 * @param WP_Scripts $scripts Script registry.
	 */
	public function remove_migrate( $scripts ): void {
		if ( is_admin() ) {
			return;
		}
		if ( ! is_object( $scripts ) || ! isset( $scripts->registered['jquery'] ) ) {
			return;
		}
		$jquery = $scripts->registered['jquery'];
		if ( ! isset( $jquery->deps ) || ! is_array( $jquery->deps ) ) {
			return;
		}
		$jquery->deps = array_diff( $jquery->deps, array( 'jquery-migrate' ) );
	}
}
