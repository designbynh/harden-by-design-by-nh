<?php
/**
 * Feature: Disallow file editing via the WordPress admin (DISALLOW_FILE_EDIT).
 *
 * The actual constant is defined in the main plugin file at plugins_loaded
 * priority 0, well before any hooks run. By the time the feature registry calls
 * register() the constant is already in effect, so this class intentionally has
 * an empty register() — it exists solely to satisfy the Harden_Feature contract
 * and keep the registry list complete.
 *
 * @package HardenByDesignByNH
 */

declare(strict_types=1);

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

final class Harden_Feature_Disallow_File_Edit implements Harden_Feature {

	public function id(): string {
		return 'disallow_file_edit';
	}

	/**
	 * No-op: the DISALLOW_FILE_EDIT constant is already defined at
	 * plugins_loaded priority 0 in the main plugin bootstrap file.
	 */
	public function register(): void {
		// Intentionally empty — see class docblock.
	}
}
