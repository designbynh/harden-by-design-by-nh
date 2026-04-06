<?php
declare(strict_types=1);

/**
 * SEO / sitemap provider interface.
 *
 * @package HardenByDesignByNH
 */

defined( 'ABSPATH' ) || exit;

/**
 * Contract for SEO‑related providers (e.g. sitemap generators).
 */
interface Harden_Seo_Provider extends Harden_Provider {

	/**
	 * The wp_options key this provider stores its data under.
	 */
	public function option_key(): string;
}
