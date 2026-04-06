<?php
declare(strict_types=1);

/**
 * reCAPTCHA v3 (score) login provider.
 *
 * @package HardenByDesignByNH
 */

defined( 'ABSPATH' ) || exit;

/**
 * Renders and verifies a Google reCAPTCHA v3 invisible challenge on the login form.
 */
final class Harden_Login_Recaptcha_V3 implements Harden_Login_Provider {

	public function id(): string {
		return 'recaptcha_v3';
	}

	public function type(): string {
		return 'login_captcha';
	}

	public function admin_label(): string {
		return 'reCAPTCHA v3 (score)';
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

		wp_enqueue_script(
			'harden-recaptcha-v3',
			'https://www.google.com/recaptcha/api.js?render=' . rawurlencode( $site_key ),
			array(),
			null,
			true
		);

		wp_add_inline_script(
			'harden-recaptcha-v3',
			'document.addEventListener("DOMContentLoaded",function(){' .
				'var form=document.getElementById("loginform");' .
				'if(!form)return;' .
				'form.addEventListener("submit",function(e){' .
					'var tok=form.querySelector("[name=harden_recaptcha_token]");' .
					'if(tok&&tok.value)return;' .
					'e.preventDefault();' .
					'grecaptcha.ready(function(){' .
						'grecaptcha.execute(' . wp_json_encode( $site_key ) . ',{action:"login"}).then(function(t){' .
							'tok.value=t;' .
							'form.submit();' .
						'});' .
					'});' .
				'});' .
			'});'
		);
	}

	public function render_login_field( array $opts ): void {
		wp_nonce_field( 'harden_login_captcha', 'harden_login_captcha_nonce' );
		echo '<input type="hidden" name="harden_recaptcha_token" value="" />';
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

		$token = isset( $_POST['harden_recaptcha_token'] )
			? sanitize_text_field( wp_unslash( $_POST['harden_recaptcha_token'] ) )
			: '';

		if ( '' === $token ) {
			return new \WP_Error(
				'harden_captcha_missing',
				__( '<strong>Error:</strong> CAPTCHA verification failed. Please try again.', 'harden-by-design-by-nh' )
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

		$score     = isset( $result['score'] ) ? (float) $result['score'] : 0.0;
		$action    = isset( $result['action'] ) ? (string) $result['action'] : '';
		$threshold = isset( $opts['recaptcha_v3_score_threshold'] ) ? (float) $opts['recaptcha_v3_score_threshold'] : 0.5;

		if ( $score < $threshold || 'login' !== $action ) {
			return new \WP_Error(
				'harden_captcha_score',
				__( '<strong>Error:</strong> Suspicious activity detected. Please try again.', 'harden-by-design-by-nh' )
			);
		}

		return $user;
	}
}
