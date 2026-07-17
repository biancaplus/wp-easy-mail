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
	 * 当前请求是否渲染了表单短代码。
	 *
	 * @var bool
	 */
	private $form_shortcode_rendered = false;

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
		add_action( 'init', array( $this, 'register_frontend_assets' ) );
		add_action( 'init', array( 'WPEM_Activator', 'maybe_upgrade' ), 5 );
		add_action( 'wp_enqueue_scripts', array( $this, 'maybe_enqueue_frontend_assets' ) );
		add_action( 'wp_footer', array( $this, 'maybe_enqueue_frontend_assets_late' ), 1 );
		add_action( 'wp_footer', array( $this, 'maybe_enqueue_frontend_assets_late' ), 20 );
		add_filter( 'script_loader_tag', array( $this, 'filter_frontend_script_tag' ), 10, 3 );
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
	 * 注册前台资源，便于短代码渲染时延迟入队。
	 *
	 * @return void
	 */
	public function register_frontend_assets() {
		wp_register_style( 'wpem-frontend', WPEM_URL . 'assets/css/frontend.css', array(), WPEM_VERSION );
		wp_register_script( 'wpem-bootstrap', WPEM_URL . 'assets/js/bootstrap.js', array(), WPEM_VERSION, true );
		wp_register_script( 'wpem-frontend', WPEM_URL . 'assets/js/frontend.js', array(), WPEM_VERSION, true );
	}

	/**
	 * 在页面头部阶段预加载短代码资源。
	 *
	 * @return void
	 */
	public function maybe_enqueue_frontend_assets() {
		if ( ! $this->current_page_has_shortcode() ) {
			return;
		}

		$recaptcha    = get_option( 'wpem_recaptcha_settings', array() );
		$recaptcha_on = $this->is_recaptcha_enabled( $recaptcha );
		$this->enqueue_frontend_assets( $recaptcha, $recaptcha_on );
	}

	/**
	 * 短代码在 wp_enqueue_scripts 之后渲染时，于 footer 兜底加载资源。
	 *
	 * @return void
	 */
	public function maybe_enqueue_frontend_assets_late() {
		if ( ! $this->form_shortcode_rendered || $this->frontend_assets_enqueued ) {
			return;
		}

		$recaptcha    = get_option( 'wpem_recaptcha_settings', array() );
		$recaptcha_on = $this->is_recaptcha_enabled( $recaptcha );
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

		$this->form_shortcode_rendered = true;

		$settings     = WPEM_Forms::get_settings( $form_id );
		$fields       = WPEM_Form_Types::get_fields( $settings['form_type'] );
		$instance_id  = wp_unique_id( 'wpem-form-' );
		$recaptcha    = get_option( 'wpem_recaptcha_settings', array() );
		$recaptcha_on = $this->is_recaptcha_enabled( $recaptcha );
		$site_key     = $recaptcha_on ? $recaptcha['site_key'] : '';
		$theme_color  = WPEM_Form_Types::sanitize_theme_color(
			isset( $settings['theme_color'] ) ? $settings['theme_color'] : '#111111'
		);
		$template     = WPEM_Form_Templates::sanitize(
			isset( $settings['form_template'] ) ? $settings['form_template'] : 'classic'
		);
		$ajax_url            = admin_url( 'admin-ajax.php' );
		$frontend_script_url = WPEM_URL . 'assets/js/frontend.js?ver=' . WPEM_VERSION;
		$bootstrap_script_url = WPEM_URL . 'assets/js/bootstrap.js?ver=' . WPEM_VERSION;

		$this->enqueue_frontend_assets( $recaptcha, $recaptcha_on );

		ob_start();
		include WPEM_Form_Templates::get_path( $template );
		$output = (string) ob_get_clean();

		$output .= $this->render_frontend_bootstrap_script( $bootstrap_script_url );

		return $output;
	}

	/**
	 * 判断当前页面是否包含表单短代码。
	 *
	 * @return bool
	 */
	private function current_page_has_shortcode() {
		$post = get_post();
		if ( $post instanceof WP_Post && $this->content_contains_shortcode( $post->post_content ) ) {
			return true;
		}

		$queried = get_queried_object();
		if ( $queried instanceof WP_Post && ( ! $post instanceof WP_Post || $queried->ID !== $post->ID ) ) {
			return $this->content_contains_shortcode( $queried->post_content );
		}

		return false;
	}

	/**
	 * 判断内容字符串是否包含表单短代码（兼容古腾堡 Custom HTML 等块）。
	 *
	 * @param string $content 文章内容。
	 * @return bool
	 */
	private function content_contains_shortcode( $content ) {
		if ( ! is_string( $content ) || '' === $content ) {
			return false;
		}

		if ( has_shortcode( $content, 'wp_easy_mail' ) ) {
			return true;
		}

		if ( false !== stripos( $content, '[wp_easy_mail' ) ) {
			return true;
		}

		if ( ! function_exists( 'parse_blocks' ) || false === strpos( $content, '<!-- wp:' ) ) {
			return false;
		}

		return $this->blocks_contain_shortcode( parse_blocks( $content ) );
	}

	/**
	 * 递归检查区块树中是否包含短代码。
	 *
	 * @param array<int, array<string, mixed>> $blocks 区块列表。
	 * @return bool
	 */
	private function blocks_contain_shortcode( $blocks ) {
		foreach ( $blocks as $block ) {
			if ( ! empty( $block['innerHTML'] ) && false !== stripos( $block['innerHTML'], '[wp_easy_mail' ) ) {
				return true;
			}

			if ( ! empty( $block['innerContent'] ) && is_array( $block['innerContent'] ) ) {
				foreach ( $block['innerContent'] as $inner ) {
					if ( is_string( $inner ) && false !== stripos( $inner, '[wp_easy_mail' ) ) {
						return true;
					}
				}
			}

			if ( ! empty( $block['innerBlocks'] ) && $this->blocks_contain_shortcode( $block['innerBlocks'] ) ) {
				return true;
			}

			if ( 'core/block' === ( $block['blockName'] ?? '' ) && ! empty( $block['attrs']['ref'] ) ) {
				$reusable = get_post( (int) $block['attrs']['ref'] );
				if ( $reusable instanceof WP_Post && $this->content_contains_shortcode( $reusable->post_content ) ) {
					return true;
				}
			}
		}

		return false;
	}

	/**
	 * 输出兜底 bootstrap 脚本标签（直接跟随表单 HTML，避免优化插件漏载）。
	 *
	 * @param string $bootstrap_script_url bootstrap.js 地址。
	 * @return string
	 */
	private function render_frontend_bootstrap_script( $bootstrap_script_url ) {
		return sprintf(
			'<script src="%1$s" data-wpem-bootstrap="1" data-cfasync="false"></script>',
			esc_url( $bootstrap_script_url )
		);
	}

	/**
	 * 判断是否启用 reCAPTCHA。
	 *
	 * @param array<string, mixed> $recaptcha reCAPTCHA 配置。
	 * @return bool
	 */
	private function is_recaptcha_enabled( $recaptcha ) {
		return ! empty( $recaptcha['enabled'] ) && ! empty( $recaptcha['site_key'] ) && ! empty( $recaptcha['secret_key'] );
	}

	/**
	 * 获取前台脚本配置。
	 *
	 * @param array<string, mixed> $recaptcha    reCAPTCHA 配置。
	 * @param bool                 $recaptcha_on 是否启用。
	 * @return array<string, mixed>
	 */
	private function get_frontend_config( $recaptcha, $recaptcha_on ) {
		return array(
			'ajaxUrl'          => admin_url( 'admin-ajax.php' ),
			'recaptchaEnabled' => $recaptcha_on,
			'siteKey'          => $recaptcha_on ? $recaptcha['site_key'] : '',
		);
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

		wp_enqueue_style( 'wpem-frontend' );
		wp_enqueue_script( 'wpem-bootstrap' );
		wp_enqueue_script( 'wpem-frontend' );
		wp_localize_script(
			'wpem-frontend',
			'WPEasyMail',
			$this->get_frontend_config( $recaptcha, $recaptcha_on )
		);

		if ( $recaptcha_on ) {
			$url = add_query_arg( 'render', $recaptcha['site_key'], 'https://www.google.com/recaptcha/api.js' );
			wp_enqueue_script( 'wpem-recaptcha-v3', esc_url( $url ), array(), null, true );
		}
	}

	/**
	 * 为前台脚本添加兼容属性，降低缓存/延迟加载插件干扰。
	 *
	 * @param string $tag    脚本标签。
	 * @param string $handle 脚本句柄。
	 * @param string $src    脚本地址。
	 * @return string
	 */
	public function filter_frontend_script_tag( $tag, $handle, $src ) {
		unset( $src );

		$handles = array( 'wpem-bootstrap', 'wpem-frontend', 'wpem-recaptcha-v3' );
		if ( ! in_array( $handle, $handles, true ) ) {
			return $tag;
		}

		if ( false !== strpos( $tag, 'data-wpem-frontend' ) || false !== strpos( $tag, 'data-wpem-bootstrap' ) ) {
			return $tag;
		}

		$attr = 'wpem-bootstrap' === $handle ? 'data-wpem-bootstrap="1"' : 'data-wpem-frontend="1"';

		return str_replace( ' src=', ' ' . $attr . ' data-cfasync="false" src=', $tag );
	}
}
