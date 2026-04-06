<?php
/**
 * AJAX handlers for the HardenWP settings page.
 *
 * @package HardenByDesignByNH
 */

declare(strict_types=1);

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

final class Harden_Admin_Ajax {

	public static function init(): void {
		add_action( 'wp_ajax_harden_by_nh_save_switch', array( self::class, 'ajax_save_switch' ) );
		add_action( 'wp_ajax_harden_by_nh_save_page_slug', array( self::class, 'ajax_save_page_slug' ) );
		add_action( 'wp_ajax_harden_by_nh_save_login_protection', array( self::class, 'ajax_save_login_protection' ) );
		add_action( 'wp_ajax_harden_by_nh_save_rest_policy', array( self::class, 'ajax_save_rest_policy' ) );
		add_action( 'wp_ajax_harden_by_nh_regenerate_login_rescue', array( self::class, 'ajax_regenerate_login_rescue' ) );
	}

	public static function ajax_save_switch(): void {
		check_ajax_referer( 'harden_by_nh_ajax', 'nonce' );

		if ( ! current_user_can( Harden_Admin_Page::CAP ) ) {
			wp_send_json_error( array( 'message' => __( 'Forbidden.', 'harden-by-design-by-nh' ) ), 403 );
		}

		$field = isset( $_POST['field'] ) ? sanitize_key( wp_unslash( (string) $_POST['field'] ) ) : '';
		if ( ! in_array( $field, Harden_Option_Schema::boolean_ajax_keys(), true ) ) {
			wp_send_json_error( array( 'message' => __( 'Invalid field.', 'harden-by-design-by-nh' ) ), 400 );
		}

		$raw = isset( $_POST['value'] ) ? wp_unslash( $_POST['value'] ) : '0';
		$on  = ( $raw === '1' || $raw === 1 || $raw === true || $raw === 'true' );

		if ( 'disallow_file_edit' === $field && $on && defined( 'DISALLOW_FILE_EDIT' ) && ! DISALLOW_FILE_EDIT ) {
			wp_send_json_error( array( 'message' => __( 'Cannot enable while DISALLOW_FILE_EDIT is false in wp-config.php.', 'harden-by-design-by-nh' ) ), 400 );
		}

		$opts           = Harden_Options::get();
		$opts[ $field ] = $on;

		if ( 'disable_wp_login_page' === $field ) {
			if ( $on ) {
				$opts['login_rescue_enabled'] = true;
				$token                        = Harden_Feature_Login_Rescue::new_rescue_token();
				if ( '' === $token ) {
					wp_send_json_error( array( 'message' => __( 'Could not generate a rescue link. Try again.', 'harden-by-design-by-nh' ) ), 500 );
				}
				$opts['login_rescue_token'] = $token;
			} else {
				$opts['login_rescue_enabled'] = false;
				$opts['login_rescue_token']   = '';
			}
		} elseif ( 'login_rescue_enabled' === $field ) {
			if ( $on ) {
				$token = Harden_Feature_Login_Rescue::new_rescue_token();
				if ( '' === $token ) {
					wp_send_json_error( array( 'message' => __( 'Could not generate a rescue link. Try again.', 'harden-by-design-by-nh' ) ), 500 );
				}
				$opts['login_rescue_token'] = $token;
			} else {
				$opts['login_rescue_token'] = '';
			}
		}

		Harden_Options::update_all( $opts );

		$data = array(
			'field' => $field,
			'value' => $on,
		);
		if ( 'disallow_file_edit' === $field ) {
			$data['file_editor_effective'] = Harden_Options::is_file_editor_disabled();
		}

		if ( in_array( $field, array( 'disable_wp_login_page', 'login_rescue_enabled' ), true ) ) {
			$fresh                        = Harden_Options::get();
			$data['login_rescue_enabled'] = ! empty( $fresh['login_rescue_enabled'] );
			$data['rescue_url']            = Harden_Feature_Login_Rescue::public_url_from_options( $fresh );
		}

		wp_send_json_success( $data );
	}

	public static function ajax_save_page_slug(): void {
		check_ajax_referer( 'harden_by_nh_ajax', 'nonce' );

		if ( ! current_user_can( Harden_Admin_Page::CAP ) ) {
			wp_send_json_error( array( 'message' => __( 'Forbidden.', 'harden-by-design-by-nh' ) ), 403 );
		}

		$group = isset( $_POST['group'] ) ? sanitize_key( wp_unslash( (string) $_POST['group'] ) ) : '';
		$slug  = isset( $_POST['slug'] ) ? sanitize_key( wp_unslash( (string) $_POST['slug'] ) ) : '';
		if ( ! in_array( $group, Harden_Options::page_slug_list_keys(), true ) || '' === $slug ) {
			wp_send_json_error( array( 'message' => __( 'Invalid request.', 'harden-by-design-by-nh' ) ), 400 );
		}

		if ( 'disabled_taxonomy_archives' === $group && ! taxonomy_exists( $slug ) ) {
			wp_send_json_error( array( 'message' => __( 'Unknown taxonomy.', 'harden-by-design-by-nh' ) ), 400 );
		}
		if ( ( 'disabled_post_type_archives' === $group || 'disabled_post_type_singles' === $group ) && ! post_type_exists( $slug ) ) {
			wp_send_json_error( array( 'message' => __( 'Unknown post type.', 'harden-by-design-by-nh' ) ), 400 );
		}

		$raw = isset( $_POST['value'] ) ? wp_unslash( $_POST['value'] ) : '0';
		$on  = ( $raw === '1' || $raw === 1 || $raw === true || $raw === 'true' );

		$opts = Harden_Options::get();
		$list = isset( $opts[ $group ] ) && is_array( $opts[ $group ] ) ? $opts[ $group ] : array();
		$list = Harden_Options::sanitize_slug_list( $list );

		if ( $on ) {
			if ( ! in_array( $slug, $list, true ) ) {
				$list[] = $slug;
			}
		} else {
			$list = array_values( array_diff( $list, array( $slug ) ) );
		}

		$opts[ $group ] = $list;
		Harden_Options::update_all( $opts );

		wp_send_json_success(
			array(
				'group' => $group,
				'slug'  => $slug,
				'value' => $on,
			)
		);
	}

	public static function ajax_save_login_protection(): void {
		check_ajax_referer( 'harden_by_nh_ajax', 'nonce' );

		if ( ! current_user_can( Harden_Admin_Page::CAP ) ) {
			wp_send_json_error( array( 'message' => __( 'Forbidden.', 'harden-by-design-by-nh' ) ), 403 );
		}

		$opts = Harden_Options::get();

		if ( isset( $_POST['login_protection_provider'] ) ) {
			$pid = sanitize_key( wp_unslash( (string) $_POST['login_protection_provider'] ) );
			$opts['login_protection_provider'] = in_array( $pid, Harden_Options::login_protection_provider_ids(), true ) ? $pid : 'none';
		}

		if ( isset( $_POST['recaptcha_site_key'] ) ) {
			$opts['recaptcha_site_key'] = sanitize_text_field( wp_unslash( (string) $_POST['recaptcha_site_key'] ) );
		}

		if ( isset( $_POST['recaptcha_secret_key'] ) ) {
			$opts['recaptcha_secret_key'] = sanitize_text_field( wp_unslash( (string) $_POST['recaptcha_secret_key'] ) );
		}

		if ( isset( $_POST['recaptcha_v3_score_threshold'] ) ) {
			$opts['recaptcha_v3_score_threshold'] = (float) $_POST['recaptcha_v3_score_threshold'];
		}

		if ( isset( $_POST['turnstile_site_key'] ) ) {
			$opts['turnstile_site_key'] = sanitize_text_field( wp_unslash( (string) $_POST['turnstile_site_key'] ) );
		}

		if ( isset( $_POST['turnstile_secret_key'] ) ) {
			$opts['turnstile_secret_key'] = sanitize_text_field( wp_unslash( (string) $_POST['turnstile_secret_key'] ) );
		}

		Harden_Options::update_all( $opts );

		wp_send_json_success( array( 'saved' => true ) );
	}

	public static function ajax_save_rest_policy(): void {
		check_ajax_referer( 'harden_by_nh_ajax', 'nonce' );

		if ( ! current_user_can( Harden_Admin_Page::CAP ) ) {
			wp_send_json_error( array( 'message' => __( 'Forbidden.', 'harden-by-design-by-nh' ) ), 403 );
		}

		$raw    = isset( $_POST['rest_api_policy'] ) ? sanitize_key( wp_unslash( (string) $_POST['rest_api_policy'] ) ) : 'off';
		$policy = in_array( $raw, Harden_Options::rest_api_policies(), true ) ? $raw : 'off';

		$opts                    = Harden_Options::get();
		$opts['rest_api_policy'] = $policy;
		Harden_Options::update_all( $opts );

		wp_send_json_success( array( 'rest_api_policy' => $policy ) );
	}

	public static function ajax_regenerate_login_rescue(): void {
		check_ajax_referer( 'harden_by_nh_ajax', 'nonce' );

		if ( ! current_user_can( Harden_Admin_Page::CAP ) ) {
			wp_send_json_error( array( 'message' => __( 'Forbidden.', 'harden-by-design-by-nh' ) ), 403 );
		}

		$opts = Harden_Options::get();
		if ( empty( $opts['login_rescue_enabled'] ) ) {
			wp_send_json_error( array( 'message' => __( 'Turn on Enable rescue link first.', 'harden-by-design-by-nh' ) ), 400 );
		}

		$token = Harden_Feature_Login_Rescue::new_rescue_token();
		if ( '' === $token ) {
			wp_send_json_error( array( 'message' => __( 'Could not generate a token. Try again.', 'harden-by-design-by-nh' ) ), 500 );
		}

		$opts['login_rescue_token'] = $token;
		Harden_Options::update_all( $opts );

		wp_send_json_success(
			array(
				'url' => Harden_Feature_Login_Rescue::url_for_token( $token ),
			)
		);
	}
}
