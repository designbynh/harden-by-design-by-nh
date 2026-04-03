<?php
/**
 * Runtime hardening features.
 *
 * @package HardenByDesignByNH
 */

declare(strict_types=1);

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class Harden_Features
 */
final class Harden_Features {

	public static function init(): void {
		add_action( 'template_redirect', array( self::class, 'maybe_block_public_query_templates' ), 1 );

		add_action( 'init', array( self::class, 'maybe_hide_version' ) );
		add_action( 'admin_init', array( self::class, 'maybe_hide_version_admin' ) );

		add_action( 'admin_init', array( self::class, 'maybe_hide_branding' ) );
		add_action( 'admin_menu', array( self::class, 'maybe_remove_site_editor_menu' ), 999 );
		add_action( 'admin_init', array( self::class, 'maybe_block_site_editor_screen' ) );
		add_action( 'admin_bar_menu', array( self::class, 'maybe_remove_site_editor_admin_bar' ), 999 );
		add_action( 'login_init', array( self::class, 'maybe_block_wp_login' ), 1 );
		add_action( 'login_init', array( self::class, 'maybe_hide_login_branding' ) );
		add_action( 'login_init', array( self::class, 'maybe_hide_login_version' ) );

		add_action( 'init', array( self::class, 'maybe_disable_comments' ), 100 );
		add_action( 'admin_menu', array( self::class, 'maybe_remove_comments_menu' ), 999 );
		add_action( 'admin_menu', array( self::class, 'maybe_remove_posts_admin_menu' ), 99 );
		add_action( 'admin_init', array( self::class, 'maybe_block_posts_admin_access' ) );
		add_action( 'admin_init', array( self::class, 'maybe_block_comments_admin' ) );
		add_action( 'admin_bar_menu', array( self::class, 'maybe_remove_comments_admin_bar' ), 999 );
		add_action( 'admin_bar_menu', array( self::class, 'maybe_remove_posts_admin_bar_nodes' ), 999 );
		add_action( 'admin_menu', array( self::class, 'maybe_remove_blocked_taxonomy_admin_menus' ), 999 );
		add_action( 'admin_init', array( self::class, 'maybe_block_blocked_taxonomy_admin_screens' ) );
		add_action( 'wp_dashboard_setup', array( self::class, 'maybe_remove_comments_dashboard' ) );

		add_action( 'init', array( self::class, 'maybe_disable_application_passwords' ), 1 );
		add_action( 'init', array( self::class, 'maybe_disable_xmlrpc' ), 1 );
		add_action( 'init', array( self::class, 'maybe_register_rest_policy' ), 1 );

		add_action( 'login_enqueue_scripts', array( self::class, 'login_recaptcha_assets' ) );
		add_action( 'login_form', array( self::class, 'login_recaptcha_field' ) );
		add_filter( 'authenticate', array( self::class, 'login_recaptcha_verify' ), 20, 3 );
	}

	/**
	 * @return array<string, mixed>
	 */
	private static function opts(): array {
		return Harden_Options::get();
	}

	/**
	 * Whether a taxonomy’s front-end archives are blocked (Pages tab).
	 */
	private static function taxonomy_archive_admin_blocked( string $taxonomy ): bool {
		if ( '' === $taxonomy || ! taxonomy_exists( $taxonomy ) ) {
			return false;
		}
		$o = self::opts();
		if ( ! empty( $o['disable_all_taxonomy_archives'] ) ) {
			return true;
		}
		$list = isset( $o['disabled_taxonomy_archives'] ) && is_array( $o['disabled_taxonomy_archives'] ) ? $o['disabled_taxonomy_archives'] : array();
		return in_array( $taxonomy, $list, true );
	}

	/**
	 * Remove Categories / Tags (and other taxonomy) submenus when those archives are blocked on the front.
	 */
	public static function maybe_remove_blocked_taxonomy_admin_menus(): void {
		if ( ! is_admin() ) {
			return;
		}
		/**
		 * Keep taxonomy admin screens even when front-end archives are blocked.
		 *
		 * @param bool $hide Whether to remove matching admin submenu items (default true).
		 */
		if ( ! (bool) apply_filters( 'harden_by_nh_hide_blocked_taxonomy_admin_menus', true ) ) {
			return;
		}

		global $submenu;
		if ( ! isset( $submenu ) || ! is_array( $submenu ) ) {
			return;
		}

		$removals = array();
		foreach ( $submenu as $parent_slug => $items ) {
			if ( ! is_string( $parent_slug ) || ! is_array( $items ) ) {
				continue;
			}
			$posts_root = ( 'edit.php' === $parent_slug );
			$cpt_root   = ( 0 === strpos( $parent_slug, 'edit.php?post_type=' ) );
			if ( ! $posts_root && ! $cpt_root ) {
				continue;
			}
			foreach ( $items as $item ) {
				if ( empty( $item[2] ) || ! is_string( $item[2] ) ) {
					continue;
				}
				$child_slug = $item[2];
				if ( 0 !== strpos( $child_slug, 'edit-tags.php' ) ) {
					continue;
				}
				if ( ! preg_match( '/[?&]taxonomy=([^&]+)/', $child_slug, $m ) ) {
					continue;
				}
				$tax = sanitize_key( rawurldecode( $m[1] ) );
				if ( '' === $tax ) {
					continue;
				}
				if ( self::taxonomy_archive_admin_blocked( $tax ) ) {
					$removals[] = array( $parent_slug, $child_slug );
				}
			}
		}

		foreach ( $removals as $pair ) {
			remove_submenu_page( $pair[0], $pair[1] );
		}
	}

	public static function maybe_block_blocked_taxonomy_admin_screens(): void {
		if ( ! is_admin() ) {
			return;
		}
		if ( ! (bool) apply_filters( 'harden_by_nh_hide_blocked_taxonomy_admin_menus', true ) ) {
			return;
		}
		global $pagenow;
		if ( 'edit-tags.php' !== $pagenow || empty( $_GET['taxonomy'] ) ) { // phpcs:ignore WordPress.Security.NonceVerification.Recommended
			return;
		}
		$tax = sanitize_key( wp_unslash( (string) $_GET['taxonomy'] ) ); // phpcs:ignore WordPress.Security.NonceVerification.Recommended
		if ( ! self::taxonomy_archive_admin_blocked( $tax ) ) {
			return;
		}
		wp_safe_redirect( admin_url() );
		exit;
	}

	/**
	 * Return 404 for front-end URLs blocked by Pages tab options (author, tax, archives, singles, etc.).
	 */
	public static function maybe_block_public_query_templates(): void {
		if ( is_admin() || wp_doing_ajax() || wp_doing_cron() ) {
			return;
		}
		if ( function_exists( 'is_customize_preview' ) && is_customize_preview() ) {
			return;
		}

		$o = self::opts();

		if ( ! empty( $o['disable_author_pages'] ) && is_author() ) {
			self::force_404();
			return;
		}

		if ( ! empty( $o['disable_date_archives'] ) && is_date() ) {
			self::force_404();
			return;
		}

		if ( ! empty( $o['disable_all_taxonomy_archives'] ) ) {
			if ( is_category() || is_tag() || is_tax() ) {
				self::force_404();
				return;
			}
		} else {
			$tax_list = isset( $o['disabled_taxonomy_archives'] ) && is_array( $o['disabled_taxonomy_archives'] ) ? $o['disabled_taxonomy_archives'] : array();
			if ( ! empty( $tax_list ) ) {
				if ( is_category() && in_array( 'category', $tax_list, true ) ) {
					self::force_404();
					return;
				}
				if ( is_tag() && in_array( 'post_tag', $tax_list, true ) ) {
					self::force_404();
					return;
				}
				if ( is_tax() ) {
					$tx = get_queried_object();
					if ( $tx && isset( $tx->taxonomy ) && in_array( (string) $tx->taxonomy, $tax_list, true ) ) {
						self::force_404();
						return;
					}
				}
			}
		}

		if ( ! empty( $o['disable_all_post_type_archives'] ) ) {
			if ( is_post_type_archive() ) {
				self::force_404();
				return;
			}
		} else {
			$pta = isset( $o['disabled_post_type_archives'] ) && is_array( $o['disabled_post_type_archives'] ) ? $o['disabled_post_type_archives'] : array();
			if ( ! empty( $pta ) && is_post_type_archive() ) {
				$pt = get_post_type();
				if ( $pt && in_array( $pt, $pta, true ) ) {
					self::force_404();
					return;
				}
			}
		}

		if ( ! empty( $o['disable_blog_index'] ) && is_home() ) {
			self::force_404();
			return;
		}

		$singles = isset( $o['disabled_post_type_singles'] ) && is_array( $o['disabled_post_type_singles'] ) ? $o['disabled_post_type_singles'] : array();
		if ( ! empty( $singles ) && is_singular() && ! is_preview() ) {
			$pt = get_post_type();
			if ( $pt && in_array( $pt, $singles, true ) ) {
				self::force_404();
				return;
			}
		}
	}

	private static function force_404(): void {
		global $wp_query;
		if ( is_object( $wp_query ) && method_exists( $wp_query, 'set_404' ) ) {
			$wp_query->set_404();
		}
		status_header( 404 );
		nocache_headers();
	}

	/**
	 * Built-in types that get menu / admin-bar cleanup when their singles are blocked on the front.
	 *
	 * @return list<string>
	 */
	private static function builtin_types_with_admin_hide(): array {
		return array( 'post', 'page' );
	}

	/**
	 * Whether a post type’s single URLs are blocked (Pages tab).
	 */
	private static function post_type_singles_blocked( string $post_type ): bool {
		$o       = self::opts();
		$singles = isset( $o['disabled_post_type_singles'] ) && is_array( $o['disabled_post_type_singles'] ) ? $o['disabled_post_type_singles'] : array();
		return in_array( $post_type, $singles, true );
	}

	/**
	 * Hide core admin UI for post or page when that type’s public singles are disabled.
	 *
	 * Types stay registered; we only remove menus and block edit screens (same as Posts).
	 */
	private static function should_hide_builtin_type_admin_ui( string $post_type ): bool {
		if ( ! in_array( $post_type, self::builtin_types_with_admin_hide(), true ) ) {
			return false;
		}
		if ( ! self::post_type_singles_blocked( $post_type ) ) {
			return false;
		}
		if ( 'post' === $post_type ) {
			/**
			 * Keep the Posts menu and edit screens even when single post URLs are blocked.
			 *
			 * @param bool $hide Whether to hide Posts in admin (default true when singles are blocked).
			 */
			return (bool) apply_filters( 'harden_by_nh_hide_posts_admin_when_singles_blocked', true );
		}
		/**
		 * Keep the Pages menu and edit screens even when single page URLs are blocked.
		 *
		 * @param bool $hide Whether to hide Pages in admin (default true when singles are blocked).
		 */
		return (bool) apply_filters( 'harden_by_nh_hide_pages_admin_when_singles_blocked', true );
	}

	public static function maybe_remove_posts_admin_menu(): void {
		if ( ! is_admin() ) {
			return;
		}
		if ( self::should_hide_builtin_type_admin_ui( 'post' ) ) {
			remove_menu_page( 'edit.php' );
		}
		if ( self::should_hide_builtin_type_admin_ui( 'page' ) ) {
			remove_menu_page( 'edit.php?post_type=page' );
		}
	}

	public static function maybe_block_posts_admin_access(): void {
		if ( ! is_admin() ) {
			return;
		}

		global $pagenow;

		if ( 'edit.php' === $pagenow ) {
			$pt = isset( $_GET['post_type'] ) ? sanitize_key( wp_unslash( (string) $_GET['post_type'] ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification.Recommended
			if ( ( '' === $pt || 'post' === $pt ) && self::should_hide_builtin_type_admin_ui( 'post' ) ) {
				wp_safe_redirect( admin_url() );
				exit;
			}
			if ( 'page' === $pt && self::should_hide_builtin_type_admin_ui( 'page' ) ) {
				wp_safe_redirect( admin_url() );
				exit;
			}
		}

		if ( 'post-new.php' === $pagenow ) {
			$pt = isset( $_GET['post_type'] ) ? sanitize_key( wp_unslash( (string) $_GET['post_type'] ) ) : 'post'; // phpcs:ignore WordPress.Security.NonceVerification.Recommended
			if ( in_array( $pt, self::builtin_types_with_admin_hide(), true ) && self::should_hide_builtin_type_admin_ui( $pt ) ) {
				wp_safe_redirect( admin_url() );
				exit;
			}
		}

		if ( 'post.php' === $pagenow && isset( $_GET['post'] ) ) { // phpcs:ignore WordPress.Security.NonceVerification.Recommended
			$post_id = (int) $_GET['post'];
			$type    = $post_id > 0 ? get_post_type( $post_id ) : '';
			if ( $type && in_array( $type, self::builtin_types_with_admin_hide(), true ) && self::should_hide_builtin_type_admin_ui( $type ) ) {
				wp_safe_redirect( admin_url() );
				exit;
			}
		}
	}

	/**
	 * @param WP_Admin_Bar $wp_admin_bar Admin bar instance.
	 */
	public static function maybe_remove_posts_admin_bar_nodes( $wp_admin_bar ): void {
		if ( ! is_object( $wp_admin_bar ) || ! method_exists( $wp_admin_bar, 'remove_node' ) ) {
			return;
		}
		if ( self::should_hide_builtin_type_admin_ui( 'post' ) ) {
			$wp_admin_bar->remove_node( 'new-post' );
		}
		if ( self::should_hide_builtin_type_admin_ui( 'page' ) ) {
			$wp_admin_bar->remove_node( 'new-page' );
		}
	}

	/**
	 * Appearance → Editor: block editor (block themes), not the theme file editor.
	 */
	private static function appearance_site_editor_should_disable(): bool {
		if ( empty( self::opts()['disable_appearance_site_editor'] ) ) {
			return false;
		}
		/**
		 * Keep the Site Editor available despite the Harden option (return false).
		 *
		 * @param bool $disable Whether to remove menu and block site-editor.php (default true).
		 */
		return (bool) apply_filters( 'harden_by_nh_disable_appearance_site_editor', true );
	}

	public static function maybe_remove_site_editor_menu(): void {
		if ( ! is_admin() || ! self::appearance_site_editor_should_disable() ) {
			return;
		}
		remove_submenu_page( 'themes.php', 'site-editor.php' );
	}

	public static function maybe_block_site_editor_screen(): void {
		if ( ! is_admin() || ! self::appearance_site_editor_should_disable() ) {
			return;
		}
		global $pagenow;
		if ( 'site-editor.php' === $pagenow ) {
			wp_safe_redirect( admin_url() );
			exit;
		}
	}

	/**
	 * @param WP_Admin_Bar $wp_admin_bar Admin bar instance.
	 */
	public static function maybe_remove_site_editor_admin_bar( $wp_admin_bar ): void {
		if ( ! self::appearance_site_editor_should_disable() ) {
			return;
		}
		if ( is_object( $wp_admin_bar ) && method_exists( $wp_admin_bar, 'remove_node' ) ) {
			$wp_admin_bar->remove_node( 'site-editor' );
		}
	}

	public static function maybe_hide_version(): void {
		if ( empty( self::opts()['hide_wp_version'] ) ) {
			return;
		}

		remove_action( 'wp_head', 'wp_generator' );
		add_filter( 'the_generator', '__return_empty_string' );

		add_filter( 'style_loader_src', array( self::class, 'strip_version_query_arg' ), 20 );
		add_filter( 'script_loader_src', array( self::class, 'strip_version_query_arg' ), 20 );
	}

	public static function maybe_hide_version_admin(): void {
		if ( empty( self::opts()['hide_wp_version'] ) ) {
			return;
		}
		add_filter( 'update_footer', '__return_empty_string', 11 );
	}

	/**
	 * @param string $src Asset URL.
	 * @return string
	 */
	public static function strip_version_query_arg( string $src ): string {
		if ( strpos( $src, 'ver=' ) === false ) {
			return $src;
		}
		return remove_query_arg( 'ver', $src );
	}

	public static function maybe_hide_branding(): void {
		if ( empty( self::opts()['hide_wp_branding'] ) ) {
			return;
		}

		add_action( 'admin_bar_menu', array( self::class, 'remove_wp_admin_bar_logo' ), 999 );
		add_filter( 'admin_footer_text', '__return_empty_string', 20 );
		add_filter( 'update_footer', '__return_empty_string', 20 );
	}

	/**
	 * Block the public login screen on wp-login.php when enabled (Advanced).
	 *
	 * Logged-in users are not blocked (core may redirect). `logout` and `postpass`
	 * stay allowed. Host or SSO login flows can use `harden_by_nh_allow_wp_login_request`.
	 * Additional `action` values: `harden_by_nh_disabled_login_allowed_actions`.
	 */
	public static function maybe_block_wp_login(): void {
		if ( empty( self::opts()['disable_wp_login_page'] ) ) {
			return;
		}

		if ( is_user_logged_in() ) {
			return;
		}

		if ( (bool) apply_filters( 'harden_by_nh_allow_wp_login_request', false ) ) {
			return;
		}

		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Route only; guests have no nonce.
		$action = isset( $_REQUEST['action'] ) ? sanitize_key( wp_unslash( (string) $_REQUEST['action'] ) ) : 'login';
		if ( '' === $action ) {
			$action = 'login';
		}

		$allowed_actions = apply_filters(
			'harden_by_nh_disabled_login_allowed_actions',
			array( 'logout', 'postpass' )
		);
		if ( ! is_array( $allowed_actions ) ) {
			$allowed_actions = array( 'logout', 'postpass' );
		}
		$allowed_actions = array_map(
			static function ( $a ): string {
				return sanitize_key( is_string( $a ) ? $a : (string) $a );
			},
			$allowed_actions
		);

		if ( in_array( $action, $allowed_actions, true ) ) {
			return;
		}

		status_header( 403 );
		wp_die(
			esc_html__( 'The login page is disabled on this site.', 'harden-by-design-by-nh' ),
			esc_html__( 'Forbidden', 'harden-by-design-by-nh' ),
			array( 'response' => 403 )
		);
	}

	public static function maybe_hide_login_branding(): void {
		if ( empty( self::opts()['hide_wp_branding'] ) ) {
			return;
		}

		add_filter( 'login_headerurl', array( self::class, 'login_header_url' ) );
		add_filter( 'login_headertext', array( self::class, 'login_header_text' ) );
		add_filter( 'login_title', array( self::class, 'login_document_title' ), 10, 2 );

		add_action( 'login_enqueue_scripts', array( self::class, 'enqueue_login_branding_css' ), 20 );
	}

	public static function login_header_url(): string {
		return home_url( '/' );
	}

	public static function login_header_text(): string {
		return get_bloginfo( 'name', 'display' );
	}

	/**
	 * Remove trailing "— WordPress" from the login screen &lt;title&gt;.
	 *
	 * @param string $login_title Full title after core formatting.
	 * @param string $title       Original screen title (e.g. Log In); unused but required by filter.
	 */
	public static function login_document_title( string $login_title, string $title ): string {
		unset( $title ); // Passed by apply_filters( 'login_title', $login_title, $title ).
		// Core uses &#8212; (em dash) before "WordPress".
		$login_title = preg_replace( '/\s*(?:&#8212;|&mdash;|—)\s*WordPress\s*$/iu', '', $login_title );
		return $login_title;
	}

	/**
	 * Strip WordPress logo images from login CSS; show link text (site name) instead.
	 */
	public static function enqueue_login_branding_css(): void {
		wp_enqueue_style(
			'harden-by-nh-login-branding',
			HARDEN_BY_NH_URL . 'assets/login-branding.css',
			array( 'login' ),
			HARDEN_BY_NH_VERSION
		);
	}

	public static function maybe_hide_login_version(): void {
		if ( empty( self::opts()['hide_wp_version'] ) ) {
			return;
		}
		remove_action( 'login_head', 'wp_generator' );
	}

	public static function remove_wp_admin_bar_logo(): void {
		global $wp_admin_bar;
		if ( is_object( $wp_admin_bar ) ) {
			$wp_admin_bar->remove_node( 'wp-logo' );
		}
	}

	public static function maybe_disable_comments(): void {
		if ( empty( self::opts()['disable_comments'] ) ) {
			return;
		}

		add_filter( 'comments_open', '__return_false', 20, 2 );
		add_filter( 'pings_open', '__return_false', 20, 2 );
		add_filter( 'comments_array', '__return_empty_array', 10, 2 );

		foreach ( get_post_types( '', 'names' ) as $post_type ) {
			remove_post_type_support( $post_type, 'comments' );
			remove_post_type_support( $post_type, 'trackbacks' );
		}
	}

	public static function maybe_remove_comments_menu(): void {
		if ( empty( self::opts()['disable_comments'] ) || ! is_admin() ) {
			return;
		}
		remove_menu_page( 'edit-comments.php' );
	}

	public static function maybe_block_comments_admin(): void {
		if ( empty( self::opts()['disable_comments'] ) || ! is_admin() ) {
			return;
		}
		global $pagenow;
		if ( 'edit-comments.php' === $pagenow ) {
			wp_safe_redirect( admin_url() );
			exit;
		}
	}

	/**
	 * @param WP_Admin_Bar $wp_admin_bar Admin bar instance.
	 */
	public static function maybe_remove_comments_admin_bar( $wp_admin_bar ): void {
		if ( empty( self::opts()['disable_comments'] ) ) {
			return;
		}
		if ( is_object( $wp_admin_bar ) && method_exists( $wp_admin_bar, 'remove_node' ) ) {
			$wp_admin_bar->remove_node( 'comments' );
		}
	}

	public static function maybe_remove_comments_dashboard(): void {
		if ( empty( self::opts()['disable_comments'] ) ) {
			return;
		}
		remove_meta_box( 'dashboard_recent_comments', 'dashboard', 'normal' );
	}

	public static function maybe_disable_application_passwords(): void {
		if ( empty( self::opts()['disable_application_passwords'] ) ) {
			return;
		}
		add_filter( 'wp_is_application_passwords_available', '__return_false' );
	}

	public static function maybe_disable_xmlrpc(): void {
		if ( empty( self::opts()['disable_xmlrpc'] ) ) {
			return;
		}
		add_filter( 'xmlrpc_enabled', '__return_false' );
		add_filter( 'wp_headers', array( self::class, 'remove_x_pingback_header' ) );
		add_filter( 'pings_open', '__return_false', 9999 );
		add_filter( 'pre_update_option_enable_xmlrpc', '__return_false' );
		add_filter( 'pre_option_enable_xmlrpc', '__return_zero' );
		self::intercept_xmlrpc_request();
	}

	/**
	 * @param array<string, string> $headers Response headers.
	 * @return array<string, string>
	 */
	public static function remove_x_pingback_header( array $headers ): array {
		unset( $headers['X-Pingback'], $headers['x-pingback'] );
		return $headers;
	}

	public static function intercept_xmlrpc_request(): void {
		if ( ! isset( $_SERVER['SCRIPT_FILENAME'] ) ) {
			return;
		}
		$script = wp_basename( wp_unslash( (string) $_SERVER['SCRIPT_FILENAME'] ) );
		if ( 'xmlrpc.php' !== $script ) {
			return;
		}
		status_header( 403 );
		exit( 'Forbidden' );
	}

	public static function maybe_register_rest_policy(): void {
		$policy = isset( self::opts()['rest_api_policy'] ) ? (string) self::opts()['rest_api_policy'] : 'off';
		if ( 'off' === $policy ) {
			return;
		}
		add_filter( 'rest_authentication_errors', array( self::class, 'rest_authentication_errors' ), 100 );
	}

	/**
	 * @param WP_Error|null|bool|mixed $errors Prior REST auth result.
	 * @return WP_Error|null|bool|mixed
	 */
	public static function rest_authentication_errors( $errors ) {
		$policy = isset( self::opts()['rest_api_policy'] ) ? (string) self::opts()['rest_api_policy'] : 'off';
		if ( 'off' === $policy ) {
			return $errors;
		}
		if ( ! empty( $errors ) ) {
			return $errors;
		}

		$route = '';
		if ( isset( $GLOBALS['wp'] ) && is_object( $GLOBALS['wp'] ) && isset( $GLOBALS['wp']->query_vars['rest_route'] ) ) {
			$route = (string) $GLOBALS['wp']->query_vars['rest_route'];
		}

		if ( '' !== $route ) {
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
				$ex = (string) $ex;
				if ( '' !== $ex && false !== strpos( $route, $ex ) ) {
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
			return new WP_Error(
				'rest_forbidden',
				__( 'You do not have permission to access the REST API.', 'harden-by-design-by-nh' ),
				array( 'status' => 401 )
			);
		}

		return $errors;
	}

	public static function login_recaptcha_assets(): void {
		$o = self::opts();
		if ( empty( $o['recaptcha_enabled'] ) || ! self::recaptcha_configured( $o ) ) {
			return;
		}

		$site_key = (string) $o['recaptcha_site_key'];
		$version  = isset( $o['recaptcha_version'] ) ? (string) $o['recaptcha_version'] : 'v3';

		if ( 'v3' === $version ) {
			wp_enqueue_script(
				'harden-recaptcha-v3',
				'https://www.google.com/recaptcha/api.js?render=' . rawurlencode( $site_key ),
				array(),
				null,
				true
			);

			$inline = sprintf(
				'(function(){var f=document.getElementById("loginform");if(!f)return;f.addEventListener("submit",function(e){if(f.dataset.hardenRecaptchaDone)return;e.preventDefault();var i=document.getElementById("harden_recaptcha_token");if(!i||!window.grecaptcha)return;grecaptcha.ready(function(){grecaptcha.execute(%s,{action:"login"}).then(function(t){i.value=t;f.dataset.hardenRecaptchaDone="1";f.submit();});});});})();',
				wp_json_encode( $site_key )
			);
			wp_add_inline_script( 'harden-recaptcha-v3', $inline, 'after' );
		} else {
			wp_register_script( 'harden-recaptcha-v2-inline', false, array(), null, true );
			wp_enqueue_script( 'harden-recaptcha-v2-inline' );
			wp_add_inline_script(
				'harden-recaptcha-v2-inline',
				sprintf(
					'function hardenRecaptchaOnload(){if(typeof grecaptcha!==\'undefined\'){grecaptcha.render("harden-recaptcha-widget",{sitekey:%s});}}',
					wp_json_encode( $site_key )
				)
			);
			wp_enqueue_script(
				'harden-recaptcha-v2',
				'https://www.google.com/recaptcha/api.js?onload=hardenRecaptchaOnload&render=explicit',
				array( 'harden-recaptcha-v2-inline' ),
				null,
				true
			);
			wp_script_add_data( 'harden-recaptcha-v2', 'async', true );
			wp_script_add_data( 'harden-recaptcha-v2', 'defer', true );
		}
	}

	public static function login_recaptcha_field(): void {
		$o = self::opts();
		if ( empty( $o['recaptcha_enabled'] ) || ! self::recaptcha_configured( $o ) ) {
			return;
		}

		$version = isset( $o['recaptcha_version'] ) ? (string) $o['recaptcha_version'] : 'v3';
		wp_nonce_field( 'harden_recaptcha_login', 'harden_recaptcha_nonce' );

		if ( 'v3' === $version ) {
			echo '<input type="hidden" name="harden_recaptcha_token" id="harden_recaptcha_token" value="" />';
		} else {
			echo '<div id="harden-recaptcha-widget" class="harden-recaptcha-v2"></div>';
		}
	}

	/**
	 * @param WP_User|WP_Error|null $user     User or error.
	 * @param string                 $username Login.
	 * @param string                 $password Password.
	 * @return WP_User|WP_Error|null
	 */
	public static function login_recaptcha_verify( $user, string $username, string $password ) { // phpcs:ignore Generic.CodeAnalysis.UnusedFunctionParameter.FoundAfterLastUsed
		$o = self::opts();
		if ( empty( $o['recaptcha_enabled'] ) || ! self::recaptcha_configured( $o ) ) {
			return $user;
		}

		// Only enforce on the standard wp-login.php form (avoid breaking XML-RPC, app passwords, etc.).
		if ( ! isset( $_POST['log'], $_POST['pwd'] ) ) {
			return $user;
		}

		if ( ! isset( $_POST['harden_recaptcha_nonce'] ) || ! wp_verify_nonce( sanitize_text_field( wp_unslash( (string) $_POST['harden_recaptcha_nonce'] ) ), 'harden_recaptcha_login' ) ) {
			return new WP_Error( 'harden_recaptcha_nonce', __( 'Login verification failed. Please try again.', 'harden-by-design-by-nh' ) );
		}

		$secret = (string) $o['recaptcha_secret_key'];
		$version = isset( $o['recaptcha_version'] ) ? (string) $o['recaptcha_version'] : 'v3';

		$response = '';
		if ( 'v3' === $version ) {
			$response = isset( $_POST['harden_recaptcha_token'] ) ? sanitize_text_field( wp_unslash( (string) $_POST['harden_recaptcha_token'] ) ) : '';
		} else {
			$response = isset( $_POST['g-recaptcha-response'] ) ? sanitize_text_field( wp_unslash( (string) $_POST['g-recaptcha-response'] ) ) : '';
		}

		if ( '' === $response ) {
			return new WP_Error( 'harden_recaptcha_missing', __( 'Please complete the reCAPTCHA verification.', 'harden-by-design-by-nh' ) );
		}

		$verify = wp_remote_post(
			'https://www.google.com/recaptcha/api/siteverify',
			array(
				'timeout' => 10,
				'body'    => array(
					'secret'   => $secret,
					'response' => $response,
					'remoteip' => isset( $_SERVER['REMOTE_ADDR'] ) ? sanitize_text_field( wp_unslash( (string) $_SERVER['REMOTE_ADDR'] ) ) : '',
				),
			)
		);

		if ( is_wp_error( $verify ) ) {
			return new WP_Error( 'harden_recaptcha_http', __( 'Could not verify login. Try again shortly.', 'harden-by-design-by-nh' ) );
		}

		$code = wp_remote_retrieve_response_code( $verify );
		$body = json_decode( (string) wp_remote_retrieve_body( $verify ), true );
		if ( 200 !== $code || ! is_array( $body ) || empty( $body['success'] ) ) {
			return new WP_Error( 'harden_recaptcha_invalid', __( 'reCAPTCHA verification failed.', 'harden-by-design-by-nh' ) );
		}

		if ( 'v3' === $version ) {
			$score = isset( $body['score'] ) ? (float) $body['score'] : 0.0;
			if ( $score < 0.5 ) {
				return new WP_Error( 'harden_recaptcha_score', __( 'Login blocked by security check.', 'harden-by-design-by-nh' ) );
			}
			$action = isset( $body['action'] ) ? (string) $body['action'] : '';
			if ( '' !== $action && 'login' !== $action ) {
				return new WP_Error( 'harden_recaptcha_action', __( 'reCAPTCHA verification failed.', 'harden-by-design-by-nh' ) );
			}
		}

		return $user;
	}

	/**
	 * @param array<string, mixed> $o Options.
	 */
	private static function recaptcha_configured( array $o ): bool {
		return '' !== trim( (string) $o['recaptcha_site_key'] ) && '' !== trim( (string) $o['recaptcha_secret_key'] );
	}
}
