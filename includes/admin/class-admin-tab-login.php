<?php
/**
 * Login settings tab — bot-challenge provider selection.
 *
 * @package HardenByDesignByNH
 */

declare(strict_types=1);

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

final class Harden_Admin_Tab_Login {

	public function slug(): string {
		return 'login';
	}

	public function label(): string {
		return __( 'Login', 'harden-by-design-by-nh' );
	}

	/**
	 * @param array<string, mixed> $opts Plugin options.
	 */
	public function render( array $opts ): void {
		$provider = isset( $opts['login_protection_provider'] ) ? sanitize_key( (string) $opts['login_protection_provider'] ) : 'none';
		if ( ! in_array( $provider, Harden_Options::login_protection_provider_ids(), true ) ) {
			$provider = 'none';
		}

		Harden_Admin_Page::render_settings_card_open(
			__( 'Login page', 'harden-by-design-by-nh' ),
			''
		);
		Harden_Admin_Page::render_switch_rows_from_schema( $opts, 'login', 'login_protection' );
		?>
		<p class="description">
			<?php esc_html_e( 'Developers can allow specific requests to wp-login.php with the harden_by_nh_allow_wp_login_request or harden_by_nh_disabled_login_allowed_actions filters.', 'harden-by-design-by-nh' ); ?>
		</p>
		<?php
		Harden_Admin_Page::render_settings_card_close();

		$login_rescue_url = Harden_Feature_Login_Rescue::public_url_from_options( $opts );

		Harden_Admin_Page::render_settings_card_open(
			__( 'Emergency login access', 'harden-by-design-by-nh' ),
			''
		);
		Harden_Admin_Page::render_switch_rows_from_schema( $opts, 'login', 'login_rescue' );
		?>
		<p class="description">
			<?php esc_html_e( 'When you turn on Block public login page, the rescue link is enabled automatically and a new secret URL is created. You can turn the rescue link off; that clears the URL. Turning the rescue link on again always creates a new URL. Turning off Block public login page turns off the rescue link and clears the URL. Opening the link once disables blocking and consumes the link; sign in with your username and password on the next screen.', 'harden-by-design-by-nh' ); ?>
		</p>
		<table class="form-table" role="presentation">
			<tr>
				<th scope="row"><?php esc_html_e( 'Rescue link', 'harden-by-design-by-nh' ); ?></th>
				<td>
					<p id="harden-login-rescue-empty" class="description"<?php echo '' !== $login_rescue_url ? ' hidden' : ''; ?>><?php esc_html_e( 'No link is active. Turn on Block public login page or Enable rescue link to generate a URL.', 'harden-by-design-by-nh' ); ?></p>
					<p id="harden-login-rescue-url-wrap" class="harden-login-rescue-url-wrap"<?php echo '' === $login_rescue_url ? ' hidden' : ''; ?>>
						<code id="harden-login-rescue-url" class="large-text"><?php echo '' !== $login_rescue_url ? esc_html( $login_rescue_url ) : ''; ?></code>
					</p>
					<p>
						<button type="button" class="button button-secondary" id="harden-login-rescue-copy"<?php echo '' === $login_rescue_url ? ' disabled' : ''; ?>><?php esc_html_e( 'Copy link', 'harden-by-design-by-nh' ); ?></button>
						<button type="button" class="button button-secondary" id="harden-login-rescue-regenerate"><?php esc_html_e( 'Regenerate rescue link', 'harden-by-design-by-nh' ); ?></button>
					</p>
				</td>
			</tr>
		</table>
		<?php
		Harden_Admin_Page::render_settings_card_close();

		Harden_Admin_Page::render_settings_card_open(
			__( 'Bot challenge', 'harden-by-design-by-nh' ),
			__( 'Add a CAPTCHA or challenge on wp-login.php. XML-RPC, application passwords, and other non-form logins are not affected.', 'harden-by-design-by-nh' ),
			false
		);

		$registry  = Harden_Feature_Registry::instance();
		$providers = $registry ? $registry->login_providers() : array();
		?>
		<div id="harden-login-tab-root">
			<p class="description">
				<?php esc_html_e( 'Choose one integration. Register additional providers from other plugins using the harden_by_nh_register_login_captcha_providers action and the harden_by_nh_login_protection_provider_ids filter.', 'harden-by-design-by-nh' ); ?>
			</p>
			<table class="form-table" role="presentation">
				<tr>
					<th scope="row"><label for="harden-login-provider"><?php esc_html_e( 'Integration', 'harden-by-design-by-nh' ); ?></label></th>
					<td>
						<select id="harden-login-provider" class="harden-login-protection-field">
							<option value="none" <?php selected( $provider, 'none' ); ?>><?php esc_html_e( 'None', 'harden-by-design-by-nh' ); ?></option>
							<?php
							foreach ( $providers as $p ) {
								printf(
									'<option value="%1$s" %3$s>%2$s</option>',
									esc_attr( $p->id() ),
									esc_html( $p->admin_label() ),
									selected( $provider, $p->id(), false )
								);
							}
							?>
						</select>
					</td>
				</tr>
			</table>
			<table class="form-table" role="presentation">
				<tbody id="harden-login-panel-google" class="harden-login-panel"<?php echo ( 'recaptcha_v2' === $provider || 'recaptcha_v3' === $provider ) ? '' : ' hidden'; ?>>
					<tr>
						<th scope="row"><label for="harden-recaptcha-site-key"><?php esc_html_e( 'reCAPTCHA site key', 'harden-by-design-by-nh' ); ?></label></th>
						<td>
							<input type="text" class="regular-text code harden-login-protection-field" id="harden-recaptcha-site-key" value="<?php echo esc_attr( (string) $opts['recaptcha_site_key'] ); ?>" autocomplete="off" />
							<p class="description"><?php esc_html_e( 'Create keys in Google reCAPTCHA Admin. The key type must match the version you selected above.', 'harden-by-design-by-nh' ); ?></p>
						</td>
					</tr>
					<tr>
						<th scope="row"><label for="harden-recaptcha-secret-key"><?php esc_html_e( 'reCAPTCHA secret key', 'harden-by-design-by-nh' ); ?></label></th>
						<td>
							<input type="password" class="regular-text code harden-login-protection-field" id="harden-recaptcha-secret-key" value="<?php echo esc_attr( (string) $opts['recaptcha_secret_key'] ); ?>" autocomplete="new-password" />
						</td>
					</tr>
				</tbody>
				<tbody id="harden-login-panel-v3-score" class="harden-login-panel"<?php echo 'recaptcha_v3' === $provider ? '' : ' hidden'; ?>>
					<tr>
						<th scope="row"><label for="harden-recaptcha-v3-score"><?php esc_html_e( 'Score threshold', 'harden-by-design-by-nh' ); ?></label></th>
						<td>
							<input type="number" class="small-text harden-login-protection-field" id="harden-recaptcha-v3-score" value="<?php echo esc_attr( (string) ( $opts['recaptcha_v3_score_threshold'] ?? 0.5 ) ); ?>" min="0" max="1" step="0.1" />
							<p class="description"><?php esc_html_e( 'Requests scoring below this value are blocked. Google recommends 0.5. Lower values are more lenient; higher values are stricter.', 'harden-by-design-by-nh' ); ?></p>
						</td>
					</tr>
				</tbody>
				<tbody id="harden-login-panel-turnstile" class="harden-login-panel"<?php echo 'turnstile' === $provider ? '' : ' hidden'; ?>>
					<tr>
						<th scope="row"><label for="harden-turnstile-site-key"><?php esc_html_e( 'Turnstile site key', 'harden-by-design-by-nh' ); ?></label></th>
						<td>
							<input type="text" class="regular-text code harden-login-protection-field" id="harden-turnstile-site-key" value="<?php echo esc_attr( (string) ( $opts['turnstile_site_key'] ?? '' ) ); ?>" autocomplete="off" />
							<p class="description"><?php esc_html_e( 'Create a Turnstile widget in the Cloudflare dashboard.', 'harden-by-design-by-nh' ); ?></p>
						</td>
					</tr>
					<tr>
						<th scope="row"><label for="harden-turnstile-secret-key"><?php esc_html_e( 'Turnstile secret key', 'harden-by-design-by-nh' ); ?></label></th>
						<td>
							<input type="password" class="regular-text code harden-login-protection-field" id="harden-turnstile-secret-key" value="<?php echo esc_attr( (string) ( $opts['turnstile_secret_key'] ?? '' ) ); ?>" autocomplete="new-password" />
						</td>
					</tr>
				</tbody>
			</table>
		</div>
		<?php
		Harden_Admin_Page::render_settings_card_close();
	}
}
