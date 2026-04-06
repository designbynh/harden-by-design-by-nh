<?php
/**
 * GitHub updates via Plugin Update Checker (Yahnis Elsts).
 *
 * @package HardenByDesignByNH
 */

declare(strict_types=1);

use YahnisElsts\PluginUpdateChecker\v5\PucFactory;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class Harden_Updates
 */
final class Harden_Updates {

	/**
	 * @param string $plugin_file Absolute path to the main plugin file (__FILE__).
	 */
	public static function init( string $plugin_file ): void {
		$loader = HARDEN_BY_NH_PATH . 'lib/plugin-update-checker/load-v5p6.php';
		if ( ! is_readable( $loader ) ) {
			return;
		}

		require_once $loader;

		/**
		 * GitHub repository URL for update metadata (trailing slash).
		 *
		 * @param string $url Default public repo URL.
		 */
		$repo = (string) apply_filters(
			'harden_by_nh_update_repository_url',
			'https://github.com/designbynh/harden-by-design-by-nh/'
		);

		/**
		 * PUC slug (stored in options / transients). Keep stable across installs.
		 *
		 * @param string $slug Default slug.
		 */
		$slug = (string) apply_filters( 'harden_by_nh_update_checker_slug', 'harden-by-design-by-nh' );

		$checker = PucFactory::buildUpdateChecker(
			$repo,
			$plugin_file,
			$slug,
			12
		);

		$checker->setBranch( 'main' );

		if ( defined( 'HARDEN_BY_NH_GITHUB_TOKEN' ) && is_string( HARDEN_BY_NH_GITHUB_TOKEN ) && '' !== trim( HARDEN_BY_NH_GITHUB_TOKEN ) ) {
			$checker->setAuthentication( trim( HARDEN_BY_NH_GITHUB_TOKEN ) );
		}
	}
}
