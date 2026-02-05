# LeSeo - 一个轻量的WordPress SEO插件

一款简单、实用且有温度的 WordPress 性能优化与 SEO 插件。

[![WordPress](https://img.shields.io/badge/WordPress-5.9.1%2B-blue.svg)](https://wordpress.org/)
[![PHP](https://img.shields.io/badge/PHP-7.0%2B-purple.svg)](https://php.net/)
[![License](https://img.shields.io/badge/License-GPLv2-green.svg)](http://www.gnu.org/licenses/gpl-2.0.html)

---

## 简介

LeSeo 是一个比较全面、免费的 WordPress SEO 插件，集成了性能优化、内容优化、搜索推送、静态分离等多项实用功能，帮助站长轻松提升网站速度与搜索引擎表现。

**作者：** 老蒋和他的伙伴们  
**公众号：** 老蒋朋友圈

---

## 主要功能

### 一、WordPress 性能优化

- **禁用古登堡编辑器** - 恢复经典编辑器体验
- **禁止文章自动保存** - 减少数据库写入
- **禁止自动升级** - 手动控制 WordPress 版本更新
- **禁止 RSS 订阅** - 防止被 RSS 阅读器采集
- **禁止字符转码** - 保留原始文本格式
- **禁止 JSON/REST API** - 减少爬虫抓取
- **禁止小工具样式** - 恢复原始小工具
- **禁止 XML-RPC** - 降低被攻击风险
- **禁止离线编辑端口** - 防止外部推送文章
- **禁止 EMOJI 表情** - 减少站内体积

### 二、WordPress 加速优化

- **上传图片重命名** - 按时间戳重命名，避免中文文件名
- **禁止裁剪大图** - 禁止 2560px 以上图片自动裁剪
- **禁止垃圾评论** - 评论需含中文，过滤日文
- **禁止生成缩略图** - 减少服务器资源占用
- **压缩 HTML** - 压缩前端 HTML 代码
- **精简头部代码** - 移除不必要的 head 标签
- **移除 CSS/JS 版本号** - 精简资源加载
- **禁止前端搜索** - 可改用站外搜索接口

### 三、WordPress 内容优化

- **图片自动本地化** - 复制外部图片粘贴时自动下载到本地
- **移除图片 srcset/size** - 移除响应式图片标签
- **解除图片宽高限制** - 灵活控制图片显示
- **移除 wp-block-library-css** - 提高加载速度
- **禁止复制和右键** - 保护内容不被复制

### 四、WordPress 搜索推送

- **百度普通收录** - 一键提交链接至百度
- **百度快速收录** - 支持快速收录 API
- **文章发布时推送** - 编辑文章时可勾选推送选项
- **手动批量提交** - 支持批量提交链接

### 五、WordPress 附加功能

- **自定义头部代码** - 在 `wp_head` 插入自定义 JS/HTML
- **自定义底部代码** - 在 `wp_footer` 插入自定义代码
- **自定义 CSS** - 添加全站自定义样式
- **robots.txt 自定义** - 自定义 robots.txt 内容

### 六、WordPress 静态分离

支持将媒体文件上传至对象存储，实现静态资源分离：

- 阿里云 OSS
- 腾讯云 COS
- 七牛云
- 又拍云
- 亚马逊云 S3
- CloudFlare R2

---

## SEO 功能

- **TDK 自定义** - 首页、分类、标签、文章页独立 SEO 设置
- **Open Graph** - 支持社交分享预览
- **Canonical 链接** - 避免重复内容
- **页面反斜杠** - URL 规范化
- **隐藏分类 Category** - 缩短 URL 长度
- **图片自动 ALT** - 自动为图片添加 alt 和 title
- **自动 TAG 内链** - 文章内自动添加标签链接
- **网站地图** - 自定义 sitemap 支持

---

## 系统要求

| 项目 | 要求 |
|------|------|
| WordPress | 5.9.1 及以上 |
| PHP | 7.0 及以上 |
| 测试版本 | WordPress 6.8.1 |

---

## 安装方法

1. 将 `leseo` 文件夹上传至 `/wp-content/plugins/` 目录
2. 在 WordPress 后台 **插件** 列表中激活 LeSeo
3. 进入 **LeSeo 设置** 配置插件参数
4. 详细设置教程：[乐在云 - LeSeo 插件设置介绍](https://www.lezaiyun.com/817.html)

---

## 目录结构

```
leseo/
├── leseo.php              # 主插件文件
├── leseo-admin-options.php # 后台配置选项
├── uninstall.php          # 卸载脚本
├── readme.txt             # WordPress 官方 readme
├── screenshot.png         # 插件截图
├── inc/
│   ├── baidu-submit/      # 百度推送 API
│   ├── awss3/             # 对象存储 API（S3 兼容）
│   ├── cache/             # 缓存模块
│   └── codestar-framework/# CSF 配置框架
└── static/
    └── js/                # 前端脚本
```

---

## 注意事项

- 首次使用前建议**备份网站**，确保错误设置不会导致网站故障
- 如遇插件异常，可开启 WordPress 调试模式获取错误信息
- 静态分离功能需正确配置对象存储参数，Region 请使用英文标识

---

## 更新日志

### 1.2.4
- 兼容 WordPress 6.8.1 测试
- 修改文档

### 1.2.3
- 替换 cdn.jsdelivr.net 为 bootcdn 镜像，提高加载速度

### 1.2.2
- 清理框架多余文件

### 1.2.0
- 修复「静态分离」Region 中文字符导致网站崩溃

### 1.1.1
- 修复「静态分离」开启后留空导致插件崩溃

### 1.1.0
- 升级 Codestar Framework 支持 PHP 8+
- 新增静态分离功能，支持主流对象存储

---

## 插件团队和技术支持

[乐在云](https://www.lezaiyun.com/)（老蒋和他的伙伴们），本着资源共享原则，在运营网站过程中用到的或者是有需要用到的主题、插件资源，有选择的免费分享给广大的网友站长，希望能够帮助到你建站过程中提高效率。

感谢团队成员，以及网友提出的优化工具的建议，才有后续产品的不断迭代适合且满足用户需要。不能确保100%的符合兼容网站，我们也仅能做到在工作之余不断的接近和满足你的需要。

| 类目             | 信息                                                         |
| ---------------- | ------------------------------------------------------------ |
| 插件更新地址     | https://www.lezaiyun.com/817.html                            |
| 团队成员         | [老蒋](https://www.laojiang.me/)、老赵、[CNJOEL](https://www.rakvps.com/)、木村 |
| 支持网站         | 乐在云、主机评价网、老蒋玩主机                               |
| 建站资源推荐     | [便宜VPS推荐](https://www.zhujipingjia.com/pianyivps.html)、[美国VPS推荐](https://www.zhujipingjia.com/uscn2gia.html)、[外贸建站主机](https://www.zhujipingjia.com/wordpress-hosting.html)、[SSL证书推荐](https://www.zhujipingjia.com/two-ssls.html)、[WordPress主机推荐](https://www.zhujipingjia.com/wpblog-host.html) |
| 提交WP官网（是） | https://cn.wordpress.org/plugins/leseo/                      |

---

## 致谢

- 部分代码参考自网上教程和热心网友分享
- 部分功能根据站长实际需求开发
- 基于 [Codestar Framework](https://codestarframework.com/) 构建后台界面

---

## 许可证

GPLv2 或更高版本 - [查看完整许可证](http://www.gnu.org/licenses/gpl-2.0.html)

![](wechat.png)
