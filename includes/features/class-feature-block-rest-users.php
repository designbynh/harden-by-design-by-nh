<?php
/**
 * Feature: Block user enumeration via the wp/v2/users REST endpoint.
 *
 * Requests to /wp/v2/users are denied for users without the `list_users`
 * capability. The /wp/v2/users/me endpoint is left alone.
 *
 * @package HardenByDesignByNH
 */

declare(strict_types=1);

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

final class Harden_Feature_Block_REST_Users implements Harden_Feature {

	public function id(): string {
		return 'rest_block_users_endpoint';
	}

	public function register(): void {
		add_filter( 'rest_pre_dispatch', array( $this, 'block_users_endpoint' ), 10, 3 );
	}

	/**
	 * Return a WP_Error for /wp/v2/users requests from unprivileged users.
	 *
	 * @param mixed            $result  Response to replace the requested one with.
	 * @param \WP_REST_Server  $server  REST server instance.
	 * @param \WP_REST_Request $request Request used to generate the response.
	 * @return mixed|\WP_Error
	 */
	public function block_users_endpoint( $result, $server, $request ) {
		unset( $server );
		if ( null !== $result ) {
			return $result;
		}

		if ( ! (bool) apply_filters( 'harden_by_nh_rest_block_users_endpoint', true ) ) {
			return $result;
		}

		if ( ! is_object( $request ) || ! method_exists( $request, 'get_route' ) ) {
			return $result;
		}

		$route = (string) $request->get_route();
		if ( ! preg_match( '#^/wp/v2/users#', $route ) ) {
			return $result;
		}
		if ( preg_match( '#^/wp/v2/users/me$#', $route ) ) {
			return $result;
		}

		if ( current_user_can( 'list_users' ) ) {
			return $result;
		}

		return new \WP_Error(
			'rest_user_cannot_view',
			__( 'Sorry, you are not allowed to list users.', 'harden-by-design-by-nh' ),
			array( 'status' => 401 )
		);
	}
}
