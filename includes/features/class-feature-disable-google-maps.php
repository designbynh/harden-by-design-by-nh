<?php
/**
 * Feature: Strip Google Maps script tags from front-end output.
 *
 * @package HardenByDesignByNH
 */

declare(strict_types=1);

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

final class Harden_Feature_Disable_Google_Maps implements Harden_Feature {

	public function id(): string {
		return 'disable_google_maps';
	}

	public function register(): void {
		add_action( 'template_redirect', array( $this, 'start_output_buffer' ), 1 );
	}

	public function start_output_buffer(): void {
		if ( is_admin() ) {
			return;
		}
		ob_start( array( $this, 'strip_google_maps_scripts' ) );
	}

	/**
	 * Remove Google Maps script tags from buffered HTML.
	 *
	 * @param string $html Page output.
	 * @return string
	 */
	public function strip_google_maps_scripts( string $html ): string {
		$pattern = '#<script[^>]*(?:maps\.google(?:apis)?\.com)[^>]*>.*?</script>#is';
		return (string) preg_replace( $pattern, '', $html );
	}
}
