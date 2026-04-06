<?php
/**
 * Plugin Name:       HardenWP
 * Plugin URI:        https://designbynh.com
 * Description:       Security hardening for WordPress (by Design by NH): modular login bot protection, public URL blocking, frontend cleanup, and reduced version/branding exposure.
 * Version:           2.0.2
 * Requires at least: 6.0
 * Requires PHP:      7.4
 * Author:            Design by NH
 * Author URI:        https://designbynh.com
 * License:           GPL-2.0-or-later
 * License URI:       https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain:       harden-by-design-by-nh
 * Update URI:        https://github.com/designbynh/harden-by-design-by-nh
 *
 * @package HardenByDesignByNH
 */

declare(strict_types=1);

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

define( 'HARDEN_BY_NH_VERSION', '2.0.2' );
define( 'HARDEN_BY_NH_PATH', plugin_dir_path( __FILE__ ) );
define( 'HARDEN_BY_NH_URL', plugin_dir_url( __FILE__ ) );

// Core.
require_once HARDEN_BY_NH_PATH . 'includes/class-harden-option-schema.php';
require_once HARDEN_BY_NH_PATH . 'includes/class-harden-options.php';
require_once HARDEN_BY_NH_PATH . 'includes/class-harden-remote-verify.php';

// Provider interfaces.
require_once HARDEN_BY_NH_PATH . 'includes/providers/interface-harden-provider.php';
require_once HARDEN_BY_NH_PATH . 'includes/providers/interface-harden-login-provider.php';
require_once HARDEN_BY_NH_PATH . 'includes/providers/interface-harden-seo-provider.php';

// Login providers.
require_once HARDEN_BY_NH_PATH . 'includes/providers/login/class-login-recaptcha-v2.php';
require_once HARDEN_BY_NH_PATH . 'includes/providers/login/class-login-recaptcha-v3.php';
require_once HARDEN_BY_NH_PATH . 'includes/providers/login/class-login-turnstile.php';

// SEO providers.
require_once HARDEN_BY_NH_PATH . 'includes/providers/seo/class-seo-wordpress-core.php';
require_once HARDEN_BY_NH_PATH . 'includes/providers/seo/class-seo-slim-seo.php';

// Feature interface + individual features.
require_once HARDEN_BY_NH_PATH . 'includes/features/interface-harden-feature.php';
require_once HARDEN_BY_NH_PATH . 'includes/features/class-feature-disable-emojis.php';
require_once HARDEN_BY_NH_PATH . 'includes/features/class-feature-disable-dashicons.php';
require_once HARDEN_BY_NH_PATH . 'includes/features/class-feature-disable-embeds.php';
require_once HARDEN_BY_NH_PATH . 'includes/features/class-feature-remove-jquery-migrate.php';
require_once HARDEN_BY_NH_PATH . 'includes/features/class-feature-remove-global-styles.php';
require_once HARDEN_BY_NH_PATH . 'includes/features/class-feature-remove-shortlink.php';
require_once HARDEN_BY_NH_PATH . 'includes/features/class-feature-disable-rss-feeds.php';
require_once HARDEN_BY_NH_PATH . 'includes/features/class-feature-remove-feed-links.php';
require_once HARDEN_BY_NH_PATH . 'includes/features/class-feature-disable-self-pingbacks.php';
require_once HARDEN_BY_NH_PATH . 'includes/features/class-feature-remove-rest-api-links.php';
require_once HARDEN_BY_NH_PATH . 'includes/features/class-feature-disable-google-maps.php';
require_once HARDEN_BY_NH_PATH . 'includes/features/class-feature-disable-password-strength-meter.php';
require_once HARDEN_BY_NH_PATH . 'includes/features/class-feature-remove-comment-urls.php';
require_once HARDEN_BY_NH_PATH . 'includes/features/class-feature-hide-wp-version.php';
require_once HARDEN_BY_NH_PATH . 'includes/features/class-feature-hide-wp-branding.php';
require_once HARDEN_BY_NH_PATH . 'includes/features/class-feature-block-wp-login.php';
require_once HARDEN_BY_NH_PATH . 'includes/features/class-feature-login-rescue.php';
require_once HARDEN_BY_NH_PATH . 'includes/features/class-feature-disable-site-editor.php';
require_once HARDEN_BY_NH_PATH . 'includes/features/class-feature-disable-comments.php';
require_once HARDEN_BY_NH_PATH . 'includes/features/class-feature-disable-application-passwords.php';
require_once HARDEN_BY_NH_PATH . 'includes/features/class-feature-disable-xmlrpc.php';
require_once HARDEN_BY_NH_PATH . 'includes/features/class-feature-rest-api-policy.php';
require_once HARDEN_BY_NH_PATH . 'includes/features/class-feature-block-rest-users.php';
require_once HARDEN_BY_NH_PATH . 'includes/features/class-feature-security-headers.php';
require_once HARDEN_BY_NH_PATH . 'includes/features/class-feature-disallow-file-edit.php';
require_once HARDEN_BY_NH_PATH . 'includes/features/class-feature-block-public-urls.php';
require_once HARDEN_BY_NH_PATH . 'includes/features/class-feature-suppress-update-notifications.php';

// Registry.
require_once HARDEN_BY_NH_PATH . 'includes/class-harden-feature-registry.php';

// Admin.
require_once HARDEN_BY_NH_PATH . 'includes/admin/class-harden-admin-page.php';
require_once HARDEN_BY_NH_PATH . 'includes/admin/class-harden-admin-ajax.php';
require_once HARDEN_BY_NH_PATH . 'includes/admin/class-admin-tab-advanced.php';
require_once HARDEN_BY_NH_PATH . 'includes/admin/class-admin-tab-pages.php';
require_once HARDEN_BY_NH_PATH . 'includes/admin/class-admin-tab-login.php';
require_once HARDEN_BY_NH_PATH . 'includes/admin/class-admin-tab-frontend.php';
require_once HARDEN_BY_NH_PATH . 'includes/admin/class-admin-tab-notifications.php';
require_once HARDEN_BY_NH_PATH . 'includes/admin/class-admin-tab-integrations.php';
require_once HARDEN_BY_NH_PATH . 'includes/admin/class-admin-tab-settings.php';

// Updates.
require_once HARDEN_BY_NH_PATH . 'includes/class-harden-updates.php';

/**
 * First activation only: save baseline options.
 */
function harden_by_nh_activate(): void {
	Harden_Options::seed_first_install_if_needed();
}

register_activation_hook( __FILE__, 'harden_by_nh_activate' );

/**
 * Define DISALLOW_FILE_EDIT before admin runs (only if not already set in wp-config.php).
 */
function harden_by_nh_maybe_disallow_file_edit(): void {
	if ( defined( 'DISALLOW_FILE_EDIT' ) ) {
		return;
	}
	if ( empty( Harden_Options::get()['disallow_file_edit'] ) ) {
		return;
	}
	define( 'DISALLOW_FILE_EDIT', true );
}

add_action( 'plugins_loaded', 'harden_by_nh_maybe_disallow_file_edit', 0 );

/**
 * Bootstrap the plugin.
 */
function harden_by_nh_bootstrap(): void {
	Harden_Admin_Page::init();
	Harden_Feature_Registry::init();
	Harden_Updates::init( __FILE__ );
}

add_action( 'plugins_loaded', 'harden_by_nh_bootstrap' );
