<?php

if (!defined('WP_UNINSTALL_PLUGIN')) {
    exit;
}

$settings = get_option('simple_geo_llms_settings', array());
if (empty($settings['cleanup_on_uninstall'])) {
    return;
}

$option_keys = array(
    'simple_geo_llms_last_result',
    'simple_geo_llms_last_scan',
    'simple_geo_llms_settings',
);

foreach ($option_keys as $option_key) {
    delete_option($option_key);
    delete_site_option($option_key);
}

$files = array(ABSPATH . 'llms.txt', ABSPATH . 'llms-full.txt');
foreach ($files as $file) {
    if (file_exists($file)) {
        @unlink($file);
    }
}