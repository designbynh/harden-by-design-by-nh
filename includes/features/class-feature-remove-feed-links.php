<?php
/**
 * Feature: Remove RSS feed discovery links from wp_head.
 *
 * @package HardenByDesignByNH
 */

declare(strict_types=1);

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

final class Harden_Feature_Remove_Feed_Links implements Harden_Feature {

	public function id(): string {
		return 'remove_feed_links';
	}

	public function register(): void {
		remove_action( 'wp_head', 'feed_links', 2 );
		remove_action( 'wp_head', 'feed_links_extra', 3 );
	}
}
