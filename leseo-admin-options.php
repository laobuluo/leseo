<?php
//WordPress csf options

if ( ! class_exists( 'CSF' ) ) {
	require_once plugin_dir_path( __FILE__ ) . 'inc/codestar-framework/codestar-framework.php';
}


/**
 * Custom function for get an option
 */
if ( ! function_exists( 'get_lezaiyun_leseo_opt' ) ) {
	function get_lezaiyun_leseo_opt( $option = '', $default = null ) {
		$options_meta = '_lezaiyun_leseo_option';
		$options      = get_option( $options_meta );

		return ( isset( $options[ $option ] ) ) ? $options[ $option ] : $default;
	}
}


/**
 * 获取当前静态分离开关状态（用于条件校验）
 * 保存时从表单数据获取，页面加载时从已存选项获取
 */
if ( ! function_exists( 'leseo_s3_switch_is_on' ) ) {
    function leseo_s3_switch_is_on() {
        $options = array();
        if ( ! empty( $_POST['data'] ) ) {
            $decoded = json_decode( wp_unslash( $_POST['data'] ), true );
            $options = isset( $decoded['_lezaiyun_leseo_option'] ) ? $decoded['_lezaiyun_leseo_option'] : ( is_array( $decoded ) ? $decoded : array() );
        } elseif ( ! empty( $_POST['_lezaiyun_leseo_option'] ) && is_array( $_POST['_lezaiyun_leseo_option'] ) ) {
            $options = wp_unslash( $_POST['_lezaiyun_leseo_option'] );
        } else {
            $options = get_option( '_lezaiyun_leseo_option', array() );
        }
        if ( ! is_array( $options ) ) {
            $options = array();
        }
        $switch = isset( $options['leseo-s3-switch'] ) ? $options['leseo-s3-switch'] : '';
        return ( $switch === 'true' || $switch === '1' || $switch === true );
    }
}

/**
 * 静态分离必填项校验（仅当开关开启时校验）
 */
if ( ! function_exists( 'leseo_validate_s3_required' ) ) {
    function leseo_validate_s3_required( $value ) {
        if ( ! leseo_s3_switch_is_on() ) {
            return null;
        }
        return csf_validate_required( $value );
    }
}

/**
 * 静态分离 Region 校验（仅当开关开启时校验，允许七牛/R2 的 auto）
 */
if ( ! function_exists( 'leseo_validate_s3_region' ) ) {
    function leseo_validate_s3_region( $value ) {
        if ( ! leseo_s3_switch_is_on() ) {
            return null;
        }
        if ( empty( $value ) ) {
            return esc_html__( 'This field is required.', 'csf' );
        }
        return null;
    }
}

/**
 * 静态分离自定义域名校验（空值不校验，非空时校验 URL 格式）
 */
if ( ! function_exists( 'leseo_validate_s3_domain' ) ) {
    function leseo_validate_s3_domain( $value ) {
        if ( ! leseo_s3_switch_is_on() || empty( trim( $value ) ) ) {
            return null;
        }
        if ( ! filter_var( trim( $value ), FILTER_VALIDATE_URL ) ) {
            return esc_html__( 'Please enter a valid URL.', 'csf' );
        }
        return null;
    }
}

/**
 * AWS S3 Region 校验（保留以兼容旧配置，新配置使用 leseo_validate_s3_region）
 */
if ( ! function_exists( 'csf_customize_validate_region' ) ) {
    function csf_customize_validate_region( $value ) {
        return leseo_validate_s3_region( $value );
    }
}


function lezaiyun_leseo_option_init( $params ) {

	$params['framework_title'] = 'LeSEO - 一个有温度的WP性能优化插件';
	$params['menu_title']      = 'LeSEO设置';
	$params['theme']           = 'light'; //  light OR dark
	$params['show_bar_menu']   = false;
	$params['enqueue_webfont'] = false;
	$params['enqueue']         = false;
	$params['show_search']     = false;

	return $params;
}
add_filter( 'csf__lezaiyun_leseo_option_args', 'lezaiyun_leseo_option_init' );


$leseo_robots_filename = 'robots.txt';
function leseo_robots_switch( $params, $leseo_robots_filename = 'robots.txt') {
	if ( isset($params['leseo-robots-switch']) && $params['leseo-robots-switch'] ) {
		if ( isset($params['leseo-robots-content']) ) {
			$robots_path = ABSPATH . $leseo_robots_filename;
			file_put_contents( $robots_path, sanitize_textarea_field( $params['leseo-robots-content'] ) );
			$params['leseo-robots-content'] = null;
		}
	}
	return $params;
}
add_filter( 'csf__lezaiyun_leseo_option_save', 'leseo_robots_switch' );


// Control core classes for avoid errors
if ( class_exists( 'CSF' ) ) {

	// Set a unique slug-like ID
	$prefix = '_lezaiyun_leseo_option';

	// Create options
	CSF::createOptions( $prefix, array(
		'menu_title' => 'LeSeo插件',
		'menu_slug'  => 'lezaiyun-leseo-options',
	) );

	// 菜单清单
	// 基础优化
	CSF::createSection( $prefix, array(
		'title'  => '基础优化',
		'icon'   => 'fa fa-clipboard',
		'fields' => array(
			array(
				'type'    => 'heading',
				'content' => 'WP基础优化设置',
			),
			array(
				'type'    => 'notice',
				'style'   => 'info',
				'content' => '通过简单的开关键有选择的优化WP程序自身的功能',
			),

			array(
				'id'         => 'leseo-gutenberg',
				'type'       => 'switcher',
				'title'      => '禁止古登堡编辑器',
				'label'      => '默认启用WP自带古登堡编辑器，不喜欢可以关闭',
				'text_off'   => '点击关闭古登堡',
				'text_on'    => '点击开启古登堡',
				'text_width' => 140,
			),
			array(
				'id'         => 'leseo-autosave',
				'type'       => 'switcher',
				'title'      => '禁止文章自动保存',
				'label'      => '编辑文章会自动保存修订版本，不喜欢可以关闭',
				'text_off'   => '点击关闭自动保存',
				'text_on'    => '点击开启自动保存',
				'text_width' => 140,

			),
			array(
				'id'         => 'leseo-autoupgrade',
				'type'       => 'switcher',
				'title'      => '禁止自动升级版本',
				'label'      => '禁止WordPress自动升级新版本，我们可选择手动升级',
				'text_off'   => '点击关闭自动升级',
				'text_on'    => '点击开启自动升级',
				'text_width' => 140,

			),
			array(
				'id'         => 'leseo-rssfeed',
				'type'       => 'switcher',
				'title'      => '禁止RSS订阅网站',
				'label'      => '禁止WP网站被RSS阅读器订阅和采集',
				'text_off'   => '点击关闭RSS订阅',
				'text_on'    => '点击开启RSS订阅',
				'text_width' => 140,

			),
			array(
				'id'         => 'leseo-wptexturize',
				'type'       => 'switcher',
				'title'      => '禁止字符转码',
				'label'      => '禁止纯文本字符转换成格式化的HTML符号',
				'text_off'   => '点击关闭字符转码',
				'text_on'    => '点击开启字符转码',
				'text_width' => 140,

			),
			array(
				'id'         => 'leseo-wpjson',
				'type'       => 'switcher',
				'title'      => '禁止JSON',
				'label'      => '禁止WP-JSON和REST API外部调用，减少爬虫抓取',
				'text_off'   => '点击关闭JSON',
				'text_on'    => '点击开启JSON',
				'text_width' => 140,

			),
			array(
				'id'         => 'leseo-widgets-block-editor',
				'type'       => 'switcher',
				'title'      => '禁止新小工具样式',
				'label'      => '禁止新小工具样式，恢复原始小工具',
				'text_off'   => '点击关闭小工具样式',
				'text_on'    => '点击开启小工具样式',
				'text_width' => 140,

			),
			array(
				'id'         => 'leseo-xmlrpc',
				'type'       => 'switcher',
				'title'      => '禁止XML-RPC',
				'label'      => '禁止XML-RPC减少网站被爬虫和软件抓取占用负载',
				'text_off'   => '点击关闭RPC',
				'text_on'    => '点击开启RPC',
				'text_width' => 140,

			),
			array(
				'id'         => 'leseo-wlwmanifest',
				'type'       => 'switcher',
				'title'      => '禁止离线编辑端口',
				'label'      => '禁止且移除移除离线编辑器端口，防止外部推送文章',
				'text_off'   => '点击关闭离线编辑',
				'text_on'    => '点击开启离线编辑',
				'text_width' => 140,

			),
			array(
				'id'         => 'leseo-emoji',
				'type'       => 'switcher',
				'title'      => '禁止EMOJI表情',
				'label'      => '禁止EMOJI表情表情包，减少站内体积',
				'text_off'   => '点击关闭EMOJI表情',
				'text_on'    => '点击开启EMOJI表情',
				'text_width' => 140,

			),
			array(
				'id'         => 'leseo-rest-api',
				'type'       => 'switcher',
				'title'      => '屏蔽REST API',
				'label'      => '完全屏蔽WordPress REST API访问，提高安全性',
				'text_off'   => '点击关闭REST API',
				'text_on'    => '点击开启REST API',
				'text_width' => 140,

			),
			array(
				'id'         => 'leseo-trackback',
				'type'       => 'switcher',
				'title'      => '屏蔽Trackbacks/Pingback',
				'label'      => '禁用Trackbacks和Pingback功能，减少垃圾请求',
				'text_off'   => '点击关闭Trackbacks',
				'text_on'    => '点击开启Trackbacks',
				'text_width' => 140,

			),
			array(
				'id'         => 'leseo-admin-bar',
				'type'       => 'switcher',
				'title'      => '前台顶部管理菜单',
				'label'      => '控制是否在前台显示顶部管理工具栏',
				'text_off'   => '隐藏管理菜单',
				'text_on'    => '显示管理菜单',
				'text_width' => 140,

			),
			array(
				'id'         => 'leseo-dns-prefetch',
				'type'       => 'switcher',
				'title'      => '移除dns-prefetch',
				'label'      => '移除WordPress自动添加的DNS预取链接',
				'text_off'   => '点击关闭dns-prefetch',
				'text_on'    => '点击开启dns-prefetch',
				'text_width' => 140,

			),
			array(
				'id'         => 'leseo-dashicons',
				'type'       => 'switcher',
				'title'      => '移除Dashicons',
				'label'      => '前台移除Dashicons字体文件，减少加载资源',
				'text_off'   => '点击关闭Dashicons',
				'text_on'    => '点击开启Dashicons',
				'text_width' => 140,

			),
			array(
				'id'         => 'leseo-rsd',
				'type'       => 'switcher',
				'title'      => '移除RSD',
				'label'      => '移除RSD (Really Simple Discovery) 链接',
				'text_off'   => '点击关闭RSD',
				'text_on'    => '点击开启RSD',
				'text_width' => 140,

			),


		)
	) );

	// 功能优化
	CSF::createSection( $prefix, array(
		'title'  => '功能优化',
		'icon'   => 'fa fa-rocket',
		'fields' => array(
			array(
				'type'    => 'heading',
				'content' => 'WP功能优化设置',
			),
			array(
				'type'    => 'notice',
				'style'   => 'info',
				'content' => 'WP简单的功能优化设置，减少站内负载，加速优化代码提速',
			),

			array(
				'id'         => 'leseo-renameimg',
				'type'       => 'switcher',
				'title'      => '上传图片重命名',
				'label'      => '上传图片按照时间戳重命名，防止用户中文名上传',
				'text_off'   => '点击开启图片重命名',
				'text_on'    => '点击关闭图片重命名',
				'text_width' => 140,
			),

			array(
				'id'         => 'leseo-cropimage',
				'type'       => 'switcher',
				'title'      => '禁止裁剪大图',
				'label'      => '禁止上传大图的时候被WP自动裁剪（大于2560）',
				'text_off'   => '点击关闭裁剪大图',
				'text_on'    => '点击开启裁剪大图',
				'text_width' => 140,
			),
			array(
				'id'         => 'leseo-spamcomments',
				'type'       => 'switcher',
				'title'      => '禁止垃圾评论',
				'label'      => '通过特定的限制来阻止被人工和机器自动评论，减少数据库和页面负载',
				'text_off'   => '点击关闭垃圾评论',
				'text_on'    => '点击开启垃圾评论',
				'text_width' => 140,

			),
			array(
				'id'         => 'leseo-autothumbnail',
				'type'       => 'switcher',
				'title'      => '禁止生成缩略图',
				'label'      => '禁止WP自动生成上传图片后的缩略图，减少服务器资源占用',
				'text_off'   => '点击关闭生成缩略图',
				'text_on'    => '点击开启生成缩略图',
				'text_width' => 140,

			),
			array(
				'id'         => 'leseo-compress-html',
				'type'       => 'switcher',
				'title'      => '压缩网站HTML',
				'label'      => '压缩网站前端HTML代码，降低网站体积提高访问速度',
				'text_off'   => '点击开启压缩HTML',
				'text_on'    => '点击关闭压缩HTML',
				'text_width' => 140,

			),
//			/****
//			 * CSS和JS压缩放到后期的加速插件里
//			 * array(
//			 * 'id' => 'leseo-compress-css',
//			 * 'type' => 'switcher',
//			 * 'title' => '压缩CSS样式',
//			 * 'label' => '压缩网站CSS样式降低CSS样式表体积',
//			 * 'text_off' => '点击开启压缩CSS',
//			 * 'text_on' => '点击关闭压缩CSS',
//			 * 'text_width' => 140,
//			 *
//			 * ),array(
//			 * 'id' => 'leseo-compress-js',
//			 * 'type' => 'switcher',
//			 * 'title' => '压缩JS体积',
//			 * 'label' => '压缩网站JS脚本体积，降低整体网站的体积提高速度',
//			 * 'text_off' => '点击开启压缩JS',
//			 * 'text_on' => '点击关闭压缩JS',
//			 * 'text_width' => 140,
//			 *
//			 * ),
//			 */
			array(
				'id'         => 'leseo-remove-header',
				'type'       => 'switcher',
				'title'      => '精简头部代码',
				'label'      => '一键精简头部不必须的代码，降低网站的体积',
				'text_off'   => '点击开启精简代码',
				'text_on'    => '点击关闭精简代码',
				'text_width' => 140,

			),
			array(
				'id'         => 'leseo-remove-cssjsversion',
				'type'       => 'switcher',
				'title'      => '移除CSS/JS版本号',
				'label'      => '移除CSS、JS后缀版本号和WP版本号，精简代码',
				'text_off'   => '点击关闭版本号',
				'text_on'    => '点击开启版本号',
				'text_width' => 140,

			),
			array(
				'id'         => 'leseo-disable-search',
				'type'       => 'switcher',
				'title'      => '禁止前端搜索',
				'label'      => '禁止用户前端搜索站内内容，根据需要改用站外搜索接口',
				'text_off'   => '点击关闭站内搜索',
				'text_on'    => '点击开启站内搜索',
				'text_width' => 140,

			),
			array(
				'id'         => 'leseo-disable-uploadimg',
				'type'       => 'switcher',
				'title'      => '图片自动本地',
				'label'      => '复制外部图片粘贴到编辑器自动本地化上传',
				'text_off'   => '点击关闭',
				'text_on'    => '点击开启',
				'text_width' => 140,

			),
			array(
				'id'         => 'leseo-disable-copyrights',
				'type'       => 'switcher',
				'title'      => '禁止复制右键',
				'label'      => '禁止内容被复制和右键',
				'text_off'   => '点击关闭',
				'text_on'    => '点击开启',
				'text_width' => 140,

			),
			array(
				'id'         => 'leseo-disable-wpblock-librarycss',
				'type'       => 'switcher',
				'title'      => '移除编辑器样式',
				'label'      => '移除 wp-block-library-css 样式，提高加载速度',
				'text_off'   => '点击关闭',
				'text_on'    => '点击开启',
				'text_width' => 140,

			),
			array(
				'id'         => 'leseo-disable-widthheight',
				'type'       => 'switcher',
				'title'      => '图片限制高度',
				'label'      => '解除默认图片限制高度和宽度',
				'text_off'   => '点击关闭',
				'text_on'    => '点击开启',
				'text_width' => 140,

			),
			array(
				'id'         => 'leseo-disable-srcsetsize',
				'type'       => 'switcher',
				'title'      => '响应图片尺寸',
				'label'      => '移除图片响应式尺寸 srcset 和 Size 标签',
				'text_off'   => '点击关闭',
				'text_on'    => '点击开启',
				'text_width' => 140,

			),
			array(
				'type'    => 'heading',
				'content' => '分页设置',
			),
			array(
				'id'         => 'leseo-custom-pagination',
				'type'       => 'switcher',
				'title'      => '自定义分页符',
				'label'      => '开启后可以自定义分页符，替代默认的page参数',
				'text_off'   => '点击开启自定义分页',
				'text_on'    => '点击关闭自定义分页',
				'text_width' => 140,

			),
			array(
				'id'         => 'leseo-pagination-string',
				'type'       => 'text',
				'title'      => '自定义分页符',
				'default'    => 'page',
				'desc'       => '设置自定义的分页符，例如：page、p、pagination等',
				'dependency' => array( 'leseo-custom-pagination', '==', 'true' ),
			),

			array(
				'type'    => 'heading',
				'content' => '图片压缩（TinyPNG）',
			),
			array(
				'id'         => 'leseo-tinypng-switch',
				'type'       => 'switcher',
				'title'      => '开启TinyPNG图片压缩',
				'label'      => '上传图片后自动使用TinyPNG压缩（每月免费500张）。需要配置 TinyPNG API Key。',
				'text_off'   => '点击开启',
				'text_on'    => '点击关闭',
				'text_width' => 140,
			),
			array(
				'id'         => 'leseo-tinypng-api-key',
				'type'       => 'text',
				'title'      => 'TinyPNG API Key',
				'desc'       => '在 TinyPNG 官网申请 API Key（每月免费500张压缩）。',
				'attributes' => array(
					'style' => 'width: 60%;',
				),
				'dependency' => array( 'leseo-tinypng-switch', '==', 'true' ),
			),

		)
	) );


	// SEO优化
	CSF::createSection( $prefix, array(
		'id'    => 'leseo-seo-fields',
		'title' => 'SEO优化',
		'icon'  => 'fa fa-square',
	) );

//
// SEO基础优化
//
	CSF::createSection( $prefix, array(
		'parent' => 'leseo-seo-fields',
		'title'  => '基础优化',
		'icon'   => 'far fa-square',
		'fields' => array(

			array(
				'type'    => 'heading',
				'content' => '基础SEO设置',
			),

			array(
				'type'    => 'notice',
				'style'   => 'info',
				'content' => '简单的勾选和设置，完成部分通用的SEO设置',
			),

			array(
				'id'         => 'leseo-backslash',
				'type'       => 'switcher',
				'title'      => '页面添加反斜杠',
				'label'      => '分类和别名文章添加反斜杠URL结尾，提高SEO体验',
				'text_off'   => '点击开启反斜杠',
				'text_on'    => '点击关闭反斜杠',
				'text_width' => 140,

			),

			array(
				'id'         => 'leseo-remove-category',
				'type'       => 'switcher',
				'title'      => '隐藏分类Category',
				'label'      => '隐藏分类Category分类字符，缩短SEO URL的长度',
				'text_off'   => '点击隐藏Category',
				'text_on'    => '点击开启Category',
				'text_width' => 140,

			),

			array(
				'id'         => 'leseo-autoimgalt',
				'type'       => 'switcher',
				'title'      => '图片自动添加ALT',
				'label'      => '自动图片添加ALT描述文本，提高图片的SEO效果',
				'text_off'   => '点击开启图片ALT',
				'text_on'    => '点击关闭图片ALT',
				'text_width' => 140,

			),

			array(
				'id'         => 'leseo-autotaglink',
				'type'       => 'switcher',
				'title'      => '开启自动TAG内链',
				'label'      => '自动为文章篇幅中的TAG关键字添加内链（默认频率2次）',
				'text_off'   => '点击开启自动内链',
				'text_on'    => '点击关闭自动内链',
				'text_width' => 140,

			),
			array(
				'id'         => 'leseo-tag-rewrite',
				'type'       => 'switcher',
				'title'      => '标签URL更改',
				'label'      => '开启后标签URL改为 /tag/%tag_id% 格式，提高SEO效果',
				'text_off'   => '点击开启标签ID模式',
				'text_on'    => '点击关闭标签ID模式',
				'text_width' => 140,

			),
			array(
				'id'         => 'leseo-webp-convert',
				'type'       => 'switcher',
				'title'      => '图片转换WebP格式',
				'label'      => '上传的图片自动转换为WebP格式，减小体积、加快加载',
				'text_off'   => '点击开启',
				'text_on'    => '点击关闭',
				'text_width' => 140,

			),

			array(
				'type'    => 'heading',
				'content' => '站外链接优化',
			),
			array(
				'id'         => 'leseo-extlink-enable',
				'type'       => 'switcher',
				'title'      => '开启站外链接优化',
				'label'      => '统一处理正文中的站外链接，可使用 ?goto= 中转模式并支持 Base64 加密。',
				'text_off'   => '点击开启',
				'text_on'    => '点击关闭',
				'text_width' => 140,
			),
			array(
				'id'         => 'leseo-extlink-mode',
				'type'       => 'select',
				'title'      => '站外链接模式',
				'options'    => array(
					'normal'      => '正常链接（不处理，默认）',
					'goto_base64' => '中转 + Base64：?goto=BASE64(url)',
					'goto_plain'  => '中转（明文）：?goto=实际URL',
				),
				'default'    => 'normal',
				'dependency' => array( 'leseo-extlink-enable', '==', 'true' ),
			),
			array(
				'id'         => 'leseo-extlink-newtab',
				'type'       => 'checkbox',
				'title'      => '新窗口打开站外链接',
				'label'      => '为站外链接添加 target=\"_blank\"（推荐）',
				'dependency' => array( 'leseo-extlink-enable', '==', 'true' ),
			),
			array(
				'id'         => 'leseo-extlink-nofollow',
				'type'       => 'checkbox',
				'title'      => '添加 rel=\"nofollow\"',
				'label'      => '为站外链接添加 rel=\"nofollow\"，可提升权重控制',
				'dependency' => array( 'leseo-extlink-enable', '==', 'true' ),
			),
			array(
				'id'         => 'leseo-extlink-whitelist',
				'type'       => 'textarea',
				'title'      => '白名单域名',
				'desc'       => '每行一个域名（不含协议），如：example.com。白名单内的链接保持为正常内链模式，不走 ?goto= 中转。',
				'dependency' => array( 'leseo-extlink-enable', '==', 'true' ),
			),
			array(
				'id'         => 'leseo-extlink-transition',
				'type'       => 'checkbox',
				'title'      => '使用中间过渡页面',
				'label'      => '站外链接跳转前先展示一页提示页面。',
				'dependency' => array(
					'leseo-extlink-enable', '==', 'true',
				),
			),
			array(
				'id'         => 'leseo-extlink-transition-auto',
				'type'       => 'checkbox',
				'title'      => '过渡页自动跳转',
				'label'      => '勾选后在显示过渡页的同时 N 秒后自动跳转；不勾选则必须手动点击按钮。',
				'dependency' => array(
					'leseo-extlink-enable', '==', 'true',
				),
			),

		)
	) );

//
// TDK SEO
//
	CSF::createSection( $prefix, array(
		'parent' => 'leseo-seo-fields',
		'title'  => 'TDK设置',
		'icon'   => 'far fa-square',
		'fields' => array(

			array(
				'type'    => 'heading',
				'content' => 'SEO TDK设置',
			),

			array(
				'type'    => 'notice',
				'style'   => 'info',
				'content' => '通过开启自定义设置首页、分类、页面TDK设置',
			),

			array(
				'id'         => 'leseo-selfseotdk',
				'type'       => 'switcher',
				'title'      => '开启自定义SEO',
				'text_off'   => '点击开启自定义SEO',
				'text_on'    => '点击关闭自定义SEO',
				'text_width' => 140,

			),

			array(
				'id'    => 'leseo-linkmark',
				'type'  => 'text',
				'title' => '全站连接符号',
			),

			array(
				'id'    => 'leseo-pageandsitename',
				'type'  => 'checkbox',
				'title' => '页面不跟随网站名称',
				'label' => '文章页面Title采用文章标题还是带上网站名称',
			),

			array(
				'id'    => 'leseo-selfindextitle',
				'type'  => 'text',
				'title' => '自定义首页标题',
				'desc'  => '留空则使用站点默认标题。即使未开启上方「自定义SEO」也可生效。若主题自带 SEO 标题设置，LeSeo 会以更高优先级覆盖。',
			),

			array(
				'id'    => 'leseo-selfindexkeywords',
				'type'  => 'text',
				'title' => '自定义首页关键字',
				'desc'  => '关键字中间用英文逗号","隔开',
			),

			array(
				'id'    => 'leseo-selfindexdesc',
				'type'  => 'textarea',
				'title' => '自定义首页描述',
				'help'  => '建议不要超过150个字符',
			),

			array(
				'id'    => 'leseo-opengraph',
				'type'  => 'checkbox',
				'title' => '开启Open Graph',

			),

			array(
				'id'    => 'leseo-canonical',
				'type'  => 'checkbox',
				'title' => '开启Canonical',

			),


		)
	) );


//
// 网站地图
//
	CSF::createSection( $prefix, array(
		'parent' => 'leseo-seo-fields',
		'title'  => '网站地图',
		'icon'   => 'far fa-square',
		'fields' => array(

			array(
				'type'    => 'heading',
				'content' => '网站地图优化',
			),

			array(
				'type'    => 'notice',
				'style'   => 'info',
				'content' => '禁止自带地图和Robots.txt设置',
			),

			array(
				'id'         => 'leseo-selfsitemap',
				'type'       => 'switcher',
				'title'      => '关闭自带SiteMap',
				'label'      => '如果采用第三方SitaMap插件这个可以关闭',
				'text_off'   => '点击关闭自带地图',
				'text_on'    => '点击开启自带地图',
				'text_width' => 140,

			),

			array(
				'id'         => 'leseo-robots-switch',
				'type'       => 'switcher',
				'title'      => '自定义Robots.txt',
				'label'      => '设置自定义Robots协议',
				'text_off'   => '点击开启协议',
				'text_on'    => '点击关闭协议',
				'text_width' => 140,

			),

			array(
				'id'    => 'leseo-robots-content',
				'type'  => 'textarea',
				'title' => 'Robots.txt',
				'help'  => '如果根目录没有生成可以自定义创建rebots.txt复制进',
				'value' => file_exists( ABSPATH . $leseo_robots_filename ) ?
					esc_textarea( file_get_contents( ABSPATH . $leseo_robots_filename ) ) :
					'',
				'dependency' => array( 'leseo-robots-switch', '==', 'true' ),
			),

		)
	) );

	// 搜索推送
	CSF::createSection( $prefix, array(
		'title'  => '搜索推送',
		'icon'   => 'fa fa-circle',
		'fields' => array(
			array(
				'type'    => 'heading',
				'content' => '搜索引擎推送',
			),
			array(
				'type'    => 'notice',
				'style'   => 'info',
				'content' => '主动推送网站内容至搜索引擎，加速内容抓取和收录概率',
			),

			array(
				'id'         => 'leseo-submit-switch',
				'type'       => 'switcher',
				'title'      => '开启推送',
				'label'      => '打开开启推送开关，才可以推送文章',
				'text_off'   => '点击开启推送',
				'text_on'    => '点击关闭推送',
				'text_width' => 100,
			),

			array(
				'id'      => 'leseo-resource-type',
				'type'    => 'checkbox',
				'title'   => '资源类型',
				'inline'  => true,
				'options' => array(
					'post'    => '文章',
					'page'    => '页面',

				),
			),

			array(
				'type'    => 'subheading',
				'content' => '百度推送设置',
			),

			array(
				'id'    => 'leseo-submit-bdtoken',
				'type'  => 'text',
				'title' => '百度推送API TOKEN',
				'label' => '填写TOKEN',
			),

			array(
				'id'      => 'leseo-submit-type',
				'type'    => 'checkbox',
				'title'   => '百度推送类型',
				'inline'  => true,
				'options' => array(
					'normal' => '普通推送',
					'daily'  => '快速推送',
				),
			),

		)
	) );

// 附加功能
	CSF::createSection( $prefix, array(
		'id'    => 'leseo-add-func',
		'title' => '附加功能',
		'icon'  => 'fa fa-adjust',
	) );

//
// SEO基础优化
//
	CSF::createSection( $prefix, array(
		'parent' => 'leseo-add-func',
		'title'  => '插入代码',
		'icon'   => 'far fas fa-code',
		'fields' => array(

			array(
				'type'    => 'heading',
				'content' => '自定义插入代码',
			),

			array(
				'type'    => 'notice',
				'style'   => 'info',
				'content' => '自定义给网站头部、底部和CSS添加外部代码',
			),

			array(
      			'id'       => 'leseo-code-header',
      			'type'     => 'code_editor',
      			'title'    => '自定义头部代码',
     			'subtitle' => '位于</head>之前，通常是CSS样式、自定义的标签、头部JS等需要提前加载的代码',
   			 ),

			array(
      			'id'       => 'leseo-code-footer',
      			'type'     => 'code_editor',
      			'title'    => '自定义底部代码',
     			'subtitle' => '位于</body>之前，这部分代码是在主要内容加载完毕加载，通常是JS代码',
   			 ),

			array(
      			'id'       => 'leseo-code-cssjs',
      			'type'     => 'code_editor',
      			'title'    => '自定义CSS样式',
     			'subtitle' => '直接写样式代码，无需添加 style 标签',
   			 'settings' => array(
        'theme'  => 'mbo',
        'mode'   => 'css',
      ),
    'help' => '直接书写CSS规则，系统会自动包裹 &lt;style&gt; 标签输出',
    ),

		)
	) );

	//静态分离
CSF::createSection( $prefix, array(
	'id'    => 'leseo-s3',
	'title' => '静态分离',
	'icon'  => 'fa fa-upload',
	'fields' => array(
	
        array(
            'type'    => 'heading',
            'content' => '综合对象存储静态分离设置',
        ),

        array(
              'type'    => 'notice',
              'style'   => 'info',
              'content' => '主流对象存储服务商均采用的S3 SDK，支持腾讯云、阿里云、七牛云、又拍云、S3、R2等。（<a href="https://www.lezaiyun.com/817.html" target="_blank">说明文档</a>）',
        ),

        array(
             'id' => 'leseo-s3-switch',
             'type' => 'switcher',
             'title' => '启动关闭静态分离',
             'label' => '图片静态文件上传和云主机分离存储',
             'text_on'  => '开启',
             'text_off'  => '关闭',
             'text_width' => 60,
        ),
        array(
            'id'         => 'leseo-s3-local-file',
            'type'       => 'switcher',
            'title'      => '不在本地保存',
            'label'      => '禁止文件保存本地。本地不保存，减少服务器占用资源',
            'text_on'    => '开启',
            'text_off'   => '关闭',
            'text_width' => 60,
            'dependency' => array( 'leseo-s3-switch', '==', 'true' ),
        ),

        array(
            'id'    => 'leseo-s3-bucket',
            'type'  => 'Text',
            'title' => '空间名称（Bucket）',
            'after'    => '<p>需要用户提前在对应对象存储服务商创建存储桶。</p>',
            'dependency' => array( 'leseo-s3-switch', '==', 'true' ),
            'validate' => 'leseo_validate_s3_required',
        ),

        array(
            'id'    => 'leseo-s3-region',
            'type'  => 'Text',
            'title' => '所属地域（Region）',
            'after'    => '<p>存储桶所属地区，示范：ap-shanghai。七牛云、CloudFlare R2 填auto。【不能填写中文】</p>',
            'dependency' => array( 'leseo-s3-switch', '==', 'true' ),
            'validate' => 'leseo_validate_s3_region',
        ),

        array(
            'id'    => 'leseo-s3-endpoint',
            'type'  => 'Text',
            'title' => 'EndPoint',
            'after' => '<p>输入EndPoint地址，比如：http（https）://{EndPoint}，不要用"/"结尾。</p>',
            'dependency' => array( 'leseo-s3-switch', '==', 'true' ),
        ),

        array(
            'id'    => 'leseo-s3-domain',
            'type'  => 'Text',
            'title' => '自定义域名',
            'validate' => 'leseo_validate_s3_domain',
            'attributes' => array(
                    'style'    => 'width: 80%;'
                ),
            'after' => '<p>填写静态文件URL，官方提供的免费URL或者自定义的域名。不要用"/"结尾</p>',
            'dependency' => array( 'leseo-s3-switch', '==', 'true' ),
        ),

        array(
            'id'    => 'leseo-s3-accesskey',
            'type'  => 'Text',
            'title' => 'AccessKey',
            'attributes' => array(
                'style'    => 'width:60%;'
            ),
            'dependency' => array( 'leseo-s3-switch', '==', 'true' ),
            'validate' => 'leseo_validate_s3_required',
        ),
        array(
            'id'    => 'leseo-s3-secretkey',
            'type'  => 'Text',
            'title' => 'SecretKey',
            'attributes' => array(
                'style'    => 'width: 80%;'
            ),
            'after' => '<p>对应对象存储服务商的AccessKey/SecretKey密钥信息。</p>',
            'dependency' => array( 'leseo-s3-switch', '==', 'true' ),
            'validate' => 'leseo_validate_s3_required',
        ),
        array(
            'id'         => 'leseo-s3-skip-ssl',
            'type'       => 'switcher',
            'title'      => '跳过 SSL 证书验证',
            'label'      => '遇到 cURL 60（证书链/自签名）报错时可开启，仅建议在代理或内网环境使用',
            'text_on'    => '开启',
            'text_off'   => '关闭',
            'text_width' => 60,
            'dependency' => array( 'leseo-s3-switch', '==', 'true' ),
        ),
    )
));
	
// Create a section
	CSF::createSection( $prefix, array(
		'title'       => '关于插件',
		'icon'        => 'fa fa-bell',
		'description' => '<div class="groups_title"><h3>LeSeo插件</h3><p>LeSeo，一款有温度的从底层优化WordPress插件。</p><h3>关于开发者</h3><p>开发者：老蒋和他的伙伴们</p><p>邮箱：info@lezaiyun.com</p><p>公众号：老蒋朋友圈</p><h3>开发引用</h3><p>插件框架采用Codestar Framework且获得授权，插件功能代码有参考网上热心网友分享的代码。</p><h3>帮助支持</h3><p><a href="https://www.laojiang.me/" class="button button-secondary" target="_blank">老蒋的博客</a> <a href="https://www.lezaiyun.com/817.html" class="button button-secondary" target="_blank">插件官网介绍</a> <a href="https://www.laojiang.me/contact/" class="button button-secondary" target="_blank">交流社群</a></p></div>',
		'fields'      => array()

	) );


####### Options End #######
$options = get_option('_lezaiyun_leseo_option');
if ( isset($options['leseo-selfseotdk']) && $options['leseo-selfseotdk'] ) {
	// 文章页面MetaBox设置
	$prefix_post_opts = 'leseo_singular_meta_options';

	CSF::createMetabox( $prefix_post_opts, array(
		'title'        => '自定义TDK',
		'post_type'    => array('post', 'page'),
		# 'show_restore' => true,  # 恢复默认按钮
	) );

	CSF::createSection( $prefix_post_opts, array(
		'fields' => array(
			array(
				'id'    => 'leseo-singular-meta-switcher',
				'type'  => 'switcher',
				'title' => '自定义SEO TDK',
				'label' => '是否开启自定义TDK',
			),
			array(
				'id'    => 'leseo-singular-meta-title',
				'type'  => 'text',
				'title' => 'Title',
				'dependency' => array( 'leseo-singular-meta-switcher', '==', 'true' ),
			),
			array(
				'id'    => 'leseo-singular-meta-description',
				'type'  => 'textarea',
				'title' => 'Description',
				# 'help'  => 'The help text of the field.',
				'dependency' => array( 'leseo-singular-meta-switcher', '==', 'true' ),
			),
			array(
				'id'    => 'leseo-singular-meta-keywords',
				'type'  => 'text',
				'title' => 'Keywords',
				'dependency' => array( 'leseo-singular-meta-switcher', '==', 'true' ),
			),
		)
	) );


############# Post & Page MetaBox End #############
	$prefix_tax_opts = 'leseo_taxonomy_meta_options';

	// Create taxonomy options
	CSF::createTaxonomyOptions( $prefix_tax_opts, array(
		'title'    => '自定义TDK',
		'taxonomy' => array('post_tag', 'category'),
	) );
	CSF::createSection( $prefix_tax_opts, array(
		'fields' => array(
			array(
				'id'    => 'leseo-taxonomy-meta-switcher',
				'type'  => 'switcher',
				'title' => '自定义SEO TDK',
				'label' => '是否开启自定义TDK',
			),
			array(
				'id'    => 'leseo-taxonomy-meta-title',
				'type'  => 'text',
				'title' => 'Title',
				'dependency' => array( 'leseo-taxonomy-meta-switcher', '==', 'true' ),
			),
			array(
				'id'    => 'leseo-taxonomy-meta-description',
				'type'  => 'textarea',
				'title' => 'Description',
				# 'help'  => 'The help text of the field.',
				'dependency' => array( 'leseo-taxonomy-meta-switcher', '==', 'true' ),
			),
			array(
				'id'    => 'leseo-taxonomy-meta-keywords',
				'type'  => 'text',
				'title' => 'Keywords',
				'dependency' => array( 'leseo-taxonomy-meta-switcher', '==', 'true' ),
			),
		)
	) );
	}
}
