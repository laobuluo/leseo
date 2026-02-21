<?php
namespace lezaiyun\Leseo\inc\TinyPNG;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class LeseoTinyPng {
	/**
	 * TinyPNG API endpoint
	 * @var string
	 */
	private static $endpoint = 'https://api.tinify.com/shrink';

	/**
	 * 压缩本地图片文件并覆盖写回原文件
	 *
	 * @param string $file_path
	 * @param string $mime
	 * @param string $api_key
	 * @return true|\WP_Error
	 */
	public static function compress_file( $file_path, $mime, $api_key ) {
		if ( empty( $file_path ) || ! file_exists( $file_path ) ) {
			return new \WP_Error( 'leseo_tinypng_missing_file', '文件不存在，无法压缩' );
		}
		if ( empty( $api_key ) ) {
			return new \WP_Error( 'leseo_tinypng_missing_key', 'TinyPNG API Key 为空' );
		}

		$body = @file_get_contents( $file_path );
		if ( $body === false ) {
			return new \WP_Error( 'leseo_tinypng_read_failed', '读取图片文件失败' );
		}

		$auth = base64_encode( 'api:' . $api_key );
		$before_size = filesize( $file_path );
		$resp = wp_remote_post(
			self::$endpoint,
			array(
				'timeout' => 60,
				'headers' => array(
					'Authorization' => 'Basic ' . $auth,
					'Content-Type'  => $mime,
				),
				'body'    => $body,
			)
		);

		if ( is_wp_error( $resp ) ) {
			return $resp;
		}

		$code     = wp_remote_retrieve_response_code( $resp );
		$location = wp_remote_retrieve_header( $resp, 'location' );

		if ( (int) $code !== 201 || empty( $location ) ) {
			$msg = wp_remote_retrieve_body( $resp );
			// TinyPNG 会返回 JSON {error,message}
			$json = json_decode( $msg, true );
			if ( is_array( $json ) && ! empty( $json['message'] ) ) {
				$msg = $json['message'];
			}
			return new \WP_Error( 'leseo_tinypng_shrink_failed', 'TinyPNG 压缩失败：' . ( $msg ? $msg : 'unknown' ) );
		}

		// 下载压缩后的文件并覆盖写回
		$download = wp_remote_get(
			$location,
			array(
				'timeout' => 60,
				'headers' => array(
					'Authorization' => 'Basic ' . $auth,
				),
			)
		);

		if ( is_wp_error( $download ) ) {
			return $download;
		}

		$data = wp_remote_retrieve_body( $download );
		if ( empty( $data ) ) {
			return new \WP_Error( 'leseo_tinypng_download_failed', 'TinyPNG 下载压缩结果失败' );
		}

		$written = @file_put_contents( $file_path, $data, LOCK_EX );
		if ( $written === false ) {
			return new \WP_Error( 'leseo_tinypng_write_failed', '写入压缩后的文件失败' );
		}

		return true;
	}
}

