/**
 * Auto-save switches and reCAPTCHA fields via admin-ajax.
 */
(function ($) {
	'use strict';

	var $status = $('.harden-by-nh-status');
	var hideTimer;
	var recaptchaTimer;

	function setStatus(msg, isError) {
		$status.text(msg || '');
		$status.toggleClass('harden-by-nh-status--error', !!isError);
		window.clearTimeout(hideTimer);
		if (msg && !isError && msg === hardenByNH.i18n.saved) {
			hideTimer = window.setTimeout(function () {
				$status.text('');
			}, 2500);
		}
	}

	function postSwitch(field, checked) {
		setStatus(hardenByNH.i18n.saving, false);
		$.post(
			hardenByNH.ajaxUrl,
			{
				action: 'harden_by_nh_save_switch',
				nonce: hardenByNH.nonce,
				field: field,
				value: checked ? '1' : '0',
			},
			null,
			'json'
		)
			.done(function (res) {
				if (res && res.success) {
					if (
						res.data &&
						res.data.field === 'disallow_file_edit' &&
						typeof res.data.file_editor_effective === 'boolean'
					) {
						$('#harden-switch-disallow_file_edit').prop(
							'checked',
							res.data.file_editor_effective
						);
					}
					setStatus(hardenByNH.i18n.saved, false);
				} else {
					setStatus(hardenByNH.i18n.error, true);
				}
			})
			.fail(function () {
				setStatus(hardenByNH.i18n.error, true);
			});
	}

	function saveRecaptcha() {
		if (!$('#harden-recaptcha-version').length) {
			return;
		}
		setStatus(hardenByNH.i18n.saving, false);
		$.post(
			hardenByNH.ajaxUrl,
			{
				action: 'harden_by_nh_save_recaptcha',
				nonce: hardenByNH.nonce,
				recaptcha_version: $('#harden-recaptcha-version').val(),
				recaptcha_site_key: $('#harden-recaptcha-site-key').val(),
				recaptcha_secret_key: $('#harden-recaptcha-secret-key').val(),
			},
			null,
			'json'
		)
			.done(function (res) {
				if (res && res.success) {
					setStatus(hardenByNH.i18n.saved, false);
				} else {
					setStatus(hardenByNH.i18n.error, true);
				}
			})
			.fail(function () {
				setStatus(hardenByNH.i18n.error, true);
			});
	}

	$(document).on('change', '.harden-by-nh-switch-input', function () {
		var field = $(this).data('field');
		if (!field) {
			return;
		}
		postSwitch(field, this.checked);
	});

	function postPageSlug(group, slug, checked, $input) {
		var revertTo = !checked;
		setStatus(hardenByNH.i18n.saving, false);
		$.post(
			hardenByNH.ajaxUrl,
			{
				action: 'harden_by_nh_save_page_slug',
				nonce: hardenByNH.nonce,
				group: group,
				slug: slug,
				value: checked ? '1' : '0',
			},
			null,
			'json'
		)
			.done(function (res) {
				if (res && res.success) {
					setStatus(hardenByNH.i18n.saved, false);
				} else {
					if ($input && $input.length) {
						$input.prop('checked', revertTo);
					}
					setStatus(hardenByNH.i18n.error, true);
				}
			})
			.fail(function () {
				if ($input && $input.length) {
					$input.prop('checked', revertTo);
				}
				setStatus(hardenByNH.i18n.error, true);
			});
	}

	$(document).on('change', '.harden-by-nh-page-slug-input', function () {
		var $el = $(this);
		var group = $el.data('group');
		var slug = $el.data('slug');
		if (!group || !slug) {
			return;
		}
		postPageSlug(group, slug, this.checked, $el);
	});

	$(document).on('change', '#harden-recaptcha-version', function () {
		saveRecaptcha();
	});

	$(document).on('input', '#harden-recaptcha-site-key, #harden-recaptcha-secret-key', function () {
		window.clearTimeout(recaptchaTimer);
		recaptchaTimer = window.setTimeout(saveRecaptcha, 500);
	});

	function saveRestPolicy() {
		if (!$('#harden-rest-api-policy').length) {
			return;
		}
		setStatus(hardenByNH.i18n.saving, false);
		$.post(
			hardenByNH.ajaxUrl,
			{
				action: 'harden_by_nh_save_rest_policy',
				nonce: hardenByNH.nonce,
				rest_api_policy: $('#harden-rest-api-policy').val(),
			},
			null,
			'json'
		)
			.done(function (res) {
				if (res && res.success) {
					setStatus(hardenByNH.i18n.saved, false);
				} else {
					setStatus(hardenByNH.i18n.error, true);
				}
			})
			.fail(function () {
				setStatus(hardenByNH.i18n.error, true);
			});
	}

	$(document).on('change', '#harden-rest-api-policy', function () {
		saveRestPolicy();
	});
})(jQuery);
