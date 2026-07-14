<?php
/**
 * 插件卸载清理。
 *
 * @package WPEasyMail
 */

if ( ! defined( 'WP_UNINSTALL_PLUGIN' ) ) {
	exit;
}

/**
 * 清理当前站点的插件数据。
 *
 * @return void
 */
function wpem_uninstall_site_data() {
	$wpem_options = get_option( 'wpem_recaptcha_settings', array() );
	if ( empty( $wpem_options['delete_data'] ) ) {
		return;
	}

	global $wpdb;
	$wpem_form_ids = $wpdb->get_col( "SELECT ID FROM {$wpdb->posts} WHERE post_type = 'wpem_form'" );
	foreach ( $wpem_form_ids as $wpem_form_id ) {
		wp_delete_post( (int) $wpem_form_id, true );
	}

	$wpdb->query( "DROP TABLE IF EXISTS {$wpdb->prefix}wp_easy_mail_submissions" );
	delete_option( 'wpem_recaptcha_settings' );
	delete_option( 'wpem_db_version' );
}

if ( is_multisite() ) {
	$wpem_site_ids = get_sites(
		array(
			'fields' => 'ids',
			'number' => 0,
		)
	);
	foreach ( $wpem_site_ids as $wpem_site_id ) {
		switch_to_blog( (int) $wpem_site_id );
		wpem_uninstall_site_data();
		restore_current_blog();
	}
} else {
	wpem_uninstall_site_data();
}
