<?php
declare(strict_types=1);

/**
 * Central registry for features, login providers, and SEO providers.
 *
 * @package HardenByDesignByNH
 */

defined( 'ABSPATH' ) || exit;

/**
 * Singleton that collects every {@see Harden_Feature}, {@see Harden_Login_Provider},
 * and {@see Harden_Seo_Provider}, then activates them based on stored options.
 */
final class Harden_Feature_Registry {

	/** @var self|null */
	private static $instance = null;

	/** @var array<string, Harden_Feature> */
	private $features = array();

	/** @var array<string, Harden_Login_Provider> */
	private $login_providers = array();

	/** @var array<string, Harden_Seo_Provider> */
	private $seo_providers = array();

	private function __construct() {}

	/**
	 * Bootstrap the registry: register defaults, fire extensibility hooks, activate.
	 */
	public static function init(): void {
		$registry = new self();
		self::$instance = $registry;

		$registry->register_default_features();
		$registry->register_default_login_providers();
		$registry->register_default_seo_providers();

		/** Allow third‑party code to register additional features. */
		do_action( 'harden_by_nh_register_features' );

		/** @deprecated Use harden_by_nh_register_features instead. Kept for backward compat. */
		do_action( 'harden_by_nh_register_login_captcha_providers' );

		/** Allow third‑party code to register additional SEO providers. */
		do_action( 'harden_by_nh_register_seo_providers' );

		$registry->activate_features();
		$registry->activate_login_protection();
		$registry->activate_seo_providers();
	}

	/**
	 * Return the singleton (available after {@see self::init()}).
	 */
	public static function instance(): ?self {
		return self::$instance;
	}

	/* ------------------------------------------------------------------
	 * Registration
	 * ----------------------------------------------------------------*/

	public function register_feature( Harden_Feature $feature ): void {
		$this->features[ $feature->id() ] = $feature;
	}

	public function register_login_provider( Harden_Login_Provider $provider ): void {
		$this->login_providers[ $provider->id() ] = $provider;
	}

	public function register_seo_provider( Harden_Seo_Provider $provider ): void {
		$this->seo_providers[ $provider->id() ] = $provider;
	}

	/* ------------------------------------------------------------------
	 * Accessors
	 * ----------------------------------------------------------------*/

	/**
	 * @return array<string, Harden_Login_Provider>
	 */
	public function login_providers(): array {
		return $this->login_providers;
	}

	/**
	 * @return array<string, Harden_Seo_Provider>
	 */
	public function seo_providers(): array {
		return $this->seo_providers;
	}

	/**
	 * The currently selected login‑CAPTCHA provider (or null when set to "none").
	 */
	public function active_login_provider(): ?Harden_Login_Provider {
		$opts = Harden_Options::get();
		$id   = isset( $opts['login_protection_provider'] ) ? sanitize_key( (string) $opts['login_protection_provider'] ) : 'none';
		if ( 'none' === $id || '' === $id ) {
			return null;
		}
		return $this->login_providers[ $id ] ?? null;
	}

	/* ------------------------------------------------------------------
	 * Default registrations
	 * ----------------------------------------------------------------*/

	private function register_default_features(): void {
		$this->register_feature( new Harden_Feature_Disable_Emojis() );
		$this->register_feature( new Harden_Feature_Disable_Dashicons() );
		$this->register_feature( new Harden_Feature_Disable_Embeds() );
		$this->register_feature( new Harden_Feature_Remove_jQuery_Migrate() );
		$this->register_feature( new Harden_Feature_Remove_Global_Styles() );
		$this->register_feature( new Harden_Feature_Remove_Shortlink() );
		$this->register_feature( new Harden_Feature_Disable_RSS_Feeds() );
		$this->register_feature( new Harden_Feature_Remove_Feed_Links() );
		$this->register_feature( new Harden_Feature_Disable_Self_Pingbacks() );
		$this->register_feature( new Harden_Feature_Remove_REST_API_Links() );
		$this->register_feature( new Harden_Feature_Disable_Google_Maps() );
		$this->register_feature( new Harden_Feature_Disable_Password_Strength_Meter() );
		$this->register_feature( new Harden_Feature_Remove_Comment_URLs() );
		$this->register_feature( new Harden_Feature_Hide_WP_Version() );
		$this->register_feature( new Harden_Feature_Hide_WP_Branding() );
		$this->register_feature( new Harden_Feature_Block_WP_Login() );
		$this->register_feature( new Harden_Feature_Login_Rescue() );
		$this->register_feature( new Harden_Feature_Disable_Site_Editor() );
		$this->register_feature( new Harden_Feature_Disable_Comments() );
		$this->register_feature( new Harden_Feature_Disable_Application_Passwords() );
		$this->register_feature( new Harden_Feature_Disable_XMLRPC() );
		$this->register_feature( new Harden_Feature_REST_API_Policy() );
		$this->register_feature( new Harden_Feature_Block_REST_Users() );
		$this->register_feature( new Harden_Feature_Security_Headers() );
		$this->register_feature( new Harden_Feature_Disallow_File_Edit() );
		$this->register_feature( new Harden_Feature_Block_Public_URLs() );
		$this->register_feature( new Harden_Feature_Suppress_Update_Notifications() );
	}

	private function register_default_login_providers(): void {
		$this->register_login_provider( new Harden_Login_Recaptcha_V2() );
		$this->register_login_provider( new Harden_Login_Recaptcha_V3() );
		$this->register_login_provider( new Harden_Login_Turnstile() );
	}

	private function register_default_seo_providers(): void {
		$this->register_seo_provider( new Harden_Seo_WordPress_Core() );
		$this->register_seo_provider( new Harden_Seo_Slim_Seo() );
	}

	/* ------------------------------------------------------------------
	 * Activation
	 * ----------------------------------------------------------------*/

	/** @var list<string> Features that are always activated (they check options internally). */
	private const ALWAYS_REGISTER = array(
		'block_public_urls',
		'login_rescue',
		'suppress_update_notifications',
	);

	/** @var list<string> Features whose option is an enum; activate when value is not the default. */
	private const ENUM_FEATURES = array(
		'rest_api_policy',
	);

	private function activate_features(): void {
		$opts     = Harden_Options::get();
		$defaults = Harden_Option_Schema::defaults();

		foreach ( $this->features as $feature ) {
			$fid = $feature->id();

			if ( in_array( $fid, self::ALWAYS_REGISTER, true ) ) {
				$feature->register();
				continue;
			}

			if ( in_array( $fid, self::ENUM_FEATURES, true ) ) {
				$current = $opts[ $fid ] ?? ( $defaults[ $fid ] ?? '' );
				$default = $defaults[ $fid ] ?? '';
				if ( $current !== $default ) {
					$feature->register();
				}
				continue;
			}

			if ( ! empty( $opts[ $fid ] ) ) {
				$feature->register();
			}
		}
	}

	private function activate_login_protection(): void {
		add_action( 'login_enqueue_scripts', array( $this, 'login_enqueue_scripts' ) );
		add_action( 'login_form', array( $this, 'login_form' ) );
		add_filter( 'authenticate', array( $this, 'authenticate' ), 20, 3 );
		add_filter( 'script_loader_tag', array( $this, 'script_async_defer' ), 10, 3 );
	}

	private function activate_seo_providers(): void {
		foreach ( $this->seo_providers as $provider ) {
			if ( $provider->is_available() ) {
				$provider->register();
			}
		}
	}

	/* ------------------------------------------------------------------
	 * Login protection orchestration
	 * ----------------------------------------------------------------*/

	public function login_enqueue_scripts(): void {
		$p = $this->active_login_provider();
		if ( null === $p ) {
			return;
		}
		$opts = Harden_Options::get();
		if ( ! $p->is_configured( $opts ) ) {
			return;
		}
		$p->enqueue_login_assets( $opts );
	}

	public function login_form(): void {
		$p = $this->active_login_provider();
		if ( null === $p ) {
			return;
		}
		$opts = Harden_Options::get();
		if ( ! $p->is_configured( $opts ) ) {
			return;
		}
		wp_nonce_field( 'harden_login_captcha', 'harden_login_captcha_nonce' );
		$p->render_login_field( $opts );
	}

	/**
	 * @param WP_User|WP_Error|null $user     User or error.
	 * @param string                $username Login.
	 * @param string                $password Password.
	 * @return WP_User|WP_Error|null
	 */
	public function authenticate( $user, string $username, string $password ) {
		$p = $this->active_login_provider();
		if ( null === $p ) {
			return $user;
		}
		$opts = Harden_Options::get();
		if ( ! $p->is_configured( $opts ) ) {
			return $user;
		}
		return $p->verify_authenticate( $user, $username, $password );
	}

	/**
	 * Add async/defer to external CAPTCHA scripts on the login screen.
	 *
	 * @param string $tag    Full script HTML.
	 * @param string $handle Script handle.
	 * @param string $src    Source URL.
	 */
	public function script_async_defer( string $tag, string $handle, string $src ): string {
		unset( $src );
		if ( ! in_array( $handle, array( 'harden-recaptcha-v2', 'harden-turnstile' ), true ) ) {
			return $tag;
		}
		if ( false !== stripos( $tag, ' async' ) || false !== stripos( $tag, ' defer' ) ) {
			return $tag;
		}
		return str_replace( '<script ', '<script async defer ', $tag );
	}
}
