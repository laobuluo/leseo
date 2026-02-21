<?php
/**
Plugin Name: LeSeo
Plugin URI: https://www.lezaiyun.com/817.html
Description: LeSeo，一个比较全面、免费的WordPress SEO插件。公众号：老蒋朋友圈。
Version: 1.2.10
Author: 老蒋和他的伙伴们
Author URI: https://www.lezaiyun.com
Requires PHP: 7.0
 */
namespace lezaiyun\Leseo;


use Exception;

if (!defined('ABSPATH')) die();
if ( !function_exists( 'add_action' ) ) {
	echo 'Hi there!  I\'m just a plugin, not much I can do when called directly.';
	exit;
}

if (!class_exists('LESEO')) {
	class LESEO {
		private $option_var_name       = '_lezaiyun_leseo_option';       // 插件参数保存名称
		private $options;
		/**
		 * @var string
		 */
		private $base_folder;
        private $wp_upload_dir;
		/**
		 * 一个关键字少于多少不替换;
		 * @var int
		 */
		private $match_num_from = 2;
		/**
		 * 一个关键字最多替换
		 * @var int
		 */
		private $match_num_to   = 3;

        private $s3_object      = null;
        /**
         * @var string
         */
        private $leseo_submit_meta_key;
        private $menu_slug;
        private $site;


        private function includes() {
            require_once plugin_dir_path( __FILE__ ) . 'leseo-admin-options.php';  //获取后台主题选项参数
            include_once plugin_dir_path( __FILE__ ) . 'inc/baidu-submit/api.php';
            include_once plugin_dir_path( __FILE__ ) . 'inc/cache/LeCache.php';
            include_once plugin_dir_path( __FILE__ ) . 'inc/awss3/api.php';
			include_once plugin_dir_path( __FILE__ ) . 'inc/leseo-tinypng.php';
			include_once plugin_dir_path( __FILE__ ) . 'inc/leseo-base64.php';
        }


		public function __construct() {
			$this->includes();

			$this->options = get_option( $this->option_var_name );

			$this->base_folder = plugin_basename(dirname(__FILE__));
			$this->menu_slug       = [
				'setting_page'     => $this->base_folder . '/setting',
				'manually_submit'  => $this->base_folder . '/manually_submit',
				'check_baidu_page' => $this->base_folder . '/check_baidu',
			];

			$this->site    = site_url();

			add_action( 'laobuluo_bs_event', array($this, 'bs_cron_event') );

			register_activation_hook( __FILE__, array($this, 'leseo_activate') );      # 启用时刷新重写规则
			register_deactivation_hook( __FILE__, array($this, 'leseo_deactivate') ); # 禁用时触发钩子

            // csf框架相关hooks
            add_filter( 'csf__lezaiyun_leseo_option_save', array($this, 'leseo_s3_switch_csf_filter') );


			//基础优化设置 - 禁用5.0编辑器
			if ( isset($this->options['leseo-gutenberg']) && $this->options['leseo-gutenberg'] == 1 ) {
				add_filter( 'use_block_editor_for_post_type', '__return_false' );
				remove_action( 'wp_enqueue_scripts', 'wp_common_block_scripts_and_styles' );
			}

			//基础优化设置 - 禁止自动保存文章
			if ( isset($this->options['leseo-autosave']) && $this->options['leseo-autosave'] == 1 ) {
				add_action( 'wp_print_scripts', array($this, 'leseo_no_autosave') );
			}

			//禁止WP自动升级
			if ( isset($this->options['leseo-autoupgrade']) && $this->options['leseo-autoupgrade'] == 1 ) {
				add_filter( 'automatic_updater_disabled', '__return_true' );
			}

			//禁止RSS订阅功能
			if ( isset($this->options['leseo-rssfeed']) && $this->options['leseo-rssfeed'] == 1 ) {
				add_action( 'do_feed', array($this, 'leseo_disable_feed'), 1 );
				add_action( 'do_feed_rdf', array($this, 'leseo_disable_feed'), 1 );
				add_action( 'do_feed_rss', array($this, 'leseo_disable_feed'), 1 );
				add_action( 'do_feed_rss2', array($this, 'leseo_disable_feed'), 1 );
				add_action( 'do_feed_atom', array($this, 'leseo_disable_feed'), 1 );
			}

			//禁止内容字符转码
			if ( isset($this->options['leseo-wptexturize']) && $this->options['leseo-wptexturize'] == 1 ) {
				add_filter( 'run_wptexturize', '__return_false' );
			}

			//禁止JSON
			if ( isset($this->options['leseo-wpjson']) && $this->options['leseo-wpjson'] == 1 ) {
				remove_action( 'wp_head', 'rest_output_link_wp_head' );
				remove_action( 'wp_head', 'wp_oembed_add_discovery_links' );
				remove_action( 'template_redirect', 'rest_output_link_header', 11, 0 );
			}

			//屏蔽REST API (增强版的JSON禁用)
			if ( isset($this->options['leseo-rest-api']) && $this->options['leseo-rest-api'] == 1 ) {
				add_filter( 'rest_enabled', '__return_false' );
				add_filter( 'rest_jsonp_enabled', '__return_false' );
				remove_action( 'wp_head', 'rest_output_link_wp_head' );
				remove_action( 'wp_head', 'wp_oembed_add_discovery_links' );
				remove_action( 'template_redirect', 'rest_output_link_header', 11, 0 );
				add_action( 'json_api_enabled', '__return_false' );
				// 完全禁用REST API访问
				add_filter( 'rest_authentication_errors', function( $access ) {
					if ( ! is_user_logged_in() ) {
						return new WP_Error( 'rest_not_logged_in', 'REST API仅对登录用户开放', array( 'status' => 401 ) );
					}
					return $access;
				});
			}

			//禁止小工具样式
			if ( isset($this->options['leseo-widgets-block-editor']) && $this->options['leseo-widgets-block-editor'] == 1 ) {
				add_action( 'after_setup_theme', array($this, 'leseo_example_theme_support') );
			}

			//禁止XML-RPC
			if ( isset($this->options['leseo-xmlrpc']) && $this->options['leseo-xmlrpc'] == 1 ) {
				add_filter( 'xmlrpc_enabled', '__return_false' );
			}

			//禁止离线编辑端口
			if ( isset($this->options['leseo-wlwmanifest']) && $this->options['leseo-wlwmanifest'] == 1 ) {
				remove_action( 'wp_head', 'rsd_link' );
				remove_action( 'wp_head', 'wlwmanifest_link' );
			}

			//禁止EMOJI表情
			if ( isset($this->options['leseo-emoji']) && $this->options['leseo-emoji'] == 1 ) {
				add_action( 'init', array($this, 'leseo_disable_emojis') );
			}

			//屏蔽Trackbacks/Pingback
			if ( isset($this->options['leseo-trackback']) && $this->options['leseo-trackback'] == 1 ) {
				add_filter( 'xmlrpc_methods', array($this, 'leseo_remove_xmlrpc_methods') );
				add_filter( 'wp_headers', array($this, 'leseo_remove_pingback_headers') );
				add_action( 'pre_ping', array($this, 'leseo_no_self_ping') );
			}

			//前台顶部管理菜单开关
			if ( isset($this->options['leseo-admin-bar']) && $this->options['leseo-admin-bar'] == 1 ) {
				add_filter( 'show_admin_bar', '__return_false' );
			}

			//移除dns-prefetch
			if ( isset($this->options['leseo-dns-prefetch']) && $this->options['leseo-dns-prefetch'] == 1 ) {
				remove_action( 'wp_head', 'wp_resource_hints', 2 );
				add_filter( 'wp_resource_hints', array($this, 'leseo_remove_dns_prefetch'), 10, 2 );
			}

			//移除Dashicons
			if ( isset($this->options['leseo-dashicons']) && $this->options['leseo-dashicons'] == 1 ) {
				add_action( 'wp_enqueue_scripts', array($this, 'leseo_remove_dashicons'), 999 );
			}

			//移除RSD (独立控制，与离线编辑端口分开)
			if ( isset($this->options['leseo-rsd']) && $this->options['leseo-rsd'] == 1 ) {
				remove_action( 'wp_head', 'rsd_link' );
			}

			// 功能优化
			// 上传图片重命名
			if ( isset($this->options['leseo-renameimg']) && $this->options['leseo-renameimg'] == 1 ) {
				add_filter( 'wp_handle_upload_prefilter', array($this, 'leseo_rename_upload_img') );
			}

			// 图片转换WebP格式（SEO基础优化）
			if ( isset($this->options['leseo-webp-convert']) && $this->options['leseo-webp-convert'] == 1 ) {
				add_filter( 'wp_handle_upload', array($this, 'leseo_convert_upload_to_webp'), 10, 2 );
				add_filter( 'upload_mimes', array($this, 'leseo_allow_webp_upload') );
			}

			// TinyPNG 图片压缩（功能优化）
			if (
				isset( $this->options['leseo-tinypng-switch'] ) && $this->options['leseo-tinypng-switch'] == 1
				&& ! empty( $this->options['leseo-tinypng-api-key'] )
			) {
				// 放在 WebP 转换之后执行（若启用 WebP）
				add_filter( 'wp_handle_upload', array( $this, 'leseo_tinypng_compress_upload' ), 30, 2 );
			}

			//禁止裁剪大图2560
			if ( isset($this->options['leseo-cropimage']) && $this->options['leseo-cropimage'] == 1 ) {
				add_filter( 'big_image_size_threshold', '__return_false' );
			}

			//禁止垃圾评论
			if ( isset($this->options['leseo-spamcomments']) && $this->options['leseo-spamcomments'] == 1 ) {
				add_filter( 'preprocess_comment', array($this, 'leseo_refused_spam_comments') );
			}

			//禁止生成缩略图  From https://perishablepress.com/disable-wordpress-generated-images/
			if ( isset($this->options['leseo-autothumbnail']) && $this->options['leseo-autothumbnail'] == 1 ) {
				add_action('intermediate_image_sizes_advanced', array($this, 'leseo_shapeSpace_disable_image_sizes'));
				// disable scaled image size
				add_filter( 'big_image_size_threshold', '__return_false' );
				add_action( 'init', array($this, 'leseo_shapeSpace_disable_other_image_sizes') );
			}

			//压缩HTML  From https://zhangge.net/4731.html
			if ( isset($this->options['leseo-compress-html']) && $this->options['leseo-compress-html'] == 1 ) {
				add_action( 'after_setup_theme', array($this, 'leseo_compress_html') );
			}

			//精简头部无用代码(持续增加)
			if ( isset($this->options['leseo-remove-header']) && $this->options['leseo-remove-header'] == 1 ) {
				remove_action( 'wp_head', 'index_rel_link' );//去除本页唯一链接信息
				remove_action( 'wp_head', 'parent_post_rel_link', 10, 0 ); //清除前后文信息
				remove_action( 'wp_head', 'start_post_rel_link', 10, 0 );//清除前后文信息
				remove_action( 'wp_head', 'adjacent_posts_rel_link_wp_head', 10, 0 );//清除前后文信息
				remove_action( 'wp_head', 'feed_links', 2 );//移除文章和评论feed
				remove_action( 'wp_head', 'feed_links_extra', 3 ); //移除分类等feed
				remove_action( 'wp_head', 'rel_canonical' ); //rel=canonical
				remove_action( 'wp_head', 'wp_shortlink_wp_head', 10, 0 ); //rel=shortlink
				remove_action( 'wp_head', 'wp_resource_hints', 2 );//移除dns-prefetch
			}

			//移除CSS JS版本号
			if ( isset($this->options['leseo-remove-cssjsversion']) && $this->options['leseo-remove-cssjsversion'] == 1 ) {
				add_filter( 'style_loader_src', array($this, 'leseo_remove_cssjs_ver'), 999 );
				add_filter( 'script_loader_src', array($this, 'leseo_remove_cssjs_ver'), 999 );
			}

			//禁止前端搜索功能  From https://hostpapasupport.com/disable-search-feature-wordpress/
			if ( isset($this->options['leseo-disable-search']) && $this->options['leseo-disable-search'] == 1 ) {
				add_action( 'parse_query', array($this, 'leseo_wpb_filter_query') );
				add_filter( 'get_search_form', function($a) { return null; } );
				add_action( 'widgets_init', array($this, 'leseo_remove_search_widget') );
			}

			// 粘贴图片本地化（功能优化）
			if ( isset($this->options['leseo-disable-uploadimg']) && $this->options['leseo-disable-uploadimg'] == 1 ) {
				// 改为手动触发：经典编辑器按钮 + Gutenberg 按钮，通过AJAX执行本地化并替换正文URL
				add_action( 'media_buttons', array( $this, 'leseo_add_localize_images_button' ), 20 );
				add_action( 'admin_enqueue_scripts', array( $this, 'leseo_enqueue_localize_images_assets' ) );
				add_action( 'wp_ajax_leseo_localize_images', array( $this, 'leseo_ajax_localize_images' ) );
				add_action( 'enqueue_block_editor_assets', array( $this, 'leseo_enqueue_localize_images_gutenberg' ) );
			}

			// 移除图片 srcset 和 Size 标签（功能优化）
			if ( isset($this->options['leseo-disable-srcsetsize']) && $this->options['leseo-disable-srcsetsize'] == 1 ) {
				add_filter( 'max_srcset_image_width', function () { return 1; } );
			}

			// 关闭图片高度限制（功能优化）
			if ( isset($this->options['leseo-disable-widthheight']) && $this->options['leseo-disable-widthheight'] == 1 ) {
				add_filter( 'post_thumbnail_html', array($this, 'leseo_remove_width_attribute'), 10 );
				add_filter( 'image_send_to_editor', array($this, 'leseo_remove_width_attribute'), 10 );
			}

			// 移除 wp-block-library-css 样式（功能优化）
			if ( isset($this->options['leseo-disable-wpblock-librarycss']) && $this->options['leseo-disable-wpblock-librarycss'] == 1 ) {
				add_action( 'wp_enqueue_scripts', function () { wp_dequeue_style( 'wp-block-library' ); }, 100 );
			}

			// 禁止复制（功能优化）
			if ( isset($this->options['leseo-disable-copyrights']) && $this->options['leseo-disable-copyrights'] == 1 ) {
				add_action( 'wp_enqueue_scripts',
					function () {
						if ( ! current_user_can('edit_posts') ) {
							wp_enqueue_script('disable_copy_script', plugins_url('static/js/disable-copy.js', __FILE__), [], '1.0.0', true);
						}
					},
					100
				);
			}

			// 自定义分页符
			if ( isset($this->options['leseo-custom-pagination']) && $this->options['leseo-custom-pagination'] == 1 ) {
				add_filter( 'query_vars', array($this, 'leseo_custom_pagination_query_vars') );
				add_action( 'init', array($this, 'leseo_pagination_rewrite_init') );
				add_filter( 'paginate_links', array($this, 'leseo_custom_pagination_links') );
				add_filter( 'get_pagenum_link', array($this, 'leseo_custom_pagenum_link'), 10, 2 );
				add_filter( 'next_posts_link_attributes', array($this, 'leseo_custom_next_posts_link') );
				add_filter( 'previous_posts_link_attributes', array($this, 'leseo_custom_previous_posts_link') );
			}


			// SEO优化 - 基础优化
			// 页面添加反斜杠
			if ( isset($this->options['leseo-backslash']) && $this->options['leseo-backslash'] == 1 ) {
				add_filter( 'user_trailingslashit', array($this, 'leseo_nice_trailingslashit'), 10, 2 );
			}

			//隐藏分类Category
			if ( isset($this->options['leseo-remove-category']) && $this->options['leseo-remove-category'] == 1 ) {
				add_action( 'load-themes.php', array($this, 'leseo_no_category_base_refresh_rules') );
				add_action( 'created_category', array($this, 'leseo_no_category_base_refresh_rules') );
				add_action( 'edited_category', array($this, 'leseo_no_category_base_refresh_rules') );
				add_action( 'delete_category', array($this, 'leseo_no_category_base_refresh_rules') );
				register_deactivation_hook( __FILE__, array($this, 'leseo_no_category_base_deactivate') );
				add_action( 'init', array($this, 'leseo_no_category_base_permastruct') );  // Remove category base
				// Add our custom category rewrite rules
				add_filter( 'category_rewrite_rules', array($this, 'leseo_no_category_base_rewrite_rules') );
				// Add 'category_redirect' query variable
				add_filter( 'query_vars', array($this, 'leseo_no_category_base_query_vars') );
				// Redirect if 'category_redirect' is set
				add_filter( 'request', array($this, 'leseo_no_category_base_request') );
			}

			//内容图片加上ALT标签
			if ( isset($this->options['leseo-autoimgalt']) && $this->options['leseo-autoimgalt'] == 1 ) {
				add_filter( 'the_content', array($this, 'leseo_image_alt_tag'), 99999 );
			}

			//自动TAG内链
			if ( isset($this->options['leseo-autotaglink']) && $this->options['leseo-autotaglink'] == 1 ) {
				add_filter( 'the_content', array($this, 'leseo_tag_link'), 1 );
			}

			//标签URL更改：开启后标签URL改为 /tag/%tag_id% 格式
			if ( isset($this->options['leseo-tag-rewrite']) && $this->options['leseo-tag-rewrite'] == 1 ) {
				add_action( 'init', array($this, 'leseo_tag_rewrite_init') );
				add_filter( 'post_tag_rewrite_rules', array($this, 'leseo_tag_rewrite_rules') );
				add_filter( 'query_vars', array($this, 'leseo_tag_query_vars') );
				add_filter( 'request', array($this, 'leseo_tag_request') );
				add_filter( 'term_link', array($this, 'leseo_tag_term_link_id'), 10, 3 );
				// 在设置保存时刷新重写规则
				add_action( 'admin_init', array($this, 'leseo_flush_rewrite_rules') );
			}

			// TDK SEO
			$custom_index_title = trim( $this->options['leseo-selfindextitle'] ?? '' );
			$has_custom_home_title = ! empty( $custom_index_title );
			if ( isset($this->options['leseo-selfseotdk']) && $this->options['leseo-selfseotdk'] ) {
				add_action( 'wp_head', array($this, 'leseo_seo'), 1 );
				add_filter( 'pre_get_document_title', array($this, 'leseo_pre_get_document_title'), PHP_INT_MAX );
				if ( $has_custom_home_title ) {
					add_filter( 'document_title', array($this, 'leseo_document_title_home_override'), PHP_INT_MAX );
				}
				if (isset($this->options['leseo-linkmark']) && $this->options['leseo-linkmark']) {
					add_filter('document_title_separator', array($this, 'leseo_document_title_separator'));
				}
			} elseif ( $has_custom_home_title ) {
				add_filter( 'pre_get_document_title', array($this, 'leseo_pre_get_document_title'), PHP_INT_MAX );
				add_filter( 'document_title', array($this, 'leseo_document_title_home_override'), PHP_INT_MAX );
			}

			// 站外链接优化（SEO基础优化）
			if ( ! empty( $this->options['leseo-extlink-enable'] ) ) {
				add_filter( 'the_content', array( $this, 'leseo_optimize_external_links' ), 20 );
				// ?goto= 中转处理交由单独文件中的类完成
				$ext_cfg = array(
					'mode'            => isset( $this->options['leseo-extlink-mode'] ) ? $this->options['leseo-extlink-mode'] : 'normal',
					'transition'      => ! empty( $this->options['leseo-extlink-transition'] ),
					'transition_auto' => ! empty( $this->options['leseo-extlink-transition-auto'] ),
					'whitelist'       => isset( $this->options['leseo-extlink-whitelist'] ) ? $this->options['leseo-extlink-whitelist'] : '',
				);
				add_action(
					'template_redirect',
					function () use ( $ext_cfg ) {
						if ( class_exists( '\\lezaiyun\\Leseo\\inc\\Base64\\LeseoBase64' ) ) {
							\lezaiyun\Leseo\inc\Base64\LeseoBase64::handle_goto( $ext_cfg );
						}
					}
				);
			}

			// 网站地图
			if ( isset($this->options['leseo-selfsitemap']) && $this->options['leseo-selfsitemap'] ) {
				//禁止WordPress默认地图
				add_filter( 'wp_sitemaps_enabled', '__return_false' );
			}


			// 搜索推送
			if ( isset($this->options['leseo-submit-switch']) && $this->options['leseo-submit-switch'] ) {
				$this->leseo_meta_box_info     = [
					'id'               => 'leseo_baidu_submitter_meta_box_id',
					'title'            => '百度推送',
					'context'          => 'side',
					'priority'         => 'default',
					'nonce'            => [
						'action'       => 'leseo_baidu_submitter_nonce_action',
						'name'         => 'leseo_baidu_submitter_nonce',
					],
				];
				$this->leseo_submit_meta_key = 'is_leseo_baidu_submit';
				# 添加快速收录于普通收录勾选meta_box
				add_action( 'add_meta_boxes', array($this, 'leseo_add_baidu_submitter_meta_box') );

				add_action( 'save_post', array($this, 'leseo_save_baidu_submitter_post_data') );

				# 手动推送与百度收录查询子菜单
				add_action( 'admin_menu', array($this, 'leseo_add_submit_submenus'), 20 );
			}

			// 页头页尾CSS代码插入（附加功能，仅管理员可设置）
			if ( ! empty($this->options['leseo-code-header']) ) {
				$leseo_customize_code_header = wp_unslash( $this->options['leseo-code-header'] );
				add_action('wp_head', function () use ($leseo_customize_code_header) {
					echo $leseo_customize_code_header; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- 管理员自定义代码
				});
			}
			if ( ! empty($this->options['leseo-code-footer']) ) {
				$leseo_customize_code_footer = wp_unslash( $this->options['leseo-code-footer'] );
				add_action('wp_footer', function () use ($leseo_customize_code_footer) {
					echo $leseo_customize_code_footer; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- 管理员自定义代码
				});
			}
			if ( ! empty($this->options['leseo-code-cssjs']) ) {
				$leseo_customize_code_cssjs = wp_unslash( $this->options['leseo-code-cssjs'] );
				add_action('wp_head', function () use ($leseo_customize_code_cssjs) {
					$css = str_replace( array( '</style>', '</script>' ), '', $leseo_customize_code_cssjs );
					echo '<style>' . esc_html( $css ) . '</style>';
				});
			}


            // 静态分离 - 第三方存储（仅当 S3 客户端初始化成功时注册上传/删除钩子，避免 Region 异常等导致上传报错）
            if ( ! empty($this->options['leseo-s3-switch'] ) ) {
                $this->wp_upload_dir = wp_get_upload_dir();
                $this->s3_object = new Tools\S3\Api\LeseoS3Api($this->options);  // option更新后，若变动了参数，则AwsS3Api实例的重新创建，目前只有setting中会触发

                if ( $this->s3_object->isReady() ) {
                    # 避免上传插件/主题被同步到对象存储
                    if ( substr_count( $_SERVER['REQUEST_URI'], '/update.php' ) <= 0 ) {
                        add_filter('wp_handle_upload', array($this, 'leseo_s3_upload_attachments'));
                        if ( version_compare(get_bloginfo('version'), 5.3, '<') ){
                            add_filter( 'wp_update_attachment_metadata', array($this, 'leseo_s3_upload_and_thumbs') );
                        } else {
                            add_filter( 'wp_generate_attachment_metadata', array($this, 'leseo_s3_upload_and_thumbs') );
                            add_filter( 'wp_save_image_editor_file', array($this, 'leseo_s3_save_image_editor_file') );
                        }
                    }

                    # 检测不重复的文件名
                    add_filter('wp_unique_filename', array($this, 'leseo_s3_unique_filename') );

                    # 删除文件时触发删除远端文件，该删除会默认删除缩略图
                    add_action('delete_attachment', array($this, 'leseo_s3_delete_remote_attachment'));
                }
            }

		}


		/**
		 * 插件激活时刷新重写规则
		 */
		public function leseo_activate() {
			flush_rewrite_rules();
		}

		/**
		 * 百度推送定时任务回调：批量推送未提交的文章
		 */
		public function bs_cron_event() {
			if ( empty( $this->options['leseo-submit-switch'] ) || empty( $this->options['leseo-submit-bdtoken'] ) ) {
				return;
			}
			$types = isset( $this->options['leseo-resource-type'] ) && $this->options['leseo-resource-type']
				? $this->options['leseo-resource-type'] : array( 'post' );
			$posts = get_posts( array(
				'post_type'      => $types,
				'post_status'    => 'publish',
				'posts_per_page' => 50,
				'date_query'     => array( array( 'after' => '7 days ago' ) ),
				'meta_query'     => array(
					array(
						'key'     => $this->leseo_submit_meta_key,
						'compare' => 'NOT EXISTS',
					),
				),
			) );
			if ( empty( $posts ) ) {
				return;
			}
			$urls = array();
			foreach ( $posts as $p ) {
				$permalink = get_permalink( $p->ID );
				if ( $permalink ) {
					$urls[] = $permalink;
				}
			}
			if ( ! empty( $urls ) ) {
				$baidu = new BaiduSubmit\LeoBaiduSubmitter(
					array( 'token' => $this->options['leseo-submit-bdtoken'] ),
					site_url()
				);
				$resp = $baidu->request( 'normal', $urls );
				if ( ! is_wp_error( $resp ) && $resp->error === null ) {
					foreach ( $posts as $p ) {
						update_post_meta( $p->ID, $this->leseo_submit_meta_key, 'normal' );
					}
				}
			}
		}

		public function leseo_deactivate() {
			// 停用插件
			$this->options = get_option( $this->option_var_name );
			if ( is_array( $this->options ) && isset( $this->options['Delete'] ) && $this->options['Delete'] ) {
				delete_option( $this->option_var_name );
			}

			// 恢复存储插件的URL前缀（兼容旧版 lseso 拼写）
			$backup_path = $this->options['leseo-s3-backup_url_path'] ?? $this->options['lseso-s3-backup_url_path'] ?? null;
			if ( $backup_path ) {
				update_option( 'upload_url_path', $backup_path );
			}
		}

		public function leseo_no_autosave() {
			wp_deregister_script( 'autosave' );
		}

		public function leseo_disable_feed() {
			wp_die( __( '网站关闭RSS订阅功能' ) );
		}

		public function leseo_example_theme_support() { remove_theme_support( 'widgets-block-editor' ); }

		public function leseo_disable_emojis() {
			remove_action( 'wp_head', 'print_emoji_detection_script', 7 );
			remove_action( 'admin_print_scripts', 'print_emoji_detection_script' );
			remove_action( 'wp_print_styles', 'print_emoji_styles' );
			remove_action( 'admin_print_styles', 'print_emoji_styles' );
			remove_filter( 'the_content_feed', 'wp_staticize_emoji' );
			remove_filter( 'comment_text_rss', 'wp_staticize_emoji' );
			remove_filter( 'wp_mail', 'wp_staticize_emoji_for_email' );
			add_filter( 'tiny_mce_plugins', array($this, 'leseo_disable_emojis_tinymce') );
		}

		public function leseo_disable_emojis_tinymce( $plugins ) {
			if ( is_array( $plugins ) ) {
				return array_diff( $plugins, array( 'wpemoji' ) );
			} else {
				return array();
			}
		}

		/**
		 * 移除XML-RPC方法中的pingback功能
		 */
		public function leseo_remove_xmlrpc_methods( $methods ) {
			unset( $methods['pingback.ping'] );
			unset( $methods['pingback.extensions.getPingbacks'] );
			unset( $methods['pingback.extensions.getPings'] );
			return $methods;
		}

		/**
		 * 移除HTTP头中的pingback相关头信息
		 */
		public function leseo_remove_pingback_headers( $headers ) {
			if ( isset( $headers['X-Pingback'] ) ) {
				unset( $headers['X-Pingback'] );
			}
			return $headers;
		}

		/**
		 * 禁止自我pingback
		 */
		public function leseo_no_self_ping( &$links ) {
			$home = get_option( 'home' );
			foreach ( $links as $l => $link ) {
				if ( 0 === strpos( $link, $home ) ) {
					unset( $links[$l] );
				}
			}
		}

		/**
		 * 移除DNS预取
		 */
		public function leseo_remove_dns_prefetch( $hints, $relation_type ) {
			if ( 'dns-prefetch' === $relation_type ) {
				return array();
			}
			return $hints;
		}

		/**
		 * 移除Dashicons字体
		 */
		public function leseo_remove_dashicons() {
			if ( ! is_user_logged_in() ) {
				wp_deregister_style( 'dashicons' );
				wp_dequeue_style( 'dashicons' );
			}
		}


		public function leseo_rename_upload_img( $file ) {
			$time         = date( "Y-m-d H:i:s" );
			$file['name'] = $time . "" . mt_rand( 100, 999 ) . "." . pathinfo( $file['name'], PATHINFO_EXTENSION );
			return $file;
		}

		/**
		 * 将上传的图片转换为WebP格式
		 *
		 * @param array $file   上传文件信息
		 * @param string $overrides 覆盖选项（未使用）
		 * @return array
		 */
		public function leseo_convert_upload_to_webp( $file, $overrides = '' ) {
			if ( ! function_exists( 'imagewebp' ) || empty( $file['file'] ) || ! empty( $file['error'] ) ) {
				return $file;
			}
			$mime = isset( $file['type'] ) ? $file['type'] : '';
			$supported = array( 'image/jpeg', 'image/png', 'image/gif' );
			if ( ! in_array( $mime, $supported, true ) ) {
				return $file;
			}

			$file_path = $file['file'];
			$img = null;
			if ( $mime === 'image/jpeg' ) {
				$img = @imagecreatefromjpeg( $file_path );
			} elseif ( $mime === 'image/png' ) {
				$img = @imagecreatefrompng( $file_path );
				if ( $img ) {
					imagepalettetotruecolor( $img );
					imagealphablending( $img, true );
					imagesavealpha( $img, true );
				}
			} elseif ( $mime === 'image/gif' ) {
				$img = @imagecreatefromgif( $file_path );
			}

			if ( ! $img ) {
				return $file;
			}

			$webp_path = preg_replace( '/\.(jpe?g|png|gif)$/i', '.webp', $file_path );
			$quality = 82;

			if ( @imagewebp( $img, $webp_path, $quality ) ) {
				imagedestroy( $img );
				if ( file_exists( $webp_path ) && filesize( $webp_path ) > 0 ) {
					@unlink( $file_path );
					$file['file'] = $webp_path;
					$file['url']  = str_replace( basename( $file['url'] ), basename( $webp_path ), $file['url'] );
					$file['type'] = 'image/webp';
					$file['size'] = filesize( $webp_path );
				}
			} else {
				imagedestroy( $img );
			}
			return $file;
		}

		/**
		 * 确保 WebP 格式可被上传（兼容旧版 WordPress）
		 */
		public function leseo_allow_webp_upload( $mimes ) {
			if ( ! isset( $mimes['webp'] ) ) {
				$mimes['webp'] = 'image/webp';
			}
			return $mimes;
		}

		/**
		 * 站外链接优化：将正文中的站外链接改写为 ?goto=... 形式，并可附加新窗口 / nofollow
		 */
		public function leseo_optimize_external_links( $content ) {
			$mode = isset( $this->options['leseo-extlink-mode'] ) ? $this->options['leseo-extlink-mode'] : 'normal';
			if ( $mode === 'normal' ) {
				return $content;
			}

			$site_host = wp_parse_url( site_url(), PHP_URL_HOST );
			$newtab    = ! empty( $this->options['leseo-extlink-newtab'] );
			$nofollow  = ! empty( $this->options['leseo-extlink-nofollow'] );
			$whitelist_raw = isset( $this->options['leseo-extlink-whitelist'] ) ? $this->options['leseo-extlink-whitelist'] : '';
			$whitelist = array();
			if ( ! empty( $whitelist_raw ) ) {
				$lines = preg_split( '/\r\n|\r|\n/', $whitelist_raw );
				foreach ( $lines as $line ) {
					$line = trim( $line );
					if ( $line !== '' ) {
						$whitelist[] = strtolower( $line );
					}
				}
			}

			$pattern = '/<a\b[^>]*href=[\"\']([^\"\']+)[\"\'][^>]*>/i';
			$content = preg_replace_callback(
				$pattern,
				function ( $m ) use ( $mode, $site_host, $newtab, $nofollow, $whitelist ) {
					$tag  = $m[0];
					$href = $m[1];

					// 跳过 mailto:/tel:/javascript:/# 等
					if ( preg_match( '/^(mailto:|tel:|javascript:|\\#)/i', $href ) ) {
						return $tag;
					}

					// 相对链接视为站内
					$href_host = wp_parse_url( $href, PHP_URL_HOST );
					if ( empty( $href_host ) ) {
						return $tag;
					}

					// 本站域名直接跳过
					if ( $site_host && strtolower( $href_host ) === strtolower( $site_host ) ) {
						return $tag;
					}

					// 白名单域名跳过（正常内链模式）
					if ( ! empty( $whitelist ) ) {
						$lower_host = strtolower( $href_host );
						foreach ( $whitelist as $domain ) {
							if ( $lower_host === $domain || substr( $lower_host, - ( strlen( $domain ) + 1 ) ) === '.' . $domain ) {
								return $tag;
							}
						}
					}

					$target_url = $href;
					if ( $mode === 'goto_base64' ) {
						$encoded = base64_encode( $target_url );
						$new_url = add_query_arg( 'goto', rawurlencode( $encoded ), home_url( '/' ) );
					} elseif ( $mode === 'goto_plain' ) {
						$new_url = add_query_arg( 'goto', rawurlencode( $target_url ), home_url( '/' ) );
					} else {
						return $tag;
					}

					// 替换 href
					$tag = preg_replace( '/href=[\"\'][^\"\']+[\"\']/', 'href="' . esc_url( $new_url ) . '"', $tag, 1 );

					// 处理 target
					if ( $newtab ) {
						if ( preg_match( '/\btarget=[\"\'][^\"\']+[\"\']/', $tag ) ) {
							$tag = preg_replace( '/\btarget=[\"\'][^\"\']+[\"\']/', 'target="_blank"', $tag, 1 );
						} else {
							$tag = rtrim( $tag, '>' ) . ' target="_blank">';
						}
					}

					// 处理 rel
					if ( $nofollow ) {
						if ( preg_match( '/\brel=[\"\']([^\"\']*)[\"\']/', $tag, $rm ) ) {
							$rels = array_map( 'trim', explode( ' ', $rm[1] ) );
							if ( ! in_array( 'nofollow', $rels, true ) ) {
								$rels[] = 'nofollow';
							}
							if ( $newtab && ! in_array( 'noopener', $rels, true ) ) {
								$rels[] = 'noopener';
							}
							$tag = preg_replace( '/\brel=[\"\'][^\"\']*[\"\']/', 'rel="' . esc_attr( implode( ' ', $rels ) ) . '"', $tag, 1 );
						} else {
							$rels = array( 'nofollow' );
							if ( $newtab ) {
								$rels[] = 'noopener';
							}
							$tag = rtrim( $tag, '>' ) . ' rel="' . esc_attr( implode( ' ', $rels ) ) . '">';
						}
					}

					return $tag;
				},
				$content
			);

			return $content;
		}

		/**
		 * TinyPNG：压缩上传后的图片（JPEG/PNG/WebP）
		 *
		 * @param array  $file
		 * @param string $overrides
		 * @return array
		 */
		public function leseo_tinypng_compress_upload( $file, $overrides = '' ) {
			$api_key = isset( $this->options['leseo-tinypng-api-key'] ) ? trim( (string) $this->options['leseo-tinypng-api-key'] ) : '';
			if ( empty( $api_key ) || empty( $file['file'] ) || ! empty( $file['error'] ) ) {
				return $file;
			}

			$mime = isset( $file['type'] ) ? $file['type'] : '';
			$supported = array( 'image/jpeg', 'image/png', 'image/webp' );
			if ( ! in_array( $mime, $supported, true ) ) {
				return $file;
			}

			if ( ! class_exists( '\\lezaiyun\\Leseo\\inc\\TinyPNG\\LeseoTinyPng' ) ) {
				return $file;
			}

			$result = \lezaiyun\Leseo\inc\TinyPNG\LeseoTinyPng::compress_file( $file['file'], $mime, $api_key );
			if ( is_wp_error( $result ) ) {
				// 不阻断上传，仅记录错误
				error_log( '[LeSEO TinyPNG] ' . $result->get_error_message() );
				return $file;
			}

			if ( file_exists( $file['file'] ) ) {
				$file['size'] = filesize( $file['file'] );
			}
			return $file;
		}

		public function leseo_refused_spam_comments( $comment_data ) {
			// 评论中需要有中文 防止全部英文评论
			$pattern  = '/[一-龥]/u';
			$jpattern = '/[ぁ-ん]+|[ァ-ヴ]+/u';
			if ( ! preg_match( $pattern, $comment_data['comment_content'] ) ) {
				wp_die( __( '评论中需要有一个汉字！' ) );
			}
			if ( preg_match( $jpattern, $comment_data['comment_content'] ) ) {
				wp_die( __( '不能有日文！' ) );
			}
			return ( $comment_data );
		}

		public function leseo_shapeSpace_disable_image_sizes( $sizes ) {
			unset( $sizes['thumbnail'] );    // disable thumbnail size
			unset( $sizes['medium'] );       // disable medium size
			unset( $sizes['large'] );        // disable large size
			unset( $sizes['medium_large'] ); // disable medium-large size
			unset( $sizes['1536x1536'] );    // disable 2x medium-large size
			unset( $sizes['2048x2048'] );    // disable 2x large size
			return $sizes;
		}

		public function leseo_shapeSpace_disable_other_image_sizes() {
			// disable other image sizes
			remove_image_size( 'post-thumbnail' ); // disable images added via set_post_thumbnail_size()
			remove_image_size( 'another-size' );   // disable any other added image sizes
		}

		public function leseo_compress_html() {
			if (!is_user_logged_in()) {
				ob_start(
					function ( $buffer ) {
						$initial = strlen( $buffer );
						$buffer  = explode( "<!--wp-compress-html-->", $buffer );
						$count   = count( $buffer );
						$buffer_out = '';
						for ( $i = 0; $i < $count; $i ++ ) {
							if ( stristr( $buffer[ $i ], '<!--wp-compress-html no compression-->') ) {
								$buffer[ $i ] = ( str_replace( "<!--wp-compress-html no compression-->", " ", $buffer[ $i ] ) );
							} else {
								$buffer[ $i ] = ( str_replace( "\t", " ", $buffer[ $i ] ) );
								$buffer[ $i ] = ( str_replace( "\n\n", "\n", $buffer[ $i ] ) );
								$buffer[ $i ] = ( str_replace( "\n", "", $buffer[ $i ] ) );
								$buffer[ $i ] = ( str_replace( "\r", "", $buffer[ $i ] ) );
								while ( stristr( $buffer[ $i ], '  ' ) ) {
									$buffer[ $i ] = ( str_replace( "  ", " ", $buffer[ $i ] ) );
								}
							}
							$buffer_out .= $buffer[ $i ];
						}
						$final      = strlen( $buffer_out );
						$savings    = ( $initial - $final ) / $initial * 100;
						$savings    = round( $savings, 2 );
						$buffer_out .= "\n<!--压缩前的大小: $initial bytes; 压缩后的大小: $final bytes; 节约：$savings% -->";

						return $buffer_out;
					}
				);
			}
		}

		public function leseo_remove_cssjs_ver( $src ) {
			if ( strpos( $src, 'ver=' ) ) { $src = remove_query_arg( 'ver', $src ); }
			return $src;
		}

		public function leseo_wpb_filter_query( $query, $error = true ) {
			if ( is_search() && ! is_user_logged_in() ) {
				$query->is_search       = false;
				$query->query_vars[ 's' ] = false;
				$query->query[ 's' ]      = false;
				if ( $error ) { $query->is_404 = true; }
			}
		}

		public function leseo_remove_search_widget() {
			unregister_widget( 'WP_Widget_Search' );
		}

		/**
		 * 标签URL重写初始化
		 */
		public function leseo_tag_rewrite_init() {
			// 添加重写规则，将 /tag/123/ 映射到标签ID 123
			add_rewrite_rule( '^tag/([0-9]+)/?$', 'index.php?tag_id=$matches[1]', 'top' );
			// 刷新重写规则仅通过 leseo_flush_rewrite_rules 在保存设置时触发，避免每次 init 执行
		}

		/**
		 * 标签重写规则
		 */
		public function leseo_tag_rewrite_rules( $rules ) {
			$new_rules = array();
			$tags = get_tags( array( 'hide_empty' => false ) );
			foreach ( $tags as $tag ) {
				// 为每个标签ID创建重写规则
				$new_rules['^tag/' . $tag->term_id . '/?$'] = 'index.php?tag=' . $tag->slug;
			}
			return $new_rules + $rules;
		}

		/**
		 * 添加标签ID查询变量
		 */
		public function leseo_tag_query_vars( $query_vars ) {
			$query_vars[] = 'tag_id';
			return $query_vars;
		}

		/**
		 * 处理标签请求
		 */
		public function leseo_tag_request( $query_vars ) {
			if ( isset( $query_vars['tag_id'] ) && is_numeric( $query_vars['tag_id'] ) ) {
				$tag = get_term( $query_vars['tag_id'], 'post_tag' );
				if ( $tag && ! is_wp_error( $tag ) ) {
					// 将标签ID转换为slug，让WordPress正常处理
					$query_vars['tag'] = $tag->slug;
					unset( $query_vars['tag_id'] );
				}
			}
			return $query_vars;
		}

		/**
		 * 将标签链接从 /tag/slug/ 改为 /tag/term_id/ 格式
		 */
		public function leseo_tag_term_link_id( $termlink, $term, $taxonomy ) {
			if ( $taxonomy !== 'post_tag' ) {
				return $termlink;
			}
			return home_url( '/tag/' . $term->term_id . '/' );
		}

		/**
		 * 刷新重写规则
		 */
		public function leseo_flush_rewrite_rules() {
			// 只在设置页面且选项发生变化时刷新
			if ( isset( $_POST['action'] ) && strpos( $_POST['action'], 'csf' ) !== false ) {
				flush_rewrite_rules();
			}
		}

		/**
		 * 自定义分页符重写初始化
		 * 需设置 $wp_rewrite->pagination_base，否则 WordPress 会在已生成的链接上再次追加 /page/N 导致 /laojiang/2/page/2/ 问题
		 */
		public function leseo_pagination_rewrite_init() {
			$pagination_string = isset( $this->options['leseo-pagination-string'] ) ? 
				sanitize_title( $this->options['leseo-pagination-string'] ) : 'page';

			global $wp_rewrite;
			$wp_rewrite->pagination_base = $pagination_string;
			
			// 添加通用分页重写规则
			add_rewrite_rule( '^(.+?)/' . $pagination_string . '/([0-9]+)/?$', 'index.php?pagename=$matches[1]&paged=$matches[2]', 'top' );
			add_rewrite_rule( '^' . $pagination_string . '/([0-9]+)/?$', 'index.php?paged=$matches[1]', 'top' );
			// 刷新重写规则仅通过 leseo_flush_rewrite_rules 在保存设置时触发
		}

		/**
		 * 自定义分页符查询变量
		 */
		public function leseo_custom_pagination_query_vars( $query_vars ) {
			$pagination_string = isset( $this->options['leseo-pagination-string'] ) ? 
				sanitize_title( $this->options['leseo-pagination-string'] ) : 'page';
			$query_vars[] = $pagination_string;
			return $query_vars;
		}

		/**
		 * 自定义分页链接
		 */
		public function leseo_custom_pagination_links( $links ) {
			$pagination_string = isset( $this->options['leseo-pagination-string'] ) ? 
				sanitize_title( $this->options['leseo-pagination-string'] ) : 'page';
			
			if ( is_array( $links ) ) {
				foreach ( $links as &$link ) {
					if ( is_string( $link ) ) {
						$link = str_replace( '/page/', '/' . $pagination_string . '/', $link );
					}
				}
			}
			return $links;
		}

		/**
		 * 自定义分页数字链接
		 */
		public function leseo_custom_pagenum_link( $url, $pagenum ) {
			$pagination_string = isset( $this->options['leseo-pagination-string'] ) ? 
				sanitize_title( $this->options['leseo-pagination-string'] ) : 'page';
			
			$url = str_replace( '/page/', '/' . $pagination_string . '/', $url );
			return $url;
		}

		/**
		 * 自定义下一页链接
		 */
		public function leseo_custom_next_posts_link( $attr ) {
			$pagination_string = isset( $this->options['leseo-pagination-string'] ) ? 
				sanitize_title( $this->options['leseo-pagination-string'] ) : 'page';
			
			if ( isset( $attr['href'] ) ) {
				$attr['href'] = str_replace( '/page/', '/' . $pagination_string . '/', $attr['href'] );
			}
			return $attr;
		}

		/**
		 * 自定义上一页链接
		 */
		public function leseo_custom_previous_posts_link( $attr ) {
			$pagination_string = isset( $this->options['leseo-pagination-string'] ) ? 
				sanitize_title( $this->options['leseo-pagination-string'] ) : 'page';
			
			if ( isset( $attr['href'] ) ) {
				$attr['href'] = str_replace( '/page/', '/' . $pagination_string . '/', $attr['href'] );
			}
			return $attr;
		}


		/**
		 * 下载图片 & 替换正文内容
		 *
		 * @param $match_images :  图片本地绝对路径
		 * @param $wp_upload_dir : 图片mimetype
		 * @param $post :          Post
		 *
		 * @return mixed :         Post
		 */
		public function _leseo_curl_get_contents( $match_images, $wp_upload_dir, $post) {
			set_time_limit(1800);  // 脚本执行限制时间30分钟。若不能满足需求，后续可设置为可配置。
			$ch = curl_init();                                             // 创建会话

			// 设置请求选项
			curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);   // 使其以字符串形式返回数据而不是直接输出
			curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);  // 取消SSL证书验证
			curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);   // 启用跟随location重定向
			curl_setopt($ch, CURLOPT_MAXREDIRS,20);           // 指定最大重定向次数为20
			curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 30);     // 指定连接超时时间为30秒

			$site_host = isset( $_SERVER['HTTP_HOST'] ) ? $_SERVER['HTTP_HOST'] : wp_parse_url( site_url(), PHP_URL_HOST );
			foreach ($match_images[1] as $src) {
				// 当非本站图片时
				if (isset($src) && $site_host && strpos($src, $site_host) === false) {
					curl_setopt($ch, CURLOPT_URL, $src);            // 设置cURL的URL选项为图片$url
					if ( ! wp_check_filetype( basename($src) )['ext'] ) { // file_info['ext' =>'扩展名','type' =>'类型']
						// 无扩展名和webp格式的图片无法判断类型
						$file_name = dechex(mt_rand(10000, 99999)) .'-'. date('YmdHis').'.tmp';
					} else {
						// 重命名图片防重复
						$file_name = dechex(mt_rand(10000, 99999)) . '-' . basename($src);
					}
					$file_path = $wp_upload_dir['path'] . '/' . $file_name;
					$fp = fopen($file_path, 'w');
					curl_setopt($ch, CURLOPT_FILE, $fp);            // 将响应写入到文件中
					curl_exec($ch);                                       // 执行会话并获取响应
					fclose($fp);

					if ( file_exists($file_path) && filesize($file_path) > 0 ) {
						// 将扩展名为tmp的图片转换为jpeg文件并重命名
						if ( pathinfo($file_path, PATHINFO_EXTENSION) == 'tmp' ) {
							$file_path = $this->_leseo_image_convert( $file_path );
						}

						$filename = basename($file_path);
						$new_src = $wp_upload_dir['url'] . '/' . $filename;

						// 替换文章内容中的src
						$post->post_content = str_replace($src, $new_src, $post->post_content);

						// 构造附件post参数并插入媒体库(作为一个post插入到数据库)
						$file_type = wp_check_filetype($filename);
						$attachment = array(
							'post_type' => 'attachment',
							'guid' => $new_src,
							'post_mime_type' => $file_type['type'],
							'post_title' => preg_replace('/\.[^.]+$/', '', $filename),
							'post_content' => '',
							'post_status' => 'inherit'
						);

						// 生成并更新图片的metadata信息
						$attach_id = wp_insert_attachment( $attachment, ltrim($wp_upload_dir['subdir'] . '/' . $filename, '/'), 0 );
						if ( !function_exists('wp_generate_attachment_metadata') ) require ABSPATH . 'wp-admin/includes/image.php';
						$attach_data = wp_generate_attachment_metadata( $attach_id, $file_path );  // 生成wp图片的各种规格
						wp_update_attachment_metadata( $attach_id, $attach_data );                 // 更新metadata
					}
				}
			}
			curl_close($ch);  // 关闭会话

			return $post;
		}

		/**
		 * 转换未知格式的图片为jpg格式
		 *
		 * @param $non_type_image : 未知格式的图片本地绝对路径
		 *
		 * @return String 转换后的图片本地绝对路径
		 */
		public function _leseo_image_convert( $non_type_image): string {
			// 加载图片
			$image = imagecreatefromstring(file_get_contents($non_type_image));

			// 获得图片的宽度和高度
			$width = imagesx($image);
			$height = imagesy($image);

			// 创建新的JPG图片
			$jpeg = imagecreatetruecolor($width, $height);

			// 将未知类型的图片转换为JPG格式
			imagecopy($jpeg, $image, 0, 0, 0, 0, $width, $height);

			$new_image_path = str_replace('.tmp', '.jpeg', $non_type_image);
			if (imagejpeg($jpeg, $new_image_path, 100)) {
				try {
					unlink($non_type_image);
				} catch (\Exception $e) {
					$error_msg = sprintf('删除本地文件失败 %s: %s', $image,
						$e->getMessage());
					error_log($error_msg);
				}
			} else {
				$new_image_path = $non_type_image;
			}
			// 释放内存
			imagedestroy($image);
			imagedestroy($jpeg);

			return $new_image_path;
		}



		/**
		 * 钩子函数：将post_content中本站服务器域名外的img上传至服务器并替换url
		 * 正文图片本地化（功能优化）
		 *
		 * @param $post_id : post id
		 * @param $post    : WP_Post对象
		 */
		public function leseo_save_images_in_post( $post_id, $post) {
			if ( ! $post || ! is_a( $post, 'WP_Post' ) || $post->post_status != 'publish' ) {
				return;
			}
			// 匹配<img>、src，存入$matches数组
			$preg = '/<img.*[\s]src=[\"|\'](.*)[\"|\'].*>/iU';
			$num = preg_match_all($preg, $post->post_content, $matches);

			if ($num) {
				$post = $this->_leseo_curl_get_contents($matches, wp_upload_dir(), $post);
				global $wpdb;
				$wpdb->update( $wpdb->posts, array('post_content' => $post->post_content), array('ID' => $post->ID));
			}
		}

		/**
		 * 在经典编辑器“添加媒体”右侧增加图片本地化按钮
		 */
		public function leseo_add_localize_images_button() {
			$screen = function_exists( 'get_current_screen' ) ? get_current_screen() : null;
			if ( ! $screen || empty( $screen->base ) || ! in_array( $screen->base, array( 'post', 'page' ), true ) ) {
				return;
			}
			if ( ! current_user_can( 'edit_posts' ) ) {
				return;
			}
			echo '<button type="button" class="button" id="leseo-localize-images-btn">' . esc_html__( '图片本地化', 'LeSEO' ) . '</button>';
		}

		/**
		 * 后台编辑页加载脚本
		 */
		public function leseo_enqueue_localize_images_assets( $hook ) {
			if ( ! in_array( $hook, array( 'post.php', 'post-new.php' ), true ) ) {
				return;
			}
			if ( ! current_user_can( 'edit_posts' ) ) {
				return;
			}
			wp_enqueue_script(
				'leseo-localize-images',
				plugins_url( 'static/js/localize-images.js', __FILE__ ),
				array(),
				'1.0.0',
				true
			);
			wp_localize_script(
				'leseo-localize-images',
				'LESEO_LOCALIZE',
				array(
					'ajaxUrl' => admin_url( 'admin-ajax.php' ),
					'nonce'   => wp_create_nonce( 'leseo_localize_images' ),
				)
			);
		}

		/**
		 * Gutenberg 区块编辑器加载图片本地化脚本
		 */
		public function leseo_enqueue_localize_images_gutenberg() {
			if ( ! current_user_can( 'edit_posts' ) ) {
				return;
			}
			wp_enqueue_script(
				'leseo-localize-images-gutenberg',
				plugins_url( 'static/js/localize-images-gutenberg.js', __FILE__ ),
				array( 'wp-element', 'wp-i18n', 'wp-data', 'wp-edit-post', 'wp-plugins' ),
				'1.0.0',
				true
			);
			wp_localize_script(
				'leseo-localize-images-gutenberg',
				'LESEO_LOCALIZE',
				array(
					'ajaxUrl' => admin_url( 'admin-ajax.php' ),
					'nonce'   => wp_create_nonce( 'leseo_localize_images' ),
				)
			);
		}

		/**
		 * AJAX：将编辑器内容中的外链图片本地化并返回替换后的HTML
		 */
		public function leseo_ajax_localize_images() {
			if ( ! current_user_can( 'edit_posts' ) ) {
				wp_send_json_error( array( 'message' => 'permission_denied' ), 403 );
			}
			check_ajax_referer( 'leseo_localize_images', 'nonce' );

			$content = isset( $_POST['content'] ) ? wp_unslash( $_POST['content'] ) : '';
			$post_id = isset( $_POST['postId'] ) ? absint( $_POST['postId'] ) : 0;
			if ( empty( $content ) ) {
				wp_send_json_success( array( 'content' => $content, 'replaced' => 0, 'errors' => array() ) );
			}

			$result = $this->leseo_localize_images_in_html( $content, $post_id );
			wp_send_json_success( $result );
		}

		/**
		 * 本地化HTML中外链图片并替换URL
		 *
		 * @return array { content, replaced, errors }
		 */
		private function leseo_localize_images_in_html( $content, $post_id = 0 ) {
			$errors   = array();
			$replaced = 0;

			$site_host = wp_parse_url( site_url(), PHP_URL_HOST );
			$preg = '/<img[^>]+src=[\"\\\']([^\"\\\']+)[\"\\\']/i';
			$num  = preg_match_all( $preg, $content, $matches );
			if ( ! $num || empty( $matches[1] ) ) {
				return array( 'content' => $content, 'replaced' => 0, 'errors' => array() );
			}

			$srcs = array_values( array_unique( $matches[1] ) );
			foreach ( $srcs as $src ) {
				$src = trim( $src );
				if ( empty( $src ) ) {
					continue;
				}
				// 跳过 data:、blob: 以及本站图片（兼容 PHP 7）
				if ( strpos( $src, 'data:' ) === 0 || strpos( $src, 'blob:' ) === 0 ) {
					continue;
				}
				$src_host = wp_parse_url( $src, PHP_URL_HOST );
				if ( $site_host && $src_host && strtolower( $src_host ) === strtolower( $site_host ) ) {
					continue;
				}

				$new_url = $this->leseo_sideload_image_to_media( $src, $post_id );
				if ( is_wp_error( $new_url ) ) {
					$errors[] = $new_url->get_error_message();
					continue;
				}
				if ( $new_url ) {
					$content = str_replace( $src, $new_url, $content );
					$replaced++;
				}
			}

			return array(
				'content'  => $content,
				'replaced' => $replaced,
				'errors'   => $errors,
			);
		}

		/**
		 * 下载远程图片到媒体库，返回新URL
		 */
		private function leseo_sideload_image_to_media( $url, $post_id = 0 ) {
			if ( ! function_exists( 'download_url' ) ) {
				require_once ABSPATH . 'wp-admin/includes/file.php';
			}
			if ( ! function_exists( 'wp_handle_sideload' ) ) {
				require_once ABSPATH . 'wp-admin/includes/file.php';
			}
			if ( ! function_exists( 'wp_generate_attachment_metadata' ) ) {
				require_once ABSPATH . 'wp-admin/includes/image.php';
			}

			$tmp = download_url( $url, 30 );
			if ( is_wp_error( $tmp ) ) {
				return $tmp;
			}

			$path = wp_parse_url( $url, PHP_URL_PATH );
			$name = $path ? basename( $path ) : '';
			if ( empty( $name ) || ! wp_check_filetype( $name )['ext'] ) {
				$name = 'image-' . gmdate( 'YmdHis' ) . '-' . wp_generate_password( 6, false ) . '.jpg';
			}

			$file_array = array(
				'name'     => $name,
				'tmp_name' => $tmp,
			);

			$overrides = array( 'test_form' => false );
			$sideload  = wp_handle_sideload( $file_array, $overrides );
			if ( ! empty( $sideload['error'] ) ) {
				@unlink( $tmp );
				return new \WP_Error( 'leseo_sideload_failed', $sideload['error'] );
			}

			$attachment = array(
				'post_mime_type' => $sideload['type'],
				'post_title'     => preg_replace( '/\.[^.]+$/', '', basename( $sideload['file'] ) ),
				'post_content'   => '',
				'post_status'    => 'inherit',
			);

			$attach_id = wp_insert_attachment( $attachment, $sideload['file'], $post_id );
			if ( is_wp_error( $attach_id ) ) {
				return $attach_id;
			}

			$attach_data = wp_generate_attachment_metadata( $attach_id, $sideload['file'] );
			wp_update_attachment_metadata( $attach_id, $attach_data );

			return wp_get_attachment_url( $attach_id );
		}


		public function leseo_remove_width_attribute( $html ) {
			return preg_replace( '/(width|height)="\d*"\s/', "", $html );
		}


		public function leseo_nice_trailingslashit( $string, $type_of_url ) {
			if ( $type_of_url != 'single' ) { $string = trailingslashit( $string ); }
			return $string;
		}

		public function leseo_no_category_base_refresh_rules() {
			global $wp_rewrite;
			$wp_rewrite->flush_rules();
		}

		public function leseo_no_category_base_deactivate() {
			remove_filter( 'category_rewrite_rules', array($this, 'leseo_no_category_base_rewrite_rules') );
			$this->leseo_no_category_base_refresh_rules();
		}

		public function leseo_no_category_base_permastruct() {
			global $wp_rewrite, $wp_version;
			if ( version_compare( $wp_version, '3.4', '<' ) ) {
				// For pre-3.4 support
				$wp_rewrite->extra_permastructs['category'][0] = '%category%';
			} else {
				$wp_rewrite->extra_permastructs['category']['struct'] = '%category%';
			}
		}

		public function leseo_no_category_base_rewrite_rules( $category_rewrite ) {
			$category_rewrite = array();
			$categories       = get_categories( array( 'hide_empty' => false ) );
			foreach ( $categories as $category ) {
				$category_nicename = $category->slug;
				if ( $category->parent == $category->cat_ID )// recursive recursion
				{
					$category->parent = 0;
				} elseif ( $category->parent != 0 ) {
					$category_nicename = get_category_parents( $category->parent, false, '/', true ) . $category_nicename;
				}
				$category_rewrite[ '(' . $category_nicename . ')/(?:feed/)?(feed|rdf|rss|rss2|atom)/?$' ] = 'index.php?category_name=$matches[1]&feed=$matches[2]';
				$category_rewrite[ '(' . $category_nicename . ')/page/?([0-9]{1,})/?$' ]                  = 'index.php?category_name=$matches[1]&paged=$matches[2]';
				$category_rewrite[ '(' . $category_nicename . ')/?$' ]                                    = 'index.php?category_name=$matches[1]';
			}
			// Redirect support from Old Category Base
//			global $wp_rewrite;
			$old_category_base                                 = get_option( 'category_base' ) ? get_option( 'category_base' ) : 'category';
			$old_category_base                                 = trim( $old_category_base, '/' );
			$category_rewrite[ $old_category_base . '/(.*)$' ] = 'index.php?category_redirect=$matches[1]';

			return $category_rewrite;
		}

		public function leseo_no_category_base_query_vars( $public_query_vars ) {
			$public_query_vars[] = 'category_redirect';
			return $public_query_vars;
		}

		public function leseo_no_category_base_request( $query_vars ) {
			if ( isset( $query_vars['category_redirect'] ) ) {
				$catlink = trailingslashit( get_option( 'home' ) ) . user_trailingslashit( $query_vars['category_redirect'], 'category' );
				status_header( 301 );
				header( "Location: $catlink" );
				exit();
			}
			return $query_vars;
		}

	/**
	* 批量给 WordPress 没有Alt 和Title加上标签
	*/
		public function leseo_image_alt_tag($content) {
        global $post;
        if ( ! $post || ! isset( $post->post_title ) ) {
            return $content;
        }
        $post_title = $post->post_title;
        $pattern = '/<img(.*?)\/>/i';
        preg_match_all($pattern, $content, $matches);
        foreach ($matches[0] as $index => $img_tag) {
            if (strpos($img_tag, ' alt=') === false || preg_match('/ alt=["\']\s*["\']/', $img_tag)) {
                $replacement = preg_replace('/<img/', '<img alt="' . $post_title . ' - 第' . ($index + 1) . '张" title="' . $post_title . ' - 第' . ($index + 1) . '张"', $img_tag);
                $content = str_replace($img_tag, $replacement, $content);
            }
        }
        return $content;
    }
    

		public function leseo_tag_link( $content ) {
			//改变标签关键字
			$post_tags = get_the_tags();
			if ( $post_tags ) {
				usort( $post_tags,
					function ( $a, $b ) {
						// 按长度排序
						if ( $a->name == $b->name ) { return 0; }
						return ( strlen( $a->name ) > strlen( $b->name ) ) ? - 1 : 1;
					});
				foreach ( $post_tags as $tag ) {
					$link    = get_tag_link( $tag->term_id );
					$keyword = $tag->name;
					//连接代码
					$clean_keyword = stripslashes( $keyword );
					$url           = "<a href=\"$link\" title=\"" . str_replace( '%s', addcslashes( $clean_keyword, '$' ), __( 'View all posts in %s' ) ) . "\"";
					$url           .= ' target="_blank" class="tag_link"';
					$url           .= ">" . addcslashes( $clean_keyword, '$' ) . "</a>";
					$limit         = rand( $this->match_num_from, $this->match_num_to );
					//不连接的代码
					$ex_word = ''; $case='';  // 保留口子，可以拓展
					$content      = preg_replace( '|(<a[^>]+>)(.*)(' . $ex_word . ')(.*)(</a[^>]*>)|U' . $case, '$1$2%&&&&&%$4$5', $content );
					$content       = preg_replace( '|(<img)(.*?)(' . $ex_word . ')(.*?)(>)|U' . $case, '$1$2%&&&&&%$4$5', $content );
					$clean_keyword = preg_quote( $clean_keyword, '\'' );
					$regEx         = '\'(?!((<.*?)|(<a.*?)))(' . $clean_keyword . ')(?!(([^<>]*?)>)|([^>]*?</a>))\'s' . $case;
					$content       = preg_replace( $regEx, $url, $content, $limit );
					$content      = str_replace( '%&&&&&%', stripslashes( $ex_word ), $content );
				}
			}
			return $content;
		}

		/**
		 * 添加手动推送、百度收录查询子菜单
		 */
		public function leseo_add_submit_submenus() {
			add_submenu_page(
				'lezaiyun-leseo-options',
				'手动推送',
				'手动推送',
				'manage_options',
				'leseo-manually-submit',
				array( $this, 'leseo_render_manually_submit_page' )
			);
			add_submenu_page(
				'lezaiyun-leseo-options',
				'百度收录查询',
				'百度收录查询',
				'manage_options',
				'leseo-check-baidu',
				array( $this, 'leseo_render_check_baidu_page' )
			);
		}

		/**
		 * 手动推送页面：批量提交URL到百度
		 */
		public function leseo_render_manually_submit_page() {
			if ( ! current_user_can( 'manage_options' ) ) {
				wp_die( esc_html__( '您没有权限访问此页面', 'LeSEO' ) );
			}
			$message = '';
			if ( isset( $_POST['leseo_manually_submit_nonce'] ) && wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['leseo_manually_submit_nonce'] ) ), 'leseo_manually_submit' ) ) {
				$urls_text = isset( $_POST['leseo_submit_urls'] ) ? sanitize_textarea_field( wp_unslash( $_POST['leseo_submit_urls'] ) ) : '';
				$type      = isset( $_POST['leseo_submit_type'] ) && $_POST['leseo_submit_type'] === 'daily' ? 'daily' : 'normal';
				$urls      = array_filter( array_map( 'trim', explode( "\n", $urls_text ) ) );
				$urls      = array_filter( $urls, 'esc_url_raw' );
				if ( ! empty( $urls ) && ! empty( $this->options['leseo-submit-bdtoken'] ) ) {
					$baidu = new BaiduSubmit\LeoBaiduSubmitter( array( 'token' => $this->options['leseo-submit-bdtoken'] ), site_url() );
					$resp  = $baidu->request( $type, array_slice( $urls, 0, 2000 ) );
					if ( is_wp_error( $resp ) ) {
						$message = '<div class="notice notice-error"><p>' . esc_html( $resp->get_error_message() ) . '</p></div>';
					} elseif ( $resp->error !== null ) {
						$message = '<div class="notice notice-warning"><p>' . esc_html( $resp->message ?? '推送失败' ) . '</p></div>';
					} else {
						$success = $type === 'daily' ? ( $resp->success_daily ?? 0 ) : ( $resp->success ?? 0 );
						$message = '<div class="notice notice-success"><p>' . sprintf( esc_html__( '成功推送 %d 条链接', 'LeSEO' ), (int) $success ) . '</p></div>';
					}
				} else {
					$message = '<div class="notice notice-warning"><p>' . esc_html__( '请输入有效URL或先在设置中配置百度推送TOKEN', 'LeSEO' ) . '</p></div>';
				}
			}
			?>
			<div class="wrap">
				<h1><?php esc_html_e( '手动推送', 'LeSEO' ); ?></h1>
				<?php echo $message; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
				<form method="post" action="">
					<?php wp_nonce_field( 'leseo_manually_submit', 'leseo_manually_submit_nonce' ); ?>
					<table class="form-table">
						<tr>
							<th><label for="leseo_submit_urls"><?php esc_html_e( 'URL列表（每行一个，最多2000条）', 'LeSEO' ); ?></label></th>
							<td>
								<textarea name="leseo_submit_urls" id="leseo_submit_urls" rows="12" class="large-text" placeholder="https://example.com/page1&#10;https://example.com/page2"></textarea>
							</td>
						</tr>
						<tr>
							<th><label for="leseo_submit_type"><?php esc_html_e( '推送类型', 'LeSEO' ); ?></label></th>
							<td>
								<select name="leseo_submit_type" id="leseo_submit_type">
									<option value="normal"><?php esc_html_e( '普通推送', 'LeSEO' ); ?></option>
									<option value="daily"><?php esc_html_e( '快速收录', 'LeSEO' ); ?></option>
								</select>
							</td>
						</tr>
					</table>
					<?php submit_button( __( '提交推送', 'LeSEO' ) ); ?>
				</form>
			</div>
			<?php
		}

		/**
		 * 百度收录查询页面
		 */
		public function leseo_render_check_baidu_page() {
			if ( ! current_user_can( 'manage_options' ) ) {
				wp_die( esc_html__( '您没有权限访问此页面', 'LeSEO' ) );
			}
			$site_url = preg_replace( '#^https?://#', '', untrailingslashit( site_url() ) );
			$baidu_check_url = 'https://www.baidu.com/s?wd=site:' . esc_attr( $site_url );
			?>
			<div class="wrap">
				<h1><?php esc_html_e( '百度收录查询', 'LeSEO' ); ?></h1>
				<p><?php esc_html_e( '在百度搜索中查看本站收录情况：', 'LeSEO' ); ?></p>
				<p><a href="<?php echo esc_url( $baidu_check_url ); ?>" target="_blank" rel="noopener" class="button button-primary"><?php esc_html_e( '在百度查看 site: 收录结果', 'LeSEO' ); ?></a></p>
				<p class="description"><?php esc_html_e( '点击后将跳转到百度搜索，显示您站点的收录页面数量。建议定期查看以了解收录进度。', 'LeSEO' ); ?></p>
			</div>
			<?php
		}

		public function leseo_add_baidu_submitter_meta_box() {
			# 添加meta box模块
			$types = isset($this->options['leseo-resource-type']) && $this->options['leseo-resource-type']
				? $this->options['leseo-resource-type'] : array();
			foreach ( $types as $type ) {
				add_meta_box(
					$this->leseo_meta_box_info['id'],                       // Meta Box在前台页面源码中的id
					$this->leseo_meta_box_info['title'],                    // 显示的标题
					array($this, 'leseo_render_baidu_submitter_meta_box'),  // 回调方法，用于输出Meta Box的HTML代码
					$type,                                            // 在哪个post type页面添加
					$this->leseo_meta_box_info['context'],                  // 在哪显示该Meta Box
					$this->leseo_meta_box_info['priority']                  // 优先级
				);
			}
		}

		public function leseo_render_baidu_submitter_meta_box( $post ) {
			# 显示meta box的html代码
//			if ( !isset($this->options['leseo-resource-type']) && !$this->options['leseo-resource-type'] ) return;
			if ( !isset($this->options['leseo-resource-type']) ) return;

			// 添加 nonce 项用于save post时的安全检查
			wp_nonce_field( $this->leseo_meta_box_info['nonce']['action'], $this->leseo_meta_box_info['nonce']['name'] );

			$is_daily_html = '';
			$is_normal_html = '';

			// 检测是否存在已推送标签，若已推送，则更改展示。
			$meta_value = get_post_meta( $post->ID, $this->leseo_submit_meta_key, true );  # (optional) 如果设置为 true，返回单个值。

			$cache = new inc\Cache\LeCache('remain');
			$_remain = $cache->get('remain');
			$_remain_daily = $cache->get('remain_daily');
			$remain = ($_remain and $_remain[1] > current_time('timestamp')) ? $_remain[0] : False;
			$remain_daily = ($_remain_daily and $_remain_daily[1] > current_time('timestamp')) ? $_remain_daily[0] : False;

			$submit_types = isset( $this->options['leseo-submit-type'] ) ? $this->options['leseo-submit-type'] : array();
			if ('normal' == $meta_value) {
				$html = '已提交普通收录';
			} elseif ('daily' == $meta_value) {
				$html = '已提交快速收录';
			} else {
				if ( in_array( 'daily', $submit_types ) ) {
					$is_daily_html = '<input type="checkbox" name="daily_submit" ';
					if ( $remain_daily === False ) { $is_daily_html .= ' />快速收录  (剩余配额：10条)';
					} else if ( $remain_daily > 0 ) { $is_daily_html .= ' />快速收录  (剩余配额：' . esc_attr( $remain_daily ) . '条)';
					} else { $is_daily_html = '<span>快速收录配额已用完，请明天再试!</span>'; }
				}
				if ( in_array( 'normal', $submit_types ) ) {
					$is_normal_html = '<input type="checkbox" name="normal_submit" checked="checked" ';
					if ( $remain === False ) { $is_normal_html .= ' />普通收录  (剩余配额：99999条)';
					} else if ( $remain > 0 ) { $is_normal_html .= ' />普通收录  (剩余配额：' . esc_attr( $remain ) . '条)';
					} else { $is_normal_html = '<span>普通收录配额已用完，请明天再试!</span>'; }
				}
				$html = $is_daily_html . '<br />' . $is_normal_html;
				if ( strlen( $meta_value ) > 0 ) { $html .= '<br /><p>' . esc_html( $meta_value ) . '</p>'; }
			}
			$allowed_tags = array(
				'input' => array( 'type' => array(), 'name' => array(), 'checked' => array() ),
				'br'    => array(),
				'span'  => array(),
				'p'     => array(),
			);
			echo wp_kses( $html, $allowed_tags );
		}

		public function leseo_save_baidu_submitter_post_data( $post_id ) {
			# 处理
			if ( !isset($this->options['leseo-submit-switch']) || !$this->options['leseo-submit-switch']) return False;
			// 如果是系统自动保存，则不操作
			if ( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE ) return $post_id;

			$post = get_post($post_id);
			if ($post->post_status != 'publish') return $post_id;

			$types = isset($this->options['leseo-resource-type']) && $this->options['leseo-resource-type']
				? $this->options['leseo-resource-type'] : array();
			if ( !in_array($post->post_type, $types, True) ) return $post_id;

			// 检查nonce是否设置
			if (!isset($_POST[$this->leseo_meta_box_info['nonce']['name']]))  return $post_id;

			$nonce = $_POST[$this->leseo_meta_box_info['nonce']['name']];
			// 验证nonce是否正确
			if (!wp_verify_nonce( $nonce, $this->leseo_meta_box_info['nonce']['action'])) return $post_id;

			// 检查用户权限
			if ($_POST['post_type'] == 'post') {
				if (!current_user_can('edit_post', $post_id )) return $post_id;
			}

			if ( isset($_POST['normal_submit']) ) $meta_value = 'normal';
			if ( isset($_POST['daily_submit']) ) $meta_value = 'daily';

			# 生成页面时，提交推送成功的将不会生成meta_value, 省去get_post_meta验证，若有问题需严谨地取值验证。
			if ( isset($meta_value) ) {
				$post_link = get_permalink($post_id);
				if ($post_link) {
					if (isset($this->options['leseo-submit-bdtoken']) && $this->options['leseo-submit-bdtoken'] ) {
						$urls_array = array($post_link);
						$baidu   = new BaiduSubmit\LeoBaiduSubmitter(['token' => $this->options['leseo-submit-bdtoken']], site_url());
						$resp = $baidu->request($meta_value, $urls_array);

						if ( !is_wp_error( $resp ) ) {
							if ($resp->error === Null) {
								// 更新数据，第四个参数pre_value，用于指定之前的值替换，暂时先不添加
								update_post_meta( $post_id, $this->leseo_submit_meta_key, $meta_value );

								// 配额已由 LeseoBaiduResponse 正确写入 remain/remain_daily，此处无需重复设置
							}  else {
								update_post_meta( $post_id, $this->leseo_submit_meta_key, $resp->message );
							}
						} else {
							update_post_meta( $post_id, $this->leseo_submit_meta_key, is_wp_error( $resp ) ? $resp->get_error_message() : __( '推送请求失败', 'LeSEO' ) );
						}
					} else {
						update_post_meta( $post_id, $this->leseo_submit_meta_key, 'API TOKEN未设置，无法推送！' );
					}
				}
			}
		}

		public function leseo_seo() {
			global $post, $wp_query;
			$keywords    = '';
			$description = '';
			$seo         = '';
			if ( isset( $this->options['leseo-selfseotdk'] ) && $this->options['leseo-selfseotdk'] ) {
				$open_graph = ! isset( $this->options['leseo-opengraph'] ) || $this->options['leseo-opengraph'];

				if ( is_singular() && ! is_front_page() ) {
					# 是内容页 && 不是第一页
					global $paged;
					if ( ! $paged ) { $paged = 1; }

					$singular_meta_options = get_post_meta( $post->ID, 'leseo_singular_meta_options', true );  // 必须带true，下面tax参数那里一样

					if ( isset($singular_meta_options['leseo-singular-meta-switcher'])
					     && $singular_meta_options['leseo-singular-meta-switcher'] ) {
						$keywords    = str_replace( '，', ',',
							trim( strip_tags( $singular_meta_options['leseo-singular-meta-keywords'] ?? $keywords ) )
						);
						$description = trim( strip_tags( $singular_meta_options['leseo-singular-meta-description'] ?? $description ) );

						if ( $keywords ) {
							$seo .= '<meta name="keywords" content="' . esc_attr( $keywords ) . '" />' . "\n";
						}
						if ( $description ) {
							$seo .= '<meta name="description" content="' . esc_attr( trim( strip_tags( $description ) ) ) . '" />' . "\n";
						}
					}

					$url = get_pagenum_link( $paged );

					$meta_image_url = wp_get_attachment_image_src( get_post_thumbnail_id(), 'thumbnail');
					$image = $meta_image_url ? $meta_image_url[0] : false;

					$type = is_singular( 'page' ) ? 'page' : 'post';

					// OG
					if ( $open_graph ) {
						$post_title = $wp_query->get( 'qa_cat' ) && $wp_query->get( 'title' )
							? $wp_query->get( 'title' ) : $post->post_title;
						$seo       .= '<meta property="og:type" content="' . $type . '" />' . "\n";
						$seo       .= '<meta property="og:url" content="' . $url . '" />' . "\n";
						$seo       .= '<meta property="og:site_name" content="' . esc_attr( get_bloginfo( "name" ) ) . '" />' . "\n";
						$seo       .= '<meta property="og:title" content="' . esc_attr( $post_title ) . '" />' . "\n";
						if ( $image ) {
							$seo   .= '<meta property="og:image" content="' . esc_url( $image ) . '" />' . "\n";
						}
						if ( $description ) {
							$seo   .= '<meta property="og:description" content="' . esc_attr( trim( strip_tags( $description ) ) ) . '" />' . "\n";
						}
					}
				} else if ( is_home() || is_front_page() ) {
					# 首页 or 第一页
					global $page;
					if ( ! $page ) { $page = 1; }

					$keywords    = $this->options['leseo-selfindexkeywords'] ?? '';
					$description = $this->options['leseo-selfindexdesc'] ?? get_bloginfo( 'description' );
					$keywords    = str_replace( '，', ',', trim( strip_tags( $keywords ) ) );
					$description = trim( strip_tags( $description ) );

					if ( $keywords ) {
						$seo    .= '<meta name="keywords" content="' . esc_attr( $keywords ) . '" />' . "\n";
					}
					if ( $description ) {
						$seo    .= '<meta name="description" content="' . esc_attr( trim( strip_tags( $description ) ) ) . '" />' . "\n";
					}

					$url = get_pagenum_link( $page );

					$image = '';
					$title = $this->options['leseo-selfindextitle'] ?? '';

					if ( $title == '' ) {
						$desc = get_bloginfo( 'description' );
						if ( $desc ) {
							$title = get_option( 'blogname' ) . ( $this->options['leseo-linkmark'] ?? ' - ' ) . $desc;
						} else {
							$title = get_option( 'blogname' );
						}
					}

					if ( $open_graph ) {
						$seo .= '<meta property="og:type" content="webpage" />' . "\n";
						$seo .= '<meta property="og:url" content="' . $url . '" />' . "\n";
						$seo .= '<meta property="og:site_name" content="' . esc_attr( get_bloginfo( "name" ) ) . '" />' . "\n";
						$seo .= '<meta property="og:title" content="' . esc_attr( $title ) . '" />' . "\n";
						if ( $image ) {
							$seo .= '<meta property="og:image" content="' . esc_url( $image ) . '" />' . "\n";
						}
						if ( $description ) {
							$seo .= '<meta property="og:description" content="' . esc_attr( trim( strip_tags( $description ) ) ) . '" />' . "\n";
						}
					}
				} else if ( is_category() || is_tag() ) {  # || is_tax()
					global $paged, $cat;
					if ( ! $paged ) { $paged = 1; }

					# single_cat_title( '', false )  single_tag_title('', false) 两个函数等效

					if ( is_category() ) {
						$taxonomy_meta_options = get_term_meta( $cat, 'leseo_taxonomy_meta_options', true );  # 如果只需要分类, $cat 变量获取最简单
					}
							if ( is_tag() ) {
						$tag_name = single_tag_title( '', false );
						$term = $tag_name ? get_term_by( 'name', $tag_name, 'post_tag' ) : null;
						$taxonomy_meta_options = ( $term && ! is_wp_error( $term ) ) ? get_term_meta( $term->term_id, 'leseo_taxonomy_meta_options', true ) : array();
					}

					if ( is_array( $taxonomy_meta_options ) && isset($taxonomy_meta_options['leseo-taxonomy-meta-switcher'])
					     && $taxonomy_meta_options['leseo-taxonomy-meta-switcher'] ) {
						$keywords    = str_replace( '，', ',', trim( strip_tags( $taxonomy_meta_options['leseo-taxonomy-meta-keywords'] ?? $keywords ) ) );
						$description = trim( strip_tags( $taxonomy_meta_options['leseo-taxonomy-meta-description'] ?? $description ) );
						if ( $keywords ) {
							$seo .= '<meta name="keywords" content="' . esc_attr( $keywords ) . '" />' . "\n";
						}
						if ( $description ) {
							$seo .= '<meta name="description" content="' . esc_attr( trim( strip_tags( $description ) ) ) . '" />' . "\n";
						}
					}

					$url   = get_pagenum_link( $paged );
					$image = '';
					if ( $open_graph ) {
						$seo .= '<meta property="og:type" content="article" />' . "\n";
						$seo .= '<meta property="og:url" content="' . $url . '" />' . "\n";
						$seo .= '<meta property="og:site_name" content="' . esc_attr( get_bloginfo( "name" ) ) . '" />' . "\n";
						$seo .= '<meta property="og:title" content="' . esc_attr( single_cat_title( '', false ) ) . '" />' . "\n";
						if ( $image ) {
							$seo .= '<meta property="og:image" content="' . esc_url( $image ) . '" />' . "\n";
						}
						if ( $description ) {
							$seo .= '<meta property="og:description" content="' . esc_attr( trim( strip_tags( $description ) ) ) . '" />' . "\n";
						}
					}
				}
			}

			// 开启Canonical
			if ( isset( $this->options['leseo-canonical'] ) && $this->options['leseo-canonical'] && is_singular() ) {
				$id = get_queried_object_id();
				if ( 0 !== $id && $url = wp_get_canonical_url( $id ) ) {
					$seo .= '<link rel="canonical" href="' . esc_url( $url ) . '" />' . "\n";
				}
			}

			echo $seo;
		}

		public function leseo_document_title_separator ($sep) {
			return $this->options['leseo-linkmark'];
		}

		/**
		 * document_title 末尾过滤器：主题通过 document_title_parts 修改标题时，此回调可覆盖首页标题
		 */
		public function leseo_document_title_home_override( $title ) {
			$custom_title = trim( $this->options['leseo-selfindextitle'] ?? '' );
			if ( ( is_home() || is_front_page() ) && ! empty( $custom_title ) ) {
				return $custom_title;
			}
			return $title;
		}

		public function leseo_pre_get_document_title ( $title ) {
			global $paged, $page, $post;
			$custom_title = trim( $this->options['leseo-selfindextitle'] ?? '' );
			if ( ( is_home() || is_front_page() ) && ! empty( $custom_title ) ) {
				return $custom_title;
			}
			if ( is_singular() && $post->post_title) {
				if ( isset($this->options['leseo-pageandsitename']) && $this->options['leseo-pageandsitename'] )  {
					$title = $post->post_title;
				}

				$singular_meta_options = get_post_meta( $post->ID, 'leseo_singular_meta_options', true );  // 带true 少一层数组嵌套
				if ( isset( $singular_meta_options['leseo-singular-meta-switcher'] )
				     && $singular_meta_options['leseo-singular-meta-switcher']
				     && isset( $singular_meta_options['leseo-singular-meta-title']) ) {
					$title =  $singular_meta_options['leseo-singular-meta-title'];
				}
			}
			if ( is_category() || is_tag() ) {
				if ( ! $paged ) { $paged = 1; }

				$term = get_queried_object();
				if ( $term && ! is_wp_error( $term ) && isset( $term->term_id ) ) {
					$taxonomy_meta_options = get_term_meta( $term->term_id, 'leseo_taxonomy_meta_options', true );

					if ( isset($taxonomy_meta_options['leseo-taxonomy-meta-switcher'])
					     && $taxonomy_meta_options['leseo-taxonomy-meta-switcher']
					     && isset($taxonomy_meta_options['leseo-taxonomy-meta-title']) ) {

						if ( $paged >= 2 || $page >= 2 ) // 增加页数
						{
							$sep = $this->options['leseo-linkmark'] ?? ' - ';
							$taxonomy_meta_options['leseo-taxonomy-meta-title'] .= $sep . sprintf( __( 'Page %s', 'LeSEO' ), max( $paged, $page ) );
						}

						$title = $taxonomy_meta_options['leseo-taxonomy-meta-title'];
					}
				}
			}
			return $title;
		}


		// 页头页尾CSS代码插入（附加功能）


        // 静态分离
        /**
         * 文件上传功能基础函数，被其它需要进行文件上传的模块调用
         * @param $key  : 远端需要的Key值[包含路径]
         * @param $file_local_path : 文件在本地的路径。
         *
         * @return bool  : 暂未想好如何与wp进行响应。

         */
        public function _file_upload($key, $file_local_path) {
            ### 上传文件
            # 由于增加了独立文件名钩子对cos中同名文件的判断，避免同名文件的存在，因此这里直接覆盖上传。
            try {
                $this->s3_object->Upload(
                    $this->key_handler($key, get_option('upload_url_path')),
                    $file_local_path
                );
                // 如果上传成功，且不再本地保存，在此删除本地文件
                if ($this->options['leseo-s3-local-file']) {
                    $this->delete_local_file($file_local_path);
                }
                return True;
            } catch (\Exception $e) {
                return False;
            }
        }

        private function remote_key_exist( $filename ) {
            return $this->s3_object->hasExist( $this->key_handler($this->wp_upload_dir['subdir'] . "/$filename",
                get_option('upload_url_path')));
        }

        /**
         * 删除远程附件（包括图片的原图）
         *   这里全部以非/开头，因此上传的函数中也要替换掉key中开头的/
         * @param $post_id
         */
        public function leseo_s3_delete_remote_attachment($post_id) {
            // 获取要删除的对象Key的数组
            $deleteObjects = array();
            $meta = wp_get_attachment_metadata( $post_id );
            $upload_url_path = get_option('upload_url_path');

            if (isset($meta['file'])) {
                $attachment_key = $meta['file'];
                array_push($deleteObjects, $this->key_handler($attachment_key, $upload_url_path));
            } else {
                $file = get_attached_file( $post_id );
                $attached_key = str_replace( $this->wp_upload_dir['basedir'] . '/', '', $file );  # 不能以/开头
                $deleteObjects[] = $this->key_handler($attached_key, $upload_url_path);
            }

            if (isset($meta['sizes']) && count($meta['sizes']) > 0) {
                foreach ($meta['sizes'] as $val) {
                    $attachment_thumbs_key = dirname($meta['file']) . '/' . $val['file'];
                    $deleteObjects[] = $this->key_handler($attachment_thumbs_key, $upload_url_path);
                }
            }

            if ( !empty( $deleteObjects ) ) {
                // 执行删除远程对象
                $allKeys = array_chunk($deleteObjects, 1000);  # 每次最多删除1000个，多于1000循环进行
                foreach ($allKeys as $keys){
                    //删除文件, 每个数组1000个元素
                    $this->s3_object->Delete($keys);
                }
            }
        }


        /**
         * 此函数处理上传的key，用于支持 对象存储子目录
         * @param $key
         * @param $upload_url_path
         * @return string
         */
        private function key_handler($key, $upload_url_path){
            # 参数2 为了减少option的获取次数
            $url_parse = is_string($upload_url_path) && $upload_url_path !== '' ? wp_parse_url($upload_url_path) : false;
            # 约定url不要以/结尾，减少判断条件；空或无效时仅用 key 本身（避免 parse_url('') 导致警告）
            if ( is_array($url_parse) && array_key_exists('path', $url_parse) && $url_parse['path'] !== '' ) {
                $path = trim( $url_parse['path'], '/' );
                $bucket = isset( $this->options['leseo-s3-bucket'] ) ? trim( (string) $this->options['leseo-s3-bucket'] ) : '';
                // path-style 时 upload_url_path 为 endpoint/bucket，path 即 bucket，不应作为对象 key 前缀（请求路径已含 bucket）
                if ( $path !== '' && $path !== $bucket ) {
                    if ( substr($key, 0, 1) == '/' ) {
                        $key = $url_parse['path'] . $key;
                    } else {
                        $key = $url_parse['path'] . '/' . $key;
                    }
                }
            }
            # $url_parse['path'] 以/开头，在七牛环境下不能以/开头，所以需要处理掉
            return ltrim($key, '/');
        }

        /**
         * 删除本地文件
         * @param $file_path : 文件路径
         * @return bool
         */
        public function delete_local_file($file_path): bool
        {
            try {
                if (!@file_exists($file_path)) {  # 文件不存在
                    return TRUE;
                }
                if (!@unlink($file_path)) { # 删除文件
                    return FALSE;
                }
                return TRUE;
            } catch (Exception $ex) {
                return FALSE;
            }
        }

        /**
         * 上传图片及缩略图
         * @param $metadata: 附件元数据
         * @return array $metadata: 附件元数据
         * 官方的钩子文档上写了可以添加 $attachment_id 参数，但实际测试过程中部分wp接收到不存在的参数时会报错，上传失败，返回报错为“HTTP错误”
         */
        public function leseo_s3_upload_and_thumbs( $metadata ): array
        {
            if (isset( $metadata['file'] )) {
                # 1.先上传主图
                $attachment_key = $metadata['file'];  // 远程key路径, 此路径不是以/开头
                $attachment_local_path = $this->wp_upload_dir['basedir'] . '/' . $attachment_key;  # 在本地的存储路径
                $this->_file_upload($attachment_key, $attachment_local_path);  # 调用上传函数
            }

            # 如果存在缩略图则上传缩略图
            if (isset($metadata['sizes']) && count($metadata['sizes']) > 0) {
                foreach ($metadata['sizes'] as $val) {
                    $attachment_thumbs_key = dirname($metadata['file']) . '/' . $val['file'];  // 生成object 的 key
                    $attachment_thumbs_local_path = $this->wp_upload_dir['basedir'] . '/' . $attachment_thumbs_key;  // 本地存储路径
                    $this->_file_upload($attachment_thumbs_key, $attachment_thumbs_local_path);  //调用上传函数
                }
            }

            return $metadata;
        }

        /**
         * @param array  $upload {
         *     Array of upload data.
         *
         *     @type string $file Filename of the newly-uploaded file.
         *     @type string $url  URL of the uploaded file.
         *     @type string $type File type.
         * @return array  $upload
         */
        public function leseo_s3_upload_attachments ($upload) {
            $mime_types       = get_allowed_mime_types();
            $image_mime_types = array(
                // Image formats.
                $mime_types['jpg|jpeg|jpe'],
                $mime_types['gif'],
                $mime_types['png'],
                $mime_types['bmp'],
                $mime_types['tiff|tif'],
                $mime_types['ico'],
            );
            if ( ! in_array( $upload['type'], $image_mime_types ) ) {
                $key        = str_replace( $this->wp_upload_dir['basedir'] . '/', '', $upload['file'] );
                $local_path = $upload['file'];
                $this->_file_upload( $key, $local_path);
            }

            return $upload;
        }

        public function leseo_s3_save_image_editor_file($override){
            add_filter( 'wp_update_attachment_metadata', array($this,'image_editor_file_save' ));
            return $override;
        }

        public function image_editor_file_save( $metadata ): array
        {
            $metadata = $this->leseo_s3_upload_and_thumbs($metadata);
            remove_filter( 'wp_update_attachment_metadata', array($this, 'image_editor_file_save') );
            return $metadata;
        }

        /**
         * Filters the result when generating a unique file name.
         *
         * @param string        $filename Unique file name.
 * @return string New filename, if given wasn't unique
         *
         * 参数 $ext 在官方钩子文档中可以使用，部分 WP 版本因为多了这个参数就会报错。 返回“HTTP错误”
         *@since 4.5.0
         *
         */
        public function leseo_s3_unique_filename( string $filename): string
        {
            $ext = '.' . pathinfo( $filename, PATHINFO_EXTENSION );
            $number = '';

            while ( $this->remote_key_exist( $filename ) ) {
                $new_number = (int) $number + 1;
                if ( '' == "$number$ext" ) {
                    $filename = "$filename-" . $new_number;
                } else {
                    $filename = str_replace( array( "-$number$ext", "$number$ext" ), '-' . $new_number . $ext, $filename );
                }
                $number = $new_number;
            }
            return $filename;
        }


        // S3功能注入（保存时同步 upload_url_path，供 WP 与 key 前缀使用）
        public function leseo_s3_switch_csf_filter( $params ) {
            if ( ! empty($params['leseo-s3-switch']) ) {
                $backup = $this->options['leseo-s3-backup_url_path'] ?? $this->options['lseso-s3-backup_url_path'] ?? get_option('upload_url_path');
                $params['leseo-s3-backup_url_path'] = $backup;

                if ( ! empty( trim( (string) ( $params['leseo-s3-domain'] ?? '' ) ) ) ) {
                    update_option('upload_url_path', trim( $params['leseo-s3-domain'] ));
                } else {
                    // 1.2.6 自定义域名可为空：为空时用虚拟主机风格构造默认地址（各 S3 兼容厂商均支持）
                    $endpoint = isset($params['leseo-s3-endpoint']) ? trim( (string) $params['leseo-s3-endpoint'] ) : '';
                    $bucket   = isset($params['leseo-s3-bucket']) ? trim( (string) $params['leseo-s3-bucket'] ) : '';
                    if ( $endpoint !== '' && $bucket !== '' ) {
                        $parsed = wp_parse_url( $endpoint );
                        if ( empty( $parsed['scheme'] ) ) {
                            $endpoint = 'https://' . ltrim( $endpoint, '/' );
                            $parsed  = wp_parse_url( $endpoint );
                        }
                        if ( ! empty( $parsed['host'] ) ) {
                            $scheme      = ( isset( $parsed['scheme'] ) && $parsed['scheme'] ) ? $parsed['scheme'] : 'https';
                            $default_url = $scheme . '://' . $bucket . '.' . $parsed['host'];
                            update_option('upload_url_path', $default_url);
                        }
                    }
                }
            } else {
                $backup = $this->options['leseo-s3-backup_url_path'] ?? $this->options['lseso-s3-backup_url_path'] ?? null;
                if ( $backup ) {
                    update_option('upload_url_path', $backup);
                }
            }
            return $params;
        }


	}

	global $LESEO;
	$LESEO = new LESEO();
}
