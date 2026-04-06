<?php
/**
 * Feature: Secret URL to turn off "Block public login page" when locked out.
 *
 * Does not log anyone in; it only disables disable_wp_login_page and redirects
 * to wp-login.php. The token is one-time (cleared on success).
 *
 * @package HardenByDesignByNH
 */

declare(strict_types=1);

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

final class Harden_Feature_Login_Rescue implements Harden_Feature {

	public const QUERY_ARG = 'harden_by_nh_login_rescue';

	private const TOKEN_HEX_LENGTH = 64;

	public function id(): string {
		return 'login_rescue';
	}

	public function register(): void {
		add_action( 'init', array( $this, 'maybe_handle_rescue' ), 0 );
	}

	/**
	 * @return string 64-character lowercase hex, or empty on failure.
	 */
	public static function new_rescue_token(): string {
		try {
			return bin2hex( random_bytes( 32 ) );
		} catch ( \Exception $e ) {
			return '';
		}
	}

	/**
	 * Full front-end URL including the secret token (for admin display only).
	 */
	public static function url_for_token( string $token ): string {
		$token = sanitize_text_field( $token );
		return add_query_arg( self::QUERY_ARG, $token, home_url( '/' ) );
	}

	/**
	 * Admin display URL from options, or empty when there is no valid stored token.
	 *
	 * @param array<string, mixed> $opts Plugin options.
	 */
	public static function public_url_from_options( array $opts ): string {
		$stored = isset( $opts['login_rescue_token'] ) ? (string) $opts['login_rescue_token'] : '';
		if ( '' === $stored || strlen( $stored ) !== self::TOKEN_HEX_LENGTH || ! ctype_xdigit( $stored ) ) {
			return '';
		}
		return self::url_for_token( $stored );
	}

	public function maybe_handle_rescue(): void {
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Public GET route; token is the credential.
		if ( ! isset( $_GET[ self::QUERY_ARG ] ) ) {
			return;
		}

		// phpcs:ignore WordPress.Security.NonceVerification.Recommended
		$provided = sanitize_text_field( wp_unslash( (string) $_GET[ self::QUERY_ARG ] ) );
		$opts     = Harden_Options::get();
		if ( empty( $opts['login_rescue_enabled'] ) ) {
			return;
		}
		$stored   = isset( $opts['login_rescue_token'] ) ? (string) $opts['login_rescue_token'] : '';

		$stored_ok = ( '' !== $stored && strlen( $stored ) === self::TOKEN_HEX_LENGTH && ctype_xdigit( $stored ) );
		$prov_ok   = ( strlen( $provided ) === self::TOKEN_HEX_LENGTH && ctype_xdigit( $provided ) );

		$left  = $stored_ok ? $stored : str_repeat( '0', self::TOKEN_HEX_LENGTH );
		$right = $prov_ok ? $provided : str_repeat( '1', self::TOKEN_HEX_LENGTH );

		if ( ! hash_equals( $left, $right ) || ! $stored_ok ) {
			return;
		}

		$opts['disable_wp_login_page'] = false;
		$opts['login_rescue_token']   = '';
		// One-time link consumed; keep the toggle aligned with “no active URL” until re-enabled.
		$opts['login_rescue_enabled'] = false;
		Harden_Options::update_all( $opts );

		wp_safe_redirect( wp_login_url() );
		exit;
	}
}
