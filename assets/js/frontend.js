/**
 * WP Easy Mail 前台交互。
 */
(function () {
	'use strict';

	if (window.WPEM_FRONTEND_INITIALIZED) {
		return;
	}
	window.WPEM_FRONTEND_INITIALIZED = true;

	/**
	 * 从全局对象与表单 data 属性合并配置。
	 *
	 * @param {HTMLFormElement|null} form 表单元素。
	 * @returns {{ajaxUrl: string, siteKey: string, recaptchaEnabled: boolean}}
	 */
	function resolveConfig(form) {
		var config = window.WPEasyMail || {};

		if (form) {
			if (!config.ajaxUrl && form.dataset.ajaxUrl) {
				config.ajaxUrl = form.dataset.ajaxUrl;
			}
			if (!config.siteKey && form.dataset.siteKey) {
				config.siteKey = form.dataset.siteKey;
			}
			if (!config.recaptchaEnabled && form.dataset.recaptcha === '1') {
				config.recaptchaEnabled = true;
			}
		}

		window.WPEasyMail = config;

		return {
			ajaxUrl: config.ajaxUrl || '',
			siteKey: config.siteKey || '',
			recaptchaEnabled: Boolean(config.recaptchaEnabled),
		};
	}

	/**
	 * 显示表单响应消息。
	 *
	 * @param {HTMLFormElement} form 表单元素。
	 * @param {string} message 消息内容。
	 * @param {boolean} success 是否成功。
	 * @returns {void}
	 */
	function showResponse(form, message, success) {
		var box = form.querySelector('.wpem-response');
		if (!box) {
			return;
		}
		box.textContent = message;
		box.classList.toggle('is-success', success);
		box.classList.toggle('is-error', !success);
		box.hidden = false;
	}

	/**
	 * 校验表单字段并显示错误。
	 *
	 * @param {HTMLFormElement} form 表单元素。
	 * @returns {boolean} 是否通过校验。
	 */
	function validateForm(form) {
		var valid = true;
		var requiredMessage = form.dataset.requiredMessage || '此字段为必填项。';
		var invalidMessage = form.dataset.invalidMessage || '请输入有效的格式。';
		var fields = form.querySelectorAll('input:not([type="hidden"]), textarea');

		fields.forEach(function (field) {
			var error = field.closest('.wpem-field');
			var errorBox = error ? error.querySelector('.wpem-field-error') : null;
			field.classList.remove('has-error');
			if (errorBox) {
				errorBox.textContent = '';
			}

			if (field.required && !field.value.trim()) {
				valid = false;
				field.classList.add('has-error');
				if (errorBox) {
					errorBox.textContent = requiredMessage;
				}
				return;
			}

			if (field.value && !field.validity.valid) {
				valid = false;
				field.classList.add('has-error');
				if (errorBox) {
					errorBox.textContent = invalidMessage;
				}
			}
		});

		return valid;
	}

	/**
	 * 刷新可能因页面缓存而过期的 nonce。
	 *
	 * @param {HTMLFormElement} form 表单元素。
	 * @param {string} ajaxUrl AJAX 地址。
	 * @returns {Promise<string>} 新 nonce。
	 */
	async function refreshNonce(form, ajaxUrl) {
		var request = new FormData();
		request.append('action', 'wpem_refresh_nonce');
		request.append('form_id', form.querySelector('input[name="form_id"]').value);

		var response = await window.fetch(ajaxUrl, {
			method: 'POST',
			credentials: 'same-origin',
			body: request,
		});
		var result = await response.json();
		if (!result.success || !result.data || !result.data.nonce) {
			throw new Error('nonce_refresh_failed');
		}
		return result.data.nonce;
	}

	/**
	 * 获取 reCAPTCHA v3 token。
	 *
	 * @param {HTMLFormElement} form 表单元素。
	 * @param {string} siteKey 站点密钥。
	 * @returns {Promise<string>} token。
	 */
	function getRecaptchaToken(form, siteKey) {
		if (form.dataset.recaptcha !== '1') {
			return Promise.resolve('');
		}
		if (!window.grecaptcha || !siteKey) {
			return Promise.reject(new Error('recaptcha_unavailable'));
		}

		return new Promise(function (resolve, reject) {
			window.grecaptcha.ready(function () {
				window.grecaptcha
					.execute(siteKey, { action: 'wpem_submit' })
					.then(resolve)
					.catch(reject);
			});
		});
	}

	/**
	 * 提交表单。
	 *
	 * @param {HTMLFormElement} form 表单元素。
	 * @returns {Promise<void>}
	 */
	async function submitForm(form) {
		var config = resolveConfig(form);
		if (!config.ajaxUrl) {
			showResponse(form, '无法完成提交，请稍后重试。', false);
			return;
		}

		var button = form.querySelector('.wpem-submit');
		var responseBox = form.querySelector('.wpem-response');
		var originalText = button ? button.textContent : '';

		if (responseBox) {
			responseBox.hidden = true;
		}
		if (!validateForm(form)) {
			return;
		}

		if (button) {
			button.disabled = true;
			button.textContent = button.dataset.loadingText || originalText;
		}

		try {
			var nonce = await refreshNonce(form, config.ajaxUrl);
			form.querySelector('input[name="nonce"]').value = nonce;
			var token = await getRecaptchaToken(form, config.siteKey);
			var data = new FormData(form);
			if (token) {
				data.append('recaptcha_token', token);
			}

			var response = await window.fetch(config.ajaxUrl, {
				method: 'POST',
				credentials: 'same-origin',
				body: data,
			});
			var result = await response.json();
			var message = result.data && result.data.message ? result.data.message : '提交失败，请稍后重试。';

			showResponse(form, message, Boolean(result.success));
			if (result.success) {
				form.reset();
			}
		} catch (error) {
			showResponse(form, '无法完成提交，请稍后重试。', false);
		} finally {
			if (button) {
				button.disabled = false;
				button.textContent = originalText;
			}
		}
	}

	/**
	 * 阻止原生提交并改为 AJAX。
	 *
	 * @param {Event} event 提交事件。
	 * @returns {void}
	 */
	function onFormSubmit(event) {
		var form = event.target;
		if (!(form instanceof HTMLFormElement) || !form.classList.contains('wpem-form')) {
			return;
		}

		event.preventDefault();
		event.stopPropagation();
		submitForm(form);
	}

	/**
	 * 按钮点击提交（避免未加载脚本时触发表单刷新）。
	 *
	 * @param {Event} event 点击事件。
	 * @returns {void}
	 */
	function onSubmitButtonClick(event) {
		var button = event.target.closest('.wpem-submit');
		if (!button) {
			return;
		}

		var form = button.closest('form.wpem-form');
		if (!form) {
			return;
		}

		event.preventDefault();
		submitForm(form);
	}

	/**
	 * 初始化页面中的表单。
	 *
	 * @returns {void}
	 */
	function initialize() {
		document.addEventListener('submit', onFormSubmit, true);
		document.addEventListener('click', onSubmitButtonClick, true);
	}

	if ('loading' === document.readyState) {
		document.addEventListener('DOMContentLoaded', initialize);
	} else {
		initialize();
	}
})();
