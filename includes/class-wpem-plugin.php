<?php
/**
 * 插件主控制器。
 *
 * @package WPEasyMail
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * 组装插件各模块并渲染前台表单。
 */
class WPEM_Plugin {

	/**
	 * 前台资源是否已入队。
	 *
	 * @var bool
	 */
	private $frontend_assets_enqueued = false;

	/**
	 * 注册插件全部功能。
	 *
	 * @return void
	 */
	public function run() {
		$forms      = new WPEM_Forms();
		$admin      = new WPEM_Admin();
		$privacy    = new WPEM_Privacy();
		$submission = new WPEM_Submission_Handler( new WPEM_Mailer() );

		$forms->register();
		$admin->register();
		$privacy->register();
		$submission->register();

		add_action( 'plugins_loaded', array( $this, 'load_textdomain' ) );
		add_action( 'init', array( 'WPEM_Activator', 'maybe_upgrade' ), 5 );
		add_action( 'wp_enqueue_scripts', array( $this, 'maybe_enqueue_frontend_assets' ) );
		add_shortcode( 'wp_easy_mail', array( $this, 'render_shortcode' ) );
	}

	/**
	 * 加载翻译文件。
	 *
	 * @return void
	 */
	public function load_textdomain() {
		load_plugin_textdomain( 'wp-easy-mail', false, dirname( plugin_basename( WPEM_FILE ) ) . '/languages' );
	}

	/**
	 * 在页面头部阶段预加载短代码资源。
	 *
	 * @return void
	 */
	public function maybe_enqueue_frontend_assets() {
		global $post;

		if ( ! $post instanceof WP_Post || ! has_shortcode( $post->post_content, 'wp_easy_mail' ) ) {
			return;
		}

		$recaptcha    = get_option( 'wpem_recaptcha_settings', array() );
		$recaptcha_on = ! empty( $recaptcha['enabled'] ) && ! empty( $recaptcha['site_key'] ) && ! empty( $recaptcha['secret_key'] );
		$this->enqueue_frontend_assets( $recaptcha, $recaptcha_on );
	}

	/**
	 * 渲染表单短代码。
	 *
	 * @param array<string, mixed> $attributes 短代码属性。
	 * @return string
	 */
	public function render_shortcode( $attributes ) {
		$attributes = shortcode_atts( array( 'id' => 0 ), $attributes, 'wp_easy_mail' );
		$form_id    = absint( $attributes['id'] );
		if ( ! $form_id || 'wpem_form' !== get_post_type( $form_id ) || 'publish' !== get_post_status( $form_id ) ) {
			return current_user_can( 'edit_posts' )
				? '<p class="wpem-notice">' . esc_html__( 'WP Easy Mail：表单不存在或尚未发布。', 'wp-easy-mail' ) . '</p>'
				: '';
		}

		$settings     = WPEM_Forms::get_settings( $form_id );
		$fields       = WPEM_Form_Types::get_fields( $settings['form_type'] );
		$instance_id  = wp_unique_id( 'wpem-form-' );
		$recaptcha    = get_option( 'wpem_recaptcha_settings', array() );
		$recaptcha_on = ! empty( $recaptcha['enabled'] ) && ! empty( $recaptcha['site_key'] ) && ! empty( $recaptcha['secret_key'] );

		$this->enqueue_frontend_assets( $recaptcha, $recaptcha_on );

		ob_start();
		include WPEM_PATH . 'templates/form.php';
		return (string) ob_get_clean();
	}

	/**
	 * 加载前台资源。
	 *
	 * @param array<string, mixed> $recaptcha    reCAPTCHA 配置。
	 * @param bool                 $recaptcha_on 是否启用。
	 * @return void
	 */
	private function enqueue_frontend_assets( $recaptcha, $recaptcha_on ) {
		if ( $this->frontend_assets_enqueued ) {
			return;
		}
		$this->frontend_assets_enqueued = true;

		wp_enqueue_style( 'wpem-frontend', WPEM_URL . 'assets/css/frontend.css', array(), WPEM_VERSION );
		wp_enqueue_script( 'wpem-frontend', WPEM_URL . 'assets/js/frontend.js', array(), WPEM_VERSION, true );
		wp_localize_script(
			'wpem-frontend',
			'WPEasyMail',
			array(
				'ajaxUrl'          => admin_url( 'admin-ajax.php' ),
				'recaptchaEnabled' => $recaptcha_on,
				'siteKey'          => $recaptcha_on ? $recaptcha['site_key'] : '',
			)
		);

		if ( $recaptcha_on ) {
			$url = add_query_arg( 'render', $recaptcha['site_key'], 'https://www.google.com/recaptcha/api.js' );
			wp_enqueue_script( 'wpem-recaptcha-v3', esc_url( $url ), array(), null, true );
		}
	}
}
