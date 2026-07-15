/**
 * WP Easy Mail 后台交互。
 */
(function ($) {
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
				var nextType = typeSelect.value;
				switchFieldGroup(root, nextType);
				syncEmailMessageTemplate(root, nextType);
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
		bindTemplatePicker(root);
	}

	/**
	 * 切换表单类型时同步默认邮件正文模板。
	 *
	 * @param {HTMLElement} root 设置根元素。
	 * @param {string} type 表单类型。
	 * @returns {void}
	 */
	function syncEmailMessageTemplate(root, type) {
		var textarea = root.querySelector('textarea[name="wpem_settings[email_message]"]');
		var templates = (window.WPEasyMailAdmin && WPEasyMailAdmin.emailMessageByType) || {};
		if (!textarea || !templates[type]) {
			return;
		}

		var current = textarea.value.replace(/\r\n/g, '\n').trim();
		var isStock = Object.keys(templates).some(function (key) {
			return templates[key].replace(/\r\n/g, '\n').trim() === current;
		});

		// 仅在当前仍是某类型的默认模板时自动替换，避免覆盖用户自定义正文。
		if ('' === current || isStock) {
			textarea.value = templates[type];
		}
	}

	/**
	 * 将主题色同步到模板预览。
	 *
	 * @param {HTMLElement} root 设置根元素。
	 * @param {string} color 十六进制颜色。
	 * @returns {void}
	 */
	function syncPreviewThemeColor(root, color) {
		root.querySelectorAll('.wpem-template-preview').forEach(function (preview) {
			preview.style.setProperty('--wpem-accent', color);
		});
	}

	/**
	 * 绑定表单模板选择交互。
	 *
	 * @param {HTMLElement} root 设置根元素。
	 * @returns {void}
	 */
	function bindTemplatePicker(root) {
		root.querySelectorAll('input[name="wpem_settings[form_template]"]').forEach(function (input) {
			input.addEventListener('change', function () {
				root.querySelectorAll('.wpem-template-option').forEach(function (option) {
					var radio = option.querySelector('input[type="radio"]');
					option.classList.toggle('is-selected', !!(radio && radio.checked));
				});
			});
		});
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

			/**
			 * 应用颜色到输入与预览。
			 *
			 * @param {string} color 颜色值。
			 * @returns {void}
			 */
			function applyColor(color) {
				var next = color.toLowerCase();
				picker.value = next;
				text.value = next;
				syncPreviewThemeColor(root, next);
			}

			picker.addEventListener('input', function () {
				applyColor(picker.value);
			});

			text.addEventListener('change', function () {
				var value = text.value.trim();
				if (/^#[0-9a-fA-F]{6}$/.test(value)) {
					applyColor(value);
					return;
				}
				text.value = picker.value.toLowerCase();
			});

			var submitText = root.querySelector('input[name="wpem_settings[submit_text]"]');
			if (submitText) {
				submitText.addEventListener('input', function () {
					root.querySelectorAll('.wpem-template-preview .wpem-submit').forEach(function (button) {
						button.textContent = submitText.value || '发送信息';
					});
				});
			}
		});
	}

	/**
	 * 更新标题未读角标。
	 *
	 * @param {number} count 未读数量。
	 * @returns {void}
	 */
	function updateUnreadBadge(count) {
		var badge = $('.wpem-unread-badge');
		if (count > 0) {
			if (badge.length) {
				badge.find('.update-count').text(count);
			}
			return;
		}
		badge.remove();
	}

	/**
	 * 将某行标记为已读的 UI 状态。
	 *
	 * @param {JQuery} row 行元素。
	 * @param {string} operatorName 操作人。
	 * @returns {void}
	 */
	function markRowReadUi(row, operatorName) {
		row.removeClass('wpem-row-unread');
		row.find('.wpem-status-cell').html('<span class="wpem-status-read">' + (WPEasyMailAdmin.statusRead || '已读') + '</span>');
		row.find('.wpem-mark-read-btn').remove();
		row.find('.wpem-action-sep').first().remove();
		if (operatorName) {
			row.find('.wpem-operator-cell').text(operatorName);
		}
	}

	/**
	 * 初始化提交记录页交互。
	 *
	 * @returns {void}
	 */
	function initializeSubmissionsPage() {
		var modal = $('#wpem-message-modal');
		if (!modal.length) {
			return;
		}

		var content = $('#wpem-message-modal-content');

		/**
		 * 关闭弹窗。
		 *
		 * @returns {void}
		 */
		function closeModal() {
			modal.attr('hidden', 'hidden');
			content.text('');
		}

		$('.wpem-view-message').on('click', function (event) {
			event.preventDefault();
			var btn = $(this);
			var rowId = btn.data('id');
			var fullMessage = btn.siblings('.wpem-full-message').text();
			var row = $('#wpem-row-' + rowId);

			content.text(fullMessage);
			modal.removeAttr('hidden');

			if (!row.find('.wpem-status-unread').length) {
				return;
			}

			$.post(WPEasyMailAdmin.ajaxUrl, {
				action: 'wpem_mark_submission_read',
				nonce: WPEasyMailAdmin.markReadNonce,
				id: rowId
			}).done(function (response) {
				if (!response || !response.success) {
					return;
				}
				markRowReadUi(row, response.data.operator_name || '');
				updateUnreadBadge(response.data.unread_count || 0);
			});
		});

		modal.on('click', '.wpem-message-modal-close', closeModal);
		modal.on('click', function (event) {
			if (event.target === modal.get(0)) {
				closeModal();
			}
		});

		$(document).on('keydown.wpemModal', function (event) {
			if ('Escape' === event.key && !modal.is('[hidden]')) {
				closeModal();
			}
		});

		$('.wpem-mark-read-btn').on('click', function (event) {
			if (!window.confirm(WPEasyMailAdmin.confirmRead || '标记为已读？')) {
				event.preventDefault();
			}
		});

		$('.wpem-delete-link').on('click', function (event) {
			if (!window.confirm(WPEasyMailAdmin.confirmDelete || '确定删除此记录吗？')) {
				event.preventDefault();
			}
		});
	}

	/**
	 * 初始化后台页面。
	 *
	 * @returns {void}
	 */
	function initialize() {
		document.querySelectorAll('.wpem-settings').forEach(initializeSettings);
		initializeSubmissionsPage();
	}

	if ('loading' === document.readyState) {
		document.addEventListener('DOMContentLoaded', initialize);
	} else {
		initialize();
	}
})(jQuery);
