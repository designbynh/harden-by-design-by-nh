<?php
/**
 * Feature: Enforce a REST API access policy (guests-only or non-admins).
 *
 * The option is an enum ('off', 'guests', 'non_admins') rather than a boolean.
 * The registry should check `$opts['rest_api_policy'] !== 'off'` instead of a
 * truthy test before calling register().
 *
 * @package HardenByDesignByNH
 */

declare(strict_types=1);

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

final class Harden_Feature_REST_API_Policy implements Harden_Feature {

	public function id(): string {
		return 'rest_api_policy';
	}

	public function register(): void {
		add_filter( 'rest_authentication_errors', array( $this, 'enforce_policy' ), 100 );
	}

	/**
	 * Deny REST requests based on the configured policy.
	 *
	 * @param \WP_Error|null|bool|mixed $errors Prior REST auth result.
	 * @return \WP_Error|null|bool|mixed
	 */
	public function enforce_policy( $errors ) {
		if ( ! empty( $errors ) ) {
			return $errors;
		}

		$opts   = Harden_Options::get();
		$policy = isset( $opts['rest_api_policy'] ) ? (string) $opts['rest_api_policy'] : 'off';
		if ( 'off' === $policy ) {
			return $errors;
		}

		$route = '';
		if ( isset( $GLOBALS['wp'] ) && is_object( $GLOBALS['wp'] ) && isset( $GLOBALS['wp']->query_vars['rest_route'] ) ) {
			$route = (string) $GLOBALS['wp']->query_vars['rest_route'];
		}

		if ( '' !== $route ) {
			/** @var array<int, string> */
			$exceptions = apply_filters(
				'harden_by_nh_rest_route_exceptions',
				array(
					'contact-form-7',
					'wordfence',
					'elementor',
					'ws-form',
					'litespeed',
					'wp-recipe-maker',
					'iawp',
					'sureforms',
					'surecart',
					'sliderrevolution',
					'mollie',
				)
			);
			foreach ( (array) $exceptions as $ex ) {
				$ex = trim( str_replace( '\\', '/', (string) $ex ), '/' );
				if ( '' !== $ex && $this->route_has_namespace_prefix( $route, $ex ) ) {
					return $errors;
				}
			}
		}

		$deny = false;
		if ( 'non_admins' === $policy && ! current_user_can( 'manage_options' ) ) {
			$deny = true;
		} elseif ( 'guests' === $policy && ! is_user_logged_in() ) {
			$deny = true;
		}

		if ( $deny ) {
			return new \WP_Error(
				'rest_forbidden',
				__( 'You do not have permission to access the REST API.', 'harden-by-design-by-nh' ),
				array( 'status' => 401 )
			);
		}

		return $errors;
	}

	/**
	 * Check whether a REST route starts with the given namespace segment.
	 */
	private function route_has_namespace_prefix( string $route, string $namespace_segment ): bool {
		$route             = trim( str_replace( '\\', '/', $route ), '/' );
		$namespace_segment = trim( str_replace( '\\', '/', $namespace_segment ), '/' );
		if ( '' === $namespace_segment ) {
			return false;
		}
		return $route === $namespace_segment || strpos( $route, $namespace_segment . '/' ) === 0;
	}
}
