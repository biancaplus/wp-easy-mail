<?php
/**
 * 表单类型与默认配置。
 *
 * @package WPEasyMail
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * 提供表单类型定义。
 */
class WPEM_Form_Types {

	/**
	 * 返回支持的表单类型。
	 *
	 * @return array<string, string>
	 */
	public static function get_types() {
		return array(
			'contact_us' => __( '联系我们', 'wp-easy-mail' ),
			'get_quote'  => __( '获取报价', 'wp-easy-mail' ),
		);
	}

	/**
	 * 判断表单类型是否有效。
	 *
	 * @param string $type 表单类型。
	 * @return bool
	 */
	public static function is_valid_type( $type ) {
		return array_key_exists( $type, self::get_types() );
	}

	/**
	 * 获取表单类型名称。
	 *
	 * @param string $type 表单类型。
	 * @return string
	 */
	public static function get_type_label( $type ) {
		$types = self::get_types();
		return isset( $types[ $type ] ) ? $types[ $type ] : __( '未知类型', 'wp-easy-mail' );
	}

	/**
	 * 获取指定类型的字段定义。
	 *
	 * @param string $type 表单类型。
	 * @return array<string, array<string, mixed>>
	 */
	public static function get_fields( $type ) {
		if ( 'get_quote' === $type ) {
			return array(
				'name'         => array(
					'type'        => 'text',
					'label'       => __( '姓名', 'wp-easy-mail' ),
					'placeholder' => __( '请输入您的姓名', 'wp-easy-mail' ),
					'required'    => true,
				),
				'email'        => array(
					'type'        => 'email',
					'label'       => __( '电子邮件', 'wp-easy-mail' ),
					'placeholder' => __( '请输入您的电子邮件地址', 'wp-easy-mail' ),
					'required'    => true,
				),
				'phone'        => array(
					'type'        => 'tel',
					'label'       => __( '电话', 'wp-easy-mail' ),
					'placeholder' => __( '请输入您的电话号码', 'wp-easy-mail' ),
					'required'    => false,
					'toggleable'  => true,
				),
				'company'      => array(
					'type'        => 'text',
					'label'       => __( '公司', 'wp-easy-mail' ),
					'placeholder' => __( '请输入公司名称', 'wp-easy-mail' ),
					'required'    => false,
					'toggleable'  => true,
				),
				'requirements' => array(
					'type'        => 'textarea',
					'label'       => __( '需求描述', 'wp-easy-mail' ),
					'placeholder' => __( '请描述您的需求', 'wp-easy-mail' ),
					'required'    => true,
				),
				'budget'       => array(
					'type'        => 'text',
					'label'       => __( '预算', 'wp-easy-mail' ),
					'placeholder' => __( '请输入预算范围', 'wp-easy-mail' ),
					'required'    => true,
				),
			);
		}

		// 联系我们：姓名 → 电子邮件 → 电话 → 信息。
		return array(
			'name'    => array(
				'type'        => 'text',
				'label'       => __( '姓名', 'wp-easy-mail' ),
				'placeholder' => __( '请输入您的姓名', 'wp-easy-mail' ),
				'required'    => true,
			),
			'email'   => array(
				'type'        => 'email',
				'label'       => __( '电子邮件', 'wp-easy-mail' ),
				'placeholder' => __( '请输入您的电子邮件地址', 'wp-easy-mail' ),
				'required'    => true,
			),
			'phone'   => array(
				'type'        => 'tel',
				'label'       => __( '电话', 'wp-easy-mail' ),
				'placeholder' => __( '请输入您的电话号码', 'wp-easy-mail' ),
				'required'    => true,
				'toggleable'  => true,
			),
			'company' => array(
				'type'        => 'text',
				'label'       => __( '公司', 'wp-easy-mail' ),
				'placeholder' => __( '请输入公司名称', 'wp-easy-mail' ),
				'required'    => false,
				'toggleable'  => true,
			),
			'message' => array(
				'type'        => 'textarea',
				'label'       => __( '信息', 'wp-easy-mail' ),
				'placeholder' => __( '请输入您的留言', 'wp-easy-mail' ),
				'required'    => true,
			),
		);
	}

	/**
	 * 获取完整默认配置。
	 *
	 * @param string $type 表单类型。
	 * @return array<string, mixed>
	 */
	public static function get_defaults( $type = 'contact_us' ) {
		if ( ! self::is_valid_type( $type ) ) {
			$type = 'contact_us';
		}

		$fields = self::get_fields( $type );
		$labels = array();
		foreach ( $fields as $key => $field ) {
			$labels[ $key ] = array(
				'label'       => $field['label'],
				'placeholder' => $field['placeholder'],
				'required'    => ! empty( $field['required'] ),
				// 联系我们默认隐藏「公司」，与设计稿四字段布局一致。
				'visible'     => ( 'contact_us' === $type && 'company' === $key ) ? false : true,
			);
		}

		$site_email = self::get_site_email_defaults();

		$description = ( 'contact_us' === $type )
			? __( "我们期待您的回复。我们的团队随时准备为您提供帮助。\n请填写以下电子邮件表格，我们将与您联系。", 'wp-easy-mail' )
			: __( '请填写以下信息，我们会尽快与您联系。', 'wp-easy-mail' );

		$submit_text = ( 'contact_us' === $type )
			? __( '发送信息', 'wp-easy-mail' )
			: __( '提交', 'wp-easy-mail' );

		return array(
			'form_type'        => $type,
			'description'      => $description,
			'submit_text'      => $submit_text,
			'theme_color'      => '#111111',
			'form_template'    => 'classic',
			'email_to'         => $site_email['email_to'],
			'email_subject'    => __( '新的表单提交 - [name]', 'wp-easy-mail' ),
			'email_message'    => self::get_default_email_message( $type ),
			'email_from'       => '',
			'email_from_name'  => $site_email['email_from_name'],
			'email_reply_to'   => '[email]',
			'email_cc'         => '',
			'email_bcc'        => '',
			'email_format'     => 'html',
			'success_message'  => __( '感谢您的提交，我们会尽快与您联系！', 'wp-easy-mail' ),
			'error_message'    => __( '提交失败，请稍后重试。', 'wp-easy-mail' ),
			'required_message' => __( '此字段为必填项。', 'wp-easy-mail' ),
			'invalid_message'  => __( '请输入有效的格式。', 'wp-easy-mail' ),
			'fields'           => $labels,
		);
	}

	/**
	 * 清洗主题色，非法值回退默认。
	 *
	 * @param string $color 主题色。
	 * @return string
	 */
	public static function sanitize_theme_color( $color ) {
		$sanitized = sanitize_hex_color( $color );
		return $sanitized ? $sanitized : '#111111';
	}

	/**
	 * 获取默认邮件正文。
	 *
	 * @param string $type 表单类型。
	 * @return string
	 */
	public static function get_default_email_message( $type ) {
		if ( 'get_quote' === $type ) {
			return "姓名：[name]\n电子邮件：[email]\n电话：[phone]\n公司：[company]\n需求描述：[requirements]\n预算：[budget]\n提交时间：[date]";
		}

		return "姓名：[name]\n电子邮件：[email]\n电话：[phone]\n公司：[company]\n信息：[message]\n提交时间：[date]";
	}

	/**
	 * 读取 WordPress 站点邮件默认值。
	 *
	 * @return array{email_to: string, email_from_name: string}
	 */
	public static function get_site_email_defaults() {
		return array(
			'email_to'        => (string) get_option( 'admin_email' ),
			'email_from_name' => (string) get_bloginfo( 'name' ),
		);
	}

	/**
	 * 判断字段是否在前台显示。
	 *
	 * @param array<string, mixed> $field_settings 字段配置。
	 * @param array<string, mixed> $definition     字段定义。
	 * @return bool
	 */
	public static function is_field_visible( $field_settings, $definition ) {
		if ( empty( $definition['toggleable'] ) ) {
			return true;
		}

		return ! isset( $field_settings['visible'] ) || ! empty( $field_settings['visible'] );
	}
}
