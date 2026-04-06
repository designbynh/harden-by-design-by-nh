<?php
/**
 * Pages settings tab — public URL / archive blocking.
 *
 * @package HardenByDesignByNH
 */

declare(strict_types=1);

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

final class Harden_Admin_Tab_Pages {

	public function slug(): string {
		return 'pages';
	}

	public function label(): string {
		return __( 'Pages', 'harden-by-design-by-nh' );
	}

	/**
	 * @param array<string, mixed> $opts Plugin options.
	 */
	public function render( array $opts ): void {
		$tax_list    = isset( $opts['disabled_taxonomy_archives'] ) && is_array( $opts['disabled_taxonomy_archives'] ) ? $opts['disabled_taxonomy_archives'] : array();
		$arch_list   = isset( $opts['disabled_post_type_archives'] ) && is_array( $opts['disabled_post_type_archives'] ) ? $opts['disabled_post_type_archives'] : array();
		$single_list = isset( $opts['disabled_post_type_singles'] ) && is_array( $opts['disabled_post_type_singles'] ) ? $opts['disabled_post_type_singles'] : array();

		$tax_master  = ! empty( $opts['disable_all_taxonomy_archives'] );
		$arch_master = ! empty( $opts['disable_all_post_type_archives'] );

		$taxonomies = get_taxonomies( array( 'public' => true ), 'objects' );
		/** @var WP_Taxonomy[] $taxonomies */
		$taxonomies = apply_filters( 'harden_by_nh_pages_tab_taxonomies', $taxonomies );
		uasort(
			$taxonomies,
			static function ( $a, $b ) {
				$la = isset( $a->labels->name ) ? (string) $a->labels->name : (string) $a->name;
				$lb = isset( $b->labels->name ) ? (string) $b->labels->name : (string) $b->name;
				return strcasecmp( $la, $lb );
			}
		);

		$post_types = get_post_types( array( 'public' => true ), 'objects' );
		/** @var WP_Post_Type[] $post_types */
		$post_types = apply_filters( 'harden_by_nh_pages_tab_post_types', $post_types );
		uasort(
			$post_types,
			static function ( $a, $b ) {
				$la = isset( $a->labels->name ) ? (string) $a->labels->name : (string) $a->name;
				$lb = isset( $b->labels->name ) ? (string) $b->labels->name : (string) $b->name;
				return strcasecmp( $la, $lb );
			}
		);

		$archive_types = array();
		foreach ( $post_types as $name => $obj ) {
			if ( ! empty( $obj->has_archive ) ) {
				$archive_types[ $name ] = $obj;
			}
		}

		Harden_Admin_Page::render_settings_card_open(
			__( 'Public URL blocking', 'harden-by-design-by-nh' ),
			__( 'These options answer with a 404 on the front end for matching URLs. Previews are not blocked. Test after changes—disabling singles for Posts or Pages can make a site unreachable.', 'harden-by-design-by-nh' )
		);
		?>
		<table class="form-table" role="presentation">
			<tr>
				<th scope="row"><?php esc_html_e( 'Author archives', 'harden-by-design-by-nh' ); ?></th>
				<td>
					<?php
					Harden_Admin_Page::render_switch(
						'disable_author_pages',
						__( 'Disable author archive URLs (/author/…)', 'harden-by-design-by-nh' ),
						! empty( $opts['disable_author_pages'] )
					);
					?>
					<p class="description"><?php esc_html_e( 'Stops username-style URLs from enumerating users.', 'harden-by-design-by-nh' ); ?></p>
				</td>
			</tr>
			<tr>
				<th scope="row"><?php esc_html_e( 'Date archives', 'harden-by-design-by-nh' ); ?></th>
				<td>
					<?php
					Harden_Admin_Page::render_switch(
						'disable_date_archives',
						__( 'Disable date-based archive URLs (year, month, day)', 'harden-by-design-by-nh' ),
						! empty( $opts['disable_date_archives'] )
					);
					?>
				</td>
			</tr>
			<tr>
				<th scope="row"><?php esc_html_e( 'Posts index', 'harden-by-design-by-nh' ); ?></th>
				<td>
					<?php
					Harden_Admin_Page::render_switch(
						'disable_blog_index',
						__( 'Disable the main blog / posts listing (is_home)', 'harden-by-design-by-nh' ),
						! empty( $opts['disable_blog_index'] )
					);
					?>
					<p class="description"><?php esc_html_e( 'Use when the site does not use a post feed on the front page or "Posts" page. Static front pages still work.', 'harden-by-design-by-nh' ); ?></p>
				</td>
			</tr>
			<tr>
				<th scope="row"><?php esc_html_e( 'Taxonomy archives', 'harden-by-design-by-nh' ); ?></th>
				<td>
					<?php
					Harden_Admin_Page::render_switch(
						'disable_all_taxonomy_archives',
						__( 'Disable all taxonomy archive URLs (categories, tags, and other public taxonomies)', 'harden-by-design-by-nh' ),
						$tax_master
					);
					?>
					<p class="description">
						<?php esc_html_e( 'When off, use the list below to block only specific taxonomies. Matching taxonomy screens (e.g. Posts → Categories / Tags) are removed from the admin menu and blocked if opened directly.', 'harden-by-design-by-nh' ); ?>
					</p>
					<?php if ( ! empty( $taxonomies ) ) : ?>
						<div class="harden-by-nh-page-lists" aria-label="<?php esc_attr_e( 'Per-taxonomy archive blocking', 'harden-by-design-by-nh' ); ?>">
							<?php
							foreach ( $taxonomies as $tax_obj ) {
								$slug = $tax_obj->name;
								$lab  = isset( $tax_obj->labels->name ) ? $tax_obj->labels->name : $slug;
								Harden_Admin_Page::render_page_slug_switch(
									'disabled_taxonomy_archives',
									$slug,
									sprintf(
										/* translators: 1: taxonomy label, 2: taxonomy slug */
										__( 'Block archive URLs for %1$s (%2$s)', 'harden-by-design-by-nh' ),
										$lab,
										$slug
									),
									in_array( $slug, $tax_list, true ),
									$tax_master
								);
							}
							?>
						</div>
					<?php endif; ?>
				</td>
			</tr>
			<tr>
				<th scope="row"><?php esc_html_e( 'Post type archives', 'harden-by-design-by-nh' ); ?></th>
				<td>
					<?php
					Harden_Admin_Page::render_switch(
						'disable_all_post_type_archives',
						__( 'Disable all post type archive URLs', 'harden-by-design-by-nh' ),
						$arch_master
					);
					?>
					<p class="description"><?php esc_html_e( 'Only affects types that register an archive (not the built-in Posts index—use "Posts index" above). When off, pick types below.', 'harden-by-design-by-nh' ); ?></p>
					<?php if ( ! empty( $archive_types ) ) : ?>
						<div class="harden-by-nh-page-lists" aria-label="<?php esc_attr_e( 'Per post type archive blocking', 'harden-by-design-by-nh' ); ?>">
							<?php
							foreach ( $archive_types as $name => $pt_obj ) {
								$lab = isset( $pt_obj->labels->name ) ? $pt_obj->labels->name : $name;
								Harden_Admin_Page::render_page_slug_switch(
									'disabled_post_type_archives',
									$name,
									sprintf(
										__( 'Block archive URLs for %1$s (%2$s)', 'harden-by-design-by-nh' ),
										$lab,
										$name
									),
									in_array( $name, $arch_list, true ),
									$arch_master
								);
							}
							?>
						</div>
					<?php else : ?>
						<p class="description"><?php esc_html_e( 'No public post types with archives are registered.', 'harden-by-design-by-nh' ); ?></p>
					<?php endif; ?>
				</td>
			</tr>
			<tr>
				<th scope="row"><?php esc_html_e( 'Single URLs by post type', 'harden-by-design-by-nh' ); ?></th>
				<td>
					<p class="description">
						<?php esc_html_e( 'Blocks single URLs for each type you enable. For the built-in Post and Page types, the matching admin menu and "New → Post" / "New → Page" shortcuts are hidden and list/edit screens redirect to the dashboard. Types stay registered in WordPress; we do not unregister them.', 'harden-by-design-by-nh' ); ?>
					</p>
					<div class="harden-by-nh-page-lists" aria-label="<?php esc_attr_e( 'Per post type single blocking', 'harden-by-design-by-nh' ); ?>">
						<?php
						foreach ( $post_types as $name => $pt_obj ) {
							$lab = isset( $pt_obj->labels->name ) ? $pt_obj->labels->name : $name;
							Harden_Admin_Page::render_page_slug_switch(
								'disabled_post_type_singles',
								$name,
								sprintf(
									__( 'Block single URLs for %1$s (%2$s)', 'harden-by-design-by-nh' ),
									$lab,
									$name
								),
								in_array( $name, $single_list, true ),
								false
							);
						}
						?>
					</div>
				</td>
			</tr>
		</table>
		<?php
		Harden_Admin_Page::render_settings_card_close();
	}
}
