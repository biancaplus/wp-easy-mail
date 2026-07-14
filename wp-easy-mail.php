<?php
/**
 * Plugin Name: WP Easy Mail
 * Plugin URI: https://wordpress.org/plugins/wp-easy-mail/
 * Description: 提供可配置的联系我们与获取报价表单、邮件通知及提交记录管理。
 * Version: 1.0.3
 * Requires at least: 6.0
 * Requires PHP: 7.4
 * Author: biancaplus
 * License: GPL-2.0-or-later
 * License URI: https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain: wp-easy-mail
 * Domain Path: /languages
 *
 * @package WPEasyMail
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

define( 'WPEM_VERSION', '1.0.3' );
define( 'WPEM_FILE', __FILE__ );
define( 'WPEM_PATH', plugin_dir_path( __FILE__ ) );
define( 'WPEM_URL', plugin_dir_url( __FILE__ ) );

require_once WPEM_PATH . 'includes/class-wpem-activator.php';
require_once WPEM_PATH . 'includes/class-wpem-form-types.php';
require_once WPEM_PATH . 'includes/class-wpem-forms.php';
require_once WPEM_PATH . 'includes/class-wpem-mailer.php';
require_once WPEM_PATH . 'includes/class-wpem-submission-handler.php';
require_once WPEM_PATH . 'includes/class-wpem-submissions-list-table.php';
require_once WPEM_PATH . 'includes/class-wpem-admin.php';
require_once WPEM_PATH . 'includes/class-wpem-privacy.php';
require_once WPEM_PATH . 'includes/class-wpem-plugin.php';

register_activation_hook( WPEM_FILE, array( 'WPEM_Activator', 'activate' ) );

/**
 * 启动插件。
 *
 * @return void
 */
function wpem_run_plugin() {
	$plugin = new WPEM_Plugin();
	$plugin->run();
}

wpem_run_plugin();
