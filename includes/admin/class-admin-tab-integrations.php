<?php
/**
 * Integrations settings tab — third-party SEO / sitemap providers.
 *
 * @package HardenByDesignByNH
 */

declare(strict_types=1);

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

final class Harden_Admin_Tab_Integrations {

	public function slug(): string {
		return 'integrations';
	}

	public function label(): string {
		return __( 'Integrations', 'harden-by-design-by-nh' );
	}

	/**
	 * @param array<string, mixed> $opts Plugin options.
	 */
	public function render( array $opts ): void {
		$registry  = Harden_Feature_Registry::instance();
		$providers = $registry ? $registry->seo_providers() : array();

		foreach ( $providers as $provider ) {
			$this->render_seo_provider_card( $provider, $opts );
		}
	}

	/**
	 * Render one SEO provider card.
	 *
	 * @param Harden_Seo_Provider  $provider SEO provider instance.
	 * @param array<string, mixed> $opts     Plugin options.
	 */
	private function render_seo_provider_card( Harden_Seo_Provider $provider, array $opts ): void {
		$available  = $provider->is_available();
		$option_key = $provider->option_key();
		$checked    = ! empty( $opts[ $option_key ] );

		Harden_Admin_Page::render_settings_card_open(
			$provider->admin_label(),
			$available
				? ''
				: sprintf(
					/* translators: %s: plugin name */
					__( 'Install and activate %s, then return here to enable this integration.', 'harden-by-design-by-nh' ),
					$provider->admin_label()
				),
			$available
		);

		if ( ! $available ) {
			echo '<p class="description">';
			printf(
				/* translators: %s: plugin name */
				esc_html__( 'Install and activate %s, then return here.', 'harden-by-design-by-nh' ),
				esc_html( $provider->admin_label() )
			);
			echo '</p>';
		} else {
			$schema_entry = Harden_Option_Schema::all()[ $option_key ] ?? array();
			$description  = $schema_entry['description'] ?? '';

			echo '<ul style="list-style:disc;margin-left:1.5em;">';
			echo '<li>' . esc_html__( 'Post types blocked on the Pages tab are excluded from post-type sitemaps.', 'harden-by-design-by-nh' ) . '</li>';
			echo '<li>' . esc_html__( 'Taxonomy sitemaps omit taxonomies whose archives you block.', 'harden-by-design-by-nh' ) . '</li>';
			echo '<li>' . esc_html__( 'The user/author sitemap is disabled when author archives are blocked.', 'harden-by-design-by-nh' ) . '</li>';
			echo '</ul>';
			?>
			<table class="form-table" role="presentation">
				<tr>
					<th scope="row"><?php esc_html_e( 'Sitemap sync', 'harden-by-design-by-nh' ); ?></th>
					<td>
						<?php
						Harden_Admin_Page::render_switch(
							$option_key,
							$schema_entry['label'] ?? sprintf(
								/* translators: %s: provider label */
								__( 'Sync with %s', 'harden-by-design-by-nh' ),
								$provider->admin_label()
							),
							$checked
						);
						if ( '' !== $description ) {
							echo '<p class="description">' . esc_html( $description ) . '</p>';
						}
						?>
					</td>
				</tr>
			</table>
			<?php
		}

		Harden_Admin_Page::render_settings_card_close();
	}
}
