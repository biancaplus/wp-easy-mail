<?php
/**
 * 前台表单提交处理。
 *
 * @package WPEasyMail
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * 校验、存储并通知表单提交。
 */
class WPEM_Submission_Handler {

	/**
	 * 邮件发送器。
	 *
	 * @var WPEM_Mailer
	 */
	private $mailer;

	/**
	 * 初始化提交处理器。
	 *
	 * @param WPEM_Mailer $mailer 邮件发送器。
	 */
	public function __construct( WPEM_Mailer $mailer ) {
		$this->mailer = $mailer;
	}

	/**
	 * 注册 AJAX 钩子。
	 *
	 * @return void
	 */
	public function register() {
		add_action( 'wp_ajax_wpem_submit_form', array( $this, 'handle' ) );
		add_action( 'wp_ajax_nopriv_wpem_submit_form', array( $this, 'handle' ) );
		add_action( 'wp_ajax_wpem_refresh_nonce', array( $this, 'refresh_nonce' ) );
		add_action( 'wp_ajax_nopriv_wpem_refresh_nonce', array( $this, 'refresh_nonce' ) );
	}

	/**
	 * 为缓存页面刷新公开表单 nonce。
	 *
	 * @return void
	 */
	public function refresh_nonce() {
		$form_id = isset( $_POST['form_id'] ) ? absint( $_POST['form_id'] ) : 0;
		if ( ! $form_id || 'wpem_form' !== get_post_type( $form_id ) || 'publish' !== get_post_status( $form_id ) ) {
			wp_send_json_error( array( 'message' => __( '表单不存在。', 'wp-easy-mail' ) ), 404 );
		}

		wp_send_json_success(
			array(
				'nonce' => wp_create_nonce( 'wpem_submit_' . $form_id ),
			)
		);
	}

	/**
	 * 处理一次表单提交。
	 *
	 * @return void
	 */
	public function handle() {
		$form_id  = isset( $_POST['form_id'] ) ? absint( $_POST['form_id'] ) : 0;
		$nonce    = isset( $_POST['nonce'] ) ? sanitize_text_field( wp_unslash( $_POST['nonce'] ) ) : '';
		$honeypot = isset( $_POST['website'] ) ? sanitize_text_field( wp_unslash( $_POST['website'] ) ) : '';

		if ( ! $form_id || ! wp_verify_nonce( $nonce, 'wpem_submit_' . $form_id ) ) {
			wp_send_json_error( array( 'message' => __( '安全验证失败，请刷新页面后重试。', 'wp-easy-mail' ) ), 403 );
		}

		$settings = WPEM_Forms::get_settings( $form_id );
		if ( 'wpem_form' !== get_post_type( $form_id ) || 'publish' !== get_post_status( $form_id ) ) {
			wp_send_json_error( array( 'message' => $settings['error_message'] ), 404 );
		}

		if ( '' !== $honeypot ) {
			wp_send_json_success( array( 'message' => $settings['success_message'] ) );
		}

		$ip = $this->get_client_ip();
		if ( ! $this->check_rate_limit( $form_id, $ip ) ) {
			wp_send_json_error( array( 'message' => __( '提交过于频繁，请稍后重试。', 'wp-easy-mail' ) ), 429 );
		}

		$recaptcha = $this->verify_recaptcha( $ip );
		if ( is_wp_error( $recaptcha ) ) {
			wp_send_json_error( array( 'message' => $recaptcha->get_error_message() ), 400 );
		}

		$values = $this->sanitize_fields( $settings );
		if ( is_wp_error( $values ) ) {
			wp_send_json_error( array( 'message' => $values->get_error_message() ), 400 );
		}

		$submission_id = $this->store_submission( $form_id, $settings['form_type'], $values, $ip );
		if ( ! $submission_id ) {
			wp_send_json_error( array( 'message' => $settings['error_message'] ), 500 );
		}

		$this->set_email_status( $submission_id, 'sending' );
		$mail_sent = $this->mailer->send( $settings, $values, $ip, $form_id );
		$this->set_email_status( $submission_id, $mail_sent ? 'sent' : 'failed' );

		wp_send_json_success( array( 'message' => $settings['success_message'] ) );
	}

	/**
	 * 清洗并验证提交字段。
	 *
	 * @param array<string, mixed> $settings 表单配置。
	 * @return array<string, string>|WP_Error
	 */
	private function sanitize_fields( $settings ) {
		$raw_fields = isset( $_POST['fields'] ) && is_array( $_POST['fields'] ) ? wp_unslash( $_POST['fields'] ) : array();
		$definitions = WPEM_Form_Types::get_fields( $settings['form_type'] );
		$values      = array();

		foreach ( $definitions as $key => $definition ) {
			$field_settings = isset( $settings['fields'][ $key ] ) ? $settings['fields'][ $key ] : $definition;
			if ( ! WPEM_Form_Types::is_field_visible( $field_settings, $definition ) ) {
				continue;
			}

			$raw_value = isset( $raw_fields[ $key ] ) && is_scalar( $raw_fields[ $key ] ) ? (string) $raw_fields[ $key ] : '';
			$value     = 'textarea' === $definition['type'] ? sanitize_textarea_field( $raw_value ) : sanitize_text_field( $raw_value );
			if ( 'email' === $definition['type'] ) {
				$value = sanitize_email( $raw_value );
			}
			if ( 'tel' === $definition['type'] ) {
				$value = preg_replace( '/[^0-9+() .-]/', '', $value );
			}
			$value = $this->limit_length( $value, 'textarea' === $definition['type'] ? 5000 : 255 );

			if ( ! empty( $field_settings['required'] ) && '' === trim( $value ) ) {
				return new WP_Error( 'required_field', sprintf( '%s：%s', $field_settings['label'], $settings['required_message'] ) );
			}
			if ( 'email' === $definition['type'] && '' !== $value && ! is_email( $value ) ) {
				return new WP_Error( 'invalid_email', $settings['invalid_message'] );
			}

			$values[ $key ] = $value;
		}

		return $values;
	}

	/**
	 * 验证 reCAPTCHA v3。
	 *
	 * @param string $ip 客户端 IP。
	 * @return true|WP_Error
	 */
	private function verify_recaptcha( $ip ) {
		$options = get_option( 'wpem_recaptcha_settings', array() );
		if ( empty( $options['enabled'] ) ) {
			return true;
		}

		$token = isset( $_POST['recaptcha_token'] ) ? sanitize_text_field( wp_unslash( $_POST['recaptcha_token'] ) ) : '';
		if ( '' === $token || empty( $options['secret_key'] ) ) {
			return new WP_Error( 'recaptcha_missing', __( '安全验证不可用，请稍后重试。', 'wp-easy-mail' ) );
		}

		$response = wp_remote_post(
			'https://www.google.com/recaptcha/api/siteverify',
			array(
				'timeout' => 10,
				'body'    => array(
					'secret'   => $options['secret_key'],
					'response' => $token,
					'remoteip' => $ip,
				),
			)
		);

		if ( is_wp_error( $response ) ) {
			return new WP_Error( 'recaptcha_request', __( '无法完成安全验证，请稍后重试。', 'wp-easy-mail' ) );
		}

		$data      = json_decode( wp_remote_retrieve_body( $response ), true );
		$threshold = isset( $options['threshold'] ) ? (float) $options['threshold'] : 0.5;
		$host      = wp_parse_url( home_url(), PHP_URL_HOST );
		if (
			empty( $data['success'] ) ||
			! isset( $data['score'] ) ||
			(float) $data['score'] < $threshold ||
			isset( $data['action'] ) && 'wpem_submit' !== $data['action'] ||
			! empty( $data['hostname'] ) && $host && strtolower( $data['hostname'] ) !== strtolower( $host )
		) {
			return new WP_Error( 'recaptcha_rejected', __( '安全验证未通过，请刷新页面后重试。', 'wp-easy-mail' ) );
		}

		return true;
	}

	/**
	 * 应用基于表单和 IP 的速率限制。
	 *
	 * @param int    $form_id 表单 ID。
	 * @param string $ip      客户端 IP。
	 * @return bool
	 */
	private function check_rate_limit( $form_id, $ip ) {
		$limit = (int) apply_filters( 'wpem_rate_limit', 5, $form_id );
		$key   = 'wpem_rate_' . md5( $form_id . '|' . $ip );
		$count = (int) get_transient( $key );
		if ( $count >= $limit ) {
			return false;
		}
		set_transient( $key, $count + 1, 10 * MINUTE_IN_SECONDS );
		return true;
	}

	/**
	 * 保存提交记录。
	 *
	 * @param int                   $form_id   表单 ID。
	 * @param string                $form_type 表单类型。
	 * @param array<string, string> $values    字段值。
	 * @param string                $ip        客户端 IP。
	 * @return int
	 */
	private function store_submission( $form_id, $form_type, $values, $ip ) {
		global $wpdb;

		$table  = $wpdb->prefix . 'wp_easy_mail_submissions';
		$result = $wpdb->insert(
			$table,
			array(
				'form_id'      => $form_id,
				'form_type'    => $form_type,
				'field_data'   => wp_json_encode( $values, JSON_UNESCAPED_UNICODE ),
				'status'       => 'unread',
				'email_status' => 'pending',
				'ip_address'   => $ip,
				'user_agent'   => isset( $_SERVER['HTTP_USER_AGENT'] ) ? sanitize_text_field( wp_unslash( $_SERVER['HTTP_USER_AGENT'] ) ) : '',
				'submitted_at' => current_time( 'mysql' ),
			),
			array( '%d', '%s', '%s', '%s', '%s', '%s', '%s', '%s' )
		);

		return false === $result ? 0 : (int) $wpdb->insert_id;
	}

	/**
	 * 更新邮件状态。
	 *
	 * @param int    $submission_id 提交 ID。
	 * @param string $status        邮件状态。
	 * @return void
	 */
	private function set_email_status( $submission_id, $status ) {
		global $wpdb;
		$wpdb->update(
			$wpdb->prefix . 'wp_easy_mail_submissions',
			array( 'email_status' => $status ),
			array( 'id' => $submission_id ),
			array( '%s' ),
			array( '%d' )
		);
	}

	/**
	 * 获取客户端 IP。
	 *
	 * @return string
	 */
	private function get_client_ip() {
		return isset( $_SERVER['REMOTE_ADDR'] )
			? $this->limit_length( sanitize_text_field( wp_unslash( $_SERVER['REMOTE_ADDR'] ) ), 100 )
			: '';
	}

	/**
	 * 限制字符串长度。
	 *
	 * @param string $value  字符串。
	 * @param int    $length 最大长度。
	 * @return string
	 */
	private function limit_length( $value, $length ) {
		return function_exists( 'mb_substr' ) ? mb_substr( $value, 0, $length ) : substr( $value, 0, $length );
	}
}
