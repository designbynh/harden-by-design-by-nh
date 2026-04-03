<?php
/**
 * Front-end reductions (emoji, embeds, feeds, scripts, etc.).
 * Patterns informed by common WordPress optimization practices.
 *
 * @package HardenByDesignByNH
 */

declare(strict_types=1);

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class Harden_Frontend
 */
final class Harden_Frontend {

	public static function init(): void {
		add_action( 'init', array( self::class, 'maybe_disable_emojis' ) );
		add_action( 'wp_enqueue_scripts', array( self::class, 'maybe_disable_dashicons' ), 100 );
		add_action( 'init', array( self::class, 'maybe_disable_embeds' ), 9999 );
		add_action( 'wp_default_scripts', array( self::class, 'maybe_remove_jquery_migrate' ), 11 );

		add_action( 'init', array( self::class, 'maybe_remove_shortlink' ) );
		add_action( 'init', array( self::class, 'maybe_remove_feed_links' ) );
		add_action( 'template_redirect', array( self::class, 'maybe_disable_rss_feeds' ), 1 );
		add_action( 'pre_ping', array( self::class, 'maybe_disable_self_pingbacks' ) );

		add_action( 'init', array( self::class, 'maybe_remove_rest_api_links' ) );

		add_action( 'template_redirect', array( self::class, 'maybe_start_google_maps_buffer' ), 1 );
		add_action( 'wp_print_scripts', array( self::class, 'maybe_disable_password_strength_meter' ), 100 );

		add_action( 'template_redirect', array( self::class, 'maybe_remove_comment_urls' ), 1 );

		add_action( 'after_setup_theme', array( self::class, 'maybe_remove_global_styles' ), 11 );
	}

	/**
	 * @return array<string, mixed>
	 */
	private static function opts(): array {
		return Harden_Options::get();
	}

	public static function maybe_disable_emojis(): void {
		if ( empty( self::opts()['disable_emojis'] ) ) {
			return;
		}
		remove_action( 'wp_head', 'print_emoji_detection_script', 7 );
		remove_action( 'admin_print_scripts', 'print_emoji_detection_script' );
		remove_action( 'wp_print_styles', 'print_emoji_styles' );
		remove_action( 'admin_print_styles', 'print_emoji_styles' );
		remove_filter( 'the_content_feed', 'wp_staticize_emoji' );
		remove_filter( 'comment_text_rss', 'wp_staticize_emoji' );
		remove_filter( 'wp_mail', 'wp_staticize_emoji_for_email' );
		add_filter( 'tiny_mce_plugins', array( self::class, 'disable_emojis_tinymce' ) );
		add_filter( 'emoji_svg_url', array( self::class, 'emoji_svg_url_front' ) );
	}

	/**
	 * @param mixed $url Emoji CDN URL.
	 * @return mixed
	 */
	public static function emoji_svg_url_front( $url ) {
		return is_admin() ? $url : false;
	}

	/**
	 * @param mixed $plugins TinyMCE plugins.
	 * @return mixed
	 */
	public static function disable_emojis_tinymce( $plugins ) {
		return is_array( $plugins ) ? array_diff( $plugins, array( 'wpemoji' ) ) : array();
	}

	public static function maybe_disable_dashicons(): void {
		if ( empty( self::opts()['disable_dashicons'] ) || is_admin() ) {
			return;
		}
		if ( is_user_logged_in() ) {
			return;
		}
		wp_dequeue_style( 'dashicons' );
		wp_deregister_style( 'dashicons' );
	}

	public static function maybe_disable_embeds(): void {
		if ( empty( self::opts()['disable_embeds'] ) ) {
			return;
		}
		global $wp;
		$wp->public_query_vars = array_diff( $wp->public_query_vars, array( 'embed' ) );
		add_filter( 'embed_oembed_discover', '__return_false' );
		remove_filter( 'oembed_dataparse', 'wp_filter_oembed_result', 10 );
		remove_action( 'wp_head', 'wp_oembed_add_discovery_links' );
		remove_action( 'wp_head', 'wp_oembed_add_host_js' );
		add_filter( 'tiny_mce_plugins', array( self::class, 'disable_embeds_tinymce' ) );
		add_filter( 'rewrite_rules_array', array( self::class, 'disable_embeds_rewrites' ) );
		remove_filter( 'pre_oembed_result', 'wp_filter_pre_oembed_result', 10 );
	}

	/**
	 * @param mixed $plugins Plugins.
	 * @return mixed
	 */
	public static function disable_embeds_tinymce( $plugins ) {
		return is_array( $plugins ) ? array_diff( $plugins, array( 'wpembed' ) ) : array();
	}

	/**
	 * @param array<string, string> $rules Rewrite rules.
	 * @return array<string, string>
	 */
	public static function disable_embeds_rewrites( array $rules ): array {
		foreach ( $rules as $rule => $rewrite ) {
			if ( is_string( $rewrite ) && false !== strpos( $rewrite, 'embed=true' ) ) {
				unset( $rules[ $rule ] );
			}
		}
		return $rules;
	}

	/**
	 * @param WP_Scripts $scripts Scripts API.
	 */
	public static function maybe_remove_jquery_migrate( $scripts ): void {
		if ( empty( self::opts()['remove_jquery_migrate'] ) || is_admin() ) {
			return;
		}
		if ( ! $scripts instanceof WP_Scripts || ! isset( $scripts->registered['jquery'] ) ) {
			return;
		}
		$item = $scripts->registered['jquery'];
		if ( ! empty( $item->deps ) ) {
			$item->deps = array_diff( $item->deps, array( 'jquery-migrate' ) );
		}
	}

	public static function maybe_remove_shortlink(): void {
		if ( empty( self::opts()['remove_shortlink'] ) ) {
			return;
		}
		remove_action( 'wp_head', 'wp_shortlink_wp_head', 10 );
		remove_action( 'template_redirect', 'wp_shortlink_header', 11 );
	}

	public static function maybe_remove_feed_links(): void {
		if ( empty( self::opts()['remove_feed_links'] ) ) {
			return;
		}
		remove_action( 'wp_head', 'feed_links', 2 );
		remove_action( 'wp_head', 'feed_links_extra', 3 );
	}

	public static function maybe_disable_rss_feeds(): void {
		if ( empty( self::opts()['disable_rss_feeds'] ) ) {
			return;
		}
		if ( ! is_feed() || is_404() ) {
			return;
		}

		if ( isset( $_GET['feed'] ) ) { // phpcs:ignore WordPress.Security.NonceVerification.Recommended
			wp_safe_redirect( esc_url_raw( remove_query_arg( 'feed' ) ), 301 );
			exit;
		}

		if ( get_query_var( 'feed' ) !== 'old' ) {
			set_query_var( 'feed', '' );
		}

		redirect_canonical();

		wp_die(
			wp_kses_post(
				sprintf(
					/* translators: %s: home URL */
					__( 'No feed available. Please visit the <a href="%s">homepage</a>.', 'harden-by-design-by-nh' ),
					esc_url( home_url( '/' ) )
				)
			),
			esc_html__( 'Feed unavailable', 'harden-by-design-by-nh' ),
			array( 'response' => 404 )
		);
	}

	/**
	 * @param array<int, string> $links Ping URLs.
	 */
	public static function maybe_disable_self_pingbacks( array &$links ): void {
		if ( empty( self::opts()['disable_self_pingbacks'] ) ) {
			return;
		}
		$home = (string) get_option( 'home' );
		if ( '' === $home ) {
			return;
		}
		foreach ( $links as $l => $link ) {
			if ( is_string( $link ) && 0 === strpos( $link, $home ) ) {
				unset( $links[ $l ] );
			}
		}
	}

	public static function maybe_remove_rest_api_links(): void {
		if ( empty( self::opts()['remove_rest_api_links'] ) ) {
			return;
		}
		remove_action( 'xmlrpc_rsd_apis', 'rest_output_rsd' );
		remove_action( 'wp_head', 'rest_output_link_wp_head', 10 );
		remove_action( 'template_redirect', 'rest_output_link_header', 11 );
	}

	public static function maybe_start_google_maps_buffer(): void {
		if ( empty( self::opts()['disable_google_maps'] ) || is_admin() ) {
			return;
		}
		ob_start( array( self::class, 'strip_google_maps_scripts' ) );
	}

	/**
	 * @param string|false $html Buffered HTML.
	 * @return string|false
	 */
	public static function strip_google_maps_scripts( $html ) {
		if ( ! is_string( $html ) || '' === $html ) {
			return $html;
		}
		return (string) preg_replace( '/<script[^>]*\/\/maps\.(googleapis|google|gstatic)\.com\/[^>]*><\/script>/i', '', $html );
	}

	public static function maybe_disable_password_strength_meter(): void {
		if ( empty( self::opts()['disable_password_strength_meter'] ) || is_admin() ) {
			return;
		}

		global $pagenow;
		if ( isset( $pagenow ) && 'wp-login.php' === $pagenow ) {
			return;
		}

		if ( isset( $_GET['action'] ) && in_array( sanitize_key( wp_unslash( (string) $_GET['action'] ) ), array( 'rp', 'lostpassword', 'register' ), true ) ) { // phpcs:ignore WordPress.Security.NonceVerification.Recommended
			return;
		}

		if ( class_exists( 'WooCommerce', false ) && function_exists( 'is_account_page' ) && is_account_page() ) {
			return;
		}

		wp_dequeue_script( 'zxcvbn-async' );
		wp_deregister_script( 'zxcvbn-async' );
		wp_dequeue_script( 'password-strength-meter' );
		wp_deregister_script( 'password-strength-meter' );
		wp_dequeue_script( 'wc-password-strength-meter' );
		wp_deregister_script( 'wc-password-strength-meter' );
	}

	public static function maybe_remove_comment_urls(): void {
		if ( empty( self::opts()['remove_comment_urls'] ) ) {
			return;
		}
		add_filter( 'get_comment_author_link', array( self::class, 'comment_author_link_strip' ), 10, 3 );
		add_filter( 'get_comment_author_url', '__return_false' );
		add_filter( 'comment_form_default_fields', array( self::class, 'comment_form_remove_url_field' ), 9999 );
	}

	/**
	 * @param string $return    Link HTML.
	 * @param string $author    Author name.
	 * @param int    $comment_id Comment ID.
	 */
	/**
	 * @param string $return     Original HTML link.
	 * @param string $author     Author name.
	 * @param int|string $comment_id Comment ID.
	 */
	public static function comment_author_link_strip( string $return, string $author, $comment_id ): string { // phpcs:ignore Generic.CodeAnalysis.UnusedFunctionParameter.FoundAfterLastUsed
		unset( $return, $comment_id );
		return $author;
	}

	/**
	 * @param array<string, string> $fields Default fields.
	 * @return array<string, string>
	 */
	public static function comment_form_remove_url_field( array $fields ): array {
		unset( $fields['url'] );
		return $fields;
	}

	public static function maybe_remove_global_styles(): void {
		if ( empty( self::opts()['remove_global_styles'] ) ) {
			return;
		}
		remove_action( 'wp_enqueue_scripts', 'wp_enqueue_global_styles' );
		remove_action( 'wp_footer', 'wp_enqueue_global_styles', 1 );
	}
}
