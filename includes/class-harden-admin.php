<?php
/**
 * Tools menu settings page with tabs.
 *
 * @package HardenByDesignByNH
 */

declare(strict_types=1);

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class Harden_Admin
 */
final class Harden_Admin {

	private const PAGE_SLUG = 'harden-by-design-by-nh';
	private const CAP       = 'manage_options';

	private const IMPORT_MAX_BYTES = 1048576;

	public static function init(): void {
		add_action( 'admin_menu', array( self::class, 'register_menu' ) );
		add_action( 'admin_init', array( self::class, 'register_settings' ) );
		add_action( 'admin_enqueue_scripts', array( self::class, 'enqueue_assets' ) );
		add_action( 'admin_post_harden_by_nh_export_settings', array( self::class, 'handle_export_settings' ) );
		add_action( 'admin_post_harden_by_nh_import_settings', array( self::class, 'handle_import_settings' ) );
		add_action( 'wp_ajax_harden_by_nh_save_switch', array( self::class, 'ajax_save_switch' ) );
		add_action( 'wp_ajax_harden_by_nh_save_page_slug', array( self::class, 'ajax_save_page_slug' ) );
		add_action( 'wp_ajax_harden_by_nh_save_recaptcha', array( self::class, 'ajax_save_recaptcha' ) );
		add_action( 'wp_ajax_harden_by_nh_save_rest_policy', array( self::class, 'ajax_save_rest_policy' ) );
	}

	public static function register_menu(): void {
		add_management_page(
			__( 'Harden by Design by NH', 'harden-by-design-by-nh' ),
			__( 'Harden by NH', 'harden-by-design-by-nh' ),
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

	/**
	 * @param mixed $input Raw options.
	 * @return array<string, mixed>
	 */
	public static function sanitize_options( $input ): array {
		$existing = Harden_Options::get();

		if ( ! is_array( $input ) ) {
			return $existing;
		}

		// Avoid leading underscore in the key name: some hosts/plugins strip underscore-prefixed POST keys.
		$tab = isset( $input['harden_tab'] ) ? sanitize_key( (string) $input['harden_tab'] ) : '';
		unset( $input['harden_tab'] );

		if ( 'recaptcha' === $tab ) {
			$existing['recaptcha_enabled'] = ! empty( $input['recaptcha_enabled'] );
			$version                       = isset( $input['recaptcha_version'] ) ? sanitize_text_field( (string) $input['recaptcha_version'] ) : 'v3';
			$existing['recaptcha_version'] = in_array( $version, array( 'v2', 'v3' ), true ) ? $version : 'v3';
			$existing['recaptcha_site_key']   = isset( $input['recaptcha_site_key'] ) ? sanitize_text_field( (string) $input['recaptcha_site_key'] ) : '';
			$existing['recaptcha_secret_key'] = isset( $input['recaptcha_secret_key'] ) ? sanitize_text_field( (string) $input['recaptcha_secret_key'] ) : '';
			return Harden_Options::prepare_storage( $existing );
		}

		if ( 'pages' === $tab ) {
			$existing['disable_author_pages']           = ! empty( $input['disable_author_pages'] );
			$existing['disable_all_taxonomy_archives']  = ! empty( $input['disable_all_taxonomy_archives'] );
			$existing['disable_all_post_type_archives'] = ! empty( $input['disable_all_post_type_archives'] );
			$existing['disable_blog_index']             = ! empty( $input['disable_blog_index'] );
			$existing['disable_date_archives']          = ! empty( $input['disable_date_archives'] );
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
			$existing['hide_wp_version']               = ! empty( $input['hide_wp_version'] );
			$existing['hide_wp_branding']              = ! empty( $input['hide_wp_branding'] );
			$existing['disallow_file_edit']               = ! empty( $input['disallow_file_edit'] );
			$existing['disable_wp_login_page']            = ! empty( $input['disable_wp_login_page'] );
			$existing['disable_appearance_site_editor'] = ! empty( $input['disable_appearance_site_editor'] );
			$existing['disable_comments']              = ! empty( $input['disable_comments'] );
			$existing['disable_application_passwords'] = ! empty( $input['disable_application_passwords'] );
			$existing['disable_xmlrpc']                = ! empty( $input['disable_xmlrpc'] );
			$rp                                        = isset( $input['rest_api_policy'] ) ? sanitize_key( (string) $input['rest_api_policy'] ) : 'off';
			$existing['rest_api_policy']               = in_array( $rp, Harden_Options::rest_api_policies(), true ) ? $rp : 'off';
			return Harden_Options::prepare_storage( $existing );
		}

		if ( 'frontend' === $tab ) {
			foreach ( Harden_Options::frontend_toggle_keys() as $key ) {
				$existing[ $key ] = ! empty( $input[ $key ] );
			}
			return Harden_Options::prepare_storage( $existing );
		}

		/*
		 * update_option() always runs sanitize_option_{$option}. AJAX and direct saves
		 * do not send harden_tab; merge the incoming value over the stored option.
		 */
		return Harden_Options::prepare_storage( array_merge( $existing, $input ) );
	}

	/**
	 * @param string $hook_suffix Current admin screen hook.
	 */
	public static function enqueue_assets( string $hook_suffix ): void {
		if ( 'tools_page_' . self::PAGE_SLUG !== $hook_suffix ) {
			return;
		}

		wp_enqueue_style(
			'harden-by-nh-admin',
			HARDEN_BY_NH_URL . 'assets/admin-settings.css',
			array(),
			HARDEN_BY_NH_VERSION
		);

		wp_enqueue_script(
			'harden-by-nh-admin',
			HARDEN_BY_NH_URL . 'assets/admin-settings.js',
			array( 'jquery' ),
			HARDEN_BY_NH_VERSION,
			true
		);

		wp_localize_script(
			'harden-by-nh-admin',
			'hardenByNH',
			array(
				'ajaxUrl' => admin_url( 'admin-ajax.php' ),
				'nonce'   => wp_create_nonce( 'harden_by_nh_ajax' ),
				'i18n'    => array(
					'saving' => __( 'Saving…', 'harden-by-design-by-nh' ),
					'saved'  => __( 'Saved.', 'harden-by-design-by-nh' ),
					'error'  => __( 'Could not save. Try again.', 'harden-by-design-by-nh' ),
				),
			)
		);
	}

	public static function render_page(): void {
		if ( ! current_user_can( self::CAP ) ) {
			return;
		}

		$tab = isset( $_GET['tab'] ) ? sanitize_key( wp_unslash( (string) $_GET['tab'] ) ) : 'advanced'; // phpcs:ignore WordPress.Security.NonceVerification.Recommended
		if ( ! in_array( $tab, array( 'recaptcha', 'advanced', 'pages', 'frontend' ), true ) ) {
			$tab = 'advanced';
		}

		$base_url = admin_url( 'tools.php?page=' . self::PAGE_SLUG );
		$opts     = Harden_Options::get();

		if ( 'advanced' === $tab ) {
			self::sync_file_edit_option_if_forced_by_wp( $opts );
			$opts = Harden_Options::get();
		}

		settings_errors();
		self::maybe_display_import_export_notices();
		?>
		<div class="wrap harden-by-nh-wrap">
			<h1><?php echo esc_html( get_admin_page_title() ); ?></h1>
			<p class="harden-by-nh-status" role="status" aria-live="polite"></p>

			<h2 class="nav-tab-wrapper">
				<a href="<?php echo esc_url( $base_url ); ?>" class="nav-tab <?php echo $tab === 'advanced' ? 'nav-tab-active' : ''; ?>">
					<?php esc_html_e( 'Advanced', 'harden-by-design-by-nh' ); ?>
				</a>
				<a href="<?php echo esc_url( add_query_arg( 'tab', 'pages', $base_url ) ); ?>" class="nav-tab <?php echo $tab === 'pages' ? 'nav-tab-active' : ''; ?>">
					<?php esc_html_e( 'Pages', 'harden-by-design-by-nh' ); ?>
				</a>
				<a href="<?php echo esc_url( add_query_arg( 'tab', 'recaptcha', $base_url ) ); ?>" class="nav-tab <?php echo $tab === 'recaptcha' ? 'nav-tab-active' : ''; ?>">
					<?php esc_html_e( 'reCAPTCHA', 'harden-by-design-by-nh' ); ?>
				</a>
				<a href="<?php echo esc_url( add_query_arg( 'tab', 'frontend', $base_url ) ); ?>" class="nav-tab <?php echo $tab === 'frontend' ? 'nav-tab-active' : ''; ?>">
					<?php esc_html_e( 'Frontend', 'harden-by-design-by-nh' ); ?>
				</a>
			</h2>

			<?php
			if ( 'recaptcha' === $tab ) {
				self::render_recaptcha_fields( $opts );
			} elseif ( 'pages' === $tab ) {
				self::render_pages_fields( $opts );
			} elseif ( 'advanced' === $tab ) {
				self::render_advanced_fields( $opts );
			} else {
				self::render_frontend_fields( $opts );
			}
			self::render_import_export_section( $tab );
			?>
		</div>
		<?php
	}

	/**
	 * Admin notices after import redirect.
	 */
	private static function maybe_display_import_export_notices(): void {
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
	 * @param string $tab Current settings tab slug.
	 */
	private static function render_import_export_section( string $tab ): void {
		$export_url = wp_nonce_url(
			admin_url( 'admin-post.php?action=harden_by_nh_export_settings' ),
			'harden_by_nh_export_settings'
		);
		?>
		<hr style="margin:2em 0 1.25em;" />
		<h2><?php esc_html_e( 'Import / export settings', 'harden-by-design-by-nh' ); ?></h2>
		<div class="card" style="max-width: 46rem; padding: 12px 20px 20px;">
			<p><?php esc_html_e( 'Export downloads all current options as JSON. Import merges known keys into defaults, sanitizes them, and saves with update_option—then redirects back here.', 'harden-by-design-by-nh' ); ?></p>
			<p>
				<a href="<?php echo esc_url( $export_url ); ?>" class="button button-secondary"><?php esc_html_e( 'Download JSON export', 'harden-by-design-by-nh' ); ?></a>
			</p>
			<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" enctype="multipart/form-data">
				<?php wp_nonce_field( 'harden_by_nh_import_settings' ); ?>
				<input type="hidden" name="action" value="harden_by_nh_import_settings" />
				<input type="hidden" name="harden_redirect_tab" value="<?php echo esc_attr( $tab ); ?>" />
				<p>
					<label for="harden-import-json"><strong><?php esc_html_e( 'Import from JSON file', 'harden-by-design-by-nh' ); ?></strong></label><br />
					<input type="file" name="harden_import_file" id="harden-import-json" accept=".json,application/json" required />
				</p>
				<?php submit_button( __( 'Import and save', 'harden-by-design-by-nh' ), 'primary', 'harden_import_submit', false ); ?>
			</form>
		</div>
		<?php
	}

	public static function handle_export_settings(): void {
		if ( ! current_user_can( self::CAP ) ) {
			wp_die( esc_html__( 'You do not have permission to export settings.', 'harden-by-design-by-nh' ), '', array( 'response' => 403 ) );
		}
		check_admin_referer( 'harden_by_nh_export_settings' );

		$payload = array(
			'$schema'        => 'harden-by-nh/settings-export/v1',
			'exported_at'    => gmdate( 'c' ),
			'plugin_version' => HARDEN_BY_NH_VERSION,
			'settings'       => Harden_Options::get(),
		);
		/**
		 * Adjust the export payload (e.g. strip secrets) before download.
		 *
		 * @param array<string, mixed> $payload Export structure with settings key.
		 */
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

		$tab = isset( $_POST['harden_redirect_tab'] ) ? sanitize_key( wp_unslash( (string) $_POST['harden_redirect_tab'] ) ) : 'advanced';
		if ( ! in_array( $tab, array( 'recaptcha', 'advanced', 'pages', 'frontend' ), true ) ) {
			$tab = 'advanced';
		}
		$redirect = add_query_arg(
			array(
				'page' => self::PAGE_SLUG,
				'tab'  => $tab,
			),
			admin_url( 'tools.php' )
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

		/**
		 * Imported settings before merge with defaults and save.
		 *
		 * @param array<string, mixed> $settings Partial or full option keys.
		 */
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

	/**
	 * @param array<string, mixed> $opts Options.
	 */
	private static function render_recaptcha_fields( array $opts ): void {
		$version = isset( $opts['recaptcha_version'] ) ? (string) $opts['recaptcha_version'] : 'v3';
		?>
		<table class="form-table" role="presentation">
			<tr>
				<th scope="row"><?php esc_html_e( 'Login protection', 'harden-by-design-by-nh' ); ?></th>
				<td>
					<?php
					self::render_switch(
						'recaptcha_enabled',
						__( 'Enable reCAPTCHA on the login screen (wp-login.php)', 'harden-by-design-by-nh' ),
						! empty( $opts['recaptcha_enabled'] )
					);
					?>
				</td>
			</tr>
			<tr>
				<th scope="row"><label for="harden-recaptcha-version"><?php esc_html_e( 'reCAPTCHA version', 'harden-by-design-by-nh' ); ?></label></th>
				<td>
					<select class="harden-recaptcha-field" id="harden-recaptcha-version" data-field="recaptcha_version">
						<option value="v3" <?php selected( $version, 'v3' ); ?>><?php esc_html_e( 'v3 (invisible, score-based)', 'harden-by-design-by-nh' ); ?></option>
						<option value="v2" <?php selected( $version, 'v2' ); ?>><?php esc_html_e( 'v2 Checkbox (“I’m not a robot”)', 'harden-by-design-by-nh' ); ?></option>
					</select>
					<p class="description"><?php esc_html_e( 'Create keys in Google reCAPTCHA admin that match the version you select.', 'harden-by-design-by-nh' ); ?></p>
				</td>
			</tr>
			<tr>
				<th scope="row"><label for="harden-recaptcha-site-key"><?php esc_html_e( 'Site key', 'harden-by-design-by-nh' ); ?></label></th>
				<td>
					<input type="text" class="regular-text code harden-recaptcha-field" id="harden-recaptcha-site-key" data-field="recaptcha_site_key" value="<?php echo esc_attr( (string) $opts['recaptcha_site_key'] ); ?>" autocomplete="off" />
				</td>
			</tr>
			<tr>
				<th scope="row"><label for="harden-recaptcha-secret-key"><?php esc_html_e( 'Secret key', 'harden-by-design-by-nh' ); ?></label></th>
				<td>
					<input type="password" class="regular-text code harden-recaptcha-field" id="harden-recaptcha-secret-key" data-field="recaptcha_secret_key" value="<?php echo esc_attr( (string) $opts['recaptcha_secret_key'] ); ?>" autocomplete="new-password" />
					<p class="description"><?php esc_html_e( 'Stored in the database. Restrict who can access this screen.', 'harden-by-design-by-nh' ); ?></p>
				</td>
			</tr>
		</table>
		<?php
	}

	/**
	 * Pages tab: public URL / archive blocking.
	 *
	 * @param array<string, mixed> $opts Options.
	 */
	private static function render_pages_fields( array $opts ): void {
		$tax_list   = isset( $opts['disabled_taxonomy_archives'] ) && is_array( $opts['disabled_taxonomy_archives'] ) ? $opts['disabled_taxonomy_archives'] : array();
		$arch_list  = isset( $opts['disabled_post_type_archives'] ) && is_array( $opts['disabled_post_type_archives'] ) ? $opts['disabled_post_type_archives'] : array();
		$single_list = isset( $opts['disabled_post_type_singles'] ) && is_array( $opts['disabled_post_type_singles'] ) ? $opts['disabled_post_type_singles'] : array();

		$tax_master  = ! empty( $opts['disable_all_taxonomy_archives'] );
		$arch_master = ! empty( $opts['disable_all_post_type_archives'] );

		$taxonomies = get_taxonomies( array( 'public' => true ), 'objects' );
		/**
		 * Filter taxonomies listed under Pages → taxonomy archives.
		 *
		 * @param WP_Taxonomy[] $taxonomies Taxonomy objects keyed by name.
		 */
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
		/**
		 * Filter public post types listed on the Pages tab (singles and archives).
		 *
		 * @param WP_Post_Type[] $post_types Post type objects keyed by name.
		 */
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
		?>
		<p class="description" style="margin-bottom:1.25em;">
			<?php esc_html_e( 'These options answer with a 404 on the front end for matching URLs. Previews are not blocked. Test after changes—disabling singles for Posts or Pages can make a site unreachable.', 'harden-by-design-by-nh' ); ?>
		</p>
		<table class="form-table" role="presentation">
			<tr>
				<th scope="row"><?php esc_html_e( 'Author archives', 'harden-by-design-by-nh' ); ?></th>
				<td>
					<?php
					self::render_switch(
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
					self::render_switch(
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
					self::render_switch(
						'disable_blog_index',
						__( 'Disable the main blog / posts listing (is_home)', 'harden-by-design-by-nh' ),
						! empty( $opts['disable_blog_index'] )
					);
					?>
					<p class="description"><?php esc_html_e( 'Use when the site does not use a post feed on the front page or “Posts” page. Static front pages still work.', 'harden-by-design-by-nh' ); ?></p>
				</td>
			</tr>
			<tr>
				<th scope="row"><?php esc_html_e( 'Taxonomy archives', 'harden-by-design-by-nh' ); ?></th>
				<td>
					<?php
					self::render_switch(
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
								self::render_page_slug_switch(
									'disabled_taxonomy_archives',
									$slug,
									/* translators: 1: taxonomy label, 2: taxonomy slug */
									sprintf( __( 'Block archive URLs for %1$s (%2$s)', 'harden-by-design-by-nh' ), $lab, $slug ),
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
					self::render_switch(
						'disable_all_post_type_archives',
						__( 'Disable all post type archive URLs', 'harden-by-design-by-nh' ),
						$arch_master
					);
					?>
					<p class="description"><?php esc_html_e( 'Only affects types that register an archive (not the built-in Posts index—use “Posts index” above). When off, pick types below.', 'harden-by-design-by-nh' ); ?></p>
					<?php if ( ! empty( $archive_types ) ) : ?>
						<div class="harden-by-nh-page-lists" aria-label="<?php esc_attr_e( 'Per post type archive blocking', 'harden-by-design-by-nh' ); ?>">
							<?php
							foreach ( $archive_types as $name => $pt_obj ) {
								$lab = isset( $pt_obj->labels->name ) ? $pt_obj->labels->name : $name;
								self::render_page_slug_switch(
									'disabled_post_type_archives',
									$name,
									sprintf( __( 'Block archive URLs for %1$s (%2$s)', 'harden-by-design-by-nh' ), $lab, $name ),
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
						<?php esc_html_e( 'Blocks single URLs for each type you enable. For the built-in Post and Page types, the matching admin menu and “New → Post” / “New → Page” shortcuts are hidden and list/edit screens redirect to the dashboard. Types stay registered in WordPress; we do not unregister them.', 'harden-by-design-by-nh' ); ?>
					</p>
					<div class="harden-by-nh-page-lists" aria-label="<?php esc_attr_e( 'Per post type single blocking', 'harden-by-design-by-nh' ); ?>">
						<?php
						foreach ( $post_types as $name => $pt_obj ) {
							$lab = isset( $pt_obj->labels->name ) ? $pt_obj->labels->name : $name;
							self::render_page_slug_switch(
								'disabled_post_type_singles',
								$name,
								sprintf( __( 'Block single URLs for %1$s (%2$s)', 'harden-by-design-by-nh' ), $lab, $name ),
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
	}

	/**
	 * Toggle for a slug inside a list option (Pages tab). Uses AJAX, not data-field.
	 *
	 * @param string $group    Option key (e.g. disabled_taxonomy_archives).
	 * @param string $slug     Taxonomy or post type slug.
	 * @param string $label    Label text.
	 * @param bool   $checked  Whether this slug is in the blocked list.
	 * @param bool   $disabled Whether interaction is disabled (master switch on).
	 */
	private static function render_page_slug_switch( string $group, string $slug, string $label, bool $checked, bool $disabled ): void {
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
	 * If wp-config (or another layer) already set DISALLOW_FILE_EDIT, align the saved option so the UI matches.
	 *
	 * @param array<string, mixed> $opts Current options.
	 */
	private static function sync_file_edit_option_if_forced_by_wp( array $opts ): void {
		if ( ! Harden_Options::is_file_editor_disabled() || ! empty( $opts['disallow_file_edit'] ) ) {
			return;
		}
		$opts['disallow_file_edit'] = true;
		Harden_Options::update_all( $opts );
	}

	private static function render_advanced_fields( array $opts ): void {
		$file_edit_effective = Harden_Options::is_file_editor_disabled();
		$file_edit_defined   = defined( 'DISALLOW_FILE_EDIT' );
		$file_edit_note      = '';
		if ( $file_edit_defined ) {
			if ( DISALLOW_FILE_EDIT ) {
				$file_edit_note = __( 'DISALLOW_FILE_EDIT is true (usually in wp-config.php). The editor is off; this switch reflects that. Turning it off here only updates this plugin’s option—remove or change the constant to bring the editor back.', 'harden-by-design-by-nh' );
			} else {
				$file_edit_note = __( 'DISALLOW_FILE_EDIT is set to false in wp-config.php, so this plugin cannot define it as true until that line is removed or changed.', 'harden-by-design-by-nh' );
			}
		} elseif ( $file_edit_effective ) {
			$file_edit_note = __( 'File editing is disabled for this request (e.g. this plugin defined DISALLOW_FILE_EDIT because the option is on).', 'harden-by-design-by-nh' );
		}
		?>
		<table class="form-table" role="presentation">
			<tr>
				<th scope="row"><?php esc_html_e( 'Version strings', 'harden-by-design-by-nh' ); ?></th>
				<td>
					<?php
					self::render_switch(
						'hide_wp_version',
						__( 'Reduce WordPress version exposure (meta generator, script/query args, admin footer)', 'harden-by-design-by-nh' ),
						! empty( $opts['hide_wp_version'] )
					);
					?>
				</td>
			</tr>
			<tr>
				<th scope="row"><?php esc_html_e( 'Admin branding', 'harden-by-design-by-nh' ); ?></th>
				<td>
					<?php
					self::render_switch(
						'hide_wp_branding',
						__( 'Reduce WordPress branding in the admin (footer text, admin bar logo)', 'harden-by-design-by-nh' ),
						! empty( $opts['hide_wp_branding'] )
					);
					?>
				</td>
			</tr>
			<tr>
				<th scope="row"><?php esc_html_e( 'Theme & plugin file editor', 'harden-by-design-by-nh' ); ?></th>
				<td>
					<?php
					self::render_switch(
						'disallow_file_edit',
						__( 'Disallow editing theme and plugin files from the admin', 'harden-by-design-by-nh' ),
						$file_edit_effective
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
			<tr>
				<th scope="row"><?php esc_html_e( 'Appearance → Editor (Site Editor)', 'harden-by-design-by-nh' ); ?></th>
				<td>
					<?php
					self::render_switch(
						'disable_appearance_site_editor',
						__( 'Remove the Site Editor from the Appearance menu and block wp-admin/site-editor.php', 'harden-by-design-by-nh' ),
						! empty( $opts['disable_appearance_site_editor'] )
					);
					?>
					<p class="description"><?php esc_html_e( 'For block themes, this hides the full-site block editor (not the classic theme file editor—use Theme & plugin file editor for that). Direct URLs to the screen redirect to the dashboard.', 'harden-by-design-by-nh' ); ?></p>
				</td>
			</tr>
			<tr>
				<th scope="row"><?php esc_html_e( 'Login page (wp-login.php)', 'harden-by-design-by-nh' ); ?></th>
				<td>
					<?php
					self::render_switch(
						'disable_wp_login_page',
						__( 'Disable the public login page (403 for guests)', 'harden-by-design-by-nh' ),
						! empty( $opts['disable_wp_login_page'] )
					);
					?>
					<p class="description">
						<?php esc_html_e( 'Guests cannot use the normal username/password screen. Logout links and password-protected post forms (postpass) still work. Password reset and registration URLs on wp-login.php are blocked—use your host’s admin login (e.g. one-click) or disable this option if you get locked out. Developers can allow specific requests with the harden_by_nh_allow_wp_login_request or harden_by_nh_disabled_login_allowed_actions filters.', 'harden-by-design-by-nh' ); ?>
					</p>
				</td>
			</tr>
			<tr>
				<th scope="row"><?php esc_html_e( 'Comments', 'harden-by-design-by-nh' ); ?></th>
				<td>
					<?php
					self::render_switch(
						'disable_comments',
						__( 'Disable comments and pingbacks site-wide', 'harden-by-design-by-nh' ),
						! empty( $opts['disable_comments'] )
					);
					?>
					<p class="description"><?php esc_html_e( 'Closes comments on the front end, removes comment support from post types, and hides the Comments admin menu.', 'harden-by-design-by-nh' ); ?></p>
				</td>
			</tr>
			<tr>
				<th scope="row"><?php esc_html_e( 'Application Passwords', 'harden-by-design-by-nh' ); ?></th>
				<td>
					<?php
					self::render_switch(
						'disable_application_passwords',
						__( 'Disable Application Passwords (REST / remote access tokens)', 'harden-by-design-by-nh' ),
						! empty( $opts['disable_application_passwords'] )
					);
					?>
					<p class="description"><?php esc_html_e( 'Users will not see Application Passwords under their profile. Third-party apps that rely on them will stop working.', 'harden-by-design-by-nh' ); ?></p>
				</td>
			</tr>
			<tr>
				<th scope="row"><?php esc_html_e( 'XML-RPC', 'harden-by-design-by-nh' ); ?></th>
				<td>
					<?php
					self::render_switch(
						'disable_xmlrpc',
						__( 'Disable XML-RPC', 'harden-by-design-by-nh' ),
						! empty( $opts['disable_xmlrpc'] )
					);
					?>
					<p class="description"><?php esc_html_e( 'Blocks xmlrpc.php (classic remote publishing, some integrations). Jetpack and similar services may require it.', 'harden-by-design-by-nh' ); ?></p>
				</td>
			</tr>
			<tr>
				<th scope="row"><label for="harden-rest-api-policy"><?php esc_html_e( 'REST API', 'harden-by-design-by-nh' ); ?></label></th>
				<td>
					<select id="harden-rest-api-policy" class="harden-rest-policy-field">
						<?php
						$rp = isset( $opts['rest_api_policy'] ) ? (string) $opts['rest_api_policy'] : 'off';
						$choices = array(
							'off'         => __( 'No restriction', 'harden-by-design-by-nh' ),
							'guests'      => __( 'Block guests only (not logged in)', 'harden-by-design-by-nh' ),
							'non_admins'  => __( 'Block non-admins (require Administrator)', 'harden-by-design-by-nh' ),
						);
						foreach ( $choices as $val => $label ) {
							printf(
								'<option value="%1$s" %2$s>%3$s</option>',
								esc_attr( $val ),
								selected( $rp, $val, false ),
								esc_html( $label )
							);
						}
						?>
					</select>
					<p class="description"><?php esc_html_e( 'Some plugin routes are exempt (filter: harden_by_nh_rest_route_exceptions). Editors and other roles lose REST access when “non-admins” is selected (block editor in admin still works for Administrators).', 'harden-by-design-by-nh' ); ?></p>
				</td>
			</tr>
		</table>
		<?php
	}

	/**
	 * @param array<string, mixed> $opts Options.
	 */
	private static function render_frontend_fields( array $opts ): void {
		$rows = array(
			array(
				'disable_emojis',
				__( 'Disable emojis', 'harden-by-design-by-nh' ),
				__( 'Removes WordPress emoji scripts and styles on front and in admin.', 'harden-by-design-by-nh' ),
			),
			array(
				'disable_dashicons',
				__( 'Disable Dashicons (front)', 'harden-by-design-by-nh' ),
				__( 'Dequeues Dashicons for visitors who are not logged in. Logged-in users still load them (admin bar).', 'harden-by-design-by-nh' ),
			),
			array(
				'disable_embeds',
				__( 'Disable embeds', 'harden-by-design-by-nh' ),
				__( 'Disables oEmbed discovery, wp-embed, and related rewrite rules.', 'harden-by-design-by-nh' ),
			),
			array(
				'remove_jquery_migrate',
				__( 'Remove jQuery Migrate', 'harden-by-design-by-nh' ),
				__( 'Drops the jquery-migrate dependency on the front end. Test your theme and plugins for older jQuery code.', 'harden-by-design-by-nh' ),
			),
			array(
				'remove_shortlink',
				__( 'Remove shortlink', 'harden-by-design-by-nh' ),
				__( 'Removes the shortlink HTTP header and head tag.', 'harden-by-design-by-nh' ),
			),
			array(
				'disable_rss_feeds',
				__( 'Disable RSS feeds', 'harden-by-design-by-nh' ),
				__( 'Blocks feed URLs and shows a message. Combine with “Remove feed links” to hide head links.', 'harden-by-design-by-nh' ),
			),
			array(
				'remove_feed_links',
				__( 'Remove feed links from HTML head', 'harden-by-design-by-nh' ),
				__( 'Removes feed discovery link tags (does not stop feeds unless you also disable feeds).', 'harden-by-design-by-nh' ),
			),
			array(
				'disable_self_pingbacks',
				__( 'Disable self pingbacks', 'harden-by-design-by-nh' ),
				__( 'Stops WordPress from sending pingbacks to your own site when you link to yourself.', 'harden-by-design-by-nh' ),
			),
			array(
				'remove_rest_api_links',
				__( 'Remove REST API links from head', 'harden-by-design-by-nh' ),
				__( 'Removes the REST API link tag, header, and RSD reference. Does not disable the API (use Advanced → REST API).', 'harden-by-design-by-nh' ),
			),
			array(
				'disable_google_maps',
				__( 'Disable Google Maps scripts', 'harden-by-design-by-nh' ),
				__( 'Strips maps.googleapis.com / maps.google.com / gstatic map script tags from HTML output (output buffering).', 'harden-by-design-by-nh' ),
			),
			array(
				'disable_password_strength_meter',
				__( 'Disable password strength meter', 'harden-by-design-by-nh' ),
				__( 'Dequeues zxcvbn and password-strength-meter on the front end (not on wp-login.php or WooCommerce account).', 'harden-by-design-by-nh' ),
			),
			array(
				'remove_comment_urls',
				__( 'Remove comment author URLs', 'harden-by-design-by-nh' ),
				__( 'Hides author links, clears author URL, and removes the website field from the comment form.', 'harden-by-design-by-nh' ),
			),
			array(
				'remove_global_styles',
				__( 'Remove global styles (theme.json)', 'harden-by-design-by-nh' ),
				__( 'Stops wp_enqueue_global_styles. May affect block theme styling; test carefully.', 'harden-by-design-by-nh' ),
			),
		);
		?>
		<p class="description" style="margin-bottom:1em;">
			<?php esc_html_e( 'Hiding the WordPress version and disabling comments site-wide are on the Advanced tab. Public URL and archive options are on the Pages tab.', 'harden-by-design-by-nh' ); ?>
		</p>
		<table class="form-table" role="presentation">
			<?php foreach ( $rows as $row ) : ?>
				<tr>
					<th scope="row"><?php echo esc_html( $row[1] ); ?></th>
					<td>
						<?php
						self::render_switch(
							$row[0],
							$row[1],
							! empty( $opts[ $row[0] ] )
						);
						?>
						<p class="description"><?php echo esc_html( $row[2] ); ?></p>
					</td>
				</tr>
			<?php endforeach; ?>
		</table>
		<?php
	}

	/**
	 * Toggle switch (checkbox underneath for Settings API compatibility).
	 *
	 * @param string $field   Option field key (no brackets).
	 * @param string $label   Visible label text.
	 * @param bool   $checked Whether the option is on.
	 */
	private static function render_switch( string $field, string $label, bool $checked ): void {
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
			/>
			<label class="harden-by-nh-switch" for="<?php echo esc_attr( $id ); ?>">
				<span class="harden-by-nh-switch-ui" aria-hidden="true"></span>
				<span class="harden-by-nh-switch-label"><?php echo esc_html( $label ); ?></span>
			</label>
		</div>
		<?php
	}

	public static function ajax_save_switch(): void {
		check_ajax_referer( 'harden_by_nh_ajax', 'nonce' );

		if ( ! current_user_can( self::CAP ) ) {
			wp_send_json_error( array( 'message' => __( 'Forbidden.', 'harden-by-design-by-nh' ) ), 403 );
		}

		$field = isset( $_POST['field'] ) ? sanitize_key( wp_unslash( (string) $_POST['field'] ) ) : '';
		if ( ! in_array( $field, Harden_Options::boolean_keys(), true ) ) {
			wp_send_json_error( array( 'message' => __( 'Invalid field.', 'harden-by-design-by-nh' ) ), 400 );
		}

		$raw = isset( $_POST['value'] ) ? wp_unslash( $_POST['value'] ) : '0';
		$on  = ( $raw === '1' || $raw === 1 || $raw === true || $raw === 'true' );

		$opts           = Harden_Options::get();
		$opts[ $field ] = $on;
		Harden_Options::update_all( $opts );

		$data = array(
			'field' => $field,
			'value' => $on,
		);
		if ( 'disallow_file_edit' === $field ) {
			$data['file_editor_effective'] = Harden_Options::is_file_editor_disabled();
		}

		wp_send_json_success( $data );
	}

	public static function ajax_save_page_slug(): void {
		check_ajax_referer( 'harden_by_nh_ajax', 'nonce' );

		if ( ! current_user_can( self::CAP ) ) {
			wp_send_json_error( array( 'message' => __( 'Forbidden.', 'harden-by-design-by-nh' ) ), 403 );
		}

		$group = isset( $_POST['group'] ) ? sanitize_key( wp_unslash( (string) $_POST['group'] ) ) : '';
		$slug  = isset( $_POST['slug'] ) ? sanitize_key( wp_unslash( (string) $_POST['slug'] ) ) : '';
		if ( ! in_array( $group, Harden_Options::page_slug_list_keys(), true ) || '' === $slug ) {
			wp_send_json_error( array( 'message' => __( 'Invalid request.', 'harden-by-design-by-nh' ) ), 400 );
		}

		if ( 'disabled_taxonomy_archives' === $group && ! taxonomy_exists( $slug ) ) {
			wp_send_json_error( array( 'message' => __( 'Unknown taxonomy.', 'harden-by-design-by-nh' ) ), 400 );
		}
		if ( ( 'disabled_post_type_archives' === $group || 'disabled_post_type_singles' === $group ) && ! post_type_exists( $slug ) ) {
			wp_send_json_error( array( 'message' => __( 'Unknown post type.', 'harden-by-design-by-nh' ) ), 400 );
		}

		$raw = isset( $_POST['value'] ) ? wp_unslash( $_POST['value'] ) : '0';
		$on  = ( $raw === '1' || $raw === 1 || $raw === true || $raw === 'true' );

		$opts = Harden_Options::get();
		$list = isset( $opts[ $group ] ) && is_array( $opts[ $group ] ) ? $opts[ $group ] : array();
		$list = Harden_Options::sanitize_slug_list( $list );

		if ( $on ) {
			if ( ! in_array( $slug, $list, true ) ) {
				$list[] = $slug;
			}
		} else {
			$list = array_values( array_diff( $list, array( $slug ) ) );
		}

		$opts[ $group ] = $list;
		Harden_Options::update_all( $opts );

		wp_send_json_success(
			array(
				'group' => $group,
				'slug'  => $slug,
				'value' => $on,
			)
		);
	}

	public static function ajax_save_recaptcha(): void {
		check_ajax_referer( 'harden_by_nh_ajax', 'nonce' );

		if ( ! current_user_can( self::CAP ) ) {
			wp_send_json_error( array( 'message' => __( 'Forbidden.', 'harden-by-design-by-nh' ) ), 403 );
		}

		$opts = Harden_Options::get();

		if ( isset( $_POST['recaptcha_version'] ) ) {
			$v = sanitize_text_field( wp_unslash( (string) $_POST['recaptcha_version'] ) );
			$opts['recaptcha_version'] = in_array( $v, array( 'v2', 'v3' ), true ) ? $v : 'v3';
		}

		if ( isset( $_POST['recaptcha_site_key'] ) ) {
			$opts['recaptcha_site_key'] = sanitize_text_field( wp_unslash( (string) $_POST['recaptcha_site_key'] ) );
		}

		if ( isset( $_POST['recaptcha_secret_key'] ) ) {
			$opts['recaptcha_secret_key'] = sanitize_text_field( wp_unslash( (string) $_POST['recaptcha_secret_key'] ) );
		}

		Harden_Options::update_all( $opts );

		wp_send_json_success( array( 'saved' => true ) );
	}

	public static function ajax_save_rest_policy(): void {
		check_ajax_referer( 'harden_by_nh_ajax', 'nonce' );

		if ( ! current_user_can( self::CAP ) ) {
			wp_send_json_error( array( 'message' => __( 'Forbidden.', 'harden-by-design-by-nh' ) ), 403 );
		}

		$raw = isset( $_POST['rest_api_policy'] ) ? sanitize_key( wp_unslash( (string) $_POST['rest_api_policy'] ) ) : 'off';
		$policy = in_array( $raw, Harden_Options::rest_api_policies(), true ) ? $raw : 'off';

		$opts                      = Harden_Options::get();
		$opts['rest_api_policy'] = $policy;
		Harden_Options::update_all( $opts );

		wp_send_json_success( array( 'rest_api_policy' => $policy ) );
	}
}
