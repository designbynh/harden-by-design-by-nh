<?php
declare(strict_types=1);

/**
 * Base provider interface.
 *
 * @package HardenByDesignByNH
 */

defined( 'ABSPATH' ) || exit;

/**
 * Every Harden provider (login CAPTCHA, SEO sitemap, etc.) implements this contract.
 */
interface Harden_Provider {

	/**
	 * Unique slug for this provider (e.g. 'recaptcha_v2').
	 */
	public function id(): string;

	/**
	 * Provider category — 'login_captcha' or 'seo_sitemap'.
	 */
	public function type(): string;

	/**
	 * Human‑readable label shown in the admin UI.
	 */
	public function admin_label(): string;

	/**
	 * Whether the provider's external dependencies are reachable / usable.
	 */
	public function is_available(): bool;

	/**
	 * Hook into WordPress (add actions / filters).
	 */
	public function register(): void;
}
