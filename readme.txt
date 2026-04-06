=== HardenWP ===
Contributors: designbynh
Tags: security, hardening, recaptcha, turnstile
Requires at least: 6.0
Tested up to: 6.7
Stable tag: 2.0.0
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

Security-focused hardening (by Design by NH): URL blocking, modular login bot protection (reCAPTCHA v2/v3, Cloudflare Turnstile), reduced version exposure, frontend tweaks, and more.

== Changelog ==

= 2.0.0 =
* Login rescue link: turning on Block public login page auto-enables rescue and generates a new URL; turning block off clears rescue and the token; turning rescue off clears the token; each time rescue is turned on again, a new URL is created. Copy to clipboard shows a one-time-use notice. Regenerate requires rescue to be enabled. Successful use of the link also turns off the rescue toggle until you enable it again.
* Full architecture rewrite — declarative option schema drives defaults, sanitisation, AJAX allow-lists, and admin UI rendering from a single source of truth.
* Every toggle now has a human-friendly label and description; copy lives in the schema so new options only need one entry.
* God-class Harden_Admin (1 487 lines) split into Harden_Admin_Page + Harden_Admin_Ajax + 7 per-tab classes.
* Monolithic Harden_Features (976 lines) and Harden_Frontend (301 lines) replaced by 26 small single-responsibility feature classes under includes/features/.
* Unified provider interface: login CAPTCHA and SEO integrations now share a consistent instance-based pattern (Harden_Provider → Harden_Login_Provider / Harden_Seo_Provider). Adding a new provider is a one-file operation.
* Harden_Feature_Registry: central singleton that registers features + providers, fires extensibility hooks, and activates based on stored options.
* Shared Harden_Remote_Verify utility eliminates duplicate wp_remote_post + JSON decode logic across reCAPTCHA and Turnstile.
* Legacy recaptcha_enabled and recaptcha_version removed from stored defaults (computed at read time for backward compatibility).
* Dead code removed: Harden_Options::get_value(), empty ::init(), duplicate PHPDoc.
* 15 obsolete files deleted; includes/login-protection/ and includes/integrations/ directories removed.
* Option storage key (harden_by_nh_options) and all stored keys unchanged — zero user-facing migration needed.

= 1.4.3 =
* Login tab (renamed from reCAPTCHA): choose None, reCAPTCHA v2, reCAPTCHA v3, or Cloudflare Turnstile. Modular providers under includes/login-protection/; extend via harden_by_nh_register_login_captcha_providers and harden_by_nh_login_protection_provider_ids.
* Legacy installs migrate recaptcha_enabled + keys to login_protection_provider. JSON export omits Turnstile secret by default (filter: harden_by_nh_export_include_turnstile_secret). Old ?tab=recaptcha URLs redirect to Login.
* Settings nav: "Frontend cleanup" renamed to "Frontend".

= 1.4.2 =
* Integrations tab: optional Slim SEO sitemap sync with Public URL blocking (singles, taxonomy sitemaps, user sitemap when authors blocked). Modular code under includes/integrations/.

= 1.4.1 =
* Rebrand to HardenWP; settings header shows attribution and version; Tools menu label updated.
* Card layout uses responsive CSS Grid (multi-column when wide, single column on small viewports).

= 1.4.0 =
* Export omits reCAPTCHA secret by default (filter: harden_by_nh_export_include_recaptcha_secret).
* Tighter REST route exception matching (namespace prefix); optional block wp/v2/users for non–list_users; optional security headers + X-Powered-By removal.
* Advanced tab grouped (Security / Admin / Site features); Frontend tab renamed "Frontend cleanup".
* Reset all settings to defaults; sanitize_option merge whitelisted keys only; scripts/index.php silencer.
* reCAPTCHA v2 async/defer via script_loader_tag; comments disabled for late-registered post types; file editor switch disabled when wp-config forces editor on.

= 1.3.7 =
* Notifications: admin update UI hidden only for non-administrators; more automatic-update email toggles (core/plugin/theme/debug).

= 1.3.6 =
* Notifications tab: optional hiding of core update nag, admin bar Updates, plugin/theme/dashboard menu counts, and core auto-update emails.

= 1.3.5 =
* Advanced: optional disable public wp-login.php (403); filters for host/SSO compatibility.

= 1.3.4 =
* GitHub automatic updates (Plugin Update Checker).
