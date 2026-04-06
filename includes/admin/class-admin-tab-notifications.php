<?php
/**
 * Notifications settings tab — dashboard badges and update emails.
 *
 * @package HardenByDesignByNH
 */

declare(strict_types=1);

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

final class Harden_Admin_Tab_Notifications {

	public function slug(): string {
		return 'notifications';
	}

	public function label(): string {
		return __( 'Notifications', 'harden-by-design-by-nh' );
	}

	/**
	 * @param array<string, mixed> $opts Plugin options.
	 */
	public function render( array $opts ): void {
		// ── Dashboard & admin bar card ───────────────────────────────────
		Harden_Admin_Page::render_settings_card_open(
			__( 'Dashboard & admin bar', 'harden-by-design-by-nh' ),
			__( 'Admin notices and menu badges: only non-administrators are affected; site Administrators always keep full update UI. Custom roles can be adjusted with the harden_by_nh_user_keeps_update_ui filter.', 'harden-by-design-by-nh' )
		);
		Harden_Admin_Page::render_switch_rows_from_schema( $opts, 'notifications', 'dashboard' );
		Harden_Admin_Page::render_settings_card_close();

		// ── Email card ───────────────────────────────────────────────────
		Harden_Admin_Page::render_settings_card_open(
			__( 'Email (automatic background updates)', 'harden-by-design-by-nh' ),
			__( 'These apply site-wide when enabled—they are not per-user. Other WordPress emails (comments, password reset, privacy tools, etc.) are unchanged.', 'harden-by-design-by-nh' )
		);
		Harden_Admin_Page::render_switch_rows_from_schema( $opts, 'notifications', 'email' );
		Harden_Admin_Page::render_settings_card_close();
	}
}
