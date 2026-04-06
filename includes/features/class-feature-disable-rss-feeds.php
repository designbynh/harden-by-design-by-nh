<?php
/**
 * Feature: Disable all RSS / Atom feeds.
 *
 * @package HardenByDesignByNH
 */

declare(strict_types=1);

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

final class Harden_Feature_Disable_RSS_Feeds implements Harden_Feature {

	public function id(): string {
		return 'disable_rss_feeds';
	}

	public function register(): void {
		add_action( 'template_redirect', array( $this, 'disable_feeds' ), 1 );
	}

	public function disable_feeds(): void {
		if ( ! is_feed() || is_404() ) {
			return;
		}

		wp_die(
			esc_html__( 'RSS feeds are disabled on this site.', 'harden-by-design-by-nh' ),
			'',
			array( 'response' => 403 )
		);
	}
}
