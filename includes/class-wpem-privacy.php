<?php
/**
 * WordPress 隐私工具集成。
 *
 * @package WPEasyMail
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * 提供隐私声明、个人数据导出与擦除功能。
 */
class WPEM_Privacy {

	/**
	 * 注册隐私相关钩子。
	 *
	 * @return void
	 */
	public function register() {
		add_action( 'admin_init', array( $this, 'add_policy_content' ) );
		add_filter( 'wp_privacy_personal_data_exporters', array( $this, 'register_exporter' ) );
		add_filter( 'wp_privacy_personal_data_erasers', array( $this, 'register_eraser' ) );
	}

	/**
	 * 添加隐私政策建议文本。
	 *
	 * @return void
	 */
	public function add_policy_content() {
		if ( ! function_exists( 'wp_add_privacy_policy_content' ) ) {
			return;
		}

		$content  = '<p>' . esc_html__( '当访客提交联系或报价表单时，本站会保存表单中填写的姓名、邮箱、电话、公司及消息内容，同时保存 IP 地址、浏览器 User-Agent 和提交时间。数据用于回复咨询、防止滥用及记录邮件发送状态。', 'wp-easy-mail' ) . '</p>';
		$content .= '<p>' . esc_html__( '表单启用 reCAPTCHA v3 时，提交过程还会连接 Google reCAPTCHA 服务。请根据实际使用情况在隐私政策中说明数据保留期限及第三方服务。', 'wp-easy-mail' ) . '</p>';
		wp_add_privacy_policy_content( 'WP Easy Mail', wp_kses_post( wpautop( $content ) ) );
	}

	/**
	 * 注册个人数据导出器。
	 *
	 * @param array<string, array<string, mixed>> $exporters 已注册导出器。
	 * @return array<string, array<string, mixed>>
	 */
	public function register_exporter( $exporters ) {
		$exporters['wp-easy-mail'] = array(
			'exporter_friendly_name' => __( 'WP Easy Mail 表单提交', 'wp-easy-mail' ),
			'callback'               => array( $this, 'export_personal_data' ),
		);
		return $exporters;
	}

	/**
	 * 注册个人数据擦除器。
	 *
	 * @param array<string, array<string, mixed>> $erasers 已注册擦除器。
	 * @return array<string, array<string, mixed>>
	 */
	public function register_eraser( $erasers ) {
		$erasers['wp-easy-mail'] = array(
			'eraser_friendly_name' => __( 'WP Easy Mail 表单提交', 'wp-easy-mail' ),
			'callback'             => array( $this, 'erase_personal_data' ),
		);
		return $erasers;
	}

	/**
	 * 导出与邮箱匹配的提交数据。
	 *
	 * @param string $email_address 请求邮箱。
	 * @param int    $page          页码。
	 * @return array<string, mixed>
	 */
	public function export_personal_data( $email_address, $page = 1 ) {
		$number = 50;
		$rows   = $this->get_submissions_by_email( $email_address, $page, $number );
		$data   = array();

		foreach ( $rows as $row ) {
			$fields      = json_decode( $row['field_data'], true );
			$fields      = is_array( $fields ) ? $fields : array();
			$definitions = WPEM_Form_Types::get_fields( $row['form_type'] );
			$item_data   = array(
				array(
					'name'  => __( '表单类型', 'wp-easy-mail' ),
					'value' => WPEM_Form_Types::get_type_label( $row['form_type'] ),
				),
				array(
					'name'  => __( '表单', 'wp-easy-mail' ),
					'value' => get_the_title( (int) $row['form_id'] ),
				),
			);

			foreach ( $definitions as $key => $definition ) {
				if ( isset( $fields[ $key ] ) && '' !== $fields[ $key ] ) {
					$item_data[] = array(
						'name'  => $definition['label'],
						'value' => $fields[ $key ],
					);
				}
			}

			$item_data[] = array(
				'name'  => __( 'IP 地址', 'wp-easy-mail' ),
				'value' => $row['ip_address'],
			);
			$item_data[] = array(
				'name'  => __( '提交时间', 'wp-easy-mail' ),
				'value' => $row['submitted_at'],
			);
			$data[]      = array(
				'group_id'    => 'wp-easy-mail-submissions',
				'group_label' => __( '表单提交记录', 'wp-easy-mail' ),
				'item_id'     => 'wpem-submission-' . (int) $row['id'],
				'data'        => $item_data,
			);
		}

		return array(
			'data' => $data,
			'done' => count( $rows ) < $number,
		);
	}

	/**
	 * 擦除与邮箱匹配的提交数据。
	 *
	 * @param string $email_address 请求邮箱。
	 * @param int    $page          页码。
	 * @return array<string, mixed>
	 */
	public function erase_personal_data( $email_address, $page = 1 ) {
		global $wpdb;

		$number  = 50;
		$rows    = $this->get_submissions_by_email( $email_address, 1, $number );
		$table   = $wpdb->prefix . 'wp_easy_mail_submissions';
		$removed = false;

		foreach ( $rows as $row ) {
			$removed = (bool) $wpdb->delete( $table, array( 'id' => (int) $row['id'] ), array( '%d' ) ) || $removed;
		}

		return array(
			'items_removed'  => $removed,
			'items_retained' => false,
			'messages'       => array(),
			'done'           => count( $rows ) < $number,
		);
	}

	/**
	 * 查询与邮箱精确匹配的提交记录。
	 *
	 * @param string $email_address 邮箱。
	 * @param int    $page          页码。
	 * @param int    $number        每页数量。
	 * @return array<int, array<string, mixed>>
	 */
	private function get_submissions_by_email( $email_address, $page, $number ) {
		global $wpdb;

		$email  = sanitize_email( $email_address );
		$table  = $wpdb->prefix . 'wp_easy_mail_submissions';
		$offset = max( 0, ( absint( $page ) - 1 ) * $number );
		$like   = '%' . $wpdb->esc_like( '"email":"' . $email . '"' ) . '%';
		return $wpdb->get_results(
			$wpdb->prepare(
				"SELECT * FROM {$table} WHERE field_data LIKE %s ORDER BY id ASC LIMIT %d OFFSET %d",
				$like,
				$number,
				$offset
			),
			ARRAY_A
		);
	}
}
