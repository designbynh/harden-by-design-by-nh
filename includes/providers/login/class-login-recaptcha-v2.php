<?php
declare(strict_types=1);

/**
 * reCAPTCHA v2 (checkbox) login provider.
 *
 * @package HardenByDesignByNH
 */

defined( 'ABSPATH' ) || exit;

/**
 * Renders and verifies a Google reCAPTCHA v2 checkbox on the login form.
 */
final class Harden_Login_Recaptcha_V2 implements Harden_Login_Provider {

	public function id(): string {
		return 'recaptcha_v2';
	}

	public function type(): string {
		return 'login_captcha';
	}

	public function admin_label(): string {
		return 'reCAPTCHA v2 (checkbox)';
	}

	public function is_available(): bool {
		return true;
	}

	public function is_configured( array $opts ): bool {
		return '' !== trim( (string) ( $opts['recaptcha_site_key'] ?? '' ) )
			&& '' !== trim( (string) ( $opts['recaptcha_secret_key'] ?? '' ) );
	}

	public function register(): void {}

	public function enqueue_login_assets( array $opts ): void {
		$site_key = (string) ( $opts['recaptcha_site_key'] ?? '' );

		wp_register_script(
			'harden-recaptcha-v2-onload',
			'',
			array(),
			'1.0',
			true
		);
		wp_add_inline_script(
			'harden-recaptcha-v2-onload',
			'function hardenRecaptchaOnload(){' .
				'grecaptcha.render("harden-recaptcha-widget",{sitekey:"' . esc_js( $site_key ) . '"});' .
			'}'
		);
		wp_enqueue_script( 'harden-recaptcha-v2-onload' );

		wp_enqueue_script(
			'harden-recaptcha-v2',
			'https://www.google.com/recaptcha/api.js?onload=hardenRecaptchaOnload&render=explicit',
			array( 'harden-recaptcha-v2-onload' ),
			null,
			true
		);
	}

	public function render_login_field( array $opts ): void {
		wp_nonce_field( 'harden_login_captcha', 'harden_login_captcha_nonce' );
		echo '<div id="harden-recaptcha-widget" class="harden-recaptcha-v2"></div>';
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

		$token = isset( $_POST['g-recaptcha-response'] )
			? sanitize_text_field( wp_unslash( $_POST['g-recaptcha-response'] ) )
			: '';

		if ( '' === $token ) {
			return new \WP_Error(
				'harden_captcha_missing',
				__( '<strong>Error:</strong> Please complete the CAPTCHA.', 'harden-by-design-by-nh' )
			);
		}

		$opts   = Harden_Options::get();
		$secret = (string) ( $opts['recaptcha_secret_key'] ?? '' );

		$remote_ip = isset( $_SERVER['REMOTE_ADDR'] )
			? sanitize_text_field( wp_unslash( $_SERVER['REMOTE_ADDR'] ) )
			: '';

		$result = Harden_Remote_Verify::post_and_check(
			'https://www.google.com/recaptcha/api/siteverify',
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
