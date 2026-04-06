<?php
declare(strict_types=1);

/**
 * Login‑CAPTCHA provider interface.
 *
 * @package HardenByDesignByNH
 */

defined( 'ABSPATH' ) || exit;

/**
 * Contract for every login‑protection CAPTCHA provider.
 */
interface Harden_Login_Provider extends Harden_Provider {

	/**
	 * Whether the provider has the keys / config it needs.
	 *
	 * @param array $opts Plugin options array.
	 */
	public function is_configured( array $opts ): bool;

	/**
	 * Enqueue scripts and styles on the login page.
	 *
	 * @param array $opts Plugin options array.
	 */
	public function enqueue_login_assets( array $opts ): void;

	/**
	 * Output the CAPTCHA widget markup on the login form.
	 *
	 * @param array $opts Plugin options array.
	 */
	public function render_login_field( array $opts ): void;

	/**
	 * Verify the CAPTCHA response during authentication.
	 *
	 * @param WP_User|WP_Error|null $user     Current authenticate result.
	 * @param string                $username  Submitted username.
	 * @param string                $password  Submitted password.
	 * @return WP_User|WP_Error|null
	 */
	public function verify_authenticate( $user, string $username, string $password );
}
