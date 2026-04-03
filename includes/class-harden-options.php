<?php
/**
 * Options storage and defaults.
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
	 * Default option values.
	 *
	 * @return array<string, mixed>
	 */
	public static function defaults(): array {
		return array(
			'disable_author_pages'               => false,
			'disable_all_taxonomy_archives'      => false,
			'disabled_taxonomy_archives'         => array(),
			'disable_all_post_type_archives'     => false,
			'disabled_post_type_archives'        => array(),
			'disabled_post_type_singles'         => array(),
			'disable_blog_index'                => false,
			'disable_date_archives'             => false,
			'hide_wp_version'             => false,
			'hide_wp_branding'            => false,
			'disallow_file_edit'          => false,
			'disable_appearance_site_editor' => false,
			'disable_comments'            => false,
			'disable_application_passwords' => false,
			'disable_xmlrpc'              => false,
			'rest_api_policy'             => 'off',
			'disable_emojis'              => false,
			'disable_dashicons'           => false,
			'disable_embeds'              => false,
			'remove_jquery_migrate'       => false,
			'remove_shortlink'            => false,
			'disable_rss_feeds'           => false,
			'remove_feed_links'           => false,
			'disable_self_pingbacks'      => false,
			'remove_rest_api_links'       => false,
			'disable_google_maps'         => false,
			'disable_password_strength_meter' => false,
			'remove_comment_urls'         => false,
			'remove_global_styles'        => false,
			'recaptcha_enabled'           => false,
			'recaptcha_version'           => 'v3',
			'recaptcha_site_key'          => '',
			'recaptcha_secret_key'        => '',
		);
	}

	/**
	 * Values applied on first plugin activation only (when harden_by_nh_options is not in the DB).
	 * Mirrors the site owner’s chosen baseline; deactivate/reactivate does not re-apply.
	 *
	 * @return array<string, mixed>
	 */
	public static function first_activation_preset(): array {
		return array_merge(
			self::defaults(),
			array(
				'disable_author_pages'               => true,
				'disable_all_taxonomy_archives'      => false,
				'disabled_taxonomy_archives'         => array( 'post_tag' ),
				'disable_all_post_type_archives'     => false,
				'disabled_post_type_archives'        => array(),
				'disabled_post_type_singles'         => array(),
				'disable_blog_index'                => false,
				'disable_date_archives'             => true,
				'hide_wp_version'             => true,
				'hide_wp_branding'            => true,
				'disallow_file_edit'          => true,
				'disable_appearance_site_editor' => true,
				'disable_comments'            => true,
				'disable_application_passwords' => false,
				'disable_xmlrpc'              => false,
				'rest_api_policy'             => 'guests',
				'disable_emojis'              => true,
				'disable_dashicons'           => true,
				'disable_embeds'              => true,
				'remove_jquery_migrate'       => false,
				'remove_shortlink'            => true,
				'disable_rss_feeds'           => true,
				'remove_feed_links'           => true,
				'disable_self_pingbacks'      => true,
				'remove_rest_api_links'       => true,
				'disable_google_maps'         => false,
				'disable_password_strength_meter' => true,
				'remove_comment_urls'         => true,
				'remove_global_styles'        => false,
				'recaptcha_enabled'           => false,
				'recaptcha_version'           => 'v2',
				'recaptcha_site_key'          => '',
				'recaptcha_secret_key'        => '',
			)
		);
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
	 * Boolean option keys (AJAX toggles).
	 *
	 * @return list<string>
	 */
	public static function boolean_keys(): array {
		return array(
			'disable_author_pages',
			'disable_all_taxonomy_archives',
			'disable_all_post_type_archives',
			'disable_blog_index',
			'disable_date_archives',
			'hide_wp_version',
			'hide_wp_branding',
			'disallow_file_edit',
			'disable_appearance_site_editor',
			'disable_comments',
			'disable_application_passwords',
			'disable_xmlrpc',
			'disable_emojis',
			'disable_dashicons',
			'disable_embeds',
			'remove_jquery_migrate',
			'remove_shortlink',
			'disable_rss_feeds',
			'remove_feed_links',
			'disable_self_pingbacks',
			'remove_rest_api_links',
			'disable_google_maps',
			'disable_password_strength_meter',
			'remove_comment_urls',
			'remove_global_styles',
			'recaptcha_enabled',
		);
	}

	/**
	 * Valid REST API policy values.
	 *
	 * @return list<string>
	 */
	public static function rest_api_policies(): array {
		return array( 'off', 'guests', 'non_admins' );
	}

	/**
	 * Whether WordPress is actually blocking the theme/plugin file editor (DISALLOW_FILE_EDIT).
	 * True when the constant is defined and truthy (wp-config.php or this plugin on load).
	 */
	public static function is_file_editor_disabled(): bool {
		return defined( 'DISALLOW_FILE_EDIT' ) && DISALLOW_FILE_EDIT;
	}

	/**
	 * Option keys that store lists of taxonomy or post type slugs (Pages tab).
	 *
	 * @return list<string>
	 */
	public static function page_slug_list_keys(): array {
		return array(
			'disabled_taxonomy_archives',
			'disabled_post_type_archives',
			'disabled_post_type_singles',
		);
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
	 * Toggles shown on the Frontend settings tab.
	 *
	 * @return list<string>
	 */
	public static function frontend_toggle_keys(): array {
		return array(
			'disable_emojis',
			'disable_dashicons',
			'disable_embeds',
			'remove_jquery_migrate',
			'remove_shortlink',
			'disable_rss_feeds',
			'remove_feed_links',
			'disable_self_pingbacks',
			'remove_rest_api_links',
			'disable_google_maps',
			'disable_password_strength_meter',
			'remove_comment_urls',
			'remove_global_styles',
		);
	}

	/**
	 * @return array<string, mixed>
	 */
	public static function get(): array {
		$stored = get_option( self::OPTION_KEY, array() );
		if ( ! is_array( $stored ) ) {
			$stored = array();
		}
		$legacy_rest_guest = ! empty( $stored['disable_rest_api_guest'] );
		unset( $stored['harden_tab'], $stored['_save_tab'] );
		$merged = array_merge( self::defaults(), $stored );
		unset( $merged['disable_rest_api_guest'] );

		// Legacy: guest-only REST toggle → policy.
		if ( $legacy_rest_guest && ( ! isset( $stored['rest_api_policy'] ) || 'off' === (string) $stored['rest_api_policy'] || '' === (string) $stored['rest_api_policy'] ) ) {
			$merged['rest_api_policy'] = 'guests';
		}

		foreach ( self::boolean_keys() as $key ) {
			if ( array_key_exists( $key, $merged ) ) {
				$merged[ $key ] = self::to_bool( $merged[ $key ] );
			}
		}

		$policy = isset( $merged['rest_api_policy'] ) ? (string) $merged['rest_api_policy'] : 'off';
		if ( ! in_array( $policy, self::rest_api_policies(), true ) ) {
			$merged['rest_api_policy'] = 'off';
		} else {
			$merged['rest_api_policy'] = $policy;
		}

		foreach ( self::page_slug_list_keys() as $list_key ) {
			$merged[ $list_key ] = self::sanitize_slug_list( $merged[ $list_key ] ?? array() );
		}

		return $merged;
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
	 * @param array<string, mixed> $opts Options.
	 */
	public static function update_all( array $opts ): bool {
		return update_option( self::OPTION_KEY, self::prepare_storage( $opts ) );
	}

	/**
	 * @param array<string, mixed> $opts Options.
	 * @return array<string, mixed>
	 */
	public static function prepare_storage( array $opts ): array {
		$out = self::defaults();
		foreach ( array_keys( $out ) as $key ) {
			if ( ! array_key_exists( $key, $opts ) ) {
				continue;
			}
			$v = $opts[ $key ];
			switch ( $key ) {
				case 'disable_author_pages':
				case 'disable_all_taxonomy_archives':
				case 'disable_all_post_type_archives':
				case 'disable_blog_index':
				case 'disable_date_archives':
				case 'hide_wp_version':
				case 'hide_wp_branding':
				case 'disallow_file_edit':
				case 'disable_appearance_site_editor':
				case 'disable_comments':
				case 'disable_application_passwords':
				case 'disable_xmlrpc':
				case 'disable_emojis':
				case 'disable_dashicons':
				case 'disable_embeds':
				case 'remove_jquery_migrate':
				case 'remove_shortlink':
				case 'disable_rss_feeds':
				case 'remove_feed_links':
				case 'disable_self_pingbacks':
				case 'remove_rest_api_links':
				case 'disable_google_maps':
				case 'disable_password_strength_meter':
				case 'remove_comment_urls':
				case 'remove_global_styles':
				case 'recaptcha_enabled':
					$out[ $key ] = self::to_bool( $v );
					break;
				case 'rest_api_policy':
					$p = is_string( $v ) ? $v : (string) $v;
					$out[ $key ] = in_array( $p, self::rest_api_policies(), true ) ? $p : 'off';
					break;
				case 'recaptcha_version':
					$ver           = is_string( $v ) ? $v : (string) $v;
					$out[ $key ] = in_array( $ver, array( 'v2', 'v3' ), true ) ? $ver : 'v3';
					break;
				case 'recaptcha_site_key':
				case 'recaptcha_secret_key':
					$out[ $key ] = sanitize_text_field( is_string( $v ) ? $v : (string) $v );
					break;
				case 'disabled_taxonomy_archives':
				case 'disabled_post_type_archives':
				case 'disabled_post_type_singles':
					$out[ $key ] = self::sanitize_slug_list( $v );
					break;
				default:
					break;
			}
		}
		return $out;
	}

	/**
	 * @param string $key Option key.
	 * @param mixed  $default Fallback.
	 * @return mixed
	 */
	public static function get_value( string $key, $default = null ) {
		$all = self::get();
		return array_key_exists( $key, $all ) ? $all[ $key ] : $default;
	}

	public static function init(): void {
	}
}
