<?php
/**
 * Feature: Strip URLs from comment author output and remove the URL field from comment forms.
 *
 * @package HardenByDesignByNH
 */

declare(strict_types=1);

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

final class Harden_Feature_Remove_Comment_URLs implements Harden_Feature {

	public function id(): string {
		return 'remove_comment_urls';
	}

	public function register(): void {
		add_action( 'template_redirect', array( $this, 'add_front_end_filters' ), 1 );
	}

	public function add_front_end_filters(): void {
		add_filter( 'get_comment_author_link', array( $this, 'strip_author_link' ), 10, 3 );
		add_filter( 'get_comment_author_url', '__return_false' );
		add_filter( 'comment_form_default_fields', array( $this, 'remove_url_field' ) );
	}

	/**
	 * Return just the author display name instead of a linked anchor.
	 *
	 * @param string $link       HTML link or plain name.
	 * @param string $author     Comment author name.
	 * @param int    $comment_id Comment ID.
	 * @return string
	 */
	public function strip_author_link( string $link, string $author, int $comment_id ): string {
		return esc_html( $author );
	}

	/**
	 * Remove the website URL field from the comment form.
	 *
	 * @param array<string, string> $fields Default comment form fields.
	 * @return array<string, string>
	 */
	public function remove_url_field( array $fields ): array {
		unset( $fields['url'] );
		return $fields;
	}
}
