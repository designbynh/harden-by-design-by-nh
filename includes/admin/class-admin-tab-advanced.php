<?php
/**
 * Advanced settings tab.
 *
 * @package HardenByDesignByNH
 */

declare(strict_types=1);

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

final class Harden_Admin_Tab_Advanced {

	public function slug(): string {
		return 'advanced';
	}

	public function label(): string {
		return __( 'Advanced', 'harden-by-design-by-nh' );
	}

	/**
	 * @param array<string, mixed> $opts Plugin options.
	 */
	public function render( array $opts ): void {
		Harden_Admin_Page::sync_file_edit_option_if_forced_by_wp( $opts );
		$opts = Harden_Options::get();

		$file_edit_effective = Harden_Options::is_file_editor_disabled();
		$file_edit_defined   = defined( 'DISALLOW_FILE_EDIT' );
		$file_edit_note      = '';
		$file_edit_disabled  = $file_edit_defined && ! DISALLOW_FILE_EDIT;

		if ( $file_edit_defined ) {
			if ( DISALLOW_FILE_EDIT ) {
				$file_edit_note = __( 'DISALLOW_FILE_EDIT is true (usually in wp-config.php). The editor is off; this switch reflects that. Turning it off here only updates this plugin\'s option—remove or change the constant to bring the editor back.', 'harden-by-design-by-nh' );
			} else {
				$file_edit_note = __( 'DISALLOW_FILE_EDIT is set to false in wp-config.php, so this plugin cannot enable file blocking until that line is removed or changed. The switch is disabled.', 'harden-by-design-by-nh' );
			}
		} elseif ( $file_edit_effective ) {
			$file_edit_note = __( 'File editing is disabled for this request (e.g. this plugin defined DISALLOW_FILE_EDIT because the option is on).', 'harden-by-design-by-nh' );
		}

		// ── Security card ────────────────────────────────────────────────
		Harden_Admin_Page::render_settings_card_open(
			__( 'Security', 'harden-by-design-by-nh' ),
			''
		);
		Harden_Admin_Page::render_switch_rows_from_schema( $opts, 'advanced', 'security' );
		?>
		<table class="form-table" role="presentation">
			<tr>
				<th scope="row"><label for="harden-rest-api-policy"><?php esc_html_e( 'REST API', 'harden-by-design-by-nh' ); ?></label></th>
				<td>
					<select id="harden-rest-api-policy" class="harden-rest-policy-field">
						<?php
						$rp      = isset( $opts['rest_api_policy'] ) ? (string) $opts['rest_api_policy'] : 'off';
						$choices = array(
							'off'        => __( 'No restriction', 'harden-by-design-by-nh' ),
							'guests'     => __( 'Block guests only (not logged in)', 'harden-by-design-by-nh' ),
							'non_admins' => __( 'Block non-admins (require Administrator)', 'harden-by-design-by-nh' ),
						);
						foreach ( $choices as $val => $choice_label ) {
							printf(
								'<option value="%1$s" %2$s>%3$s</option>',
								esc_attr( $val ),
								selected( $rp, $val, false ),
								esc_html( $choice_label )
							);
						}
						?>
					</select>
					<p class="description"><?php esc_html_e( 'Plugin routes are exempt only when the REST path starts with that namespace segment (filter: harden_by_nh_rest_route_exceptions). Editors lose REST access when "non-admins" is selected (block editor in admin still works for Administrators).', 'harden-by-design-by-nh' ); ?></p>
				</td>
			</tr>
		</table>
		<?php
		Harden_Admin_Page::render_settings_card_close();

		// ── Admin interface card ─────────────────────────────────────────
		Harden_Admin_Page::render_settings_card_open(
			__( 'Admin interface', 'harden-by-design-by-nh' ),
			''
		);
		Harden_Admin_Page::render_switch_rows_from_schema( $opts, 'advanced', 'admin_interface' );
		?>
		<table class="form-table" role="presentation">
			<tr>
				<th scope="row"><?php esc_html_e( 'Theme & plugin file editor', 'harden-by-design-by-nh' ); ?></th>
				<td>
					<?php
					Harden_Admin_Page::render_switch(
						'disallow_file_edit',
						__( 'Disallow editing theme and plugin files from the admin', 'harden-by-design-by-nh' ),
						$file_edit_effective,
						$file_edit_disabled
					);
					?>
					<p class="description">
						<?php esc_html_e( 'The switch shows whether WordPress is actually blocking the file editor (DISALLOW_FILE_EDIT). When it is already set in wp-config.php, the switch appears on even if you had not enabled it here before.', 'harden-by-design-by-nh' ); ?>
						<?php
						if ( $file_edit_note ) {
							echo ' <strong>' . esc_html( $file_edit_note ) . '</strong>';
						}
						?>
					</p>
				</td>
			</tr>
		</table>
		<?php
		Harden_Admin_Page::render_settings_card_close();

		// ── Site features card ────────────────────────────────────────────
		Harden_Admin_Page::render_settings_card_open(
			__( 'Site features', 'harden-by-design-by-nh' ),
			''
		);
		Harden_Admin_Page::render_switch_rows_from_schema( $opts, 'advanced', 'site_features' );
		Harden_Admin_Page::render_settings_card_close();
	}
}
