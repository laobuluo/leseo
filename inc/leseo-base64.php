<?php
namespace lezaiyun\Leseo\inc\Base64;

use WP_Error;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class LeseoBase64 {

	/**
	 * 处理 ?goto= 中转逻辑
	 *
	 * @param array $config
	 */
	public static function handle_goto( $config = array() ) {
		if ( is_admin() ) {
			return;
		}

		if ( empty( $_GET['goto'] ) ) { // phpcs:ignore WordPress.Security.NonceVerification.Recommended
			return;
		}

		$mode       = isset( $config['mode'] ) ? $config['mode'] : 'normal';
		if ( $mode === 'normal' ) {
			return;
		}

		$transition = ! empty( $config['transition'] );
		$raw        = isset( $_GET['goto'] ) ? wp_unslash( $_GET['goto'] ) : ''; // phpcs:ignore WordPress.Security.NonceVerification.Recommended

		if ( $mode === 'goto_base64' ) {
			$decoded = base64_decode( rawurldecode( $raw ), true );
			if ( $decoded === false ) {
				self::render_error_page( '外链跳转参数异常' );
				exit;
			}
			$url = $decoded;
		} else {
			$url = rawurldecode( $raw );
		}

		$url = trim( $url );
		if ( empty( $url ) || ! preg_match( '#^https?://#i', $url ) ) {
			self::render_error_page( '目标链接无效或不安全。' );
			exit;
		}

		$site_host = wp_parse_url( site_url(), PHP_URL_HOST );
		$target_host = wp_parse_url( $url, PHP_URL_HOST );

		// 防止站内循环跳转
		if ( $site_host && $target_host && strtolower( $site_host ) === strtolower( $target_host ) ) {
			wp_safe_redirect( $url, 302 );
			exit;
		}

		if ( ! $transition ) {
			wp_safe_redirect( $url, 302 );
			exit;
		}

		$auto = ! empty( $config['transition_auto'] );
		self::render_transition_page( $url, $auto );
		exit;
	}

	/**
	 * 渲染简单的中间过渡页面
	 *
	 * @param string $url
	 */
	private static function render_transition_page( $url, $auto = true ) {
		$escaped = esc_url( $url );
		$title   = esc_html( get_bloginfo( 'name' ) . ' - 外链跳转' );
		?>
		<!DOCTYPE html>
		<html <?php language_attributes(); ?>>
		<head>
			<meta charset="<?php bloginfo( 'charset' ); ?>">
			<title><?php echo $title; ?></title>
			<?php if ( $auto ) : ?>
			<meta http-equiv="refresh" content="2;url=<?php echo $escaped; ?>">
			<?php endif; ?>
			<meta name="robots" content="noindex,nofollow">
			<style>
				body{font-family:-apple-system,BlinkMacSystemFont,"Segoe UI",Roboto,"Helvetica Neue",Arial,sans-serif;background:#f5f5f5;margin:0;padding:0;}
				.leseo-ext-wrap{max-width:520px;margin:80px auto;background:#fff;border-radius:8px;box-shadow:0 2px 8px rgba(0,0,0,.06);padding:30px;text-align:center;}
				.leseo-ext-title{font-size:20px;margin-bottom:10px;}
				.leseo-ext-url{word-break:break-all;font-size:13px;color:#666;margin:10px 0 20px;}
				.leseo-ext-btn{display:inline-block;padding:8px 18px;border-radius:4px;background:#2271b1;color:#fff;text-decoration:none;font-size:14px;}
				.leseo-ext-tip{font-size:12px;color:#999;margin-top:15px;}
			</style>
		</head>
		<body>
			<div class="leseo-ext-wrap">
				<div class="leseo-ext-title"><?php esc_html_e( '正在跳转至第三方网站', 'LeSEO' ); ?></div>
				<div class="leseo-ext-url"><?php echo $escaped; ?></div>
				<a class="leseo-ext-btn" href="<?php echo $escaped; ?>" rel="noopener noreferrer" target="_blank">
					<?php esc_html_e( '立即前往', 'LeSEO' ); ?>
				</a>
				<div class="leseo-ext-tip">
					<?php esc_html_e( '如果浏览器没有自动跳转，请点击上方按钮。', 'LeSEO' ); ?>
				</div>
			</div>
		</body>
		</html>
		<?php
	}

	/**
	 * 渲染错误提示页面
	 *
	 * @param string $message
	 */
	private static function render_error_page( $message ) {
		$message = esc_html( $message );
		?>
		<!DOCTYPE html>
		<html <?php language_attributes(); ?>>
		<head>
			<meta charset="<?php bloginfo( 'charset' ); ?>">
			<title><?php esc_html_e( '外链跳转错误', 'LeSEO' ); ?></title>
			<meta name="robots" content="noindex,nofollow">
			<style>
				body{font-family:-apple-system,BlinkMacSystemFont,"Segoe UI",Roboto,"Helvetica Neue",Arial,sans-serif;background:#f5f5f5;margin:0;padding:0;}
				.leseo-ext-wrap{max-width:520px;margin:80px auto;background:#fff;border-radius:8px;box-shadow:0 2px 8px rgba(0,0,0,.06);padding:30px;text-align:center;}
				.leseo-ext-title{font-size:20px;margin-bottom:15px;}
				.leseo-ext-msg{font-size:14px;color:#666;margin-bottom:20px;}
				.leseo-ext-home{font-size:13px;}
			</style>
		</head>
		<body>
			<div class="leseo-ext-wrap">
				<div class="leseo-ext-title"><?php esc_html_e( '外链跳转失败', 'LeSEO' ); ?></div>
				<div class="leseo-ext-msg"><?php echo $message; ?></div>
				<div class="leseo-ext-home">
					<a href="<?php echo esc_url( home_url( '/' ) ); ?>"><?php esc_html_e( '返回首页', 'LeSEO' ); ?></a>
				</div>
			</div>
		</body>
		</html>
		<?php
	}
}

