<?php
/**
 * Options storage — schema-driven.
 *
 * Every key, type, default, and validation rule lives in Harden_Option_Schema.
 * This class reads/writes the single wp_options row and applies legacy migrations.
 *
 * @package HardenByDesignByNH
 */

declare(strict_types=1);

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class Harden_Options
 */
final class Harden_Options {

	public const OPTION_KEY = 'harden_by_nh_options';

	/**
	 * Default option values (from schema).
	 *
	 * @return array<string, mixed>
	 */
	public static function defaults(): array {
		return Harden_Option_Schema::defaults();
	}

	/**
	 * Values applied on first plugin activation only (when the option row is absent).
	 *
	 * @return array<string, mixed>
	 */
	public static function first_activation_preset(): array {
		return Harden_Option_Schema::first_activation_preset();
	}

	/**
	 * Persist first-install preset if this site has never saved options (fresh install).
	 */
	public static function seed_first_install_if_needed(): void {
		if ( null !== get_option( self::OPTION_KEY, null ) ) {
			return;
		}
		self::update_all( self::first_activation_preset() );
	}

	/**
	 * All stored option keys (for sanitising untrusted merges).
	 *
	 * @return list<string>
	 */
	public static function default_option_keys(): array {
		return array_keys( self::defaults() );
	}

	/**
	 * Boolean option keys that are AJAX-switchable.
	 *
	 * @return list<string>
	 */
	public static function boolean_keys(): array {
		return Harden_Option_Schema::boolean_ajax_keys();
	}

	/**
	 * Toggles shown on the Frontend settings tab.
	 *
	 * @return list<string>
	 */
	public static function frontend_toggle_keys(): array {
		$schema = Harden_Option_Schema::all();
		$out    = array();
		foreach ( Harden_Option_Schema::keys_for_tab( 'frontend' ) as $key ) {
			$def = $schema[ $key ] ?? array();
			if ( 'bool' === ( $def['type'] ?? '' ) && ! empty( $def['ajax'] ) ) {
				$out[] = $key;
			}
		}
		return $out;
	}

	/**
	 * Toggles on the Notifications tab.
	 *
	 * @return list<string>
	 */
	public static function notification_toggle_keys(): array {
		$schema = Harden_Option_Schema::all();
		$out    = array();
		foreach ( Harden_Option_Schema::keys_for_tab( 'notifications' ) as $key ) {
			$def = $schema[ $key ] ?? array();
			if ( 'bool' === ( $def['type'] ?? '' ) && ! empty( $def['ajax'] ) ) {
				$out[] = $key;
			}
		}
		return $out;
	}

	/**
	 * Valid REST API policy values.
	 *
	 * @return list<string>
	 */
	public static function rest_api_policies(): array {
		return Harden_Option_Schema::enum_values( 'rest_api_policy' );
	}

	/**
	 * Valid values for {@see 'login_protection_provider'} (filterable).
	 *
	 * @return list<string>
	 */
	public static function login_protection_provider_ids(): array {
		return apply_filters(
			'harden_by_nh_login_protection_provider_ids',
			Harden_Option_Schema::enum_values( 'login_protection_provider' )
		);
	}

	/**
	 * Whether WordPress is actually blocking the theme/plugin file editor (DISALLOW_FILE_EDIT).
	 */
	public static function is_file_editor_disabled(): bool {
		return defined( 'DISALLOW_FILE_EDIT' ) && DISALLOW_FILE_EDIT;
	}

	/**
	 * Option keys that store lists of taxonomy or post-type slugs.
	 *
	 * @return list<string>
	 */
	public static function page_slug_list_keys(): array {
		return Harden_Option_Schema::slug_list_keys();
	}

	/**
	 * @param mixed $value Raw list.
	 * @return list<string>
	 */
	public static function sanitize_slug_list( $value ): array {
		if ( ! is_array( $value ) ) {
			return array();
		}
		$out = array();
		foreach ( $value as $item ) {
			$s = sanitize_key( is_string( $item ) ? $item : (string) $item );
			if ( '' !== $s ) {
				$out[] = $s;
			}
		}
		return array_values( array_unique( $out ) );
	}

	/**
	 * @param mixed $value Raw value.
	 */
	public static function to_bool( $value ): bool {
		if ( is_bool( $value ) ) {
			return $value;
		}
		if ( is_int( $value ) || is_float( $value ) ) {
			return (bool) $value;
		}
		if ( is_string( $value ) ) {
			$lower = strtolower( $value );
			return in_array( $lower, array( '1', 'true', 'yes', 'on' ), true );
		}
		return (bool) $value;
	}

	/**
	 * Read merged and sanitised options from the database.
	 *
	 * @return array<string, mixed>
	 */
	public static function get(): array {
		$stored = get_option( self::OPTION_KEY, array() );
		if ( ! is_array( $stored ) ) {
			$stored = array();
		}

		$legacy_rest_guest      = ! empty( $stored['disable_rest_api_guest'] );
		$had_login_provider_key = array_key_exists( 'login_protection_provider', $stored );

		unset( $stored['harden_tab'], $stored['_save_tab'] );

		$merged = array_merge( self::defaults(), $stored );
		unset( $merged['disable_rest_api_guest'] );

		// Legacy: guest-only REST toggle → policy.
		if ( $legacy_rest_guest && ( ! isset( $stored['rest_api_policy'] ) || 'off' === (string) $stored['rest_api_policy'] || '' === (string) $stored['rest_api_policy'] ) ) {
			$merged['rest_api_policy'] = 'guests';
		}

		// Legacy: recaptcha_enabled + keys → login_protection_provider.
		if ( ! $had_login_provider_key ) {
			if ( ! empty( $stored['recaptcha_enabled'] )
				&& '' !== trim( (string) ( $merged['recaptcha_site_key'] ?? '' ) )
				&& '' !== trim( (string) ( $merged['recaptcha_secret_key'] ?? '' ) ) ) {
				$ver = isset( $merged['recaptcha_version'] ) ? (string) $merged['recaptcha_version'] : 'v3';
				$merged['login_protection_provider'] = ( 'v2' === $ver ) ? 'recaptcha_v2' : 'recaptcha_v3';
			} else {
				$merged['login_protection_provider'] = 'none';
			}
		}

		// Cast booleans from schema.
		foreach ( Harden_Option_Schema::boolean_keys() as $key ) {
			if ( array_key_exists( $key, $merged ) ) {
				$merged[ $key ] = self::to_bool( $merged[ $key ] );
			}
		}

		// Validate enums from schema.
		$schema = Harden_Option_Schema::all();
		foreach ( $schema as $key => $def ) {
			if ( 'enum' !== ( $def['type'] ?? '' ) ) {
				continue;
			}
			$raw = isset( $merged[ $key ] ) ? sanitize_key( (string) $merged[ $key ] ) : '';

			if ( 'login_protection_provider' === $key ) {
				$allowed = self::login_protection_provider_ids();
			} else {
				$allowed = $def['values'] ?? array();
			}

			$merged[ $key ] = in_array( $raw, $allowed, true ) ? $raw : $def['default'];
		}

		// Sanitize slug lists from schema.
		foreach ( Harden_Option_Schema::slug_list_keys() as $key ) {
			$merged[ $key ] = self::sanitize_slug_list( $merged[ $key ] ?? array() );
		}

		// Sanitize strings from schema.
		foreach ( Harden_Option_Schema::string_keys() as $key ) {
			if ( array_key_exists( $key, $merged ) ) {
				$merged[ $key ] = sanitize_text_field( (string) $merged[ $key ] );
			}
		}

		// Clamp floats from schema.
		foreach ( $schema as $key => $def ) {
			if ( 'float' !== ( $def['type'] ?? '' ) || ! array_key_exists( $key, $merged ) ) {
				continue;
			}
			$f = (float) $merged[ $key ];
			if ( isset( $def['min'] ) ) {
				$f = max( (float) $def['min'], $f );
			}
			if ( isset( $def['max'] ) ) {
				$f = min( (float) $def['max'], $f );
			}
			$merged[ $key ] = round( $f, 2 );
		}

		// Derived values.
		$lp                      = $merged['login_protection_provider'];
		$merged['recaptcha_enabled'] = in_array( $lp, array( 'recaptcha_v2', 'recaptcha_v3' ), true );
		$merged['recaptcha_version'] = 'recaptcha_v2' === $lp ? 'v2' : 'v3';

		return $merged;
	}

	/**
	 * @param array<string, mixed> $opts Options.
	 */
	public static function update_all( array $opts ): bool {
		return update_option( self::OPTION_KEY, self::prepare_storage( $opts ) );
	}

	/**
	 * Sanitise and prepare options for database storage.
	 *
	 * @param array<string, mixed> $opts Raw input.
	 * @return array<string, mixed>
	 */
	public static function prepare_storage( array $opts ): array {
		$out    = self::defaults();
		$schema = Harden_Option_Schema::all();

		foreach ( $schema as $key => $def ) {
			if ( ! array_key_exists( $key, $opts ) ) {
				continue;
			}

			$type = $def['type'] ?? '';
			$v    = $opts[ $key ];

			switch ( $type ) {
				case 'bool':
					$out[ $key ] = self::to_bool( $v );
					break;

				case 'enum':
					$s = sanitize_key( is_string( $v ) ? $v : (string) $v );
					if ( 'login_protection_provider' === $key ) {
						$allowed = self::login_protection_provider_ids();
					} else {
						$allowed = $def['values'] ?? array();
					}
					$out[ $key ] = in_array( $s, $allowed, true ) ? $s : $def['default'];
					break;

				case 'string':
					$out[ $key ] = sanitize_text_field( is_string( $v ) ? $v : (string) $v );
					break;

				case 'float':
					$f = (float) $v;
					if ( isset( $def['min'] ) ) {
						$f = max( (float) $def['min'], $f );
					}
					if ( isset( $def['max'] ) ) {
						$f = min( (float) $def['max'], $f );
					}
					$out[ $key ] = round( $f, 2 );
					break;

				case 'slug_list':
					$out[ $key ] = self::sanitize_slug_list( $v );
					break;

				case 'derived':
					break;
			}
		}

		$out['recaptcha_enabled'] = in_array( $out['login_protection_provider'], array( 'recaptcha_v2', 'recaptcha_v3' ), true );
		if ( 'recaptcha_v2' === $out['login_protection_provider'] ) {
			$out['recaptcha_version'] = 'v2';
		} elseif ( 'recaptcha_v3' === $out['login_protection_provider'] ) {
			$out['recaptcha_version'] = 'v3';
		}

		return $out;
	}
}
