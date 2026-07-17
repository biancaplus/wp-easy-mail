/**
 * WP Easy Mail 前台脚本兜底加载器。
 * 用于古腾堡 Custom HTML、页面缓存或优化插件导致 wp_enqueue_script 失效的场景。
 */
(function () {
	'use strict';

	if (window.WPEM_BOOTSTRAP_LOADED) {
		return;
	}
	window.WPEM_BOOTSTRAP_LOADED = true;

	/**
	 * 动态加载外部脚本。
	 *
	 * @param {string} src 脚本地址。
	 * @returns {Promise<void>}
	 */
	function loadScript(src) {
		return new Promise(function (resolve, reject) {
			var base = src.split('?')[0];
			var existing = document.querySelector('script[data-wpem-frontend="1"][src="' + src + '"]')
				|| document.querySelector('script[data-wpem-frontend="1"][src^="' + base + '"]');

			if (existing) {
				resolve();
				return;
			}

			var script = document.createElement('script');
			script.src = src;
			script.async = false;
			script.setAttribute('data-wpem-frontend', '1');
			script.setAttribute('data-cfasync', 'false');
			script.onload = function () {
				resolve();
			};
			script.onerror = function () {
				reject(new Error('script_load_failed'));
			};
			document.head.appendChild(script);
		});
	}

	/**
	 * 从表单 data 属性同步全局配置。
	 *
	 * @returns {void}
	 */
	function syncConfigFromForms() {
		window.WPEasyMail = window.WPEasyMail || {};

		document.querySelectorAll('form.wpem-form').forEach(function (form) {
			if (!window.WPEasyMail.ajaxUrl && form.dataset.ajaxUrl) {
				window.WPEasyMail.ajaxUrl = form.dataset.ajaxUrl;
			}
			if (!window.WPEasyMail.siteKey && form.dataset.siteKey) {
				window.WPEasyMail.siteKey = form.dataset.siteKey;
			}
			if (form.dataset.recaptcha === '1') {
				window.WPEasyMail.recaptchaEnabled = true;
			}
		});
	}

	/**
	 * 获取 frontend.js 地址。
	 *
	 * @returns {string}
	 */
	function getFrontendScriptUrl() {
		var wrap = document.querySelector('.wpem-form-wrap[data-frontend-script]');
		if (wrap && wrap.dataset.frontendScript) {
			return wrap.dataset.frontendScript;
		}

		var current = document.currentScript;
		if (current && current.src) {
			return current.src.replace('bootstrap.js', 'frontend.js');
		}

		return '';
	}

	/**
	 * 启动兜底加载流程。
	 *
	 * @returns {void}
	 */
	function run() {
		syncConfigFromForms();

		var frontendUrl = getFrontendScriptUrl();
		if (!frontendUrl) {
			return;
		}

		var recaptchaOn = window.WPEasyMail.recaptchaEnabled && window.WPEasyMail.siteKey;
		var chain = Promise.resolve();

		if (recaptchaOn && !window.grecaptcha) {
			chain = loadScript(
				'https://www.google.com/recaptcha/api.js?render=' + encodeURIComponent(window.WPEasyMail.siteKey)
			);
		}

		chain.then(function () {
			return loadScript(frontendUrl);
		});
	}

	if ('loading' === document.readyState) {
		document.addEventListener('DOMContentLoaded', run);
	} else {
		run();
	}
})();
