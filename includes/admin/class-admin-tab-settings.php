<?php
/**
 * Settings tab — import, export, and reset.
 *
 * @package HardenByDesignByNH
 */

declare(strict_types=1);

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

final class Harden_Admin_Tab_Settings {

	public function slug(): string {
		return 'settings';
	}

	public function label(): string {
		return __( 'Settings', 'harden-by-design-by-nh' );
	}

	/**
	 * @param array<string, mixed> $opts Plugin options (unused but kept for tab interface parity).
	 */
	public function render( array $opts ): void {
		unset( $opts );

		$export_url = wp_nonce_url(
			admin_url( 'admin-post.php?action=harden_by_nh_export_settings' ),
			'harden_by_nh_export_settings'
		);

		Harden_Admin_Page::render_settings_card_open(
			__( 'Import / export settings', 'harden-by-design-by-nh' ),
			__( 'Download a JSON backup, restore from a file, or reset all options to factory defaults.', 'harden-by-design-by-nh' ),
			false
		);
		?>
			<p><?php esc_html_e( 'Export downloads all current options as JSON. Login integration secrets (reCAPTCHA and Turnstile) are omitted by default (filters: harden_by_nh_export_include_recaptcha_secret, harden_by_nh_export_include_turnstile_secret). Import merges known keys into defaults, sanitizes them, and saves—then redirects back here.', 'harden-by-design-by-nh' ); ?></p>
			<p>
				<a href="<?php echo esc_url( $export_url ); ?>" class="button button-secondary"><?php esc_html_e( 'Download JSON export', 'harden-by-design-by-nh' ); ?></a>
			</p>
			<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" enctype="multipart/form-data">
				<?php wp_nonce_field( 'harden_by_nh_import_settings' ); ?>
				<input type="hidden" name="action" value="harden_by_nh_import_settings" />
				<input type="hidden" name="harden_redirect_tab" value="settings" />
				<p>
					<label for="harden-import-json"><strong><?php esc_html_e( 'Import from JSON file', 'harden-by-design-by-nh' ); ?></strong></label><br />
					<input type="file" name="harden_import_file" id="harden-import-json" accept=".json,application/json" required />
				</p>
				<?php submit_button( __( 'Import and save', 'harden-by-design-by-nh' ), 'primary', 'harden_import_submit', false ); ?>
			</form>
			<hr style="margin:1.25em 0;" />
			<p><strong><?php esc_html_e( 'Reset', 'harden-by-design-by-nh' ); ?></strong></p>
			<p class="description"><?php esc_html_e( 'Restore every option to the plugin\'s factory defaults (not the first-install preset). You will need to reconfigure.', 'harden-by-design-by-nh' ); ?></p>
			<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" id="harden-by-nh-reset-form">
				<?php wp_nonce_field( 'harden_by_nh_reset_settings' ); ?>
				<input type="hidden" name="action" value="harden_by_nh_reset_settings" />
				<input type="hidden" name="harden_reset_redirect_tab" value="settings" />
				<?php
				submit_button(
					__( 'Reset all settings to defaults', 'harden-by-design-by-nh' ),
					'secondary',
					'harden_reset_submit',
					false,
					array( 'id' => 'harden-by-nh-reset-submit' )
				);
				?>
			</form>
		<?php
		Harden_Admin_Page::render_settings_card_close();
	}
}
