/**
 * HardenWP admin settings — auto-save toggles, login protection, REST policy, and bulk controls.
 */
(function ($) {
	'use strict';

	var $status = $('.harden-by-nh-status');
	var hideTimer;
	var loginProtectionTimer;

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

	function applyRescueSwitchResponse(data) {
		if (!data || typeof data.rescue_url === 'undefined') {
			return;
		}
		var url = data.rescue_url || '';
		$('#harden-login-rescue-url').text(url);
		if (url) {
			$('#harden-login-rescue-url-wrap').prop('hidden', false);
			$('#harden-login-rescue-empty').prop('hidden', true);
			$('#harden-login-rescue-copy').prop('disabled', false);
		} else {
			$('#harden-login-rescue-url-wrap').prop('hidden', true);
			$('#harden-login-rescue-empty').prop('hidden', false);
			$('#harden-login-rescue-copy').prop('disabled', true);
		}
		if (typeof data.login_rescue_enabled === 'boolean') {
			$('#harden-switch-login_rescue_enabled').prop(
				'checked',
				data.login_rescue_enabled
			);
		}
	}

	function copyRescueUrlToClipboard(onSuccess, onFail) {
		var text = $('#harden-login-rescue-url').text();
		if (!text) {
			onFail();
			return;
		}
		if (navigator.clipboard && navigator.clipboard.writeText) {
			navigator.clipboard.writeText(text).then(onSuccess).catch(onFail);
			return;
		}
		var ta = document.createElement('textarea');
		ta.value = text;
		ta.setAttribute('readonly', '');
		ta.style.position = 'absolute';
		ta.style.left = '-9999px';
		document.body.appendChild(ta);
		ta.select();
		try {
			if (document.execCommand('copy')) {
				onSuccess();
			} else {
				onFail();
			}
		} catch (e) {
			onFail();
		}
		document.body.removeChild(ta);
	}

	function postSwitchAjax(field, checked) {
		return $.post(
			hardenByNH.ajaxUrl,
			{
				action: 'harden_by_nh_save_switch',
				nonce: hardenByNH.nonce,
				field: field,
				value: checked ? '1' : '0',
			},
			null,
			'json'
		).done(function (res) {
			if (
				res &&
				res.success &&
				res.data &&
				res.data.field === 'disallow_file_edit' &&
				typeof res.data.file_editor_effective === 'boolean'
			) {
				$('#harden-switch-disallow_file_edit').prop(
					'checked',
					res.data.file_editor_effective
				);
			}
			if (res && res.success && res.data) {
				applyRescueSwitchResponse(res.data);
			}
		});
	}

	function postSwitch(field, checked) {
		setStatus(hardenByNH.i18n.saving, false);
		return postSwitchAjax(field, checked)
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

	function toggleLoginPanels() {
		if (!$('#harden-login-tab-root').length) {
			return;
		}
		var v = $('#harden-login-provider').val();
		$('#harden-login-panel-google').prop(
			'hidden',
			v !== 'recaptcha_v2' && v !== 'recaptcha_v3'
		);
		$('#harden-login-panel-v3-score').prop('hidden', v !== 'recaptcha_v3');
		$('#harden-login-panel-turnstile').prop('hidden', v !== 'turnstile');
	}

	function saveLoginProtection() {
		if (!$('#harden-login-tab-root').length) {
			return;
		}
		setStatus(hardenByNH.i18n.saving, false);
		$.post(
			hardenByNH.ajaxUrl,
			{
				action: 'harden_by_nh_save_login_protection',
				nonce: hardenByNH.nonce,
				login_protection_provider: $('#harden-login-provider').val(),
				recaptcha_site_key: $('#harden-recaptcha-site-key').val(),
				recaptcha_secret_key: $('#harden-recaptcha-secret-key').val(),
				recaptcha_v3_score_threshold: $('#harden-recaptcha-v3-score').val(),
				turnstile_site_key: $('#harden-turnstile-site-key').val(),
				turnstile_secret_key: $('#harden-turnstile-secret-key').val(),
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
		var $el = $(this);
		if ($el.hasClass('harden-by-nh-bulk-master-input')) {
			return;
		}
		var field = $el.attr('data-field');
		if (!field) {
			return;
		}
		postSwitch(field, this.checked);
	});

	function postPageSlugAjax(group, slug, checked, $input) {
		return $.post(
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
				if (!(res && res.success) && $input && $input.length) {
					$input.prop('checked', !checked);
				}
			})
			.fail(function () {
				if ($input && $input.length) {
					$input.prop('checked', !checked);
				}
			});
	}

	function postPageSlug(group, slug, checked, $input) {
		setStatus(hardenByNH.i18n.saving, false);
		return postPageSlugAjax(group, slug, checked, $input)
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

	$(document).on('change', '.harden-by-nh-page-slug-input', function () {
		var $el = $(this);
		var group = $el.attr('data-group');
		var slug = $el.attr('data-slug');
		if (!group || !slug) {
			return;
		}
		postPageSlug(group, slug, this.checked, $el);
	});

	function getCardToggleInputs($card) {
		return $card.find(
			'.harden-by-nh-settings-card__body .harden-by-nh-switch-input:not(:disabled)'
		);
	}

	function syncBulkMasterState($card) {
		var $master = $card.find('.harden-by-nh-bulk-master-input');
		if (!$master.length) {
			return;
		}
		var $inputs = getCardToggleInputs($card);
		if (!$inputs.length) {
			$master.prop({ disabled: true, checked: false });
			try {
				$master[0].indeterminate = false;
			} catch (e) {}
			return;
		}
		$master.prop('disabled', false);
		var on = 0;
		var total = $inputs.length;
		$inputs.each(function () {
			if ($(this).prop('checked')) {
				on++;
			}
		});
		var el = $master[0];
		if (on === 0) {
			el.checked = false;
			el.indeterminate = false;
		} else if (on === total) {
			el.checked = true;
			el.indeterminate = false;
		} else {
			el.checked = false;
			el.indeterminate = true;
		}
	}

	function buildBulkQueue($card, enable) {
		var queue = [];
		getCardToggleInputs($card).each(function () {
			var $el = $(this);
			if ($el.prop('checked') === enable) {
				return;
			}
			var field = $el.attr('data-field');
			if (field) {
				queue.push({ type: 'field', $el: $el, field: field });
				return;
			}
			var group = $el.attr('data-group');
			var slug = $el.attr('data-slug');
			if (group && slug) {
				queue.push({ type: 'slug', $el: $el, group: group, slug: slug });
			}
		});
		return queue;
	}

	function runBulkForCard($card, enable) {
		var $master = $card.find('.harden-by-nh-bulk-master-input');
		var queue = buildBulkQueue($card, enable);
		if (!queue.length) {
			setStatus(hardenByNH.i18n.bulkNothing, false);
			syncBulkMasterState($card);
			return;
		}
		$master.prop('disabled', true);
		setStatus(hardenByNH.i18n.saving, false);
		var failed = false;
		var i = 0;

		function runNext() {
			if (i >= queue.length) {
				$master.prop('disabled', false);
				syncBulkMasterState($card);
				setStatus(
					failed ? hardenByNH.i18n.error : hardenByNH.i18n.saved,
					failed
				);
				return;
			}
			var item = queue[i++];
			item.$el.prop('checked', enable);
			var req =
				item.type === 'field'
					? postSwitchAjax(item.field, enable)
					: postPageSlugAjax(item.group, item.slug, enable, item.$el);
			req
				.done(function (res) {
					if (!(res && res.success)) {
						failed = true;
						if (item.type === 'field') {
							item.$el.prop('checked', !enable);
						}
					}
				})
				.fail(function () {
					failed = true;
					if (item.type === 'field') {
						item.$el.prop('checked', !enable);
					}
				})
				.always(runNext);
		}
		runNext();
	}

	$(document).on('change', '.harden-by-nh-bulk-master-input', function () {
		var $master = $(this);
		var $card = $master.closest('.harden-by-nh-settings-card');
		if (!$card.length) {
			return;
		}
		var enable = $master.prop('checked');
		runBulkForCard($card, enable);
	});

	$(document).on(
		'change',
		'.harden-by-nh-settings-card__body .harden-by-nh-switch-input',
		function () {
			var $card = $(this).closest('.harden-by-nh-settings-card');
			if ($card.length) {
				syncBulkMasterState($card);
			}
		}
	);

	$(function () {
		$('.harden-by-nh-settings-card').each(function () {
			syncBulkMasterState($(this));
		});
	});

	$(document).on('change', '#harden-login-provider', function () {
		toggleLoginPanels();
		saveLoginProtection();
	});

	$(document).on(
		'input',
		'#harden-login-tab-root .harden-login-protection-field',
		function () {
			if ($(this).attr('id') === 'harden-login-provider') {
				return;
			}
			window.clearTimeout(loginProtectionTimer);
			loginProtectionTimer = window.setTimeout(saveLoginProtection, 500);
		}
	);

	$(function () {
		toggleLoginPanels();
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

	$(document).on('click', '#harden-login-rescue-copy', function () {
		if ($(this).prop('disabled')) {
			return;
		}
		window.alert(hardenByNH.i18n.rescueOneTimeNotice);
		copyRescueUrlToClipboard(
			function () {
				setStatus(hardenByNH.i18n.rescueCopied, false);
			},
			function () {
				setStatus(hardenByNH.i18n.rescueCopyFailed, true);
			}
		);
	});

	$(document).on('click', '#harden-login-rescue-regenerate', function () {
		setStatus(hardenByNH.i18n.saving, false);
		$.post(
			hardenByNH.ajaxUrl,
			{
				action: 'harden_by_nh_regenerate_login_rescue',
				nonce: hardenByNH.nonce,
			},
			null,
			'json'
		)
			.done(function (res) {
				if (res && res.success && res.data && res.data.url) {
					$('#harden-login-rescue-url').text(res.data.url);
					$('#harden-login-rescue-url-wrap').prop('hidden', false);
					$('#harden-login-rescue-empty').prop('hidden', true);
					$('#harden-login-rescue-copy').prop('disabled', false);
					setStatus(hardenByNH.i18n.saved, false);
				} else {
					setStatus(hardenByNH.i18n.error, true);
				}
			})
			.fail(function () {
				setStatus(hardenByNH.i18n.error, true);
			});
	});

	$(document).on('submit', '#harden-by-nh-reset-form', function (e) {
		if (!window.confirm(hardenByNH.i18n.resetConfirm)) {
			e.preventDefault();
		}
	});
})(jQuery);
