<?php
/**
 * Frontend settings tab — scripts, feeds, and miscellaneous tweaks.
 *
 * @package HardenByDesignByNH
 */

declare(strict_types=1);

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

final class Harden_Admin_Tab_Frontend {

	public function slug(): string {
		return 'frontend';
	}

	public function label(): string {
		return __( 'Frontend', 'harden-by-design-by-nh' );
	}

	/**
	 * @param array<string, mixed> $opts Plugin options.
	 */
	public function render( array $opts ): void {
		?>
		<p class="description harden-by-nh-tab-lead">
			<?php esc_html_e( 'Front-end tweaks and lightweight performance options. Version hiding, REST, and comments are on the Advanced tab. Public URL options are on the Pages tab. Login challenges are on the Login tab.', 'harden-by-design-by-nh' ); ?>
		</p>
		<?php

		// ── Scripts & assets card ────────────────────────────────────────
		Harden_Admin_Page::render_settings_card_open(
			__( 'Scripts & assets', 'harden-by-design-by-nh' ),
			__( 'Emoji, icons, embeds, jQuery Migrate, and global styles.', 'harden-by-design-by-nh' )
		);
		Harden_Admin_Page::render_switch_rows_from_schema( $opts, 'frontend', 'scripts' );
		Harden_Admin_Page::render_settings_card_close();

		// ── Feeds & head metadata card ───────────────────────────────────
		Harden_Admin_Page::render_settings_card_open(
			__( 'Feeds & head metadata', 'harden-by-design-by-nh' ),
			__( 'Shortlinks, RSS, discovery links, pingbacks, and REST hints in HTML.', 'harden-by-design-by-nh' )
		);
		Harden_Admin_Page::render_switch_rows_from_schema( $opts, 'frontend', 'feeds' );
		Harden_Admin_Page::render_settings_card_close();

		// ── Other front-end tweaks card ──────────────────────────────────
		Harden_Admin_Page::render_settings_card_open(
			__( 'Other front-end tweaks', 'harden-by-design-by-nh' ),
			__( 'Maps, passwords, and comments.', 'harden-by-design-by-nh' )
		);
		Harden_Admin_Page::render_switch_rows_from_schema( $opts, 'frontend', 'other' );
		Harden_Admin_Page::render_settings_card_close();
	}
}
