<?php
/**
 * 表单通知邮件。
 *
 * @package WPEasyMail
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * 发送表单通知邮件。
 */
class WPEM_Mailer {

	/**
	 * 发送通知。
	 *
	 * @param array<string, mixed>  $settings 表单配置。
	 * @param array<string, string> $values   字段值。
	 * @param string                $ip       提交 IP。
	 * @return bool
	 */
	public function send( $settings, $values, $ip ) {
		$recipients = $this->sanitize_recipients( $settings['email_to'] );
		if ( empty( $recipients ) ) {
			return false;
		}

		$replacements = $this->build_replacements( $values, $ip, 'html' === $settings['email_format'] );
		$subject      = strtr( $settings['email_subject'], $this->build_replacements( $values, $ip, false ) );
		$message      = strtr( $settings['email_message'], $replacements );
		$headers      = $this->build_headers( $settings, $values );

		if ( 'html' === $settings['email_format'] ) {
			$message = wpautop( $message );
		} else {
			$message = wp_strip_all_tags( $message );
		}

		return wp_mail( $recipients, $subject, $message, $headers );
	}

	/**
	 * 构建模板替换数据。
	 *
	 * @param array<string, string> $values 字段值。
	 * @param string                $ip     提交 IP。
	 * @param bool                  $html   是否按 HTML 转义。
	 * @return array<string, string>
	 */
	private function build_replacements( $values, $ip, $html ) {
		$replacements = array(
			'[date]' => current_time( 'Y-m-d H:i:s' ),
			'[ip]'   => $html ? esc_html( $ip ) : $ip,
		);

		foreach ( array( 'name', 'email', 'phone', 'company', 'message', 'requirements', 'budget' ) as $key ) {
			$value                      = isset( $values[ $key ] ) ? $values[ $key ] : '';
			$replacements[ '[' . $key . ']' ] = $html ? nl2br( esc_html( $value ) ) : $value;
		}

		return $replacements;
	}

	/**
	 * 构建邮件头。
	 *
	 * @param array<string, mixed>  $settings 表单配置。
	 * @param array<string, string> $values   字段值。
	 * @return array<int, string>
	 */
	private function build_headers( $settings, $values ) {
		$headers   = array();
		$headers[] = 'html' === $settings['email_format']
			? 'Content-Type: text/html; charset=UTF-8'
			: 'Content-Type: text/plain; charset=UTF-8';

		if ( ! empty( $settings['email_from'] ) && is_email( $settings['email_from'] ) ) {
			$from_name = ! empty( $settings['email_from_name'] ) ? $settings['email_from_name'] : get_bloginfo( 'name' );
			$headers[] = sprintf( 'From: %s <%s>', sanitize_text_field( $from_name ), sanitize_email( $settings['email_from'] ) );
		}

		$reply_to = str_replace( '[email]', isset( $values['email'] ) ? $values['email'] : '', $settings['email_reply_to'] );
		if ( is_email( $reply_to ) ) {
			$headers[] = 'Reply-To: ' . sanitize_email( $reply_to );
		}

		foreach ( array( 'email_cc' => 'Cc', 'email_bcc' => 'Bcc' ) as $key => $header_name ) {
			foreach ( $this->sanitize_recipients( $settings[ $key ] ) as $email ) {
				$headers[] = $header_name . ': ' . $email;
			}
		}

		return $headers;
	}

	/**
	 * 清洗逗号分隔的邮箱地址。
	 *
	 * @param string $emails 邮箱列表。
	 * @return array<int, string>
	 */
	private function sanitize_recipients( $emails ) {
		$valid = array();
		foreach ( explode( ',', (string) $emails ) as $email ) {
			$email = sanitize_email( trim( $email ) );
			if ( is_email( $email ) ) {
				$valid[] = $email;
			}
		}
		return array_values( array_unique( $valid ) );
	}
}
