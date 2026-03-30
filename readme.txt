=== Simple GEO LLMS Generator ===
Contributors: SatoMini
Tags: llms, seo, geo, ai-crawler, generator
Requires at least: 6.0
Tested up to: 6.7
Requires PHP: 7.4
Stable tag: 1.0.0
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

Automates llms.txt and llms-full.txt generation for AI crawlers while monitoring critical GEO/SEO health signals.

== Description ==

Simple GEO LLMS Generator keeps `llms.txt` and `llms-full.txt` fresh and validates crawl-critical GEO signals.

= How it works =

`llms.txt` is a plain-text index file that helps search engines and AI crawlers discover and navigate your site content. This plugin generates and maintains it automatically.

= Features =

* Generates `llms.txt` with up to 36 articles per content type, 5 categories, and 24 pages
* Generates `llms-full.txt` with up to 90 articles per content type, 10 categories, and 36 pages
* Scans key endpoints: robots.txt, sitemap.xml, llms.txt, llms-full.txt
* Scans key GEO/SEO signals: homepage H1, link rel="llms", canonical tag, OG tags
* Outputs `<link rel="llms">` in site header (optional)
* Bilingual support: auto-switches between Chinese and English based on site language

= GEO/SEO Health Scan =

The plugin checks:

* **Endpoints** - Verifies robots.txt, sitemap.xml, llms.txt, and llms-full.txt are accessible
* **Homepage H1** - Confirms the homepage has a properly set H1 tag
* **LLMS Link** - Checks if `<link rel="llms">` is present in the HTML
* **Canonical** - Checks for canonical tag presence
* **OG Tags** - Validates Open Graph meta tags for social sharing

== Installation ==

1. Upload the plugin folder to `/wp-content/plugins/`, or install via the WordPress plugins screen
2. Activate the plugin through the 'Plugins' screen in WordPress
3. Go to Settings -> Simple GEO LLMS
4. Click "Regenerate LLMS Files" to generate your initial llms.txt
5. Click "Run Scan" to perform a GEO/SEO health check

== Frequently Asked Questions ==

= Does this plugin require an external service? =

No. The plugin works entirely within WordPress.

= Does the plugin replace a full SEO plugin? =

No. It focuses on llms.txt generation and GEO/SEO health scanning only.

= Can I customize the output limits? =

Yes. In `simple-geo-llms-generator.php`, find the "Customization Zone" inside `regenerate_files()` and adjust the `$limit_short_*` and `$limit_full_*` variables.

= How does language switching work? =

The plugin follows your WordPress site language setting. If your admin is set to English, all UI text and llms.txt structure labels will appear in English. For Chinese, set the site language to Chinese.

== Changelog ==

= 1.0.0 =
* Initial release

== Upgrade Notice ==

= 1.0.0 =
Initial release.

---

== 描述 ==

Simple GEO LLMS Generator 保持 `llms.txt` 和 `llms-full.txt` 的新鲜度，并验证抓取关键的 GEO 信号。

= 工作原理 =

`llms.txt` 是一个纯文本索引文件，帮助搜索引擎和 AI 爬虫发现和导航您的网站内容。本插件自动生成和维护它。

= 功能 =

* 生成 `llms.txt`，每种内容类型最多 36 篇文章、5 个分类、24 个页面
* 生成 `llms-full.txt`，每种内容类型最多 90 篇文章、10 个分类、36 个页面
* 扫描关键端点：robots.txt、sitemap.xml、llms.txt、llms-full.txt
* 扫描关键 GEO/SEO 信号：首页 H1、link rel="llms"、canonical 标签、OG 标签
* 在网站头部输出 `<link rel="llms">`（可选）
* 双语支持：根据站点语言自动切换中文和英文

= GEO/SEO 健康扫描 =

插件检查以下内容：

* **端点** - 验证 robots.txt、sitemap.xml、llms.txt 和 llms-full.txt 是否可访问
* **首页 H1** - 确认首页正确设置了 H1 标签
* **LLMS Link** - 检查 HTML 中是否存在 `<link rel="llms">`
* **Canonical** - 检查 canonical 标签是否存在
* **OG 标签** - 验证 Open Graph 元标签以便社交分享

= 安装 =

1. 将插件文件夹上传到 `/wp-content/plugins/`，或通过 WordPress 插件页面安装
2. 在 WordPress 的"插件"页面激活插件
3. 进入 设置 -> Simple GEO LLMS
4. 点击"重建 LLMS 文件"生成初始的 llms.txt
5. 点击"运行扫描"执行 GEO/SEO 健康检查

= 常见问题 =

= 此插件需要外部服务吗？=

不需要。插件完全在 WordPress 内部工作。

= 插件可以替代完整的 SEO 插件吗？=

不能。它仅专注于 llms.txt 生成和 GEO/SEO 健康扫描。

= 可以自定义输出限制吗？=

可以。在 `simple-geo-llms-generator.php` 中，找到 `regenerate_files()` 内的"自定义修改区域"，调整 `$limit_short_*` 和 `$limit_full_*` 变量。

= 语言切换如何工作？=

插件遵循您的 WordPress 站点语言设置。如果您的后台设置为英文，所有 UI 文本和 llms.txt 结构标签将以英文显示。中文同理。
