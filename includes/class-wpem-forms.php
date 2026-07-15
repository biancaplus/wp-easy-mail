<?php
/**
 * 表单实体与后台编辑配置。
 *
 * @package WPEasyMail
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * 管理表单实体。
 */
class WPEM_Forms {

	/**
	 * 注册 WordPress 钩子。
	 *
	 * @return void
	 */
	public function register() {
		add_action( 'init', array( $this, 'register_post_type' ) );
		add_action( 'add_meta_boxes', array( $this, 'add_meta_boxes' ) );
		add_action( 'save_post_wpem_form', array( $this, 'save_form' ) );
		add_filter( 'manage_wpem_form_posts_columns', array( $this, 'filter_columns' ) );
		add_action( 'manage_wpem_form_posts_custom_column', array( $this, 'render_column' ), 10, 2 );
	}

	/**
	 * 注册私有表单实体。
	 *
	 * @return void
	 */
	public function register_post_type() {
		register_post_type(
			'wpem_form',
			array(
				'labels' => array(
					'name'          => __( '表单', 'wp-easy-mail' ),
					'singular_name' => __( '表单', 'wp-easy-mail' ),
					'add_new_item'  => __( '新建表单', 'wp-easy-mail' ),
					'edit_item'     => __( '编辑表单', 'wp-easy-mail' ),
					'search_items'  => __( '搜索表单', 'wp-easy-mail' ),
					'not_found'     => __( '暂无表单', 'wp-easy-mail' ),
				),
				'public'              => false,
				'show_ui'             => true,
				'show_in_menu'        => false,
				'supports'            => array( 'title' ),
				'capability_type'     => 'post',
				'map_meta_cap'        => true,
				'exclude_from_search' => true,
				'show_in_rest'        => false,
			)
		);
	}

	/**
	 * 注册表单配置框。
	 *
	 * @return void
	 */
	public function add_meta_boxes() {
		add_meta_box(
			'wpem_form_settings',
			__( '表单配置', 'wp-easy-mail' ),
			array( $this, 'render_settings_box' ),
			'wpem_form',
			'normal',
			'high'
		);
		add_meta_box(
			'wpem_form_shortcode',
			__( '表单短代码', 'wp-easy-mail' ),
			array( $this, 'render_shortcode_box' ),
			'wpem_form',
			'side',
			'high'
		);
	}

	/**
	 * 获取表单保存配置。
	 *
	 * @param int $form_id 表单 ID。
	 * @return array<string, mixed>
	 */
	public static function get_settings( $form_id ) {
		$saved = get_post_meta( $form_id, '_wpem_settings', true );
		$type  = is_array( $saved ) && ! empty( $saved['form_type'] ) ? $saved['form_type'] : 'contact_us';
		$settings = wp_parse_args( is_array( $saved ) ? $saved : array(), WPEM_Form_Types::get_defaults( $type ) );

		// 尚未保存配置的表单，始终读取当前站点设置作为邮件默认值。
		if ( ! is_array( $saved ) || empty( $saved ) ) {
			$site_email                   = WPEM_Form_Types::get_site_email_defaults();
			$settings['email_to']         = $site_email['email_to'];
			$settings['email_from_name']  = $site_email['email_from_name'];
		}

		return $settings;
	}

	/**
	 * 输出表单配置框。
	 *
	 * @param WP_Post $post 当前表单。
	 * @return void
	 */
	public function render_settings_box( $post ) {
		$settings = self::get_settings( $post->ID );
		$types    = WPEM_Form_Types::get_types();
		wp_nonce_field( 'wpem_save_form', 'wpem_form_nonce' );
		?>
		<div class="wpem-settings" data-current-type="<?php echo esc_attr( $settings['form_type'] ); ?>">
			<nav class="wpem-tabs" aria-label="<?php esc_attr_e( '表单配置分组', 'wp-easy-mail' ); ?>">
				<button type="button" class="wpem-tab is-active" data-tab="form"><?php esc_html_e( '表单设置', 'wp-easy-mail' ); ?></button>
				<button type="button" class="wpem-tab" data-tab="email"><?php esc_html_e( '邮件设置', 'wp-easy-mail' ); ?></button>
				<button type="button" class="wpem-tab" data-tab="messages"><?php esc_html_e( '自定义消息', 'wp-easy-mail' ); ?></button>
				<button type="button" class="wpem-tab" data-tab="fields"><?php esc_html_e( '字段设置', 'wp-easy-mail' ); ?></button>
			</nav>

			<section class="wpem-panel is-active" data-panel="form">
				<?php
				$this->render_select( 'form_type', __( '表单类型', 'wp-easy-mail' ), $settings['form_type'], $types );
				$this->render_template_picker(
					isset( $settings['form_template'] ) ? $settings['form_template'] : 'classic',
					isset( $settings['theme_color'] ) ? $settings['theme_color'] : '#111111',
					isset( $settings['submit_text'] ) ? $settings['submit_text'] : __( '发送信息', 'wp-easy-mail' )
				);
				$this->render_textarea( 'description', __( '表单描述', 'wp-easy-mail' ), $settings['description'] );
				$this->render_text_input( 'submit_text', __( '提交按钮文字', 'wp-easy-mail' ), $settings['submit_text'] );
				$this->render_color_input(
					'theme_color',
					__( '主题色', 'wp-easy-mail' ),
					isset( $settings['theme_color'] ) ? $settings['theme_color'] : '#111111',
					__( '同步用于前台提交按钮、合规链接与 HTML 通知邮件的主题色。', 'wp-easy-mail' )
				);
				?>
			</section>

			<section class="wpem-panel" data-panel="email">
				<?php
				$site_email = WPEM_Form_Types::get_site_email_defaults();
				$this->render_text_input(
					'email_to',
					__( '收件人', 'wp-easy-mail' ),
					$settings['email_to'],
					sprintf(
						/* translators: %s: WordPress admin email */
						__( '新建表单时默认读取「设置 > 常规」中的管理员邮箱（当前：%s）；保存后按该表单独立配置生效。多个邮箱请用英文逗号分隔。', 'wp-easy-mail' ),
						$site_email['email_to']
					)
				);
				$this->render_text_input( 'email_subject', __( '邮件主题', 'wp-easy-mail' ), $settings['email_subject'] );
				$this->render_textarea(
					'email_message',
					__( '邮件正文（备用）', 'wp-easy-mail' ),
					$settings['email_message'],
					__( '实际发送的提交详情会按当前表单类型的可见字段自动生成；此处仅作为参考预览或备用模板，切换类型后请同步检查字段设置。', 'wp-easy-mail' ),
					9
				);
				$this->render_text_input( 'email_from', __( '发件人邮箱', 'wp-easy-mail' ), $settings['email_from'], __( '留空时使用 WordPress 默认发件人。', 'wp-easy-mail' ) );
				$this->render_text_input(
					'email_from_name',
					__( '发件人名称', 'wp-easy-mail' ),
					$settings['email_from_name'],
					sprintf(
						/* translators: %s: WordPress site title */
						__( '新建表单时默认读取「设置 > 常规」中的站点名称（当前：%s）；保存后按该表单独立配置生效。', 'wp-easy-mail' ),
						$site_email['email_from_name']
					)
				);
				$this->render_text_input( 'email_reply_to', __( '回复地址', 'wp-easy-mail' ), $settings['email_reply_to'] );
				$this->render_text_input( 'email_cc', __( '抄送', 'wp-easy-mail' ), $settings['email_cc'] );
				$this->render_text_input( 'email_bcc', __( '密送', 'wp-easy-mail' ), $settings['email_bcc'] );
				$this->render_select(
					'email_format',
					__( '邮件格式', 'wp-easy-mail' ),
					$settings['email_format'],
					array(
						'html'  => __( 'HTML', 'wp-easy-mail' ),
						'plain' => __( '纯文本', 'wp-easy-mail' ),
					)
				);
				?>
			</section>

			<section class="wpem-panel" data-panel="messages">
				<?php
				$this->render_text_input( 'success_message', __( '成功消息', 'wp-easy-mail' ), $settings['success_message'] );
				$this->render_text_input( 'error_message', __( '失败消息', 'wp-easy-mail' ), $settings['error_message'] );
				$this->render_text_input( 'required_message', __( '必填消息', 'wp-easy-mail' ), $settings['required_message'] );
				$this->render_text_input( 'invalid_message', __( '格式错误消息', 'wp-easy-mail' ), $settings['invalid_message'] );
				?>
			</section>

			<section class="wpem-panel" data-panel="fields">
				<?php foreach ( $types as $type => $type_label ) : ?>
					<div class="wpem-fields-group" data-form-type="<?php echo esc_attr( $type ); ?>" <?php echo $type !== $settings['form_type'] ? 'hidden' : ''; ?>>
						<h3><?php echo esc_html( $type_label ); ?></h3>
						<?php $this->render_fields( $type, $settings ); ?>
					</div>
				<?php endforeach; ?>
			</section>
		</div>
		<?php
	}

	/**
	 * 输出字段配置。
	 *
	 * @param string               $type     表单类型。
	 * @param array<string, mixed> $settings 当前配置。
	 * @return void
	 */
	private function render_fields( $type, $settings ) {
		$disabled = $type !== $settings['form_type'];
		foreach ( WPEM_Form_Types::get_fields( $type ) as $key => $field ) {
			$current = isset( $settings['fields'][ $key ] ) ? $settings['fields'][ $key ] : $field;
			?>
			<fieldset class="wpem-field-config">
				<legend><?php echo esc_html( $field['label'] ); ?></legend>
				<label>
					<span><?php esc_html_e( '标签', 'wp-easy-mail' ); ?></span>
					<input type="text" name="wpem_settings[fields][<?php echo esc_attr( $key ); ?>][label]" value="<?php echo esc_attr( $current['label'] ); ?>" <?php disabled( $disabled ); ?>>
				</label>
				<label>
					<span><?php esc_html_e( '占位文字', 'wp-easy-mail' ); ?></span>
					<input type="text" name="wpem_settings[fields][<?php echo esc_attr( $key ); ?>][placeholder]" value="<?php echo esc_attr( $current['placeholder'] ); ?>" <?php disabled( $disabled ); ?>>
				</label>
				<?php if ( ! empty( $field['toggleable'] ) ) : ?>
					<label class="wpem-checkbox">
						<input type="checkbox" class="wpem-field-visible" name="wpem_settings[fields][<?php echo esc_attr( $key ); ?>][visible]" value="1" <?php checked( ! isset( $current['visible'] ) || ! empty( $current['visible'] ) ); ?> <?php disabled( $disabled ); ?>>
						<?php esc_html_e( '显示字段', 'wp-easy-mail' ); ?>
					</label>
					<label class="wpem-checkbox wpem-field-required">
						<input type="checkbox" name="wpem_settings[fields][<?php echo esc_attr( $key ); ?>][required]" value="1" <?php checked( ! empty( $current['required'] ) ); ?> <?php disabled( $disabled || ( isset( $current['visible'] ) && empty( $current['visible'] ) ) ); ?>>
						<?php esc_html_e( '设为必填', 'wp-easy-mail' ); ?>
					</label>
				<?php else : ?>
					<input type="hidden" name="wpem_settings[fields][<?php echo esc_attr( $key ); ?>][required]" value="1" <?php disabled( $disabled ); ?>>
					<span class="description"><?php esc_html_e( '固定必填', 'wp-easy-mail' ); ?></span>
				<?php endif; ?>
			</fieldset>
			<?php
		}
	}

	/**
	 * 输出文本输入项。
	 *
	 * @param string $key         配置键。
	 * @param string $label       标签。
	 * @param string $value       当前值。
	 * @param string $description 描述。
	 * @return void
	 */
	private function render_text_input( $key, $label, $value, $description = '' ) {
		?>
		<label class="wpem-row">
			<span class="wpem-row-label"><?php echo esc_html( $label ); ?></span>
			<input type="text" class="regular-text" name="wpem_settings[<?php echo esc_attr( $key ); ?>]" value="<?php echo esc_attr( $value ); ?>">
			<?php if ( $description ) : ?>
				<span class="description"><?php echo esc_html( $description ); ?></span>
			<?php endif; ?>
		</label>
		<?php
	}

	/**
	 * 输出主题色选择项。
	 *
	 * @param string $key         配置键。
	 * @param string $label       标签。
	 * @param string $value       当前值。
	 * @param string $description 描述。
	 * @return void
	 */
	private function render_color_input( $key, $label, $value, $description = '' ) {
		$color = WPEM_Form_Types::sanitize_theme_color( $value );
		?>
		<div class="wpem-row">
			<span class="wpem-row-label"><?php echo esc_html( $label ); ?></span>
			<div class="wpem-color-field">
				<input
					type="color"
					class="wpem-color-picker"
					value="<?php echo esc_attr( $color ); ?>"
					aria-label="<?php echo esc_attr( $label ); ?>"
				>
				<input
					type="text"
					class="regular-text wpem-color-text"
					name="wpem_settings[<?php echo esc_attr( $key ); ?>]"
					value="<?php echo esc_attr( $color ); ?>"
					pattern="^#([A-Fa-f0-9]{6})$"
					maxlength="7"
					placeholder="#111111"
				>
			</div>
			<?php if ( $description ) : ?>
				<span class="description"><?php echo esc_html( $description ); ?></span>
			<?php endif; ?>
		</div>
		<?php
	}

	/**
	 * 输出表单模板选择与样式预览。
	 *
	 * @param string $current     当前模板。
	 * @param string $theme_color 主题色。
	 * @param string $submit_text 按钮文案。
	 * @return void
	 */
	private function render_template_picker( $current, $theme_color, $submit_text ) {
		$current     = WPEM_Form_Templates::sanitize( $current );
		$theme_color = WPEM_Form_Types::sanitize_theme_color( $theme_color );
		?>
		<div class="wpem-row wpem-template-row">
			<span class="wpem-row-label"><?php esc_html_e( '表单模板', 'wp-easy-mail' ); ?></span>
			<div class="wpem-template-picker" data-theme-color="<?php echo esc_attr( $theme_color ); ?>">
				<?php foreach ( WPEM_Form_Templates::get_templates() as $slug => $template ) : ?>
					<label class="wpem-template-option <?php echo $slug === $current ? 'is-selected' : ''; ?>">
						<span class="wpem-template-option-head">
							<input
								type="radio"
								name="wpem_settings[form_template]"
								value="<?php echo esc_attr( $slug ); ?>"
								<?php checked( $current, $slug ); ?>
							>
							<strong><?php echo esc_html( $template['label'] ); ?></strong>
						</span>
						<span class="description"><?php echo esc_html( $template['description'] ); ?></span>
						<div
							class="wpem-template-preview wpem-form-wrap wpem-template-<?php echo esc_attr( $slug ); ?>"
							style="--wpem-accent: <?php echo esc_attr( $theme_color ); ?>;"
							aria-hidden="true"
						>
							<header class="wpem-form-header">
								<h2><?php esc_html_e( '联系我们', 'wp-easy-mail' ); ?></h2>
								<div class="wpem-form-description">
									<p><?php esc_html_e( '我们期待您的回复。', 'wp-easy-mail' ); ?></p>
								</div>
							</header>
							<div class="wpem-fields">
								<?php if ( 'card' === $slug ) : ?>
									<div class="wpem-fields-row">
										<div class="wpem-field">
											<label><?php esc_html_e( '姓名', 'wp-easy-mail' ); ?> <span class="wpem-required">*</span></label>
											<input type="text" disabled placeholder="<?php esc_attr_e( '请输入您的姓名', 'wp-easy-mail' ); ?>">
										</div>
										<div class="wpem-field">
											<label><?php esc_html_e( '公司', 'wp-easy-mail' ); ?></label>
											<input type="text" disabled placeholder="<?php esc_attr_e( '请输入公司名称', 'wp-easy-mail' ); ?>">
										</div>
									</div>
									<div class="wpem-fields-row">
										<div class="wpem-field">
											<label><?php esc_html_e( '电子邮件', 'wp-easy-mail' ); ?> <span class="wpem-required">*</span></label>
											<input type="text" disabled placeholder="<?php esc_attr_e( '请输入您的电子邮件地址', 'wp-easy-mail' ); ?>">
										</div>
										<div class="wpem-field">
											<label><?php esc_html_e( '电话', 'wp-easy-mail' ); ?> <span class="wpem-required">*</span></label>
											<input type="text" disabled placeholder="<?php esc_attr_e( '请输入您的电话号码', 'wp-easy-mail' ); ?>">
										</div>
									</div>
								<?php else : ?>
									<div class="wpem-field">
										<label><?php esc_html_e( '姓名', 'wp-easy-mail' ); ?> <span class="wpem-required">*</span></label>
										<input type="text" disabled placeholder="<?php esc_attr_e( '请输入您的姓名', 'wp-easy-mail' ); ?>">
									</div>
									<div class="wpem-field">
										<label><?php esc_html_e( '电子邮件', 'wp-easy-mail' ); ?> <span class="wpem-required">*</span></label>
										<input type="text" disabled placeholder="<?php esc_attr_e( '请输入您的电子邮件地址', 'wp-easy-mail' ); ?>">
									</div>
								<?php endif; ?>
							</div>
							<?php if ( 'card' === $slug ) : ?>
								<div class="wpem-submit-wrap">
									<button type="button" class="wpem-submit" tabindex="-1"><?php echo esc_html( $submit_text ); ?></button>
								</div>
							<?php else : ?>
								<button type="button" class="wpem-submit" tabindex="-1"><?php echo esc_html( $submit_text ); ?></button>
							<?php endif; ?>
						</div>
					</label>
				<?php endforeach; ?>
			</div>
			<span class="description"><?php esc_html_e( '选择后可在下方预览样式；主题色变化会同步到预览按钮。', 'wp-easy-mail' ); ?></span>
		</div>
		<?php
	}

	/**
	 * 输出多行输入项。
	 *
	 * @param string $key         配置键。
	 * @param string $label       标签。
	 * @param string $value       当前值。
	 * @param string $description 描述。
	 * @param int    $rows        行数。
	 * @return void
	 */
	private function render_textarea( $key, $label, $value, $description = '', $rows = 4 ) {
		?>
		<label class="wpem-row">
			<span class="wpem-row-label"><?php echo esc_html( $label ); ?></span>
			<textarea class="large-text" rows="<?php echo esc_attr( $rows ); ?>" name="wpem_settings[<?php echo esc_attr( $key ); ?>]"><?php echo esc_textarea( $value ); ?></textarea>
			<?php if ( $description ) : ?>
				<span class="description"><?php echo esc_html( $description ); ?></span>
			<?php endif; ?>
		</label>
		<?php
	}

	/**
	 * 输出下拉选择项。
	 *
	 * @param string                $key     配置键。
	 * @param string                $label   标签。
	 * @param string                $value   当前值。
	 * @param array<string, string> $options 选项。
	 * @return void
	 */
	private function render_select( $key, $label, $value, $options ) {
		?>
		<label class="wpem-row">
			<span class="wpem-row-label"><?php echo esc_html( $label ); ?></span>
			<select name="wpem_settings[<?php echo esc_attr( $key ); ?>]">
				<?php foreach ( $options as $option_value => $option_label ) : ?>
					<option value="<?php echo esc_attr( $option_value ); ?>" <?php selected( $value, $option_value ); ?>><?php echo esc_html( $option_label ); ?></option>
				<?php endforeach; ?>
			</select>
		</label>
		<?php
	}

	/**
	 * 输出短代码配置框。
	 *
	 * @param WP_Post $post 当前表单。
	 * @return void
	 */
	public function render_shortcode_box( $post ) {
		if ( 'auto-draft' === $post->post_status ) {
			echo '<p>' . esc_html__( '保存表单后将生成短代码。', 'wp-easy-mail' ) . '</p>';
			return;
		}
		$shortcode = sprintf( '[wp_easy_mail id="%d"]', $post->ID );
		printf( '<input type="text" class="widefat wpem-shortcode" readonly value="%s">', esc_attr( $shortcode ) );
	}

	/**
	 * 保存表单配置。
	 *
	 * @param int $post_id 表单 ID。
	 * @return void
	 */
	public function save_form( $post_id ) {
		if ( ! isset( $_POST['wpem_form_nonce'] ) || ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['wpem_form_nonce'] ) ), 'wpem_save_form' ) ) {
			return;
		}
		if ( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE ) {
			return;
		}
		if ( ! current_user_can( 'edit_post', $post_id ) || ! isset( $_POST['wpem_settings'] ) || ! is_array( $_POST['wpem_settings'] ) ) {
			return;
		}

		$raw      = wp_unslash( $_POST['wpem_settings'] );
		$type     = isset( $raw['form_type'] ) && WPEM_Form_Types::is_valid_type( $raw['form_type'] ) ? $raw['form_type'] : 'contact_us';
		$defaults = WPEM_Form_Types::get_defaults( $type );
		$settings = array(
			'form_type'        => $type,
			'description'      => isset( $raw['description'] ) ? sanitize_textarea_field( $raw['description'] ) : $defaults['description'],
			'submit_text'      => isset( $raw['submit_text'] ) ? sanitize_text_field( $raw['submit_text'] ) : $defaults['submit_text'],
			'theme_color'      => isset( $raw['theme_color'] ) ? WPEM_Form_Types::sanitize_theme_color( $raw['theme_color'] ) : $defaults['theme_color'],
			'form_template'    => isset( $raw['form_template'] ) ? WPEM_Form_Templates::sanitize( $raw['form_template'] ) : $defaults['form_template'],
			'email_to'         => isset( $raw['email_to'] ) ? sanitize_text_field( $raw['email_to'] ) : $defaults['email_to'],
			'email_subject'    => isset( $raw['email_subject'] ) ? sanitize_text_field( $raw['email_subject'] ) : $defaults['email_subject'],
			'email_message'    => isset( $raw['email_message'] ) ? wp_kses_post( $raw['email_message'] ) : $defaults['email_message'],
			'email_from'       => isset( $raw['email_from'] ) ? sanitize_email( $raw['email_from'] ) : '',
			'email_from_name'  => isset( $raw['email_from_name'] ) ? sanitize_text_field( $raw['email_from_name'] ) : '',
			'email_reply_to'   => isset( $raw['email_reply_to'] ) ? sanitize_text_field( $raw['email_reply_to'] ) : '',
			'email_cc'         => isset( $raw['email_cc'] ) ? sanitize_text_field( $raw['email_cc'] ) : '',
			'email_bcc'        => isset( $raw['email_bcc'] ) ? sanitize_text_field( $raw['email_bcc'] ) : '',
			'email_format'     => isset( $raw['email_format'] ) && 'plain' === $raw['email_format'] ? 'plain' : 'html',
			'success_message'  => isset( $raw['success_message'] ) ? sanitize_text_field( $raw['success_message'] ) : $defaults['success_message'],
			'error_message'    => isset( $raw['error_message'] ) ? sanitize_text_field( $raw['error_message'] ) : $defaults['error_message'],
			'required_message' => isset( $raw['required_message'] ) ? sanitize_text_field( $raw['required_message'] ) : $defaults['required_message'],
			'invalid_message'  => isset( $raw['invalid_message'] ) ? sanitize_text_field( $raw['invalid_message'] ) : $defaults['invalid_message'],
			'fields'           => array(),
		);

		foreach ( WPEM_Form_Types::get_fields( $type ) as $key => $field ) {
			$raw_field = isset( $raw['fields'][ $key ] ) && is_array( $raw['fields'][ $key ] ) ? $raw['fields'][ $key ] : array();
			$visible   = empty( $field['toggleable'] ) || ! empty( $raw_field['visible'] );
			$settings['fields'][ $key ] = array(
				'label'       => isset( $raw_field['label'] ) ? sanitize_text_field( $raw_field['label'] ) : $field['label'],
				'placeholder' => isset( $raw_field['placeholder'] ) ? sanitize_text_field( $raw_field['placeholder'] ) : $field['placeholder'],
				'visible'     => $visible,
				'required'    => $visible && ( empty( $field['toggleable'] ) || ! empty( $raw_field['required'] ) ),
			);
		}

		update_post_meta( $post_id, '_wpem_settings', $settings );
	}

	/**
	 * 自定义表单列表列。
	 *
	 * @param array<string, string> $columns 原列。
	 * @return array<string, string>
	 */
	public function filter_columns( $columns ) {
		return array(
			'cb'        => $columns['cb'],
			'title'     => __( '表单名称', 'wp-easy-mail' ),
			'form_type' => __( '表单类型', 'wp-easy-mail' ),
			'shortcode' => __( '短代码', 'wp-easy-mail' ),
			'date'      => $columns['date'],
		);
	}

	/**
	 * 输出表单列表自定义列。
	 *
	 * @param string $column  列名。
	 * @param int    $post_id 表单 ID。
	 * @return void
	 */
	public function render_column( $column, $post_id ) {
		$settings = self::get_settings( $post_id );
		if ( 'form_type' === $column ) {
			echo esc_html( WPEM_Form_Types::get_type_label( $settings['form_type'] ) );
		}
		if ( 'shortcode' === $column ) {
			printf( '<code>[wp_easy_mail id="%d"]</code>', absint( $post_id ) );
		}
	}
}
