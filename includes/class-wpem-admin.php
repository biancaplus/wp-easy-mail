<?php
/**
 * 插件后台界面。
 *
 * @package WPEasyMail
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * 管理后台菜单、设置与记录操作。
 */
class WPEM_Admin {

	/**
	 * 注册后台钩子。
	 *
	 * @return void
	 */
	public function register() {
		add_action( 'admin_menu', array( $this, 'register_menu' ) );
		add_action( 'admin_menu', array( $this, 'adjust_menu_items' ), 999 );
		add_action( 'admin_enqueue_scripts', array( $this, 'enqueue_assets' ) );
		add_action( 'admin_post_wpem_submission_action', array( $this, 'handle_submission_action' ) );
		add_action( 'wp_ajax_wpem_mark_submission_read', array( $this, 'ajax_mark_submission_read' ) );
	}

	/**
	 * 注册“联系我们”后台菜单。
	 *
	 * @return void
	 */
	public function register_menu() {
		$unread = $this->get_unread_count();
		$label  = __( '联系我们', 'wp-easy-mail' );
		if ( $unread > 0 ) {
			$label .= sprintf( ' <span class="awaiting-mod">%d</span>', $unread );
		}

		add_menu_page(
			__( '联系我们', 'wp-easy-mail' ),
			$label,
			'edit_posts',
			'wpem',
			array( $this, 'redirect_to_forms_list' ),
			'dashicons-email-alt',
			30
		);
		add_submenu_page(
			'wpem',
			__( '新建表单', 'wp-easy-mail' ),
			__( '新建表单', 'wp-easy-mail' ),
			'edit_posts',
			'post-new.php?post_type=wpem_form'
		);
		add_submenu_page(
			'wpem',
			__( '提交记录', 'wp-easy-mail' ),
			__( '提交记录', 'wp-easy-mail' ),
			'manage_options',
			'wpem-submissions',
			array( $this, 'render_submissions_page' )
		);
		add_submenu_page(
			'wpem',
			__( 'reCAPTCHA v3', 'wp-easy-mail' ),
			__( 'reCAPTCHA v3', 'wp-easy-mail' ),
			'manage_options',
			'wpem-recaptcha',
			array( $this, 'render_recaptcha_page' )
		);
	}

	/**
	 * 加载后台资源。
	 *
	 * @param string $hook 当前页面钩子。
	 * @return void
	 */
	public function enqueue_assets( $hook ) {
		$screen         = get_current_screen();
		$is_form_screen = $screen && 'wpem_form' === $screen->post_type;
		$is_plugin_page = false !== strpos( $hook, 'wpem' );
		if ( ! $is_form_screen && ! $is_plugin_page ) {
			return;
		}

		wp_enqueue_style( 'wpem-admin', WPEM_URL . 'assets/css/admin.css', array(), WPEM_VERSION );
		wp_enqueue_script( 'wpem-admin', WPEM_URL . 'assets/js/admin.js', array( 'jquery' ), WPEM_VERSION, true );
		wp_localize_script(
			'wpem-admin',
			'WPEasyMailAdmin',
			array(
				'ajaxUrl'           => admin_url( 'admin-ajax.php' ),
				'markReadNonce'     => wp_create_nonce( 'wpem_mark_submission_read' ),
				'confirmRead'       => __( '标记为已读？', 'wp-easy-mail' ),
				'confirmDelete'     => __( '确定删除此记录吗？', 'wp-easy-mail' ),
				'statusRead'        => __( '已读', 'wp-easy-mail' ),
				'statusUnread'      => __( '未读', 'wp-easy-mail' ),
				'emailMessageByType' => array(
					'contact_us' => WPEM_Form_Types::get_default_email_message( 'contact_us' ),
					'get_quote'  => WPEM_Form_Types::get_default_email_message( 'get_quote' ),
				),
			)
		);
	}

	/**
	 * 将顶级菜单跳转到表单管理列表。
	 *
	 * @return void
	 */
	public function redirect_to_forms_list() {
		if ( ! current_user_can( 'edit_posts' ) ) {
			wp_die( esc_html__( '您无权访问此页面。', 'wp-easy-mail' ) );
		}

		wp_safe_redirect( admin_url( 'edit.php?post_type=wpem_form' ) );
		exit;
	}

	/**
	 * 将第一个子菜单改为「表单管理」，作为默认入口。
	 *
	 * @return void
	 */
	public function adjust_menu_items() {
		global $submenu;

		if ( ! isset( $submenu['wpem'][0] ) ) {
			return;
		}

		$submenu['wpem'][0][0] = __( '表单管理', 'wp-easy-mail' );
		$submenu['wpem'][0][2] = 'edit.php?post_type=wpem_form';
	}

	/**
	 * 输出提交记录页。
	 *
	 * @return void
	 */
	public function render_submissions_page() {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( '您无权访问此页面。', 'wp-easy-mail' ) );
		}

		global $wpdb;
		$table = $wpdb->prefix . 'wp_easy_mail_submissions';

		$this->process_submissions_page_actions( $table );

		$submissions  = $wpdb->get_results( "SELECT * FROM {$table} ORDER BY submitted_at DESC", ARRAY_A );
		$unread_count = (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$table} WHERE status = 'unread'" );
		?>
		<div class="wrap wpem-submissions-wrap">
			<h1>
				<?php esc_html_e( '提交记录', 'wp-easy-mail' ); ?>
				<?php if ( $unread_count > 0 ) : ?>
					<span class="wpem-unread-badge update-plugins count-<?php echo esc_attr( (string) $unread_count ); ?>">
						<span class="update-count"><?php echo esc_html( (string) $unread_count ); ?></span>
					</span>
				<?php endif; ?>
			</h1>
			<?php $this->maybe_render_submissions_notice(); ?>

			<?php if ( empty( $submissions ) ) : ?>
				<p><?php esc_html_e( '暂无提交记录', 'wp-easy-mail' ); ?></p>
			<?php else : ?>
				<table class="wp-list-table widefat fixed striped wpem-submissions-table">
					<thead>
						<tr>
							<th style="width:5%"><?php esc_html_e( 'ID', 'wp-easy-mail' ); ?></th>
							<th style="width:10%"><?php esc_html_e( '表单类型', 'wp-easy-mail' ); ?></th>
							<th style="width:9%"><?php esc_html_e( '姓名', 'wp-easy-mail' ); ?></th>
							<th style="width:10%"><?php esc_html_e( '电话', 'wp-easy-mail' ); ?></th>
							<th style="width:14%"><?php esc_html_e( '邮箱', 'wp-easy-mail' ); ?></th>
							<th style="width:20%"><?php esc_html_e( '信息', 'wp-easy-mail' ); ?></th>
							<th style="width:11%"><?php esc_html_e( '提交时间', 'wp-easy-mail' ); ?></th>
							<th style="width:5%"><?php esc_html_e( '状态', 'wp-easy-mail' ); ?></th>
							<th style="width:7%"><?php esc_html_e( '邮件', 'wp-easy-mail' ); ?></th>
							<th style="width:7%"><?php esc_html_e( '操作人', 'wp-easy-mail' ); ?></th>
							<th style="width:8%"><?php esc_html_e( '操作', 'wp-easy-mail' ); ?></th>
						</tr>
					</thead>
					<tbody>
						<?php foreach ( $submissions as $submission ) : ?>
							<?php
							$fields       = $this->decode_field_data( $submission['field_data'] );
							$message_text = $this->get_message_preview( $fields, $submission['form_type'] );
							$full_message = $this->get_full_message_text( $submission, $fields );
							$row_class    = 'unread' === $submission['status'] ? 'wpem-row-unread' : '';
							$type_class   = 'get_quote' === $submission['form_type'] ? 'wpem-tag-quote' : 'wpem-tag-contact';
							?>
							<tr id="wpem-row-<?php echo esc_attr( (string) $submission['id'] ); ?>" class="<?php echo esc_attr( $row_class ); ?>">
								<td><?php echo esc_html( (string) $submission['id'] ); ?></td>
								<td>
									<span class="wpem-type-tag <?php echo esc_attr( $type_class ); ?>">
										<?php echo esc_html( WPEM_Form_Types::get_type_label( $submission['form_type'] ) ); ?>
									</span>
								</td>
								<td><?php echo esc_html( isset( $fields['name'] ) ? $fields['name'] : '—' ); ?></td>
								<td><?php echo esc_html( isset( $fields['phone'] ) ? $fields['phone'] : '—' ); ?></td>
								<td><?php echo esc_html( isset( $fields['email'] ) ? $fields['email'] : '—' ); ?></td>
								<td>
									<a href="#" class="wpem-view-message" data-id="<?php echo esc_attr( (string) $submission['id'] ); ?>">
										<?php echo esc_html( $message_text ? wp_trim_words( $message_text, 20 ) : __( '（无内容）', 'wp-easy-mail' ) ); ?>
									</a>
									<div class="wpem-full-message" hidden><?php echo esc_html( $full_message ); ?></div>
								</td>
								<td><?php echo esc_html( $submission['submitted_at'] ); ?></td>
								<td class="wpem-status-cell">
									<?php if ( 'unread' === $submission['status'] ) : ?>
										<span class="wpem-status-unread"><?php esc_html_e( '未读', 'wp-easy-mail' ); ?></span>
									<?php else : ?>
										<span class="wpem-status-read"><?php esc_html_e( '已读', 'wp-easy-mail' ); ?></span>
									<?php endif; ?>
								</td>
								<td><?php echo $this->render_email_status_html( $submission['email_status'] ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?></td>
								<td class="wpem-operator-cell">
									<?php
									echo esc_html(
										! empty( $submission['operator_name'] )
											? $submission['operator_name']
											: '—'
									);
									?>
								</td>
								<td class="wpem-actions-cell">
									<?php
									$mark_url = wp_nonce_url(
										add_query_arg(
											array(
												'page'   => 'wpem-submissions',
												'action' => 'mark_read',
												'id'     => absint( $submission['id'] ),
											),
											admin_url( 'admin.php' )
										),
										'wpem_submission_' . absint( $submission['id'] )
									);
									$delete_url = wp_nonce_url(
										add_query_arg(
											array(
												'page'   => 'wpem-submissions',
												'action' => 'delete',
												'id'     => absint( $submission['id'] ),
											),
											admin_url( 'admin.php' )
										),
										'wpem_submission_' . absint( $submission['id'] )
									);
									?>
									<?php if ( 'unread' === $submission['status'] ) : ?>
										<a href="<?php echo esc_url( $mark_url ); ?>" class="wpem-mark-read-btn"><?php esc_html_e( '已读', 'wp-easy-mail' ); ?></a>
										<span class="wpem-action-sep">|</span>
									<?php endif; ?>
									<a href="<?php echo esc_url( $delete_url ); ?>" class="wpem-delete-link"><?php esc_html_e( '删除', 'wp-easy-mail' ); ?></a>
								</td>
							</tr>
						<?php endforeach; ?>
					</tbody>
				</table>
			<?php endif; ?>

			<div id="wpem-message-modal" class="wpem-message-modal" hidden>
				<div class="wpem-message-modal-dialog" role="dialog" aria-modal="true" aria-labelledby="wpem-message-modal-title">
					<button type="button" class="wpem-message-modal-close" aria-label="<?php esc_attr_e( '关闭', 'wp-easy-mail' ); ?>">&times;</button>
					<h2 id="wpem-message-modal-title"><?php esc_html_e( '完整信息', 'wp-easy-mail' ); ?></h2>
					<div id="wpem-message-modal-content" class="wpem-message-modal-content"></div>
				</div>
			</div>
		</div>
		<?php
	}

	/**
	 * 处理提交记录页的已读 / 删除操作。
	 *
	 * @param string $table 表名。
	 * @return void
	 */
	private function process_submissions_page_actions( $table ) {
		if ( empty( $_GET['action'] ) || empty( $_GET['id'] ) ) {
			return;
		}

		$action = sanitize_key( wp_unslash( $_GET['action'] ) );
		$id     = absint( $_GET['id'] );
		if ( ! in_array( $action, array( 'mark_read', 'delete' ), true ) || ! $id ) {
			return;
		}

		check_admin_referer( 'wpem_submission_' . $id );
		global $wpdb;

		if ( 'mark_read' === $action ) {
			$this->mark_submission_read( $table, $id );
			$notice = 'marked';
		} elseif ( 'delete' === $action ) {
			$this->record_operator_before_delete( $table, $id );
			$wpdb->delete( $table, array( 'id' => $id ), array( '%d' ) );
			$notice = 'deleted';
		} else {
			return;
		}

		wp_safe_redirect(
			add_query_arg(
				array(
					'page'         => 'wpem-submissions',
					'wpem_notice'  => $notice,
				),
				admin_url( 'admin.php' )
			)
		);
		exit;
	}

	/**
	 * 输出操作成功提示。
	 *
	 * @return void
	 */
	private function maybe_render_submissions_notice() {
		if ( empty( $_GET['wpem_notice'] ) ) {
			return;
		}

		$notice = sanitize_key( wp_unslash( $_GET['wpem_notice'] ) );
		if ( 'marked' === $notice ) {
			echo '<div class="notice notice-success is-dismissible"><p>' . esc_html__( '已标记为已读', 'wp-easy-mail' ) . '</p></div>';
		}
		if ( 'deleted' === $notice ) {
			echo '<div class="notice notice-success is-dismissible"><p>' . esc_html__( '记录已删除', 'wp-easy-mail' ) . '</p></div>';
		}
	}

	/**
	 * AJAX：查看信息时标记已读并记录操作人。
	 *
	 * @return void
	 */
	public function ajax_mark_submission_read() {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( array( 'message' => __( '无权操作', 'wp-easy-mail' ) ), 403 );
		}

		check_ajax_referer( 'wpem_mark_submission_read', 'nonce' );

		$id = isset( $_POST['id'] ) ? absint( $_POST['id'] ) : 0;
		if ( ! $id ) {
			wp_send_json_error( array( 'message' => __( 'ID 无效', 'wp-easy-mail' ) ), 400 );
		}

		global $wpdb;
		$table    = $wpdb->prefix . 'wp_easy_mail_submissions';
		$operator = $this->mark_submission_read( $table, $id );
		$unread   = (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$table} WHERE status = 'unread'" );

		wp_send_json_success(
			array(
				'unread_count'  => $unread,
				'operator_name' => $operator['operator_name'],
			)
		);
	}

	/**
	 * 标记已读并写入操作人。
	 *
	 * @param string $table 表名。
	 * @param int    $id    提交 ID。
	 * @return array{operator_id:int,operator_name:string}
	 */
	private function mark_submission_read( $table, $id ) {
		global $wpdb;
		$operator = $this->get_current_operator();
		$wpdb->update(
			$table,
			array(
				'status'        => 'read',
				'operator_id'   => $operator['operator_id'],
				'operator_name' => $operator['operator_name'],
			),
			array( 'id' => $id ),
			array( '%s', '%d', '%s' ),
			array( '%d' )
		);
		return $operator;
	}

	/**
	 * 删除前写入操作人，便于审计日志扩展；当前行会被删除。
	 *
	 * @param string $table 表名。
	 * @param int    $id    提交 ID。
	 * @return void
	 */
	private function record_operator_before_delete( $table, $id ) {
		global $wpdb;
		$operator = $this->get_current_operator();
		$wpdb->update(
			$table,
			array(
				'operator_id'   => $operator['operator_id'],
				'operator_name' => $operator['operator_name'],
			),
			array( 'id' => $id ),
			array( '%d', '%s' ),
			array( '%d' )
		);
	}

	/**
	 * 获取当前登录操作人信息。
	 *
	 * @return array{operator_id:int,operator_name:string}
	 */
	private function get_current_operator() {
		$user = wp_get_current_user();
		$name = $user->display_name ? $user->display_name : $user->user_login;
		return array(
			'operator_id'   => (int) $user->ID,
			'operator_name' => (string) $name,
		);
	}

	/**
	 * 解析字段 JSON。
	 *
	 * @param string $raw 原始 JSON。
	 * @return array<string, string>
	 */
	private function decode_field_data( $raw ) {
		$fields = json_decode( (string) $raw, true );
		return is_array( $fields ) ? $fields : array();
	}

	/**
	 * 获取列表信息列摘要。
	 *
	 * @param array<string, string> $fields    字段值。
	 * @param string                $form_type 表单类型。
	 * @return string
	 */
	private function get_message_preview( $fields, $form_type ) {
		if ( 'get_quote' === $form_type ) {
			return isset( $fields['requirements'] ) ? (string) $fields['requirements'] : '';
		}
		return isset( $fields['message'] ) ? (string) $fields['message'] : '';
	}

	/**
	 * 生成弹窗完整信息文本。
	 *
	 * @param array<string, mixed>  $submission 提交记录。
	 * @param array<string, string> $fields     字段值。
	 * @return string
	 */
	private function get_full_message_text( $submission, $fields ) {
		$settings    = WPEM_Forms::get_settings( (int) $submission['form_id'] );
		$definitions = WPEM_Form_Types::get_fields( $submission['form_type'] );
		$lines       = array();

		$lines[] = sprintf(
			'%s：%s',
			__( '表单类型', 'wp-easy-mail' ),
			WPEM_Form_Types::get_type_label( $submission['form_type'] )
		);
		$form_title = get_the_title( (int) $submission['form_id'] );
		$lines[]    = sprintf(
			'%s：%s',
			__( '表单', 'wp-easy-mail' ),
			$form_title ? $form_title : __( '已删除的表单', 'wp-easy-mail' )
		);

		foreach ( $definitions as $key => $definition ) {
			$field_settings = isset( $settings['fields'][ $key ] ) ? $settings['fields'][ $key ] : $definition;
			if ( ! WPEM_Form_Types::is_field_visible( $field_settings, $definition ) ) {
				continue;
			}
			$label   = isset( $field_settings['label'] ) ? $field_settings['label'] : $definition['label'];
			$value   = isset( $fields[ $key ] ) ? $fields[ $key ] : '';
			$lines[] = $label . '：' . $value;
		}

		$lines[] = __( '提交时间', 'wp-easy-mail' ) . '：' . $submission['submitted_at'];

		return implode( "\n", $lines );
	}

	/**
	 * 渲染邮件状态 HTML。
	 *
	 * @param string $status 状态。
	 * @return string
	 */
	private function render_email_status_html( $status ) {
		$map = array(
			'sent'    => array( 'wpem-email-sent', __( '发送成功', 'wp-easy-mail' ) ),
			'failed'  => array( 'wpem-email-failed', __( '发送失败', 'wp-easy-mail' ) ),
			'sending' => array( 'wpem-email-sending', __( '发送中', 'wp-easy-mail' ) ),
			'pending' => array( 'wpem-email-pending', __( '等待发送', 'wp-easy-mail' ) ),
		);

		if ( ! isset( $map[ $status ] ) ) {
			return '<span class="wpem-email-unknown">' . esc_html__( '未知', 'wp-easy-mail' ) . '</span>';
		}

		return sprintf(
			'<span class="%1$s">%2$s</span>',
			esc_attr( $map[ $status ][0] ),
			esc_html( $map[ $status ][1] )
		);
	}

	/**
	 * 输出 reCAPTCHA 配置页。
	 *
	 * @return void
	 */
	public function render_recaptcha_page() {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( '您无权访问此页面。', 'wp-easy-mail' ) );
		}

		$options = get_option( 'wpem_recaptcha_settings', array() );
		if ( isset( $_POST['wpem_save_recaptcha'] ) ) {
			check_admin_referer( 'wpem_save_recaptcha' );
			$enabled          = ! empty( $_POST['enabled'] );
			$site_key         = isset( $_POST['site_key'] ) ? sanitize_text_field( wp_unslash( $_POST['site_key'] ) ) : '';
			$submitted_secret = isset( $_POST['secret_key'] ) ? sanitize_text_field( wp_unslash( $_POST['secret_key'] ) ) : '';
			$secret           = '' !== $submitted_secret ? $submitted_secret : ( isset( $options['secret_key'] ) ? $options['secret_key'] : '' );
			$threshold        = isset( $_POST['threshold'] ) ? (float) wp_unslash( $_POST['threshold'] ) : 0.5;
			$site_key_changed = ! empty( $options['site_key'] ) && $site_key !== $options['site_key'];

			if ( $enabled && ( '' === $site_key || '' === $secret || ( $site_key_changed && '' === $submitted_secret ) ) ) {
				echo '<div class="notice notice-error"><p>' . esc_html__( '启用保护时必须同时提供匹配的 Site Key 和 Secret Key；更换 Site Key 时请重新输入 Secret Key。', 'wp-easy-mail' ) . '</p></div>';
			} else {
				$options = array(
					'enabled'     => $enabled,
					'site_key'    => $site_key,
					'secret_key'  => $secret,
					'threshold'   => min( 1, max( 0, $threshold ) ),
					'delete_data' => ! empty( $_POST['delete_data'] ),
				);
				update_option( 'wpem_recaptcha_settings', $options, false );
				echo '<div class="notice notice-success is-dismissible"><p>' . esc_html__( '设置已保存。', 'wp-easy-mail' ) . '</p></div>';
			}
		}
		?>
		<div class="wrap">
			<h1><?php esc_html_e( 'reCAPTCHA v3 配置', 'wp-easy-mail' ); ?></h1>
			<form method="post">
				<?php wp_nonce_field( 'wpem_save_recaptcha' ); ?>
				<table class="form-table" role="presentation">
					<tr>
						<th scope="row"><?php esc_html_e( '启用保护', 'wp-easy-mail' ); ?></th>
						<td><label><input type="checkbox" name="enabled" value="1" <?php checked( ! empty( $options['enabled'] ) ); ?>> <?php esc_html_e( '为所有表单启用 reCAPTCHA v3', 'wp-easy-mail' ); ?></label></td>
					</tr>
					<tr>
						<th scope="row"><label for="wpem-site-key"><?php esc_html_e( 'Site Key', 'wp-easy-mail' ); ?></label></th>
						<td><input id="wpem-site-key" class="regular-text" type="text" name="site_key" value="<?php echo esc_attr( isset( $options['site_key'] ) ? $options['site_key'] : '' ); ?>"></td>
					</tr>
					<tr>
						<th scope="row"><label for="wpem-secret-key"><?php esc_html_e( 'Secret Key', 'wp-easy-mail' ); ?></label></th>
						<td>
							<input id="wpem-secret-key" class="regular-text" type="password" name="secret_key" value="" autocomplete="new-password">
							<p class="description"><?php echo ! empty( $options['secret_key'] ) ? esc_html__( '密钥已保存；留空可保持不变。', 'wp-easy-mail' ) : esc_html__( '请输入 Secret Key。', 'wp-easy-mail' ); ?></p>
						</td>
					</tr>
					<tr>
						<th scope="row"><label for="wpem-threshold"><?php esc_html_e( '分数阈值', 'wp-easy-mail' ); ?></label></th>
						<td><input id="wpem-threshold" type="number" min="0" max="1" step="0.1" name="threshold" value="<?php echo esc_attr( isset( $options['threshold'] ) ? $options['threshold'] : '0.5' ); ?>"></td>
					</tr>
					<tr>
						<th scope="row"><?php esc_html_e( '卸载数据', 'wp-easy-mail' ); ?></th>
						<td><label><input type="checkbox" name="delete_data" value="1" <?php checked( ! empty( $options['delete_data'] ) ); ?>> <?php esc_html_e( '卸载插件时删除全部表单、提交记录和设置', 'wp-easy-mail' ); ?></label></td>
					</tr>
				</table>
				<?php submit_button( __( '保存设置', 'wp-easy-mail' ), 'primary', 'wpem_save_recaptcha' ); ?>
			</form>
		</div>
		<?php
	}

	/**
	 * 兼容旧版 admin-post 操作入口。
	 *
	 * @return void
	 */
	public function handle_submission_action() {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( '您无权执行此操作。', 'wp-easy-mail' ) );
		}

		$id        = isset( $_GET['submission'] ) ? absint( $_GET['submission'] ) : 0;
		$operation = isset( $_GET['operation'] ) ? sanitize_key( wp_unslash( $_GET['operation'] ) ) : '';
		check_admin_referer( 'wpem_submission_' . $id );

		global $wpdb;
		$table = $wpdb->prefix . 'wp_easy_mail_submissions';
		if ( 'read' === $operation ) {
			$this->mark_submission_read( $table, $id );
		}
		if ( 'delete' === $operation ) {
			$this->record_operator_before_delete( $table, $id );
			$wpdb->delete( $table, array( 'id' => $id ), array( '%d' ) );
		}

		wp_safe_redirect( admin_url( 'admin.php?page=wpem-submissions' ) );
		exit;
	}

	/**
	 * 获取未读记录数量。
	 *
	 * @return int
	 */
	private function get_unread_count() {
		global $wpdb;
		$table = $wpdb->prefix . 'wp_easy_mail_submissions';
		if ( get_option( 'wpem_db_version' ) !== WPEM_Activator::DB_VERSION ) {
			return 0;
		}
		return (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$table} WHERE status = 'unread'" );
	}
}
