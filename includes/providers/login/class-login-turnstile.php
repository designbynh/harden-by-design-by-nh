<?php
declare(strict_types=1);

/**
 * Cloudflare Turnstile login provider.
 *
 * @package HardenByDesignByNH
 */

defined( 'ABSPATH' ) || exit;

/**
 * Renders and verifies a Cloudflare Turnstile challenge on the login form.
 */
final class Harden_Login_Turnstile implements Harden_Login_Provider {

	public function id(): string {
		return 'turnstile';
	}

	public function type(): string {
		return 'login_captcha';
	}

	public function admin_label(): string {
		return 'Cloudflare Turnstile';
	}

	public function is_available(): bool {
		return true;
	}

	public function is_configured( array $opts ): bool {
		return '' !== trim( (string) ( $opts['turnstile_site_key'] ?? '' ) )
			&& '' !== trim( (string) ( $opts['turnstile_secret_key'] ?? '' ) );
	}

	public function register(): void {}

	public function enqueue_login_assets( array $opts ): void {
		wp_enqueue_script(
			'harden-turnstile',
			'https://challenges.cloudflare.com/turnstile/v0/api.js',
			array(),
			null,
			true
		);
	}

	public function render_login_field( array $opts ): void {
		$site_key = (string) ( $opts['turnstile_site_key'] ?? '' );

		wp_nonce_field( 'harden_login_captcha', 'harden_login_captcha_nonce' );
		echo '<div class="cf-turnstile" data-sitekey="' . esc_attr( $site_key ) . '"></div>';
	}

	/**
	 * @param WP_User|WP_Error|null $user     Current authenticate result.
	 * @param string                $username Submitted username.
	 * @param string                $password Submitted password.
	 * @return WP_User|WP_Error|null
	 */
	public function verify_authenticate( $user, string $username, string $password ) {
		if ( empty( $_POST['log'] ) || empty( $_POST['pwd'] ) ) {
			return $user;
		}

		if ( ! isset( $_POST['harden_login_captcha_nonce'] )
			|| ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['harden_login_captcha_nonce'] ) ), 'harden_login_captcha' ) ) {
			return new \WP_Error(
				'harden_nonce_fail',
				__( '<strong>Error:</strong> Security check failed. Please try again.', 'harden-by-design-by-nh' )
			);
		}

		$token = isset( $_POST['cf-turnstile-response'] )
			? sanitize_text_field( wp_unslash( $_POST['cf-turnstile-response'] ) )
			: '';

		if ( '' === $token ) {
			return new \WP_Error(
				'harden_captcha_missing',
				__( '<strong>Error:</strong> Please complete the CAPTCHA.', 'harden-by-design-by-nh' )
			);
		}

		$opts   = Harden_Options::get();
		$secret = (string) ( $opts['turnstile_secret_key'] ?? '' );

		$remote_ip = isset( $_SERVER['REMOTE_ADDR'] )
			? sanitize_text_field( wp_unslash( $_SERVER['REMOTE_ADDR'] ) )
			: '';

		$result = Harden_Remote_Verify::post_and_check(
			'https://challenges.cloudflare.com/turnstile/v0/siteverify',
			array(
				'secret'   => $secret,
				'response' => $token,
				'remoteip' => $remote_ip,
			)
		);

		if ( is_wp_error( $result ) ) {
			return $result;
		}

		if ( empty( $result['success'] ) ) {
			return new \WP_Error(
				'harden_captcha_fail',
				__( '<strong>Error:</strong> CAPTCHA verification failed. Please try again.', 'harden-by-design-by-nh' )
			);
		}

		return $user;
	}
}
