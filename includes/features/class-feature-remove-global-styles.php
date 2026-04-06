<?php
/**
 * Feature: Remove WordPress global styles inline CSS.
 *
 * @package HardenByDesignByNH
 */

declare(strict_types=1);

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

final class Harden_Feature_Remove_Global_Styles implements Harden_Feature {

	public function id(): string {
		return 'remove_global_styles';
	}

	public function register(): void {
		remove_action( 'wp_enqueue_scripts', 'wp_enqueue_global_styles' );
		remove_action( 'wp_footer', 'wp_enqueue_global_styles', 1 );
	}
}
