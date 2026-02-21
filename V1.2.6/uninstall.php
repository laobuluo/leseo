<?php
// 判断是不是从 WordPress 后台调用的
if ( ! defined( 'WP_UNINSTALL_PLUGIN' ) ) {
	exit();
}

$options = get_option( '_lezaiyun_leseo_option' );
if ( is_array( $options ) && isset( $options['lseso-s3-backup_url_path'] ) ) {
	update_option( 'upload_url_path', $options['lseso-s3-backup_url_path'] );
}
delete_option( '_lezaiyun_leseo_option' );
