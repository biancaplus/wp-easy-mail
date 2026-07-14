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
		$screen = get_current_screen();
		$is_form_screen = $screen && 'wpem_form' === $screen->post_type;
		$is_plugin_page = false !== strpos( $hook, 'wpem' );
		if ( ! $is_form_screen && ! $is_plugin_page ) {
			return;
		}

		wp_enqueue_style( 'wpem-admin', WPEM_URL . 'assets/css/admin.css', array(), WPEM_VERSION );
		wp_enqueue_script( 'wpem-admin', WPEM_URL . 'assets/js/admin.js', array(), WPEM_VERSION, true );
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

		$submission_id = isset( $_GET['submission_id'] ) ? absint( $_GET['submission_id'] ) : 0;
		$list_table    = new WPEM_Submissions_List_Table();
		$list_table->prepare_items();
		?>
		<div class="wrap">
			<h1><?php esc_html_e( '提交记录', 'wp-easy-mail' ); ?></h1>
			<?php
			if ( $submission_id ) {
				$this->render_submission_detail( $submission_id );
			}
			?>
			<form method="get">
				<input type="hidden" name="page" value="wpem-submissions">
				<?php $list_table->display(); ?>
			</form>
		</div>
		<?php
	}

	/**
	 * 输出提交详情。
	 *
	 * @param int $submission_id 提交 ID。
	 * @return void
	 */
	private function render_submission_detail( $submission_id ) {
		global $wpdb;

		$table = $wpdb->prefix . 'wp_easy_mail_submissions';
		$row   = $wpdb->get_row( $wpdb->prepare( "SELECT * FROM {$table} WHERE id = %d", $submission_id ), ARRAY_A );
		if ( ! $row ) {
			echo '<div class="notice notice-error"><p>' . esc_html__( '提交记录不存在。', 'wp-easy-mail' ) . '</p></div>';
			return;
		}

		$fields      = json_decode( $row['field_data'], true );
		$fields      = is_array( $fields ) ? $fields : array();
		$settings    = WPEM_Forms::get_settings( (int) $row['form_id'] );
		$definitions = WPEM_Form_Types::get_fields( $row['form_type'] );
		?>
		<section class="wpem-detail">
			<h2>
				<?php
				echo esc_html(
					sprintf(
						__( '提交 #%1$d · %2$s', 'wp-easy-mail' ),
						(int) $row['id'],
						WPEM_Form_Types::get_type_label( $row['form_type'] )
					)
				);
				?>
			</h2>
			<table class="widefat striped">
				<tbody>
					<?php foreach ( $definitions as $key => $definition ) : ?>
						<?php
						$field_settings = isset( $settings['fields'][ $key ] ) ? $settings['fields'][ $key ] : $definition;
						if ( ! WPEM_Form_Types::is_field_visible( $field_settings, $definition ) ) {
							continue;
						}
						?>
						<tr>
							<th><?php echo esc_html( isset( $settings['fields'][ $key ]['label'] ) ? $settings['fields'][ $key ]['label'] : $definition['label'] ); ?></th>
							<td class="wpem-detail-value"><?php echo nl2br( esc_html( isset( $fields[ $key ] ) ? $fields[ $key ] : '' ) ); ?></td>
						</tr>
					<?php endforeach; ?>
					<tr><th><?php esc_html_e( 'IP 地址', 'wp-easy-mail' ); ?></th><td><?php echo esc_html( $row['ip_address'] ); ?></td></tr>
					<tr><th><?php esc_html_e( '提交时间', 'wp-easy-mail' ); ?></th><td><?php echo esc_html( $row['submitted_at'] ); ?></td></tr>
				</tbody>
			</table>
		</section>
		<?php
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
	 * 处理标记已读或删除操作。
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
			$wpdb->update( $table, array( 'status' => 'read' ), array( 'id' => $id ), array( '%s' ), array( '%d' ) );
		}
		if ( 'delete' === $operation ) {
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
