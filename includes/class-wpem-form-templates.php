<?php
/**
 * 前台表单外观模板。
 *
 * @package WPEasyMail
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * 管理可选择的表单模板。
 */
class WPEM_Form_Templates {

	/**
	 * 返回可用模板。
	 *
	 * @return array<string, array<string, string>>
	 */
	public static function get_templates() {
		return array(
			'classic' => array(
				'label'       => __( '经典', 'wp-easy-mail' ),
				'description' => __( '简洁竖排布局，适合多数页面嵌入。', 'wp-easy-mail' ),
				'file'        => 'classic.php',
			),
			'card'    => array(
				'label'       => __( '卡片', 'wp-easy-mail' ),
				'description' => __( '圆角卡片布局；宽屏时姓名/公司、邮箱/电话各占一行，小屏自动换行。', 'wp-easy-mail' ),
				'file'        => 'card.php',
			),
		);
	}

	/**
	 * 判断模板是否有效。
	 *
	 * @param string $template 模板标识。
	 * @return bool
	 */
	public static function is_valid( $template ) {
		return array_key_exists( $template, self::get_templates() );
	}

	/**
	 * 清洗模板标识。
	 *
	 * @param string $template 模板标识。
	 * @return string
	 */
	public static function sanitize( $template ) {
		return self::is_valid( $template ) ? $template : 'classic';
	}

	/**
	 * 获取模板文件绝对路径。
	 *
	 * @param string $template 模板标识。
	 * @return string
	 */
	public static function get_path( $template ) {
		$templates = self::get_templates();
		$template  = self::sanitize( $template );
		return WPEM_PATH . 'templates/' . $templates[ $template ]['file'];
	}

	/**
	 * 获取模板显示名称。
	 *
	 * @param string $template 模板标识。
	 * @return string
	 */
	public static function get_label( $template ) {
		$templates = self::get_templates();
		$template  = self::sanitize( $template );
		return $templates[ $template ]['label'];
	}

	/**
	 * 渲染单个字段。
	 *
	 * @param string               $key         字段键。
	 * @param array<string, mixed> $definition  字段定义。
	 * @param array<string, mixed> $settings    表单配置。
	 * @param string               $instance_id 表单实例 ID。
	 * @param string               $extra_class 额外 class。
	 * @return void
	 */
	public static function render_field( $key, $definition, $settings, $instance_id, $extra_class = '' ) {
		$field_settings = isset( $settings['fields'][ $key ] ) ? $settings['fields'][ $key ] : $definition;
		if ( ! WPEM_Form_Types::is_field_visible( $field_settings, $definition ) ) {
			return;
		}

		$field_id    = $instance_id . '-' . $key;
		$is_required = ! empty( $field_settings['required'] );
		include WPEM_PATH . 'templates/partials/field.php';
	}
}
