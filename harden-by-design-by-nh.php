<?php
/**
 * Plugin Name:       Harden by Design by NH
 * Plugin URI:        https://designbynh.com
 * Description:       Security hardening for WordPress: optional public URL blocking, reCAPTCHA on login, and reduced version/branding exposure.
 * Version:           1.3.4
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

define( 'HARDEN_BY_NH_VERSION', '1.3.4' );
define( 'HARDEN_BY_NH_PATH', plugin_dir_path( __FILE__ ) );
define( 'HARDEN_BY_NH_URL', plugin_dir_url( __FILE__ ) );

require_once HARDEN_BY_NH_PATH . 'includes/class-harden-options.php';
require_once HARDEN_BY_NH_PATH . 'includes/class-harden-admin.php';
require_once HARDEN_BY_NH_PATH . 'includes/class-harden-features.php';
require_once HARDEN_BY_NH_PATH . 'includes/class-harden-frontend.php';
require_once HARDEN_BY_NH_PATH . 'includes/class-harden-updates.php';

/**
 * First activation only: save baseline options. Deactivate/reactivate keeps DB row — no reset.
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
	Harden_Options::init();
	Harden_Admin::init();
	Harden_Features::init();
	Harden_Frontend::init();
	Harden_Updates::init( __FILE__ );
}

add_action( 'plugins_loaded', 'harden_by_nh_bootstrap' );
