<?php
/**
 * Plugin Name: Simple GEO LLMS Generator
 * Description: Generate llms.txt and llms-full.txt, scan GEO/SEO health signals.
 * Version: 1.0.0
 * Requires at least: 6.0
 * Tested up to: 6.7
 * Requires PHP: 7.4
 * Author: SatoMini
 * Author URI: https://warpnav.com/
 * License: GPL v2 or later
 * License URI: https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain: simple-geo-llms-generator
 * Domain Path: /languages
 */

if (!defined('ABSPATH')) {
    exit;
}

final class GEO_LLMS_Generator {
    const VERSION = '1.0.0';
    const ADMIN_SLUG = 'simple-geo-llms';
    const OPT_KEY = 'simple_geo_llms_last_result';
    const SCAN_KEY = 'simple_geo_llms_last_scan';
    const SETTINGS_KEY = 'simple_geo_llms_settings';

    public static function init() {
        add_action('init', array(__CLASS__, 'load_textdomain'));
        add_action('admin_menu', array(__CLASS__, 'register_admin_page'));
        add_action('admin_post_simple_geo_llms_regenerate', array(__CLASS__, 'handle_regenerate'));
        add_action('admin_post_simple_geo_llms_run_scan', array(__CLASS__, 'handle_run_scan'));
        add_action('admin_post_simple_geo_llms_save_settings', array(__CLASS__, 'handle_save_settings'));
        add_action('wp_head', array(__CLASS__, 'output_llms_link'));
    }

    public static function on_activate() {
        if (!get_option(self::SETTINGS_KEY)) {
            update_option(self::SETTINGS_KEY, self::get_default_settings(), false);
        }
    }

    public static function on_deactivate() {
    }

    public static function load_textdomain() {
        load_plugin_textdomain('simple-geo-llms-generator', false, dirname(plugin_basename(__FILE__)) . '/languages');
    }

    public static function regenerate_files($generate_full = false) {
        $site_name = trim(get_bloginfo('name')) ?: __('我的网站', 'simple-geo-llms-generator');
        $site_desc = trim(get_bloginfo('description')) ?: __('网站内容索引与导航', 'simple-geo-llms-generator');
        $site_url = home_url('/');
        $locale = get_locale();

        $settings = self::get_settings();
        $post_types = $settings['post_types'] ?: array('post');

        // === CUSTOMIZATION ZONE - START ===
        // llms.txt limits
        $limit_short_articles = 36;   // max articles per content type
        $limit_short_pages = 24;      // max pages
        $limit_short_terms = 5;       // max categories
        // llms-full.txt limits
        $limit_full_articles = 90;    // max articles per content type
        $limit_full_pages = 36;       // max pages
        $limit_full_terms = 10;       // max categories
        // === CUSTOMIZATION ZONE - END ===

        $short_data = self::collect_data_for_llms($post_types, $limit_short_articles, $limit_short_pages, $limit_short_terms);
        $llms_short = self::build_llms($site_name, $site_desc, $site_url, $locale, $short_data, 'short');
        $ok_a = self::write_file('llms.txt', $llms_short);

        $llms_full_bytes = 0;
        $ok_b = true;
        if ($generate_full) {
            $full_data = self::collect_data_for_llms($post_types, $limit_full_articles, $limit_full_pages, $limit_full_terms);
            $llms_full = self::build_llms($site_name, $site_desc, $site_url, $locale, $full_data, 'full');
            $ok_b = self::write_file('llms-full.txt', $llms_full);
            $llms_full_bytes = strlen($llms_full);
        }

        update_option(self::OPT_KEY, array(
            'time' => current_time('mysql'),
            'ok' => ($ok_a && $ok_b),
            'llms_bytes' => strlen($llms_short),
            'llms_full_bytes' => $llms_full_bytes,
        ), false);
    }

    private static function collect_data_for_llms(array $post_types, $article_limit, $page_limit, $term_limit) {
        $data = array(
            'post_types' => array(),
            'pages' => array(),
        );

        foreach ($post_types as $pt) {
            if (!post_type_exists($pt)) continue;
            $pt_obj = get_post_type_object($pt);
            if (!$pt_obj || !$pt_obj->public) continue;

            $taxonomies = get_object_taxonomies($pt, 'objects');
            $term_groups = array();

            foreach ($taxonomies as $tax) {
                if (!$tax->hierarchical || !$tax->show_admin_column) continue;
                $terms = get_terms(array(
                    'taxonomy' => $tax->name,
                    'hide_empty' => true,
                    'number' => 100,
                ));
                foreach ((array) $terms as $term) {
                    $term_groups[$term->term_id] = array(
                        'term_id' => $term->term_id,
                        'name' => $term->name,
                        'slug' => $term->slug,
                        'taxonomy' => $tax->name,
                        'url' => get_term_link($term),
                        'count' => $term->count,
                    );
                }
            }

            usort($term_groups, function($a, $b) {
                return $b['count'] - $a['count'];
            });
            $term_groups = array_slice($term_groups, 0, $term_limit);

            foreach ($term_groups as $term_id => &$term) {
                $q = new WP_Query(array(
                    'post_type' => $pt,
                    'post_status' => 'publish',
                    'posts_per_page' => $article_limit,
                    'orderby' => 'date',
                    'order' => 'DESC',
                    'ignore_sticky_posts' => true,
                    'no_found_rows' => true,
                    'tax_query' => array(array(
                        'taxonomy' => $term['taxonomy'],
                        'field' => 'term_id',
                        'terms' => $term_id,
                    )),
                ));
                $term_posts = array();
                foreach ($q->posts as $post) {
                    if (!$post instanceof WP_Post) continue;
                    $url = get_permalink($post);
                    if (!$url) continue;
                    $title = get_the_title($post);
                    if (!$title || $title === 'Auto Draft') continue;
                    $excerpt = self::get_post_description($post);
                    $term_posts[] = array(
                        'title' => $title,
                        'url' => $url,
                        'desc' => self::clean_text($excerpt, 80),
                        'date' => get_the_date('Y-m-d', $post),
                        'category' => $term['name'],
                    );
                }
                wp_reset_postdata();
                $term['posts'] = $term_posts;
                $term['url'] = get_term_link($term['slug'], $term['taxonomy']);
            }
            unset($term);

            $all_posts = array();
            $q = new WP_Query(array(
                'post_type' => $pt,
                'post_status' => 'publish',
                'posts_per_page' => $article_limit,
                'orderby' => 'date',
                'order' => 'DESC',
                'ignore_sticky_posts' => true,
                'no_found_rows' => true,
            ));
            foreach ($q->posts as $post) {
                if (!$post instanceof WP_Post) continue;
                $url = get_permalink($post);
                if (!$url) continue;
                $title = get_the_title($post);
                if (!$title || $title === 'Auto Draft') continue;
                $excerpt = self::get_post_description($post);
                $categories = get_the_terms($post, $taxonomies ? reset($taxonomies)->name : 'category');
                $cat_name = ($pt === 'post' && !empty($categories) && !is_wp_error($categories)) ? $categories[0]->name : '';
                $all_posts[] = array(
                    'title' => $title,
                    'url' => $url,
                    'desc' => self::clean_text($excerpt, 80),
                    'date' => get_the_date('Y-m-d', $post),
                    'category' => $cat_name,
                );
            }
            wp_reset_postdata();

            $data['post_types'][$pt] = array(
                'name' => $pt_obj->labels->singular_name ?: $pt,
                'terms' => array_values($term_groups),
                'latest' => $all_posts,
            );
        }

        $data['pages'] = self::get_pages_for_llms($page_limit);

        return $data;
    }

    private static function get_pages_for_llms($limit) {
        $items = array();
        $home = home_url('/');
        $items[] = array('title' => __('首页', 'simple-geo-llms-generator'), 'url' => $home, 'desc' => __('网站入口与导航', 'simple-geo-llms-generator'), 'date' => '');
        $pages = get_pages(array('sort_column' => 'menu_order,post_title', 'number' => $limit, 'post_status' => 'publish'));
        foreach ($pages as $page) {
            if (!$page instanceof WP_Post) continue;
            $url = get_permalink($page);
            if (!$url || $url === $home) continue;
            $items[] = array('title' => get_the_title($page), 'url' => $url, 'desc' => self::clean_text(self::get_page_meta_description($url), 64), 'date' => '');
            if (count($items) >= $limit) break;
        }
        return $items;
    }

    private static function get_page_meta_description($url) {
        $resp = self::fetch($url);
        if (!empty($resp['body'])) {
            if (preg_match('/<meta[^>]+name=["\']description["\'][^>]+content=["\']([^"\']+)["\'][^>]*>/i', $resp['body'], $m)) {
                return trim($m[1]);
            }
            if (preg_match('/<meta[^>]+content=["\']([^"\']+)["\'][^>]+name=["\']description["\'][^>]*>/i', $resp['body'], $m)) {
                return trim($m[1]);
            }
        }
        return '';
    }

    private static function build_llms($site_name, $site_desc, $site_url, $locale, array $data, $mode) {
        $lines = array();
        $title_suffix = $mode === 'short' ? __('｜LLMS 内容索引', 'simple-geo-llms-generator') : __('｜LLMS 扩展索引', 'simple-geo-llms-generator');
        $lines[] = '# ' . self::safe($site_name . $title_suffix);
        $lines[] = '> ' . self::safe($site_desc);
        $lines[] = '';

        $pages = !empty($data['pages']) ? $data['pages'] : array();
        if (!empty($pages)) {
            $lines[] = '## ' . __('核心页面', 'simple-geo-llms-generator');
            foreach ($pages as $item) {
                $lines[] = '- [' . self::safe($item['title']) . '](' . esc_url_raw($item['url']) . '): ' . self::safe($item['desc']);
            }
            $lines[] = '';
        }

        $post_types = !empty($data['post_types']) ? $data['post_types'] : array();
        foreach ($post_types as $pt_data) {
            $pt_name = !empty($pt_data['name']) ? $pt_data['name'] : __('内容', 'simple-geo-llms-generator');
            $terms = !empty($pt_data['terms']) ? $pt_data['terms'] : array();
            $latest = !empty($pt_data['latest']) ? $pt_data['latest'] : array();

            $section_title = $pt_name . __('目录', 'simple-geo-llms-generator');
            $latest_title = __('最新', 'simple-geo-llms-generator') . $pt_name;

            if (!empty($terms)) {
                $lines[] = '## ' . $section_title;
                foreach ($terms as $term) {
                    $term_name = !empty($term['name']) ? $term['name'] : __('未分类', 'simple-geo-llms-generator');
                    $term_url = !empty($term['url']) ? $term['url'] : '#';
                    $term_count = !empty($term['count']) ? $term['count'] : 0;
                    $lines[] = '- [' . self::safe($pt_name . ': ' . $term_name) . '](' . esc_url_raw($term_url) . '): ' . $term_count . ' ' . __('篇', 'simple-geo-llms-generator') . '。';
                }
                $lines[] = '';
            }

            if (!empty($latest)) {
                $lines[] = '### ' . $latest_title;
                foreach ($latest as $item) {
                    $date_str = !empty($item['date']) ? $item['date'] : '';
                    $cat_str = !empty($item['category']) ? $item['category'] : '';
                    $meta_parts = array_filter(array($date_str, $cat_str));
                    $meta = !empty($meta_parts) ? '（' . implode(' | ', $meta_parts) . '）' : '';
                    $lines[] = '- [' . self::safe($item['title']) . '](' . esc_url_raw($item['url']) . '): ' . self::safe($item['desc']) . $meta;
                }
                $lines[] = '';
            }
        }

        $lines[] = '## ' . __('网站信息', 'simple-geo-llms-generator');
        $lines[] = '- ' . __('地址:', 'simple-geo-llms-generator') . ' ' . esc_url_raw($site_url);
        $lines[] = '- ' . __('语言:', 'simple-geo-llms-generator') . ' ' . self::safe($locale);
        $lines[] = '- ' . __('robots：', 'simple-geo-llms-generator') . esc_url_raw($site_url . 'robots.txt');
        $lines[] = '- ' . __('网站地图：', 'simple-geo-llms-generator') . esc_url_raw($site_url . 'sitemap.xml');
        $lines[] = '- ' . __('更新:', 'simple-geo-llms-generator') . ' ' . gmdate('Y-m-d');

        return implode("\n", $lines);
    }

    private static function write_file($filename, $content) {
        return (bool) @file_put_contents(ABSPATH . ltrim($filename, '/'), $content, LOCK_EX);
    }

    private static function clean_text($text, $max_len) {
        $text = html_entity_decode((string) $text, ENT_QUOTES, 'UTF-8');
        $text = strip_shortcodes($text);
        $text = wp_strip_all_tags($text, true);
        $text = preg_replace('/\s+/u', ' ', trim($text));
        if ($text === '') return __('内容摘要待补充。', 'simple-geo-llms-generator');
        $ellipsis = function_exists('mb_strlen') ? '…' : '...';
        if (function_exists('mb_strlen') && function_exists('mb_substr')) {
            return mb_strlen($text, 'UTF-8') > $max_len ? mb_substr($text, 0, $max_len, 'UTF-8') . $ellipsis : $text;
        }
        return strlen($text) > $max_len ? substr($text, 0, $max_len) . $ellipsis : $text;
    }

    private static function get_post_description($post) {
        $excerpt = get_the_excerpt($post);
        if (!empty($excerpt)) return $excerpt;

        $seo_keys = array(
            '_yoast_wpseo_metadesc',
            'rank_math_description',
            '_aioseo_description',
            '_metadesc',
            'description',
        );
        foreach ($seo_keys as $key) {
            $val = get_post_meta($post->ID, $key, true);
            if (!empty($val)) return $val;
        }

        return wp_trim_words($post->post_content, 20);
    }

    private static function safe($text) {
        return trim(preg_replace('/\s+/u', ' ', str_replace(array("\r", "\n"), ' ', (string) $text)));
    }

    private static function get_default_settings() {
        return array(
            'post_types' => array('post'),
            'enable_llms_link' => 0,
            'cleanup_on_uninstall' => 0,
        );
    }

    private static function get_settings() {
        $saved = get_option(self::SETTINGS_KEY, array());
        return is_array($saved) ? wp_parse_args($saved, self::get_default_settings()) : self::get_default_settings();
    }

    private static function save_settings(array $settings) {
        $clean = array(
            'post_types' => array(),
            'enable_llms_link' => !empty($settings['enable_llms_link']) ? 1 : 0,
            'cleanup_on_uninstall' => !empty($settings['cleanup_on_uninstall']) ? 1 : 0,
        );
        foreach ((array) ($settings['post_types'] ?: array()) as $pt) {
            $pt = sanitize_key($pt);
            if (post_type_exists($pt)) $clean['post_types'][] = $pt;
        }
        if (empty($clean['post_types'])) $clean['post_types'][] = 'post';
        update_option(self::SETTINGS_KEY, $clean, false);
    }

    public static function run_scan($persist = true, $trigger = 'manual') {
        $endpoints = self::check_endpoints();
        $signals = self::check_signals();

        $summary = array('pass' => 0, 'warn' => 0, 'fail' => 0);
        foreach (array_merge($endpoints, $signals) as $c) {
            if (isset($summary[$c['status']])) $summary[$c['status']]++;
        }

        $overall = $summary['fail'] > 0 ? 'fail' : ($summary['warn'] > 0 ? 'warn' : 'pass');
        $recommendations = self::build_recommendations($endpoints, $signals);

        $scan = array(
            'time' => current_time('mysql'),
            'trigger' => $trigger,
            'overall' => $overall,
            'summary' => $summary,
            'endpoints' => $endpoints,
            'signals' => $signals,
            'recommendations' => $recommendations,
        );

        if ($persist) {
            update_option(self::SCAN_KEY, $scan, false);
        }

        return $scan;
    }

    private static function check_endpoints() {
        $checks = array();
        foreach (array('robots.txt', 'sitemap.xml', 'llms.txt', 'llms-full.txt') as $label) {
            $url = home_url('/' . $label);
            $resp = self::fetch($url);
            if (!empty($resp['error']) || $resp['code'] === 0) {
                $status = 'fail';
                $msg = __('无法连接', 'simple-geo-llms-generator');
                $suggest = __('检查服务器配置', 'simple-geo-llms-generator');
            } elseif ($resp['code'] === 200) {
                $status = empty(trim($resp['body'])) ? 'warn' : 'pass';
                $msg = $status === 'warn' ? __('文件为空', 'simple-geo-llms-generator') : __('正常响应', 'simple-geo-llms-generator');
                $suggest = $status === 'warn' ? sprintf(__('生成内容到 %s', 'simple-geo-llms-generator'), $label) : __('无需操作', 'simple-geo-llms-generator');
            } elseif ($resp['code'] === 404) {
                $status = 'fail';
                $msg = __('文件不存在', 'simple-geo-llms-generator');
                $suggest = strpos($label, 'llms') === 0 ? __('点击"立即重建 LLMS 文件"', 'simple-geo-llms-generator') : __('检查固定链接设置', 'simple-geo-llms-generator');
            } else {
                $status = 'warn';
                $msg = 'HTTP ' . $resp['code'];
                $suggest = __('检查服务器配置', 'simple-geo-llms-generator');
            }
            $checks[] = compact('label', 'url', 'status', 'msg', 'suggest');
        }
        return $checks;
    }

    private static function check_signals() {
        $html = self::fetch(home_url('/'))['body'] ?: '';
        return array(
            self::check_h1($html),
            self::check_llms_link($html),
            self::check_canonical($html),
            self::check_og_tags($html),
        );
    }

    private static function check_h1($html) {
        if (preg_match('/<h1[^>]*>(.*?)<\/h1>/is', $html, $m)) {
            $content = trim(strip_tags($m[1]));
            return array('label' => __('首页 H1', 'simple-geo-llms-generator'), 'status' => $content ? 'pass' : 'warn', 'msg' => $content ? 'H1: ' . substr($content, 0, 40) : __('H1 为空', 'simple-geo-llms-generator'), 'suggest' => $content ? __('无需操作', 'simple-geo-llms-generator') : __('为首页添加 H1', 'simple-geo-llms-generator'));
        }
        return array('label' => __('首页 H1', 'simple-geo-llms-generator'), 'status' => 'warn', 'msg' => __('未找到 H1', 'simple-geo-llms-generator'), 'suggest' => __('添加 H1 标签', 'simple-geo-llms-generator'));
    }

    private static function check_llms_link($html) {
        $found = preg_match('/<link[^>]*rel=["\'][^"\']*llms[^"\']*["\'][^>]*>/i', $html);
        return array('label' => 'LLMS Link', 'status' => $found ? 'pass' : 'warn', 'msg' => $found ? __('已声明 llms link', 'simple-geo-llms-generator') : __('未找到 llms link', 'simple-geo-llms-generator'), 'suggest' => $found ? __('无需操作', 'simple-geo-llms-generator') : __('确保输出 <link rel="llms" href="/llms.txt">', 'simple-geo-llms-generator'));
    }

    private static function check_canonical($html) {
        if (preg_match('/<link[^>]*rel=["\']canonical["\'][^>]*>/i', $html)) {
            return array('label' => 'Canonical', 'status' => 'pass', 'msg' => __('已设置', 'simple-geo-llms-generator'), 'suggest' => __('无需操作', 'simple-geo-llms-generator'));
        }
        return array('label' => 'Canonical', 'status' => 'warn', 'msg' => __('未找到', 'simple-geo-llms-generator'), 'suggest' => __('添加 canonical 标签', 'simple-geo-llms-generator'));
    }

    private static function check_og_tags($html) {
        $found = 0;
        foreach (array('og:title', 'og:description', 'og:image') as $tag) {
            if (preg_match('/<meta[^>]*property=["\']' . preg_quote($tag) . '["\'][^>]*content=["\']([^"\']+)["\']/i', $html)) $found++;
        }
        return array('label' => 'OG 标签', 'status' => $found >= 2 ? 'pass' : ($found > 0 ? 'warn' : 'fail'), 'msg' => $found >= 2 ? __('OG 标签完整', 'simple-geo-llms-generator') : __('缺失 OG 标签', 'simple-geo-llms-generator'), 'suggest' => $found >= 2 ? __('无需操作', 'simple-geo-llms-generator') : __('安装 SEO 插件或手动添加', 'simple-geo-llms-generator'));
    }

    private static function build_recommendations(array $endpoints, array $signals) {
        $recs = array();
        $no_action = __('无需操作', 'simple-geo-llms-generator');
        foreach (array_merge($endpoints, $signals) as $c) {
            if (in_array($c['status'], array('fail', 'warn')) && $c['suggest'] !== $no_action) {
                $recs[] = '[' . $c['label'] . '] ' . $c['suggest'];
            }
        }
        return array_unique($recs);
    }

    private static function fetch($url) {
        $resp = wp_remote_get($url, array('timeout' => 10, 'user-agent' => 'Simple GEO LLMS Scanner/1.0'));
        if (is_wp_error($resp)) {
            return array('error' => $resp->get_error_message(), 'code' => 0, 'body' => '');
        }
        return array('error' => '', 'code' => (int) wp_remote_retrieve_response_code($resp), 'body' => wp_remote_retrieve_body($resp));
    }

    public static function register_admin_page() {
        add_options_page('Simple GEO LLMS Generator', 'Simple GEO LLMS', 'manage_options', self::ADMIN_SLUG, array(__CLASS__, 'render_admin_page'));
    }

    public static function render_admin_page() {
        if (!current_user_can('manage_options')) return;

        $state = get_option(self::OPT_KEY, array());
        $scan = get_option(self::SCAN_KEY, array());
        $settings = self::get_settings();
        ?>
        <div class="wrap">
            <h1>Simple GEO LLMS Generator</h1>
            <style>
                .geo-card { background:#fff;border:1px solid #dcdcde;border-radius:8px;margin:16px 0;padding:18px 20px; }
                .geo-grid { display:grid;gap:16px;grid-template-columns:repeat(auto-fit,minmax(200px,1fr)); }
                .geo-metric { background:#f6f7f7;border-radius:8px;padding:14px 16px; }
                .geo-status { border-radius:999px;display:inline-block;font-size:11px;font-weight:600;padding:5px 10px;text-transform:uppercase; }
                .geo-status-pass { background:#d4edda;color:#155724; }
                .geo-status-warn { background:#fff3cd;color:#856404; }
                .geo-status-fail { background:#f8d7da;color:#721c24; }
                .geo-table { border-collapse:collapse;width:100%;margin-top:12px; }
                .geo-table td,.geo-table th { border-top:1px solid #f0f0f1;padding:10px;text-align:left; }
                .geo-table th { font-size:13px;color:#646970; }
                .geo-muted { color:#646970;font-size:12px; }
                .geo-actions { margin:16px 0; }
                .geo-actions form { display:inline-block;margin-right:8px; }
                .geo-checkbox { display:block;margin-bottom:6px; }
            </style>

            <div class="geo-card">
                <h2><?php _e('状态', 'simple-geo-llms-generator'); ?></h2>
                <div class="geo-grid">
                    <div class="geo-metric">
                        <strong><?php _e('LLMS 重建', 'simple-geo-llms-generator'); ?></strong>
                        <p><?php echo isset($state['time']) ? esc_html($state['time']) : esc_html__('未执行', 'simple-geo-llms-generator'); ?></p>
                        <span class="geo-muted"><?php echo !empty($state['ok']) ? esc_html__('成功', 'simple-geo-llms-generator') : esc_html__('未成功', 'simple-geo-llms-generator'); ?></span>
                    </div>
                    <div class="geo-metric">
                        <strong><?php _e('最近扫描', 'simple-geo-llms-generator'); ?></strong>
                        <p><?php echo isset($scan['time']) ? esc_html($scan['time']) : esc_html__('未执行', 'simple-geo-llms-generator'); ?></p>
                        <?php if (!empty($scan['summary'])): ?>
                            <span class="geo-status geo-status-<?php echo esc_attr($scan['overall']); ?>"><?php echo esc_html($scan['overall']); ?></span>
                        <?php endif; ?>
                    </div>
                    <div class="geo-metric">
                        <strong><?php _e('网站地址', 'simple-geo-llms-generator'); ?></strong>
                        <p><?php echo esc_html(home_url('/')); ?></p>
                        <span class="geo-muted"><?php _e('需要根目录写权限', 'simple-geo-llms-generator'); ?></span>
                    </div>
                </div>
                <div class="geo-actions">
                    <form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>">
                        <?php wp_nonce_field('simple_geo_llms_regenerate'); ?>
                        <input type="hidden" name="action" value="simple_geo_llms_regenerate">
                        <label class="geo-checkbox"><input type="checkbox" name="generate_full" value="1"> <?php _e('同时生成 llms-full.txt', 'simple-geo-llms-generator'); ?></label>
                        <?php submit_button(__('重建 LLMS 文件', 'simple-geo-llms-generator'), 'primary'); ?>
                    </form>
                    <form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>">
                        <?php wp_nonce_field('simple_geo_llms_run_scan'); ?>
                        <input type="hidden" name="action" value="simple_geo_llms_run_scan">
                        <?php submit_button(__('运行扫描', 'simple-geo-llms-generator'), 'secondary'); ?>
                    </form>
                </div>
            </div>

            <?php if (!empty($scan['endpoints']) || !empty($scan['signals'])): ?>
                <div class="geo-card">
                    <h2><?php _e('扫描结果', 'simple-geo-llms-generator'); ?></h2>
                    <table class="geo-table">
                        <thead><tr><th><?php _e('检查项', 'simple-geo-llms-generator'); ?></th><th><?php _e('URL', 'simple-geo-llms-generator'); ?></th><th><?php _e('状态', 'simple-geo-llms-generator'); ?></th><th><?php _e('结果', 'simple-geo-llms-generator'); ?></th><th><?php _e('建议', 'simple-geo-llms-generator'); ?></th></tr></thead>
                        <tbody>
                            <?php foreach ((array) $scan['endpoints'] as $c): ?>
                                <tr>
                                    <td><?php echo esc_html($c['label']); ?></td>
                                    <td><?php echo esc_html($c['url']); ?></td>
                                    <td><span class="geo-status geo-status-<?php echo esc_attr($c['status']); ?>"><?php echo strtoupper($c['status']); ?></span></td>
                                    <td><?php echo esc_html($c['msg']); ?></td>
                                    <td><?php echo esc_html($c['suggest']); ?></td>
                                </tr>
                            <?php endforeach; ?>
                            <?php foreach ((array) $scan['signals'] as $c): ?>
                                <tr>
                                    <td><?php echo esc_html($c['label']); ?></td>
                                    <td>-</td>
                                    <td><span class="geo-status geo-status-<?php echo esc_attr($c['status']); ?>"><?php echo strtoupper($c['status']); ?></span></td>
                                    <td><?php echo esc_html($c['msg']); ?></td>
                                    <td><?php echo esc_html($c['suggest']); ?></td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php endif; ?>

            <?php if (!empty($scan['recommendations'])): ?>
                <div class="geo-card">
                    <h2><?php _e('建议', 'simple-geo-llms-generator'); ?></h2>
                    <ul style="margin-left:20px;">
                        <?php foreach ($scan['recommendations'] as $rec): ?>
                            <li><?php echo esc_html($rec); ?></li>
                        <?php endforeach; ?>
                    </ul>
                </div>
            <?php endif; ?>

            <div class="geo-card">
                <h2><?php _e('设置', 'simple-geo-llms-generator'); ?></h2>
                <form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>">
                    <?php wp_nonce_field('simple_geo_llms_save_settings'); ?>
                    <input type="hidden" name="action" value="simple_geo_llms_save_settings">

                    <p><strong><?php _e('LLMS输出内容类型', 'simple-geo-llms-generator'); ?></strong></p>
                    <?php foreach (get_post_types(array('public' => true), 'objects') as $pt => $obj): ?>
                        <?php if (!in_array($pt, array('attachment', 'revision', 'nav_menu_item'))): ?>
                            <label class="geo-checkbox">
                                <input type="checkbox" name="settings[post_types][]" value="<?php echo esc_attr($pt); ?>" <?php checked(in_array($pt, $settings['post_types'], true)); ?>>
                                <?php echo esc_html($obj->labels->singular_name ?: $pt); ?>
                            </label>
                        <?php endif; ?>
                    <?php endforeach; ?>

                    <p><label class="geo-checkbox"><input type="checkbox" name="settings[enable_llms_link]" value="1" <?php checked($settings['enable_llms_link']); ?>> <?php _e('输出', 'simple-geo-llms-generator'); ?> <code>&lt;link rel="llms"&gt;</code></label></p>

                    <hr style="margin:20px 0;border:none;border-top:1px solid #f0f0f1;">
                    <p><label class="geo-checkbox"><input type="checkbox" name="settings[cleanup_on_uninstall]" value="1" <?php checked($settings['cleanup_on_uninstall']); ?>> <?php _e('卸载时清理数据', 'simple-geo-llms-generator'); ?></label></p>

                    <?php submit_button(__('保存设置', 'simple-geo-llms-generator')); ?>
                </form>
            </div>

            <div class="geo-card">
                <h2><?php _e('使用说明', 'simple-geo-llms-generator'); ?></h2>
                <p><strong><?php _e('文件输出数量限制（默认）：', 'simple-geo-llms-generator'); ?></strong></p>
                <table class="geo-table">
                    <thead><tr><th><?php _e('文件', 'simple-geo-llms-generator'); ?></th><th><?php _e('每个内容类型文章数', 'simple-geo-llms-generator'); ?></th><th><?php _e('目录数', 'simple-geo-llms-generator'); ?></th><th><?php _e('页面数', 'simple-geo-llms-generator'); ?></th></tr></thead>
                    <tbody>
                        <tr><td>llms.txt</td><td>36 <?php _e('篇', 'simple-geo-llms-generator'); ?></td><td>5 <?php _e('个', 'simple-geo-llms-generator'); ?></td><td>24 <?php _e('个', 'simple-geo-llms-generator'); ?></td></tr>
                        <tr><td>llms-full.txt</td><td>90 <?php _e('篇', 'simple-geo-llms-generator'); ?></td><td>10 <?php _e('个', 'simple-geo-llms-generator'); ?></td><td>36 <?php _e('个', 'simple-geo-llms-generator'); ?></td></tr>
                    </tbody>
                </table>
                <p style="margin-top:12px;"><strong><?php _e('自定义修改：', 'simple-geo-llms-generator'); ?></strong></p>
                <p class="geo-muted"><?php printf(esc_html__('如需修改默认限制，请编辑插件文件 %s，在 %s 方法中查找「自定义修改区域」，修改以下变量值：', 'simple-geo-llms-generator'), '<code>simple-geo-llms-generator.php</code>', '<code>regenerate_files()</code>'); ?></p>
                <ul style="margin-left:20px;" class="geo-muted">
                    <li><code>$limit_short_articles</code> - <?php printf(esc_html__('llms.txt 每类型文章数（默认 %d）', 'simple-geo-llms-generator'), 36); ?></li>
                    <li><code>$limit_full_articles</code> - <?php printf(esc_html__('llms-full.txt 每类型文章数（默认 %d）', 'simple-geo-llms-generator'), 90); ?></li>
                    <li><code>$limit_short_pages</code> - <?php printf(esc_html__('llms.txt 页面数（默认 %d）', 'simple-geo-llms-generator'), 24); ?></li>
                    <li><code>$limit_full_pages</code> - <?php printf(esc_html__('llms-full.txt 页面数（默认 %d）', 'simple-geo-llms-generator'), 36); ?></li>
                    <li><code>$limit_short_terms</code> - <?php printf(esc_html__('llms.txt 目录数（默认 %d）', 'simple-geo-llms-generator'), 5); ?></li>
                    <li><code>$limit_full_terms</code> - <?php printf(esc_html__('llms-full.txt 目录数（默认 %d）', 'simple-geo-llms-generator'), 10); ?></li>
                </ul>
            </div>
        </div>
        <?php
    }

    public static function handle_regenerate() {
        if (!current_user_can('manage_options') || !check_admin_referer('simple_geo_llms_regenerate')) {
            wp_die(esc_html__('Permission denied', 'simple-geo-llms-generator'));
        }
        $generate_full = !empty($_POST['generate_full']);
        self::regenerate_files($generate_full);
        wp_safe_redirect(admin_url('options-general.php?page=' . self::ADMIN_SLUG));
        exit;
    }

    public static function handle_run_scan() {
        if (!current_user_can('manage_options') || !check_admin_referer('simple_geo_llms_run_scan')) {
            wp_die(esc_html__('Permission denied', 'simple-geo-llms-generator'));
        }
        self::run_scan(true, 'manual');
        wp_safe_redirect(admin_url('options-general.php?page=' . self::ADMIN_SLUG));
        exit;
    }

    public static function handle_save_settings() {
        if (!current_user_can('manage_options') || !check_admin_referer('simple_geo_llms_save_settings')) {
            wp_die(esc_html__('Permission denied', 'simple-geo-llms-generator'));
        }
        $raw = isset($_POST['settings']) && is_array($_POST['settings']) ? wp_unslash($_POST['settings']) : array();
        self::save_settings($raw);
        wp_safe_redirect(admin_url('options-general.php?page=' . self::ADMIN_SLUG . '&saved=1'));
        exit;
    }

    public static function output_llms_link() {
        $settings = self::get_settings();
        if (!empty($settings['enable_llms_link'])) {
            echo '<link rel="llms" href="' . esc_url(home_url('/llms.txt')) . '">' . "\n";
        }
    }
}

add_action('plugins_loaded', array('GEO_LLMS_Generator', 'init'));
register_activation_hook(__FILE__, array('GEO_LLMS_Generator', 'on_activate'));
register_deactivation_hook(__FILE__, array('GEO_LLMS_Generator', 'on_deactivate'));
