<?php
/**
 * Feature: Prevent WordPress from sending pingbacks to itself.
 *
 * @package HardenByDesignByNH
 */

declare(strict_types=1);

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

final class Harden_Feature_Disable_Self_Pingbacks implements Harden_Feature {

	public function id(): string {
		return 'disable_self_pingbacks';
	}

	public function register(): void {
		add_action( 'pre_ping', array( $this, 'filter_self_pings' ) );
	}

	/**
	 * Remove links that point to the site's own domain.
	 *
	 * @param array<int, string> &$links Pingback URLs (passed by reference).
	 */
	public function filter_self_pings( array &$links ): void {
		$home = home_url();
		foreach ( $links as $key => $link ) {
			if ( 0 === strpos( $link, $home ) ) {
				unset( $links[ $key ] );
			}
		}
	}
}
