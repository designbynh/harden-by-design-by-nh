<?php
declare(strict_types=1);

/**
 * Feature interface.
 *
 * @package HardenByDesignByNH
 */

defined( 'ABSPATH' ) || exit;

/**
 * Contract for a toggleable plugin feature.
 */
interface Harden_Feature {

	/**
	 * Unique slug for this feature (matches the wp_options key that enables it).
	 */
	public function id(): string;

	/**
	 * Hook into WordPress (add actions / filters).
	 */
	public function register(): void;
}
