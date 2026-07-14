/**
 * WP Easy Mail 前台交互。
 */
(function () {
	'use strict';

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
	 * @returns {Promise<string>} 新 nonce。
	 */
	async function refreshNonce(form) {
		var request = new FormData();
		request.append('action', 'wpem_refresh_nonce');
		request.append('form_id', form.querySelector('input[name="form_id"]').value);

		var response = await window.fetch(window.WPEasyMail.ajaxUrl, {
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
	 * @returns {Promise<string>} token。
	 */
	function getRecaptchaToken(form) {
		if (form.dataset.recaptcha !== '1') {
			return Promise.resolve('');
		}
		if (!window.grecaptcha || !window.WPEasyMail.siteKey) {
			return Promise.reject(new Error('recaptcha_unavailable'));
		}

		return new Promise(function (resolve, reject) {
			window.grecaptcha.ready(function () {
				window.grecaptcha
					.execute(window.WPEasyMail.siteKey, { action: 'wpem_submit' })
					.then(resolve)
					.catch(reject);
			});
		});
	}

	/**
	 * 提交表单。
	 *
	 * @param {SubmitEvent} event 提交事件。
	 * @returns {Promise<void>}
	 */
	async function submitForm(event) {
		event.preventDefault();
		var form = event.currentTarget;
		var button = form.querySelector('.wpem-submit');
		var responseBox = form.querySelector('.wpem-response');
		var originalText = button.textContent;

		responseBox.hidden = true;
		if (!validateForm(form)) {
			return;
		}

		button.disabled = true;
		button.textContent = button.dataset.loadingText;

		try {
			var nonce = await refreshNonce(form);
			form.querySelector('input[name="nonce"]').value = nonce;
			var token = await getRecaptchaToken(form);
			var data = new FormData(form);
			if (token) {
				data.append('recaptcha_token', token);
			}

			var response = await window.fetch(window.WPEasyMail.ajaxUrl, {
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
			button.disabled = false;
			button.textContent = originalText;
		}
	}

	/**
	 * 初始化页面中的表单。
	 *
	 * @returns {void}
	 */
	function initialize() {
		document.querySelectorAll('.wpem-form').forEach(function (form) {
			form.addEventListener('submit', submitForm);
		});
	}

	if ('loading' === document.readyState) {
		document.addEventListener('DOMContentLoaded', initialize);
	} else {
		initialize();
	}
})();
