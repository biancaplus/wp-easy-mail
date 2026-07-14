/**
 * WP Easy Mail 后台交互。
 */
(function () {
	'use strict';

	/**
	 * 切换设置面板。
	 *
	 * @param {HTMLElement} root 设置根元素。
	 * @param {string} tab 面板名称。
	 * @returns {void}
	 */
	function switchTab(root, tab) {
		root.querySelectorAll('.wpem-tab').forEach(function (button) {
			button.classList.toggle('is-active', button.dataset.tab === tab);
		});
		root.querySelectorAll('.wpem-panel').forEach(function (panel) {
			panel.classList.toggle('is-active', panel.dataset.panel === tab);
		});
	}

	/**
	 * 切换表单类型对应字段。
	 *
	 * @param {HTMLElement} root 设置根元素。
	 * @param {string} type 表单类型。
	 * @returns {void}
	 */
	function switchFieldGroup(root, type) {
		root.querySelectorAll('.wpem-fields-group').forEach(function (group) {
			var active = group.dataset.formType === type;
			group.hidden = !active;
			group.querySelectorAll('input, textarea, select').forEach(function (control) {
				control.disabled = !active;
			});
		});
	}

	/**
	 * 初始化表单编辑设置。
	 *
	 * @param {HTMLElement} root 设置根元素。
	 * @returns {void}
	 */
	function initializeSettings(root) {
		root.querySelectorAll('.wpem-tab').forEach(function (button) {
			button.addEventListener('click', function () {
				switchTab(root, button.dataset.tab);
			});
		});

		var typeSelect = root.querySelector('select[name="wpem_settings[form_type]"]');
		if (typeSelect) {
			switchFieldGroup(root, typeSelect.value);
			typeSelect.addEventListener('change', function () {
				switchFieldGroup(root, typeSelect.value);
			});
		}

		root.querySelectorAll('.wpem-field-visible').forEach(function (visibleInput) {
			/**
			 * 根据显示开关同步必填项状态。
			 *
			 * @returns {void}
			 */
			function syncRequiredState() {
				var fieldset = visibleInput.closest('.wpem-field-config');
				if (!fieldset) {
					return;
				}
				var requiredInput = fieldset.querySelector('.wpem-field-required input[type="checkbox"]');
				if (!requiredInput) {
					return;
				}
				requiredInput.disabled = !visibleInput.checked;
				if (!visibleInput.checked) {
					requiredInput.checked = false;
				}
			}

			syncRequiredState();
			visibleInput.addEventListener('change', syncRequiredState);
		});

		bindThemeColorInputs(root);
	}

	/**
	 * 同步主题色选择器与十六进制输入。
	 *
	 * @param {HTMLElement} root 设置根元素。
	 * @returns {void}
	 */
	function bindThemeColorInputs(root) {
		root.querySelectorAll('.wpem-color-field').forEach(function (field) {
			var picker = field.querySelector('.wpem-color-picker');
			var text = field.querySelector('.wpem-color-text');
			if (!picker || !text) {
				return;
			}

			picker.addEventListener('input', function () {
				text.value = picker.value.toLowerCase();
			});

			text.addEventListener('change', function () {
				var value = text.value.trim();
				if (/^#[0-9a-fA-F]{6}$/.test(value)) {
					picker.value = value.toLowerCase();
					text.value = value.toLowerCase();
					return;
				}
				text.value = picker.value.toLowerCase();
			});
		});
	}

	/**
	 * 初始化后台页面。
	 *
	 * @returns {void}
	 */
	function initialize() {
		document.querySelectorAll('.wpem-settings').forEach(initializeSettings);
		document.querySelectorAll('.wpem-delete-link').forEach(function (link) {
			link.addEventListener('click', function (event) {
				if (!window.confirm('确定删除此记录吗？')) {
					event.preventDefault();
				}
			});
		});
	}

	if ('loading' === document.readyState) {
		document.addEventListener('DOMContentLoaded', initialize);
	} else {
		initialize();
	}
})();
