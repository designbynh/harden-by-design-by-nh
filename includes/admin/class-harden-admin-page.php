<?php
/**
 * Central admin controller — settings page, tabs, rendering helpers.
 *
 * @package HardenByDesignByNH
 */

declare(strict_types=1);

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

final class Harden_Admin_Page {

	public const PAGE_SLUG        = 'harden-by-design-by-nh';
	public const CAP              = 'manage_options';
	public const IMPORT_MAX_BYTES = 1048576;

	/** @var array<string, object>|null Lazy-initialised tab instances. */
	private static ?array $tab_instances = null;

	// ── Bootstrap ────────────────────────────────────────────────────────

	public static function init(): void {
		add_action( 'admin_menu', array( self::class, 'register_menu' ) );
		add_action( 'admin_init', array( self::class, 'register_settings' ) );
		add_action( 'admin_enqueue_scripts', array( self::class, 'enqueue_assets' ) );
		add_action( 'admin_post_harden_by_nh_export_settings', array( self::class, 'handle_export_settings' ) );
		add_action( 'admin_post_harden_by_nh_import_settings', array( self::class, 'handle_import_settings' ) );
		add_action( 'admin_post_harden_by_nh_reset_settings', array( self::class, 'handle_reset_settings' ) );

		Harden_Admin_Ajax::init();
	}

	// ── Menu / settings registration ─────────────────────────────────────

	public static function register_menu(): void {
		add_options_page(
			__( 'HardenWP', 'harden-by-design-by-nh' ),
			__( 'HardenWP', 'harden-by-design-by-nh' ),
			self::CAP,
			self::PAGE_SLUG,
			array( self::class, 'render_page' )
		);
	}

	public static function register_settings(): void {
		register_setting(
			'harden_by_nh',
			Harden_Options::OPTION_KEY,
			array(
				'type'              => 'array',
				'sanitize_callback' => array( self::class, 'sanitize_options' ),
				'default'           => Harden_Options::defaults(),
			)
		);
	}

	// ── Sanitise callback ────────────────────────────────────────────────

	/**
	 * Schema-driven sanitisation for Settings API saves.
	 *
	 * @param mixed $input Raw form data.
	 * @return array<string, mixed>
	 */
	public static function sanitize_options( $input ): array {
		$existing = Harden_Options::get();

		if ( ! is_array( $input ) ) {
			return $existing;
		}

		$tab = isset( $input['harden_tab'] ) ? sanitize_key( (string) $input['harden_tab'] ) : '';
		unset( $input['harden_tab'] );

		$schema = Harden_Option_Schema::all();

		if ( 'login' === $tab ) {
			foreach ( Harden_Option_Schema::keys_for_tab( 'login' ) as $key ) {
				$def = $schema[ $key ] ?? array();
				if ( 'bool' === ( $def['type'] ?? '' ) ) {
					$existing[ $key ] = ! empty( $input[ $key ] );
				}
			}
			$pid = isset( $input['login_protection_provider'] ) ? sanitize_key( (string) $input['login_protection_provider'] ) : 'none';
			$existing['login_protection_provider'] = in_array( $pid, Harden_Options::login_protection_provider_ids(), true ) ? $pid : 'none';
			$existing['recaptcha_site_key']        = isset( $input['recaptcha_site_key'] ) ? sanitize_text_field( (string) $input['recaptcha_site_key'] ) : '';
			$existing['recaptcha_secret_key']      = isset( $input['recaptcha_secret_key'] ) ? sanitize_text_field( (string) $input['recaptcha_secret_key'] ) : '';
			if ( isset( $input['recaptcha_v3_score_threshold'] ) ) {
				$existing['recaptcha_v3_score_threshold'] = (float) $input['recaptcha_v3_score_threshold'];
			}
			$existing['turnstile_site_key']        = isset( $input['turnstile_site_key'] ) ? sanitize_text_field( (string) $input['turnstile_site_key'] ) : '';
			$existing['turnstile_secret_key']      = isset( $input['turnstile_secret_key'] ) ? sanitize_text_field( (string) $input['turnstile_secret_key'] ) : '';
			self::sync_login_tab_rescue_state( $existing );
			return Harden_Options::prepare_storage( $existing );
		}

		if ( 'pages' === $tab ) {
			foreach ( Harden_Option_Schema::keys_for_tab( 'pages' ) as $key ) {
				$def = $schema[ $key ] ?? array();
				if ( 'bool' === ( $def['type'] ?? '' ) ) {
					$existing[ $key ] = ! empty( $input[ $key ] );
				}
			}
			if ( isset( $input['disabled_taxonomy_archives'] ) ) {
				$existing['disabled_taxonomy_archives'] = Harden_Options::sanitize_slug_list( $input['disabled_taxonomy_archives'] );
			}
			if ( isset( $input['disabled_post_type_archives'] ) ) {
				$existing['disabled_post_type_archives'] = Harden_Options::sanitize_slug_list( $input['disabled_post_type_archives'] );
			}
			if ( isset( $input['disabled_post_type_singles'] ) ) {
				$existing['disabled_post_type_singles'] = Harden_Options::sanitize_slug_list( $input['disabled_post_type_singles'] );
			}
			return Harden_Options::prepare_storage( $existing );
		}

		if ( 'advanced' === $tab ) {
			foreach ( Harden_Option_Schema::keys_for_tab( 'advanced' ) as $key ) {
				$def = $schema[ $key ] ?? array();
				if ( 'bool' === ( $def['type'] ?? '' ) ) {
					$existing[ $key ] = ! empty( $input[ $key ] );
				}
			}
			$rp                         = isset( $input['rest_api_policy'] ) ? sanitize_key( (string) $input['rest_api_policy'] ) : 'off';
			$existing['rest_api_policy'] = in_array( $rp, Harden_Options::rest_api_policies(), true ) ? $rp : 'off';
			return Harden_Options::prepare_storage( $existing );
		}

		if ( 'frontend' === $tab ) {
			foreach ( Harden_Option_Schema::keys_for_tab( 'frontend' ) as $key ) {
				$def = $schema[ $key ] ?? array();
				if ( 'bool' === ( $def['type'] ?? '' ) ) {
					$existing[ $key ] = ! empty( $input[ $key ] );
				}
			}
			return Harden_Options::prepare_storage( $existing );
		}

		if ( 'notifications' === $tab ) {
			foreach ( Harden_Option_Schema::keys_for_tab( 'notifications' ) as $key ) {
				$def = $schema[ $key ] ?? array();
				if ( 'bool' === ( $def['type'] ?? '' ) ) {
					$existing[ $key ] = ! empty( $input[ $key ] );
				}
			}
			return Harden_Options::prepare_storage( $existing );
		}

		$allowed = Harden_Options::default_option_keys();
		$slice   = array();
		foreach ( $allowed as $key ) {
			if ( array_key_exists( $key, $input ) ) {
				$slice[ $key ] = $input[ $key ];
			}
		}
		return Harden_Options::prepare_storage( array_merge( $existing, $slice ) );
	}

	/**
	 * Keep rescue token and toggles consistent after a Login tab form save (mirrors AJAX switch rules).
	 *
	 * @param array<string, mixed> $existing Options being saved (modified in place).
	 */
	private static function sync_login_tab_rescue_state( array &$existing ): void {
		if ( empty( $existing['disable_wp_login_page'] ) ) {
			$existing['login_rescue_enabled'] = false;
			$existing['login_rescue_token']   = '';
			return;
		}
		if ( empty( $existing['login_rescue_enabled'] ) ) {
			$existing['login_rescue_token'] = '';
			return;
		}
		$t = isset( $existing['login_rescue_token'] ) ? (string) $existing['login_rescue_token'] : '';
		if ( 64 !== strlen( $t ) || ! ctype_xdigit( $t ) ) {
			$new = Harden_Feature_Login_Rescue::new_rescue_token();
			if ( '' !== $new ) {
				$existing['login_rescue_token'] = $new;
			}
		}
	}

	// ── Assets ───────────────────────────────────────────────────────────

	/**
	 * @param string $hook_suffix Current admin screen hook.
	 */
	public static function enqueue_assets( string $hook_suffix ): void {
		if ( 'settings_page_' . self::PAGE_SLUG !== $hook_suffix ) {
			return;
		}

		$css_path = HARDEN_BY_NH_PATH . 'assets/admin-settings.css';
		$js_path  = HARDEN_BY_NH_PATH . 'assets/admin-settings.js';
		$css_ver  = file_exists( $css_path ) ? (string) filemtime( $css_path ) : HARDEN_BY_NH_VERSION;
		$js_ver   = file_exists( $js_path ) ? (string) filemtime( $js_path ) : HARDEN_BY_NH_VERSION;

		wp_enqueue_style(
			'harden-by-nh-admin',
			HARDEN_BY_NH_URL . 'assets/admin-settings.css',
			array(),
			$css_ver
		);

		wp_enqueue_script(
			'harden-by-nh-admin',
			HARDEN_BY_NH_URL . 'assets/admin-settings.js',
			array( 'jquery' ),
			$js_ver,
			true
		);

		wp_localize_script(
			'harden-by-nh-admin',
			'hardenByNH',
			array(
				'ajaxUrl' => admin_url( 'admin-ajax.php' ),
				'nonce'   => wp_create_nonce( 'harden_by_nh_ajax' ),
				'i18n'    => array(
					'saving'              => __( 'Saving…', 'harden-by-design-by-nh' ),
					'saved'               => __( 'Saved.', 'harden-by-design-by-nh' ),
					'error'               => __( 'Could not save. Try again.', 'harden-by-design-by-nh' ),
					'resetConfirm'        => __( 'Reset every HardenWP option to factory defaults? You cannot undo this.', 'harden-by-design-by-nh' ),
					'bulkNothing'         => __( 'No toggles to update in this section.', 'harden-by-design-by-nh' ),
					'rescueOneTimeNotice' => __( 'This rescue link works only once. After you open it, it will stop working and you will need a new link.', 'harden-by-design-by-nh' ),
					'rescueCopied'        => __( 'Link copied to clipboard.', 'harden-by-design-by-nh' ),
					'rescueCopyFailed'    => __( 'Could not copy to the clipboard.', 'harden-by-design-by-nh' ),
				),
			)
		);
	}

	// ── Page rendering ───────────────────────────────────────────────────

	public static function render_page(): void {
		if ( ! current_user_can( self::CAP ) ) {
			return;
		}

		$tab = isset( $_GET['tab'] ) ? sanitize_key( wp_unslash( (string) $_GET['tab'] ) ) : 'advanced'; // phpcs:ignore WordPress.Security.NonceVerification.Recommended
		if ( 'recaptcha' === $tab ) {
			$tab = 'login';
		}
		if ( ! in_array( $tab, self::tab_slugs(), true ) ) {
			$tab = 'advanced';
		}

		$base_url = admin_url( 'options-general.php?page=' . self::PAGE_SLUG );
		$opts     = Harden_Options::get();

		if ( 'advanced' === $tab ) {
			self::sync_file_edit_option_if_forced_by_wp( $opts );
			$opts = Harden_Options::get();
		}

		settings_errors();
		self::maybe_display_import_export_notices( $tab );
		self::maybe_display_reset_notice( $tab );

		$tabs = self::tabs();
		?>
		<div class="wrap harden-by-nh-wrap">
			<header class="harden-by-nh-header" aria-labelledby="harden-by-nh-header-title">
				<div class="harden-by-nh-header__titles">
					<h1 id="harden-by-nh-header-title" class="harden-by-nh-header__title"><?php esc_html_e( 'HardenWP', 'harden-by-design-by-nh' ); ?></h1>
					<p class="harden-by-nh-header__subtitle"><?php esc_html_e( 'by Design by NH', 'harden-by-design-by-nh' ); ?></p>
				</div>
				<div class="harden-by-nh-header__meta">
					<span class="harden-by-nh-header__version">
						<?php
						echo esc_html(
							sprintf(
								/* translators: %s: plugin version number. */
								__( 'Version %s', 'harden-by-design-by-nh' ),
								HARDEN_BY_NH_VERSION
							)
						);
						?>
					</span>
				</div>
			</header>
			<p class="harden-by-nh-status" role="status" aria-live="polite"></p>

			<h2 class="nav-tab-wrapper">
				<?php foreach ( $tabs as $slug => $tab_obj ) : ?>
					<a
						href="<?php echo esc_url( 'advanced' === $slug ? $base_url : add_query_arg( 'tab', $slug, $base_url ) ); ?>"
						class="nav-tab <?php echo $tab === $slug ? 'nav-tab-active' : ''; ?>"
					><?php echo esc_html( $tab_obj->label() ); ?></a>
				<?php endforeach; ?>
			</h2>

			<div class="harden-by-nh-stack">
			<?php
			if ( isset( $tabs[ $tab ] ) ) {
				$tabs[ $tab ]->render( $opts );
			}
			?>
			</div>
		</div>
		<?php
	}

	// ── Rendering helpers (used by tab classes) ──────────────────────────

	/**
	 * Open a settings group panel.
	 *
	 * @param string $title        Group title.
	 * @param string $intro        Optional short description below the title.
	 * @param bool   $bulk_actions When true, header shows a master switch for all toggles.
	 */
	public static function render_settings_card_open( string $title, string $intro = '', bool $bulk_actions = true ): void {
		echo '<div class="card harden-by-nh-settings-card">';
		echo '<div class="harden-by-nh-settings-card__head">';
		echo '<div class="harden-by-nh-settings-card__head-main">';
		echo '<h2 class="harden-by-nh-settings-card__title">' . esc_html( $title ) . '</h2>';
		if ( '' !== $intro ) {
			echo '<p class="description harden-by-nh-settings-card__intro">' . esc_html( $intro ) . '</p>';
		}
		echo '</div>';
		if ( $bulk_actions ) {
			$bulk_id = 'harden-bulk-' . wp_unique_id();
			echo '<div class="harden-by-nh-settings-card__bulk">';
			echo '<div class="harden-by-nh-switch-row harden-by-nh-bulk-switch-row">';
			printf(
				'<input type="checkbox" class="harden-by-nh-switch-input harden-by-nh-bulk-master-input" id="%1$s" value="1" aria-label="%2$s" />',
				esc_attr( $bulk_id ),
				esc_attr__( 'Enable or disable all options in this section', 'harden-by-design-by-nh' )
			);
			printf(
				'<label class="harden-by-nh-switch" for="%1$s"><span class="harden-by-nh-switch-ui" aria-hidden="true"></span></label>',
				esc_attr( $bulk_id )
			);
			echo '</div></div>';
		}
		echo '</div><div class="harden-by-nh-settings-card__body">';
	}

	/**
	 * Close panel opened by {@see self::render_settings_card_open()}.
	 */
	public static function render_settings_card_close(): void {
		echo '</div></div>';
	}

	/**
	 * Render a toggle switch.
	 *
	 * @param string $field    Option field key.
	 * @param string $label    Visible label text.
	 * @param bool   $checked  Whether the option is on.
	 * @param bool   $disabled Whether the control is disabled.
	 */
	public static function render_switch( string $field, string $label, bool $checked, bool $disabled = false ): void {
		$id = 'harden-switch-' . $field;
		?>
		<div class="harden-by-nh-switch-row">
			<input
				type="checkbox"
				class="harden-by-nh-switch-input"
				id="<?php echo esc_attr( $id ); ?>"
				data-field="<?php echo esc_attr( $field ); ?>"
				value="1"
				<?php checked( $checked ); ?>
				<?php disabled( $disabled ); ?>
			/>
			<label class="harden-by-nh-switch" for="<?php echo esc_attr( $id ); ?>">
				<span class="harden-by-nh-switch-ui" aria-hidden="true"></span>
				<span class="harden-by-nh-switch-label"><?php echo esc_html( $label ); ?></span>
			</label>
		</div>
		<?php
	}

	/**
	 * Toggle for a slug inside a list option (Pages tab).
	 *
	 * @param string $group    Option key (e.g. disabled_taxonomy_archives).
	 * @param string $slug     Taxonomy or post type slug.
	 * @param string $label    Label text.
	 * @param bool   $checked  Whether this slug is in the blocked list.
	 * @param bool   $disabled Whether interaction is disabled (master switch on).
	 */
	public static function render_page_slug_switch( string $group, string $slug, string $label, bool $checked, bool $disabled ): void {
		$id = 'harden-page-' . $group . '-' . $slug;
		$id = preg_replace( '/[^a-zA-Z0-9_-]/', '-', $id );
		?>
		<div class="harden-by-nh-switch-row harden-by-nh-page-slug-row">
			<input
				type="checkbox"
				class="harden-by-nh-switch-input harden-by-nh-page-slug-input"
				id="<?php echo esc_attr( $id ); ?>"
				data-group="<?php echo esc_attr( $group ); ?>"
				data-slug="<?php echo esc_attr( $slug ); ?>"
				value="1"
				<?php checked( $checked ); ?>
				<?php disabled( $disabled ); ?>
			/>
			<label class="harden-by-nh-switch" for="<?php echo esc_attr( $id ); ?>">
				<span class="harden-by-nh-switch-ui" aria-hidden="true"></span>
				<span class="harden-by-nh-switch-label"><?php echo esc_html( $label ); ?></span>
			</label>
		</div>
		<?php
	}

	/**
	 * Render form-table rows automatically from schema entries for a tab + group.
	 *
	 * @param array<string, mixed> $opts  Current options.
	 * @param string               $tab   Schema tab slug.
	 * @param string               $group Schema group slug.
	 */
	public static function render_switch_rows_from_schema( array $opts, string $tab, string $group ): void {
		$entries = Harden_Option_Schema::labeled_entries_for( $tab, $group );
		if ( empty( $entries ) ) {
			return;
		}
		?>
		<table class="form-table" role="presentation">
			<?php foreach ( $entries as $key => $def ) : ?>
				<?php if ( 'bool' !== ( $def['type'] ?? '' ) ) { continue; } ?>
				<tr>
					<th scope="row"><?php echo esc_html( $def['label'] ); ?></th>
					<td>
						<?php
						self::render_switch(
							$key,
							$def['label'],
							! empty( $opts[ $key ] )
						);
						?>
						<?php if ( ! empty( $def['description'] ) ) : ?>
							<p class="description"><?php echo esc_html( $def['description'] ); ?></p>
						<?php endif; ?>
					</td>
				</tr>
			<?php endforeach; ?>
		</table>
		<?php
	}

	// ── Export / Import / Reset ───────────────────────────────────────────

	public static function handle_export_settings(): void {
		if ( ! current_user_can( self::CAP ) ) {
			wp_die( esc_html__( 'You do not have permission to export settings.', 'harden-by-design-by-nh' ), '', array( 'response' => 403 ) );
		}
		check_admin_referer( 'harden_by_nh_export_settings' );

		$settings = Harden_Options::get();

		foreach ( Harden_Option_Schema::secret_keys() as $secret_key ) {
			$filter = 'harden_by_nh_export_include_' . $secret_key;
			/** @var bool $include */
			if ( ! apply_filters( $filter, false ) ) {
				$settings[ $secret_key ] = '';
			}
		}

		$payload = array(
			'$schema'        => 'harden-by-nh/settings-export/v1',
			'exported_at'    => gmdate( 'c' ),
			'plugin_version' => HARDEN_BY_NH_VERSION,
			'settings'       => $settings,
		);

		/** This filter is documented in the old Harden_Admin class. */
		$payload = apply_filters( 'harden_by_nh_export_payload', $payload );
		if ( ! is_array( $payload ) || ! isset( $payload['settings'] ) || ! is_array( $payload['settings'] ) ) {
			wp_die( esc_html__( 'Export payload was invalid after filtering.', 'harden-by-design-by-nh' ), '', array( 'response' => 500 ) );
		}

		$filename = 'harden-by-nh-settings-' . gmdate( 'Y-m-d' ) . '.json';
		nocache_headers();
		header( 'Content-Type: application/json; charset=utf-8' );
		header( 'Content-Disposition: attachment; filename="' . $filename . '"' );
		header( 'X-Content-Type-Options: nosniff' );

		// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- JSON file download.
		echo wp_json_encode( $payload, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE );
		exit;
	}

	public static function handle_import_settings(): void {
		if ( ! current_user_can( self::CAP ) ) {
			wp_die( esc_html__( 'You do not have permission to import settings.', 'harden-by-design-by-nh' ), '', array( 'response' => 403 ) );
		}
		check_admin_referer( 'harden_by_nh_import_settings' );

		$tab = isset( $_POST['harden_redirect_tab'] ) ? sanitize_key( wp_unslash( (string) $_POST['harden_redirect_tab'] ) ) : 'settings';
		if ( ! in_array( $tab, self::tab_slugs(), true ) ) {
			$tab = 'settings';
		}
		$redirect = add_query_arg(
			array(
				'page' => self::PAGE_SLUG,
				'tab'  => $tab,
			),
			admin_url( 'options-general.php' )
		);

		if ( empty( $_FILES['harden_import_file'] ) || ! is_array( $_FILES['harden_import_file'] ) ) {
			wp_safe_redirect( add_query_arg( 'harden_import', 'error_upload', $redirect ) );
			exit;
		}

		$file = $_FILES['harden_import_file'];
		if ( ! empty( $file['error'] ) && UPLOAD_ERR_OK !== (int) $file['error'] ) {
			wp_safe_redirect( add_query_arg( 'harden_import', 'error_upload', $redirect ) );
			exit;
		}
		if ( empty( $file['tmp_name'] ) || ! is_uploaded_file( $file['tmp_name'] ) ) {
			wp_safe_redirect( add_query_arg( 'harden_import', 'error_upload', $redirect ) );
			exit;
		}
		if ( isset( $file['size'] ) && (int) $file['size'] > self::IMPORT_MAX_BYTES ) {
			wp_safe_redirect( add_query_arg( 'harden_import', 'error_size', $redirect ) );
			exit;
		}

		// phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents -- local tmp upload only.
		$raw = file_get_contents( $file['tmp_name'] );
		if ( false === $raw || '' === trim( $raw ) ) {
			wp_safe_redirect( add_query_arg( 'harden_import', 'error_empty', $redirect ) );
			exit;
		}

		$decoded = json_decode( $raw, true );
		if ( null === $decoded || ! is_array( $decoded ) ) {
			wp_safe_redirect( add_query_arg( 'harden_import', 'error_json', $redirect ) );
			exit;
		}

		$settings = null;
		if ( isset( $decoded['settings'] ) && is_array( $decoded['settings'] ) ) {
			$settings = $decoded['settings'];
		} else {
			$defaults = Harden_Options::defaults();
			$overlap  = array_intersect_key( $decoded, $defaults );
			if ( ! empty( $overlap ) ) {
				$settings = $decoded;
			}
		}

		if ( null === $settings ) {
			wp_safe_redirect( add_query_arg( 'harden_import', 'error_no_settings', $redirect ) );
			exit;
		}

		/** This filter is documented in the old Harden_Admin class. */
		$settings = apply_filters( 'harden_by_nh_import_settings', $settings );
		if ( ! is_array( $settings ) ) {
			wp_safe_redirect( add_query_arg( 'harden_import', 'error_no_settings', $redirect ) );
			exit;
		}

		$defaults = Harden_Options::defaults();
		$merged   = array_merge( $defaults, array_intersect_key( $settings, $defaults ) );
		Harden_Options::update_all( $merged );

		wp_safe_redirect( add_query_arg( 'harden_import', 'success', $redirect ) );
		exit;
	}

	public static function handle_reset_settings(): void {
		if ( ! current_user_can( self::CAP ) ) {
			wp_die( esc_html__( 'You do not have permission to reset settings.', 'harden-by-design-by-nh' ), '', array( 'response' => 403 ) );
		}
		check_admin_referer( 'harden_by_nh_reset_settings' );

		$tab = isset( $_POST['harden_reset_redirect_tab'] ) ? sanitize_key( wp_unslash( (string) $_POST['harden_reset_redirect_tab'] ) ) : 'settings';
		if ( ! in_array( $tab, self::tab_slugs(), true ) ) {
			$tab = 'settings';
		}

		Harden_Options::update_all( Harden_Options::defaults() );

		$redirect = add_query_arg(
			array(
				'page'         => self::PAGE_SLUG,
				'tab'          => $tab,
				'harden_reset' => '1',
			),
			admin_url( 'options-general.php' )
		);
		wp_safe_redirect( $redirect );
		exit;
	}

	// ── Private helpers ──────────────────────────────────────────────────

	/**
	 * Admin notices after import redirect.
	 *
	 * @param string $current_tab Active settings tab slug.
	 */
	private static function maybe_display_import_export_notices( string $current_tab ): void {
		if ( 'settings' !== $current_tab ) {
			return;
		}
		if ( ! isset( $_GET['harden_import'] ) ) { // phpcs:ignore WordPress.Security.NonceVerification.Recommended
			return;
		}
		$code = sanitize_key( wp_unslash( (string) $_GET['harden_import'] ) ); // phpcs:ignore WordPress.Security.NonceVerification.Recommended
		$msg  = '';
		$err  = true;
		switch ( $code ) {
			case 'success':
				$msg = __( 'Settings were imported and saved to the database.', 'harden-by-design-by-nh' );
				$err = false;
				break;
			case 'error_upload':
				$msg = __( 'Could not read the uploaded file. Try again.', 'harden-by-design-by-nh' );
				break;
			case 'error_size':
				$msg = __( 'That file is too large (max 1 MB).', 'harden-by-design-by-nh' );
				break;
			case 'error_empty':
				$msg = __( 'The uploaded file was empty.', 'harden-by-design-by-nh' );
				break;
			case 'error_json':
				$msg = __( 'The file is not valid JSON.', 'harden-by-design-by-nh' );
				break;
			case 'error_no_settings':
				$msg = __( 'The JSON did not contain recognizable Harden settings (expected a "settings" object or option keys).', 'harden-by-design-by-nh' );
				break;
			default:
				return;
		}
		$class = $err ? 'notice notice-error is-dismissible' : 'notice notice-success is-dismissible';
		printf(
			'<div class="%1$s"><p>%2$s</p></div>',
			esc_attr( $class ),
			esc_html( $msg )
		);
	}

	/**
	 * Notice after reset to defaults.
	 *
	 * @param string $current_tab Active settings tab slug.
	 */
	private static function maybe_display_reset_notice( string $current_tab ): void {
		if ( 'settings' !== $current_tab ) {
			return;
		}
		if ( ! isset( $_GET['harden_reset'] ) ) { // phpcs:ignore WordPress.Security.NonceVerification.Recommended
			return;
		}
		printf(
			'<div class="notice notice-success is-dismissible"><p>%s</p></div>',
			esc_html__( 'All settings were reset to plugin defaults.', 'harden-by-design-by-nh' )
		);
	}

	/**
	 * Align saved option when DISALLOW_FILE_EDIT is forced by wp-config.
	 *
	 * @param array<string, mixed> $opts Current options.
	 */
	public static function sync_file_edit_option_if_forced_by_wp( array $opts ): void {
		if ( ! Harden_Options::is_file_editor_disabled() || ! empty( $opts['disallow_file_edit'] ) ) {
			return;
		}
		$opts['disallow_file_edit'] = true;
		Harden_Options::update_all( $opts );
	}

	// ── Tab registry ─────────────────────────────────────────────────────

	/**
	 * Lazy-initialised map of tab slug → tab instance.
	 *
	 * Each tab class must implement: slug(): string, label(): string, render(array $opts): void.
	 *
	 * @return array<string, object>
	 */
	private static function tabs(): array {
		if ( null !== self::$tab_instances ) {
			return self::$tab_instances;
		}

		/**
		 * Ordered list of tab class names.
		 *
		 * Each class must expose slug(), label(), and render(array $opts).
		 *
		 * @var list<class-string> $classes
		 */
		$classes = array(
			Harden_Admin_Tab_Advanced::class,
			Harden_Admin_Tab_Pages::class,
			Harden_Admin_Tab_Login::class,
			Harden_Admin_Tab_Frontend::class,
			Harden_Admin_Tab_Notifications::class,
			Harden_Admin_Tab_Integrations::class,
			Harden_Admin_Tab_Settings::class,
		);

		self::$tab_instances = array();
		foreach ( $classes as $class ) {
			$instance = new $class();
			self::$tab_instances[ $instance->slug() ] = $instance;
		}

		return self::$tab_instances;
	}

	/**
	 * Valid tab slugs in registration order.
	 *
	 * @return list<string>
	 */
	private static function tab_slugs(): array {
		return array_keys( self::tabs() );
	}
}
