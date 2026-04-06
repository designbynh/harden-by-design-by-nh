<?php
declare(strict_types=1);

/**
 * Shared remote‑verification helper used by every CAPTCHA provider.
 *
 * @package HardenByDesignByNH
 */

defined( 'ABSPATH' ) || exit;

/**
 * Sends a POST request to a verification endpoint and decodes the JSON response.
 */
final class Harden_Remote_Verify {

	/**
	 * POST to a verification endpoint and decode the JSON response.
	 *
	 * @param string $url  Verification URL.
	 * @param array  $body POST body fields.
	 * @return array|WP_Error Decoded JSON body on success, WP_Error on failure.
	 */
	public static function post_and_check( string $url, array $body ) {
		$response = wp_remote_post(
			$url,
			array(
				'timeout' => 10,
				'body'    => $body,
			)
		);

		if ( is_wp_error( $response ) ) {
			return new WP_Error(
				'harden_verify_http',
				__( 'Could not verify login. Try again shortly.', 'harden-by-design-by-nh' )
			);
		}

		$code = wp_remote_retrieve_response_code( $response );
		$raw  = wp_remote_retrieve_body( $response );
		$data = json_decode( (string) $raw, true );

		if ( 200 !== $code || ! is_array( $data ) ) {
			return new WP_Error(
				'harden_verify_invalid',
				__( 'Verification failed.', 'harden-by-design-by-nh' )
			);
		}

		return $data;
	}
}
