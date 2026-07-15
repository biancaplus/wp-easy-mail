<?php
/**
 * 插件激活与数据库升级。
 *
 * @package WPEasyMail
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * 管理插件数据库结构。
 */
class WPEM_Activator {

	/**
	 * 当前数据库结构版本。
	 */
	const DB_VERSION = '1.1.0';

	/**
	 * 激活插件并创建数据表。
	 *
	 * @return void
	 */
	public static function activate() {
		self::create_submissions_table();
		update_option( 'wpem_db_version', self::DB_VERSION, false );
	}

	/**
	 * 在版本变化时升级数据库。
	 *
	 * @return void
	 */
	public static function maybe_upgrade() {
		if ( self::DB_VERSION !== get_option( 'wpem_db_version' ) ) {
			self::activate();
		}
	}

	/**
	 * 创建提交记录表。
	 *
	 * @return void
	 */
	private static function create_submissions_table() {
		global $wpdb;

		$table_name      = $wpdb->prefix . 'wp_easy_mail_submissions';
		$charset_collate = $wpdb->get_charset_collate();
		$sql             = "CREATE TABLE {$table_name} (
			id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
			form_id bigint(20) unsigned NOT NULL,
			form_type varchar(30) NOT NULL,
			field_data longtext NOT NULL,
			status varchar(20) NOT NULL DEFAULT 'unread',
			email_status varchar(20) NOT NULL DEFAULT 'pending',
			ip_address varchar(100) NOT NULL DEFAULT '',
			user_agent text NOT NULL,
			operator_id bigint(20) unsigned NOT NULL DEFAULT 0,
			operator_name varchar(191) NOT NULL DEFAULT '',
			submitted_at datetime NOT NULL,
			PRIMARY KEY  (id),
			KEY form_id (form_id),
			KEY form_type (form_type),
			KEY status (status),
			KEY submitted_at (submitted_at)
		) {$charset_collate};";

		require_once ABSPATH . 'wp-admin/includes/upgrade.php';
		dbDelta( $sql );
	}
}
