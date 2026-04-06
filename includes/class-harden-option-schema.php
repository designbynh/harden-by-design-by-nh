<?php
/**
 * Declarative option schema — single source of truth for every setting.
 *
 * Adding a new toggle means adding one entry here. The schema drives defaults,
 * sanitisation, AJAX allow-lists, tab rendering, and human-readable copy.
 *
 * @package HardenByDesignByNH
 */

declare(strict_types=1);

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

final class Harden_Option_Schema {

	/**
	 * Option types recognised by prepare_storage / get.
	 *
	 *  bool      – boolean toggle (AJAX switch)
	 *  enum      – string from a fixed list (values key required)
	 *  string    – free-text, sanitize_text_field
	 *  slug_list – list<string> of sanitised slugs
	 *  derived   – computed at read time, never stored directly
	 */

	/** @var array<string, array<string, mixed>>|null */
	private static ?array $cache = null;

	/**
	 * Full schema keyed by option name.
	 *
	 * @return array<string, array<string, mixed>>
	 */
	public static function all(): array {
		if ( null !== self::$cache ) {
			return self::$cache;
		}

		$s = array(

			// ── Pages tab ────────────────────────────────────────────────
			'disable_author_pages' => array(
				'type'        => 'bool',
				'default'     => false,
				'preset'      => true,
				'tab'         => 'pages',
				'group'       => 'url_blocking',
				'ajax'        => true,
				'label'       => __( 'Block author archives', 'harden-by-design-by-nh' ),
				'description' => __( 'Returns a 404 for /author/username URLs, preventing easy username enumeration.', 'harden-by-design-by-nh' ),
			),
			'disable_all_taxonomy_archives' => array(
				'type'        => 'bool',
				'default'     => false,
				'tab'         => 'pages',
				'group'       => 'url_blocking',
				'ajax'        => true,
				'label'       => __( 'Block all taxonomy archives', 'harden-by-design-by-nh' ),
				'description' => __( 'Returns a 404 for every category, tag, and custom taxonomy archive page. Use the list below to block individual taxonomies instead.', 'harden-by-design-by-nh' ),
			),
			'disabled_taxonomy_archives' => array(
				'type'    => 'slug_list',
				'default' => array(),
				'preset'  => array( 'post_tag' ),
				'tab'     => 'pages',
				'group'   => 'url_blocking',
			),
			'disable_all_post_type_archives' => array(
				'type'        => 'bool',
				'default'     => false,
				'tab'         => 'pages',
				'group'       => 'url_blocking',
				'ajax'        => true,
				'label'       => __( 'Block all post type archives', 'harden-by-design-by-nh' ),
				'description' => __( 'Returns a 404 for every custom post type archive URL (does not affect the main blog index).', 'harden-by-design-by-nh' ),
			),
			'disabled_post_type_archives' => array(
				'type'    => 'slug_list',
				'default' => array(),
				'tab'     => 'pages',
				'group'   => 'url_blocking',
			),
			'disabled_post_type_singles' => array(
				'type'    => 'slug_list',
				'default' => array(),
				'tab'     => 'pages',
				'group'   => 'url_blocking',
			),
			'disable_blog_index' => array(
				'type'        => 'bool',
				'default'     => false,
				'tab'         => 'pages',
				'group'       => 'url_blocking',
				'ajax'        => true,
				'label'       => __( 'Block blog index', 'harden-by-design-by-nh' ),
				'description' => __( 'Returns a 404 for the main posts listing page. Static front pages still load normally.', 'harden-by-design-by-nh' ),
			),
			'disable_date_archives' => array(
				'type'        => 'bool',
				'default'     => false,
				'preset'      => true,
				'tab'         => 'pages',
				'group'       => 'url_blocking',
				'ajax'        => true,
				'label'       => __( 'Block date archives', 'harden-by-design-by-nh' ),
				'description' => __( 'Returns a 404 for year, month, and day archive URLs.', 'harden-by-design-by-nh' ),
			),

			// ── Advanced tab – Security card ─────────────────────────────
			'hide_wp_version' => array(
				'type'        => 'bool',
				'default'     => false,
				'preset'      => true,
				'tab'         => 'advanced',
				'group'       => 'security',
				'ajax'        => true,
				'label'       => __( 'Hide WordPress version', 'harden-by-design-by-nh' ),
				'description' => __( 'Removes version numbers from page source, scripts, and the admin footer so attackers cannot target a specific release.', 'harden-by-design-by-nh' ),
			),
			'disable_wp_login_page' => array(
				'type'        => 'bool',
				'default'     => false,
				'tab'         => 'login',
				'group'       => 'login_protection',
				'ajax'        => true,
				'label'       => __( 'Block public login page', 'harden-by-design-by-nh' ),
				'description' => __( 'Guests see a 403 on wp-login.php. Logout and password-protected post actions still work. Enabling this also turns on the rescue link and creates a new secret URL. Turning it off clears the rescue link.', 'harden-by-design-by-nh' ),
			),
			'login_rescue_enabled' => array(
				'type'        => 'bool',
				'default'     => true,
				'tab'         => 'login',
				'group'       => 'login_rescue',
				'ajax'        => true,
				'label'       => __( 'Enable rescue link', 'harden-by-design-by-nh' ),
				'description' => __( 'When off, the secret URL is removed and rescue requests are ignored. Each time you turn this on, a new URL is created. The link works only once per URL.', 'harden-by-design-by-nh' ),
			),
			'login_rescue_token' => array(
				'type'    => 'string',
				'default' => '',
				'secret'  => true,
				'tab'     => 'login',
				'group'   => 'login_rescue',
			),
			'disable_xmlrpc' => array(
				'type'        => 'bool',
				'default'     => false,
				'tab'         => 'advanced',
				'group'       => 'security',
				'ajax'        => true,
				'label'       => __( 'Turn off XML-RPC', 'harden-by-design-by-nh' ),
				'description' => __( 'Blocks the legacy remote publishing endpoint. Disable this if you use Jetpack or apps that publish via XML-RPC.', 'harden-by-design-by-nh' ),
			),
			'rest_api_policy' => array(
				'type'    => 'enum',
				'values'  => array( 'off', 'guests', 'non_admins' ),
				'default' => 'off',
				'preset'  => 'guests',
				'tab'     => 'advanced',
				'group'   => 'security',
				'label'   => __( 'REST API access', 'harden-by-design-by-nh' ),
				'description' => __( 'Restrict who can use the REST API. Plugin routes are exempt when they match a known namespace (filterable).', 'harden-by-design-by-nh' ),
			),
			'rest_block_users_endpoint' => array(
				'type'        => 'bool',
				'default'     => true,
				'tab'         => 'advanced',
				'group'       => 'security',
				'ajax'        => true,
				'label'       => __( 'Block public user list API', 'harden-by-design-by-nh' ),
				'description' => __( 'Prevents visitors and non-admin users from listing usernames through the REST API. Your own profile endpoint (/users/me) still works.', 'harden-by-design-by-nh' ),
			),
			'enable_security_headers' => array(
				'type'        => 'bool',
				'default'     => false,
				'tab'         => 'advanced',
				'group'       => 'security',
				'ajax'        => true,
				'label'       => __( 'Add security headers', 'harden-by-design-by-nh' ),
				'description' => __( 'Sends browser security headers (clickjacking protection, content-type enforcement, referrer policy) and removes the X-Powered-By header.', 'harden-by-design-by-nh' ),
			),
			'disable_application_passwords' => array(
				'type'        => 'bool',
				'default'     => false,
				'tab'         => 'advanced',
				'group'       => 'security',
				'ajax'        => true,
				'label'       => __( 'Disable application passwords', 'harden-by-design-by-nh' ),
				'description' => __( 'Removes application password support from user profiles. Third-party apps that rely on them will stop working.', 'harden-by-design-by-nh' ),
			),

			// ── Advanced tab – Admin interface card ───────────────────────
			'hide_wp_branding' => array(
				'type'        => 'bool',
				'default'     => false,
				'preset'      => true,
				'tab'         => 'advanced',
				'group'       => 'admin_interface',
				'ajax'        => true,
				'label'       => __( 'Reduce WordPress branding', 'harden-by-design-by-nh' ),
				'description' => __( 'Replaces the admin bar logo, footer credit, and login screen logo with your site name.', 'harden-by-design-by-nh' ),
			),
			'disallow_file_edit' => array(
				'type'        => 'bool',
				'default'     => false,
				'preset'      => true,
				'tab'         => 'advanced',
				'group'       => 'admin_interface',
				'ajax'        => true,
				'label'       => __( 'Lock file editor', 'harden-by-design-by-nh' ),
				'description' => __( 'Prevents editing theme and plugin files from the WordPress admin. Mirrors the DISALLOW_FILE_EDIT constant.', 'harden-by-design-by-nh' ),
			),
			'disable_appearance_site_editor' => array(
				'type'        => 'bool',
				'default'     => false,
				'preset'      => true,
				'tab'         => 'advanced',
				'group'       => 'admin_interface',
				'ajax'        => true,
				'label'       => __( 'Hide Site Editor', 'harden-by-design-by-nh' ),
				'description' => __( 'Removes the full-site block editor from the Appearance menu and blocks direct access to site-editor.php.', 'harden-by-design-by-nh' ),
			),

			// ── Advanced tab – Site features card ─────────────────────────
			'disable_comments' => array(
				'type'        => 'bool',
				'default'     => false,
				'preset'      => true,
				'tab'         => 'advanced',
				'group'       => 'site_features',
				'ajax'        => true,
				'label'       => __( 'Disable comments', 'harden-by-design-by-nh' ),
				'description' => __( 'Closes comments site-wide, removes comment support from all post types, and hides the Comments menu.', 'harden-by-design-by-nh' ),
			),

			// ── Frontend tab – Scripts & assets card ─────────────────────
			'disable_emojis' => array(
				'type'        => 'bool',
				'default'     => false,
				'preset'      => true,
				'tab'         => 'frontend',
				'group'       => 'scripts',
				'ajax'        => true,
				'label'       => __( 'Remove emoji scripts', 'harden-by-design-by-nh' ),
				'description' => __( 'Stops WordPress from loading its emoji detection scripts and styles. Native browser emoji still works.', 'harden-by-design-by-nh' ),
			),
			'disable_dashicons' => array(
				'type'        => 'bool',
				'default'     => false,
				'preset'      => true,
				'tab'         => 'frontend',
				'group'       => 'scripts',
				'ajax'        => true,
				'label'       => __( 'Remove Dashicons for visitors', 'harden-by-design-by-nh' ),
				'description' => __( 'Dequeues Dashicons for logged-out visitors. Logged-in users keep them for the admin bar.', 'harden-by-design-by-nh' ),
			),
			'disable_embeds' => array(
				'type'        => 'bool',
				'default'     => false,
				'preset'      => true,
				'tab'         => 'frontend',
				'group'       => 'scripts',
				'ajax'        => true,
				'label'       => __( 'Disable oEmbed', 'harden-by-design-by-nh' ),
				'description' => __( 'Turns off oEmbed discovery, the wp-embed script, and related rewrite rules.', 'harden-by-design-by-nh' ),
			),
			'remove_jquery_migrate' => array(
				'type'        => 'bool',
				'default'     => false,
				'tab'         => 'frontend',
				'group'       => 'scripts',
				'ajax'        => true,
				'label'       => __( 'Drop jQuery Migrate', 'harden-by-design-by-nh' ),
				'description' => __( 'Removes the backward-compatibility shim for older jQuery code on the front end. Test your theme and plugins after enabling.', 'harden-by-design-by-nh' ),
			),
			'remove_global_styles' => array(
				'type'        => 'bool',
				'default'     => false,
				'tab'         => 'frontend',
				'group'       => 'scripts',
				'ajax'        => true,
				'label'       => __( 'Remove global styles', 'harden-by-design-by-nh' ),
				'description' => __( 'Stops WordPress from enqueueing theme.json global styles. May affect block theme styling — test carefully.', 'harden-by-design-by-nh' ),
			),

			// ── Frontend tab – Feeds & head metadata card ────────────────
			'remove_shortlink' => array(
				'type'        => 'bool',
				'default'     => false,
				'preset'      => true,
				'tab'         => 'frontend',
				'group'       => 'feeds',
				'ajax'        => true,
				'label'       => __( 'Remove shortlink', 'harden-by-design-by-nh' ),
				'description' => __( 'Removes the shortlink HTTP header and the shortlink tag from the HTML head.', 'harden-by-design-by-nh' ),
			),
			'disable_rss_feeds' => array(
				'type'        => 'bool',
				'default'     => false,
				'preset'      => true,
				'tab'         => 'frontend',
				'group'       => 'feeds',
				'ajax'        => true,
				'label'       => __( 'Disable RSS feeds', 'harden-by-design-by-nh' ),
				'description' => __( 'Blocks all feed URLs and shows a "no feed" message. Combine with "Remove feed links" to clean up the head.', 'harden-by-design-by-nh' ),
			),
			'remove_feed_links' => array(
				'type'        => 'bool',
				'default'     => false,
				'preset'      => true,
				'tab'         => 'frontend',
				'group'       => 'feeds',
				'ajax'        => true,
				'label'       => __( 'Remove feed links', 'harden-by-design-by-nh' ),
				'description' => __( 'Removes RSS/Atom feed discovery tags from the HTML head (does not disable feeds by itself).', 'harden-by-design-by-nh' ),
			),
			'disable_self_pingbacks' => array(
				'type'        => 'bool',
				'default'     => false,
				'preset'      => true,
				'tab'         => 'frontend',
				'group'       => 'feeds',
				'ajax'        => true,
				'label'       => __( 'Disable self-pingbacks', 'harden-by-design-by-nh' ),
				'description' => __( 'Stops WordPress from sending pingbacks to your own site when you link to your own posts.', 'harden-by-design-by-nh' ),
			),
			'remove_rest_api_links' => array(
				'type'        => 'bool',
				'default'     => false,
				'preset'      => true,
				'tab'         => 'frontend',
				'group'       => 'feeds',
				'ajax'        => true,
				'label'       => __( 'Remove REST API links', 'harden-by-design-by-nh' ),
				'description' => __( 'Removes the REST API link tag and header from the HTML head. Does not disable the API itself.', 'harden-by-design-by-nh' ),
			),

			// ── Frontend tab – Other card ────────────────────────────────
			'disable_google_maps' => array(
				'type'        => 'bool',
				'default'     => false,
				'tab'         => 'frontend',
				'group'       => 'other',
				'ajax'        => true,
				'label'       => __( 'Block Google Maps', 'harden-by-design-by-nh' ),
				'description' => __( 'Attempts to strip Google Maps script tags from page output. Dynamically injected maps may not be caught.', 'harden-by-design-by-nh' ),
			),
			'disable_password_strength_meter' => array(
				'type'        => 'bool',
				'default'     => false,
				'preset'      => true,
				'tab'         => 'frontend',
				'group'       => 'other',
				'ajax'        => true,
				'label'       => __( 'Remove password strength meter', 'harden-by-design-by-nh' ),
				'description' => __( 'Dequeues the zxcvbn and password-strength scripts on the front end. Login and WooCommerce account pages are not affected.', 'harden-by-design-by-nh' ),
			),
			'remove_comment_urls' => array(
				'type'        => 'bool',
				'default'     => false,
				'preset'      => true,
				'tab'         => 'frontend',
				'group'       => 'other',
				'ajax'        => true,
				'label'       => __( 'Strip comment author URLs', 'harden-by-design-by-nh' ),
				'description' => __( 'Removes the website link from comment author names and hides the URL field on the comment form.', 'harden-by-design-by-nh' ),
			),

			// ── Login tab ────────────────────────────────────────────────
			'login_protection_provider' => array(
				'type'    => 'enum',
				'values'  => array( 'none', 'recaptcha_v2', 'recaptcha_v3', 'turnstile' ),
				'default' => 'none',
				'tab'     => 'login',
				'group'   => 'login_protection',
				'label'   => __( 'Login challenge', 'harden-by-design-by-nh' ),
				'description' => __( 'Adds a bot challenge on wp-login.php. XML-RPC and application password logins are not affected.', 'harden-by-design-by-nh' ),
			),
			'recaptcha_site_key' => array(
				'type'    => 'string',
				'default' => '',
				'tab'     => 'login',
				'group'   => 'login_protection',
			),
			'recaptcha_secret_key' => array(
				'type'    => 'string',
				'default' => '',
				'tab'     => 'login',
				'group'   => 'login_protection',
				'secret'  => true,
			),
			'recaptcha_v3_score_threshold' => array(
				'type'    => 'float',
				'default' => 0.5,
				'min'     => 0.0,
				'max'     => 1.0,
				'tab'     => 'login',
				'group'   => 'login_protection',
				'label'   => __( 'Score threshold', 'harden-by-design-by-nh' ),
				'description' => __( 'Requests scoring below this value are blocked. Google recommends 0.5. Lower values are more lenient; higher values are stricter.', 'harden-by-design-by-nh' ),
			),
			'turnstile_site_key' => array(
				'type'    => 'string',
				'default' => '',
				'tab'     => 'login',
				'group'   => 'login_protection',
			),
			'turnstile_secret_key' => array(
				'type'    => 'string',
				'default' => '',
				'tab'     => 'login',
				'group'   => 'login_protection',
				'secret'  => true,
			),

			// ── Notifications tab – Dashboard & admin bar card ───────────
			'hide_notice_wp_core_nag' => array(
				'type'        => 'bool',
				'default'     => false,
				'tab'         => 'notifications',
				'group'       => 'dashboard',
				'ajax'        => true,
				'label'       => __( 'Hide core upgrade banner', 'harden-by-design-by-nh' ),
				'description' => __( 'Hides the "WordPress X.Y is available" notice for everyone except Administrators.', 'harden-by-design-by-nh' ),
			),
			'hide_notice_updates_admin_bar' => array(
				'type'        => 'bool',
				'default'     => false,
				'tab'         => 'notifications',
				'group'       => 'dashboard',
				'ajax'        => true,
				'label'       => __( 'Hide admin bar Updates', 'harden-by-design-by-nh' ),
				'description' => __( 'Removes the Updates toolbar item for non-administrator users.', 'harden-by-design-by-nh' ),
			),
			'hide_notice_plugins_menu_count' => array(
				'type'        => 'bool',
				'default'     => false,
				'tab'         => 'notifications',
				'group'       => 'dashboard',
				'ajax'        => true,
				'label'       => __( 'Hide Plugins update count', 'harden-by-design-by-nh' ),
				'description' => __( 'Removes the numbered badge next to Plugins in the sidebar for non-administrators.', 'harden-by-design-by-nh' ),
			),
			'hide_notice_themes_menu_count' => array(
				'type'        => 'bool',
				'default'     => false,
				'tab'         => 'notifications',
				'group'       => 'dashboard',
				'ajax'        => true,
				'label'       => __( 'Hide Themes update count', 'harden-by-design-by-nh' ),
				'description' => __( 'Removes the update badge next to Appearance / Themes for non-administrators.', 'harden-by-design-by-nh' ),
			),
			'hide_notice_dashboard_updates_submenu' => array(
				'type'        => 'bool',
				'default'     => false,
				'tab'         => 'notifications',
				'group'       => 'dashboard',
				'ajax'        => true,
				'label'       => __( 'Hide Dashboard Updates count', 'harden-by-design-by-nh' ),
				'description' => __( 'Removes the update count from the Dashboard > Updates submenu for non-administrators.', 'harden-by-design-by-nh' ),
			),

			// ── Notifications tab – Email card ───────────────────────────
			'hide_notice_core_update_emails' => array(
				'type'        => 'bool',
				'default'     => false,
				'tab'         => 'notifications',
				'group'       => 'email',
				'ajax'        => true,
				'label'       => __( 'Stop core update notification email', 'harden-by-design-by-nh' ),
				'description' => __( 'Prevents the email WordPress sends after some automatic core background updates.', 'harden-by-design-by-nh' ),
			),
			'hide_notice_core_auto_update_result_emails' => array(
				'type'        => 'bool',
				'default'     => false,
				'tab'         => 'notifications',
				'group'       => 'email',
				'ajax'        => true,
				'label'       => __( 'Stop core auto-update result emails', 'harden-by-design-by-nh' ),
				'description' => __( 'Prevents success, failure, and critical emails from automatic core updates.', 'harden-by-design-by-nh' ),
			),
			'hide_notice_plugin_auto_update_emails' => array(
				'type'        => 'bool',
				'default'     => false,
				'tab'         => 'notifications',
				'group'       => 'email',
				'ajax'        => true,
				'label'       => __( 'Stop plugin auto-update emails', 'harden-by-design-by-nh' ),
				'description' => __( 'Prevents emails after automatic plugin background updates.', 'harden-by-design-by-nh' ),
			),
			'hide_notice_theme_auto_update_emails' => array(
				'type'        => 'bool',
				'default'     => false,
				'tab'         => 'notifications',
				'group'       => 'email',
				'ajax'        => true,
				'label'       => __( 'Stop theme auto-update emails', 'harden-by-design-by-nh' ),
				'description' => __( 'Prevents emails after automatic theme background updates.', 'harden-by-design-by-nh' ),
			),
			'hide_notice_auto_updates_debug_emails' => array(
				'type'        => 'bool',
				'default'     => false,
				'tab'         => 'notifications',
				'group'       => 'email',
				'ajax'        => true,
				'label'       => __( 'Stop auto-update debug emails', 'harden-by-design-by-nh' ),
				'description' => __( 'Prevents the extra debug email WordPress can send for development installs after background updates.', 'harden-by-design-by-nh' ),
			),

			// ── Integrations tab ─────────────────────────────────────────
			'integrate_wp_core_sitemap' => array(
				'type'        => 'bool',
				'default'     => true,
				'tab'         => 'integrations',
				'group'       => 'seo',
				'ajax'        => true,
				'label'       => __( 'Sync WordPress sitemap', 'harden-by-design-by-nh' ),
				'description' => __( 'Automatically removes blocked URLs from the built-in WordPress sitemap (wp-sitemap.xml).', 'harden-by-design-by-nh' ),
			),
			'integrate_slim_seo_sitemap' => array(
				'type'        => 'bool',
				'default'     => false,
				'tab'         => 'integrations',
				'group'       => 'seo',
				'ajax'        => true,
				'label'       => __( 'Slim SEO sitemap sync', 'harden-by-design-by-nh' ),
				'description' => __( 'Aligns Slim SEO XML sitemaps with your Public URL blocking settings.', 'harden-by-design-by-nh' ),
			),

			// ── Derived / legacy (computed, not directly stored) ─────────
			'recaptcha_enabled' => array(
				'type'    => 'derived',
				'default' => false,
			),
			'recaptcha_version' => array(
				'type'    => 'derived',
				'default' => 'v3',
			),
		);

		/**
		 * Register additional option keys into the HardenWP schema.
		 *
		 * @param array<string, array<string, mixed>> $schema Schema entries.
		 */
		self::$cache = (array) apply_filters( 'harden_by_nh_option_schema', $s );

		return self::$cache;
	}

	// ── Query helpers ────────────────────────────────────────────────────

	/**
	 * @return array<string, mixed>
	 */
	public static function defaults(): array {
		$out = array();
		foreach ( self::all() as $key => $def ) {
			$out[ $key ] = $def['default'];
		}
		return $out;
	}

	/**
	 * Keys whose type is 'bool' and that are AJAX-switchable.
	 *
	 * @return list<string>
	 */
	public static function boolean_ajax_keys(): array {
		$out = array();
		foreach ( self::all() as $key => $def ) {
			if ( 'bool' === ( $def['type'] ?? '' ) && ! empty( $def['ajax'] ) ) {
				$out[] = $key;
			}
		}
		return $out;
	}

	/**
	 * All boolean keys (for type-casting in get/prepare_storage).
	 *
	 * @return list<string>
	 */
	public static function boolean_keys(): array {
		$out = array();
		foreach ( self::all() as $key => $def ) {
			if ( 'bool' === ( $def['type'] ?? '' ) ) {
				$out[] = $key;
			}
		}
		return $out;
	}

	/**
	 * @return list<string>
	 */
	public static function slug_list_keys(): array {
		$out = array();
		foreach ( self::all() as $key => $def ) {
			if ( 'slug_list' === ( $def['type'] ?? '' ) ) {
				$out[] = $key;
			}
		}
		return $out;
	}

	/**
	 * @return list<string>
	 */
	public static function string_keys(): array {
		$out = array();
		foreach ( self::all() as $key => $def ) {
			if ( 'string' === ( $def['type'] ?? '' ) ) {
				$out[] = $key;
			}
		}
		return $out;
	}

	/**
	 * @return list<string>
	 */
	public static function secret_keys(): array {
		$out = array();
		foreach ( self::all() as $key => $def ) {
			if ( ! empty( $def['secret'] ) ) {
				$out[] = $key;
			}
		}
		return $out;
	}

	/**
	 * Keys for a given tab (optionally filtered by group).
	 *
	 * @return list<string>
	 */
	public static function keys_for_tab( string $tab, string $group = '' ): array {
		$out = array();
		foreach ( self::all() as $key => $def ) {
			if ( ( $def['tab'] ?? '' ) !== $tab ) {
				continue;
			}
			if ( '' !== $group && ( $def['group'] ?? '' ) !== $group ) {
				continue;
			}
			$out[] = $key;
		}
		return $out;
	}

	/**
	 * Schema entries for a given tab + group that have labels (renderable toggles).
	 *
	 * @return array<string, array<string, mixed>>
	 */
	public static function labeled_entries_for( string $tab, string $group ): array {
		$out = array();
		foreach ( self::all() as $key => $def ) {
			if ( ( $def['tab'] ?? '' ) !== $tab || ( $def['group'] ?? '' ) !== $group ) {
				continue;
			}
			if ( ! isset( $def['label'] ) ) {
				continue;
			}
			$out[ $key ] = $def;
		}
		return $out;
	}

	/**
	 * Enum values for a given key, or empty array.
	 *
	 * @return list<string>
	 */
	public static function enum_values( string $key ): array {
		$def = self::all()[ $key ] ?? null;
		if ( null === $def || 'enum' !== ( $def['type'] ?? '' ) ) {
			return array();
		}
		return $def['values'] ?? array();
	}

	/**
	 * First-activation preset: defaults overridden by preset values.
	 *
	 * @return array<string, mixed>
	 */
	public static function first_activation_preset(): array {
		$out = self::defaults();
		foreach ( self::all() as $key => $def ) {
			if ( array_key_exists( 'preset', $def ) ) {
				$out[ $key ] = $def['preset'];
			}
		}
		return $out;
	}

	/**
	 * @return list<string>
	 */
	public static function all_stored_keys(): array {
		$out = array();
		foreach ( self::all() as $key => $def ) {
			if ( 'derived' !== ( $def['type'] ?? '' ) ) {
				$out[] = $key;
			}
		}
		return $out;
	}
}
