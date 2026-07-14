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
	 * @param int                   $form_id  表单 ID。
	 * @return bool
	 */
	public function send( $settings, $values, $ip, $form_id = 0 ) {
		$recipients = $this->sanitize_recipients( $settings['email_to'] );
		if ( empty( $recipients ) ) {
			return false;
		}

		$is_html      = ( 'html' === $settings['email_format'] );
		$replacements = $this->build_replacements( $values, $ip, false );
		$subject      = strtr( $settings['email_subject'], $replacements );
		$message      = strtr( $settings['email_message'], $replacements );
		$headers      = $this->build_headers( $settings, $values );

		if ( $is_html ) {
			$message = $this->wrap_html_template( $settings, $subject, $message, $form_id );
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
	 * 将正文包装为卡片式 HTML 邮件。
	 *
	 * @param array<string, mixed> $settings 表单配置。
	 * @param string               $subject  邮件主题。
	 * @param string               $message  已替换占位符的正文。
	 * @param int                  $form_id  表单 ID。
	 * @return string
	 */
	private function wrap_html_template( $settings, $subject, $message, $form_id ) {
		$theme_color = WPEM_Form_Types::sanitize_theme_color(
			isset( $settings['theme_color'] ) ? $settings['theme_color'] : '#111111'
		);
		$site_name   = (string) get_bloginfo( 'name' );
		$form_title  = $form_id ? (string) get_the_title( $form_id ) : '';
		$header_text = $form_title
			? sprintf( '【%s】%s', $site_name, $form_title )
			: sprintf( '【%s】%s', $site_name, $subject );
		$body_html   = $this->format_message_body( $message );
		$site_url    = home_url( '/' );

		ob_start();
		?>
<!DOCTYPE html>
<html lang="zh-CN">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title><?php echo esc_html( $subject ); ?></title>
</head>
<body style="margin:0;padding:0;background:#f3f4f6;font-family:-apple-system,BlinkMacSystemFont,'Segoe UI',Roboto,'Helvetica Neue',Arial,sans-serif;color:#1f2937;">
<table role="presentation" cellpadding="0" cellspacing="0" border="0" width="100%" style="background:#f3f4f6;padding:32px 12px;">
	<tr>
		<td align="center">
			<table role="presentation" cellpadding="0" cellspacing="0" border="0" width="100%" style="max-width:560px;background:#ffffff;border-radius:12px;overflow:hidden;border:1px solid #e5e7eb;">
				<tr>
					<td align="center" style="padding:18px 24px;background:<?php echo esc_attr( $theme_color ); ?>;color:#ffffff;font-size:16px;font-weight:700;line-height:1.4;">
						<?php echo esc_html( $header_text ); ?>
					</td>
				</tr>
				<tr>
					<td style="padding:28px 28px 8px;font-size:15px;line-height:1.7;color:#111827;">
						<p style="margin:0 0 12px;">您好，</p>
						<p style="margin:0 0 20px;">您收到了一份新的表单提交，请查看下方详情并尽快处理。</p>
					</td>
				</tr>
				<tr>
					<td style="padding:0 28px 20px;">
						<table role="presentation" cellpadding="0" cellspacing="0" border="0" width="100%" style="background:#f7f8fa;border-radius:10px;">
							<tr>
								<td style="padding:18px 20px;">
									<p style="margin:0 0 12px;color:#6b7280;font-size:13px;line-height:1.4;">提交详情</p>
									<?php echo $body_html; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- 内容已在 format_message_body 中转义。 ?>
								</td>
							</tr>
						</table>
					</td>
				</tr>
				<tr>
					<td style="padding:0 28px 24px;font-size:13px;line-height:1.6;color:#6b7280;">
						<p style="margin:0;">本邮件由系统自动发送，请勿直接回复无关内容。如需联系提交人，请使用邮件中的联系方式。</p>
					</td>
				</tr>
				<tr>
					<td align="center" style="padding:16px 28px 24px;border-top:1px solid #f0f0f0;font-size:12px;line-height:1.6;color:#9ca3af;">
						<a href="<?php echo esc_url( $site_url ); ?>" style="color:#9ca3af;text-decoration:none;"><?php echo esc_html( $site_name ); ?></a>
					</td>
				</tr>
			</table>
		</td>
	</tr>
</table>
</body>
</html>
		<?php
		return (string) ob_get_clean();
	}

	/**
	 * 将正文转为适合卡片展示的 HTML。
	 *
	 * @param string $message 已替换占位符的正文。
	 * @return string
	 */
	private function format_message_body( $message ) {
		$plain = trim( wp_strip_all_tags( str_replace( array( '<br>', '<br/>', '<br />' ), "\n", $message ) ) );
		$lines = preg_split( '/\r\n|\r|\n/', $plain );
		$lines = array_values(
			array_filter(
				array_map( 'trim', is_array( $lines ) ? $lines : array() ),
				static function ( $line ) {
					return '' !== $line;
				}
			)
		);

		if ( empty( $lines ) ) {
			return '<p style="margin:0;color:#374151;font-size:14px;">' . esc_html__( '（无提交内容）', 'wp-easy-mail' ) . '</p>';
		}

		$all_pairs = true;
		$pairs     = array();
		foreach ( $lines as $line ) {
			if ( ! preg_match( '/^(.+?)[：:]\s*(.*)$/u', $line, $matches ) ) {
				$all_pairs = false;
				break;
			}
			$pairs[] = array(
				'label' => $matches[1],
				'value' => $matches[2],
			);
		}

		if ( $all_pairs && ! empty( $pairs ) ) {
			$rows = '';
			foreach ( $pairs as $index => $pair ) {
				$border = ( $index < count( $pairs ) - 1 ) ? 'border-bottom:1px solid #e5e7eb;' : '';
				$rows  .= '<tr>'
					. '<td style="padding:10px 0;vertical-align:top;width:108px;color:#6b7280;font-size:13px;' . $border . '">' . esc_html( $pair['label'] ) . '</td>'
					. '<td style="padding:10px 0;vertical-align:top;color:#111827;font-size:14px;font-weight:600;word-break:break-word;' . $border . '">' . nl2br( esc_html( $pair['value'] ) ) . '</td>'
					. '</tr>';
			}

			return '<table role="presentation" cellpadding="0" cellspacing="0" border="0" width="100%">' . $rows . '</table>';
		}

		$html = '';
		foreach ( $lines as $line ) {
			$html .= '<p style="margin:0 0 10px;color:#111827;font-size:14px;line-height:1.6;">' . esc_html( $line ) . '</p>';
		}

		return $html;
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
