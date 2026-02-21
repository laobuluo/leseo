=== LeSeo ===

Contributors: laobuluo
Donate link: https://www.lezaiyun.com/donate/
Tags:WordPress SEO
Requires at least: 5.9.1
Tested up to: 6.9.1
Stable tag: 1.2.10
Requires PHP: 7.0
License: GPLv2 or later
License URI: http://www.gnu.org/licenses/gpl-2.0.html

LeSeo，一款简单、实用且有温度的WordPress性能优化插件。

== Description ==

LeSeo，一款简单、实用且有温度的WordPress性能优化插件。公众号：老蒋朋友圈。

## 主要功能

* WordPress性能优化
* WordPress加速优化
* WordPress内容优化
* WordPress搜索推送
* WordPress附加功能
* WordPress静态分离

## 网站支持

* [老蒋博客](https://www.laojiang.me/ "老蒋博客")

* [主机评价网](https://www.zhujipingjia.com/ "主机评价网")

* 欢迎加入插件和站长微信公众号：老蒋朋友圈（公众号）

## 感谢支持

* 插件的部分代码参考来自网上文章教程和热心网友分享的功能实现代码
* 插件的部分功能参考网友需要实现网站功能

== Installation ==

* 1、把文件夹上传到/wp-content/plugins/目录下<br />
* 2、在后台插件列表中激活<br />
* 3、设置插件参数<br />
* 4、插件设置介绍：https://www.lezaiyun.com/817.html

== Frequently Asked Questions ==

* 当发现插件出错时，开启调试获取错误信息
* 如果不熟悉使用这类插件的用户，一定要先备份，确保错误设置导致网站故障。

== Screenshots ==

1. screenshot.png

== Changelog ==

= 1.2.10 =
* 修复静态分离配置后无法上传图片/静态文件到对象存储的问题：自定义域名为空时未写入 upload_url_path，导致 WP 仍用本地地址且 key 前缀异常；现改为在保存时若自定义域名为空则用 EndPoint + Bucket 自动构造默认访问地址并写入。
* 修复 key_handler 在 upload_url_path 为空或无效时的 PHP 警告（parse_url 结果非数组时的安全判断）。
* 静态分离仅当 S3 客户端初始化成功（isReady）时再注册上传/删除钩子，避免 Region 异常等导致客户端为 null 时上传报错；S3 Api 增加 isReady() 及 Upload/Delete/hasExist 的空客户端防护。
* 修复上传到对象存储仍失败（无法创建目录/上传文件）：移除 putObject 的 ACL 参数，避免云厂商禁用对象 ACL 导致 403；自定义 EndPoint 时启用 path_style 并统一默认地址为 endpoint/bucket，key_handler 在 path 仅为 bucket 名时不作为 key 前缀；上传前检查本地文件可读并写入 error.log 便于排查。

= 1.2.9 =
* 新增站外链接优化：支持正常模式 / ?goto=BASE64(url) / ?goto=URL 中转模式，可选新窗口、nofollow、白名单域名，以及可配置自动或手动跳转的中间过渡页面。
* 优化和完善图片本地化功能采用手工本地化模式

= 1.2.8 =
* 新增 TinyPNG 图片压缩：在功能优化中接入 TinyPNG API，上传图片自动压缩（每月免费 500 张，可自填 API Key）。
* TinyPNG 压缩逻辑独立为 inc/leseo-tinypng.php，便于后续维护与扩展。

= 1.2.7 =
* 修复自定义分页符导致出现 /laojiang/2/page/2/ 这类重复分页路径的问题。
* 完善标签 URL 更改功能，支持 /tag/ID/ 形式，避免仅重写规则生效但链接仍为 slug 的情况。
* 修复缓存命名空间、S3 备份路径、禁用复制脚本等多处兼容与细节问题。
* 新增图片上传自动转换 WebP 开关（需服务器 GD 支持 WebP）。
* 新增「手动推送」「百度收录查询」后台页面，支持批量推送和收录查询入口。
* 新增定时批量百度推送、LeCache tmp 目录自动创建等完善项。

= 1.2.6 =
* 修复静态分离激活后未开启时仍显示红色校验提示的问题（改为仅开启时校验必填项）
* 修复与其它使用 AWS S3 SDK 插件的冲突（优先复用已加载的 SDK）
* 自定义域名支持空值，仅非空时校验 URL 格式
* 新增自定义分页page符和TAG ID URL 功能
* 新增：前台顶部管理菜单、屏蔽Trackbacks/Pingback、移除dns-prefetch、移除Dashicons、移除RSD 开关

= 1.2.5 =
* 修复未定义函数 err() 和 show_message() 导致的潜在错误，改用 wp_die() 和 WP_Error 处理
* 新增 bs_cron_event 回调方法，避免定时任务触发时报错
* 修复百度推送 Meta Box 使用 esc_html 导致表单控件无法正确渲染的问题
* 修复 SEO TDK 中 $options 变量错误，改为 $this->options
* 修复停用插件时 $this->options 未校验可能导致的 PHP Notice
* 修复附件类型拼写错误 attachement -> attachment
* 修复自定义头部/底部代码使用 sanitize_text_field 导致 script/style 标签被移除的问题
* 修复 leseo_image_alt_tag、leseo_save_images_in_post、分类标签页等多处空值未校验
* 修复 LeCache 缓存文件为空时 json_decode 返回 null 导致的错误
* 修复百度 API 错误信息未定义时的数组越界问题
* 百度推送 API 改为 HTTPS 协议
* 修复 robots.txt 路径使用 $_SERVER['DOCUMENT_ROOT']，改用 ABSPATH
* 修复 disable-copy.js 中废弃的 event.srcElement 及 PASSWORD 标签判断错误
* 修正 TAG 内链随机替换次数参数（match_num_from/to）
* 卸载插件时恢复 upload_url_path（静态分离功能）

= 1.2.4 =
* 兼容WP6.8.1测试，且修改文档

= 1.2.3 =
* 替换cdn.jsdelivr.net镜像为bootcdn镜像，提高文件加载速度

= 1.2.2 =
* 清理框架多余的文件。

= 1.2.0 =
* 修复「静态分离」Region中文字符导致网站崩溃的问题。

= 1.1.1 =
* 修复「静态分离」开启后留空导致插件崩溃的问题。

= 1.1.0 =
* 升级最新Codestar Framework框架支持PHP8+
* 增加静态分离功能，支持阿里云OSS、腾讯云COS、七牛云、又拍云、亚马逊云S3、CloudFlare R2 等主流对象存储。（一个表单全部兼容）

= 0.5.2 =
* 兼容WordPress6.5.3测试
* 修正自动Alt和Title图片标签功能

= 0.5.1 =
* 新增复制图片自动保存本地
* 新增自定义头部、底部和CSS代码
* 新增防止复制和右键
* 新增移除编辑器样式
* 新增解除图片限制高度
* 新增移除响应图片尺寸

= 0.4.2 =
* 解决SEO自定义首页头部META过滤问题

= 0.4 =
* 解决提交WP应用平台部分错误
* 调试兼容WordPress6.2.2

= 0.3 =
* 解决提交WP云库CSF框架脚本版本低被退回问题
* 调试兼容WordPress6.2

= 0.2 =
* 解决SEO标题自定义错误
* 解决百度提交链接问题

= 0.1 =
* 插件前端从Layui改用CSF框架
* 完成基本前端布局

== Upgrade Notice ==
* 