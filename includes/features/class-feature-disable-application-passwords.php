<?php
/**
 * Feature: Disable WordPress Application Passwords.
 *
 * @package HardenByDesignByNH
 */

declare(strict_types=1);

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

final class Harden_Feature_Disable_Application_Passwords implements Harden_Feature {

	public function id(): string {
		return 'disable_application_passwords';
	}

	public function register(): void {
		add_filter( 'wp_is_application_passwords_available', '__return_false' );
	}
}
