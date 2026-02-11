<?php
/*
Plugin Name: Gravatar Proxy
Plugin URI: https://github.com/bobotechnology/gravatar-proxy
Description: 本地代理 Gravatar 头像，提升加载速度
Version: 1.2.5
Author: bobo
License: GPL v2 or later
*/

if (!defined('ABSPATH')) {
    exit; // 防止直接访问
}

// 常量定义
define('GRAVATAR_PROXY_VERSION', '1.2.5');
define('GRAVATAR_PROXY_PATH', plugin_dir_path(__FILE__));
define('GRAVATAR_PROXY_URL', plugin_dir_url(__FILE__));
if (!defined('GRAVATAR_CACHE_DIR')) {
    define('GRAVATAR_CACHE_DIR', WP_CONTENT_DIR . '/cache/gravatar');
}
if (!defined('GRAVATAR_CACHE_EXPIRY')) {
    define('GRAVATAR_CACHE_EXPIRY', 7 * 24 * 60 * 60); // 7 天
}

// 加载依赖类
require_once GRAVATAR_PROXY_PATH . 'includes/class-gravatar-proxy.php';
require_once GRAVATAR_PROXY_PATH . 'includes/class-cache-manager.php';
require_once GRAVATAR_PROXY_PATH . 'includes/class-cdn-manager.php';

// 初始化插件实例
function gravatar_proxy_init() {
    return Gravatar_Proxy::get_instance();
}
add_action('plugins_loaded', 'gravatar_proxy_init');

function gravatar_proxy_action_links($links) {
    $settings = '<a href="' . esc_url(admin_url('options-general.php?page=gravatar-proxy')) . '">设置</a>';
    array_unshift($links, $settings);
    return $links;
}
add_filter('plugin_action_links_' . plugin_basename(__FILE__), 'gravatar_proxy_action_links');

function gravatar_proxy_add_rewrite_rules() {
    add_rewrite_rule('^gravatar-proxy/([a-f0-9]{32})/?$', 'index.php?gravatar_proxy_hash=$matches[1]', 'top');
}
add_action('init', 'gravatar_proxy_add_rewrite_rules');

function gravatar_proxy_query_vars($vars) {
    $vars[] = 'gravatar_proxy_hash';
    return $vars;
}
add_filter('query_vars', 'gravatar_proxy_query_vars');

function gravatar_proxy_activate() {
    gravatar_proxy_add_rewrite_rules();
    flush_rewrite_rules();
}

function gravatar_proxy_deactivate() {
    wp_clear_scheduled_hook('gravatar_proxy_cache_cleanup');
    flush_rewrite_rules();
}

// 插件停用时清理定时器/重写规则
register_deactivation_hook(__FILE__, function () {
    gravatar_proxy_deactivate();
});
register_activation_hook(__FILE__, 'gravatar_proxy_activate');

// 代理请求处理
add_action('template_redirect', function () {
    $hash = get_query_var('gravatar_proxy_hash', '');
    if (!$hash) {
        $request_path = isset($_SERVER['REQUEST_URI'])
            ? wp_parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH)
            : '';
        $is_proxy_path = is_string($request_path) && preg_match('#/gravatar-proxy/?$#', $request_path);
        if ($is_proxy_path && isset($_GET['hash']) && preg_match('/^[a-f0-9]{32}$/', $_GET['hash'])) {
            $hash = $_GET['hash'];
        }
    }

    if ($hash && preg_match('/^[a-f0-9]{32}$/', $hash)) {
        $refresh = isset($_GET['refresh']) && $_GET['refresh'] === '1';
        $cache = new Cache_Manager();

        if ($refresh) {
            $cache->delete_avatar($hash);
            $avatar = false;
        } else {
            $avatar = $cache->get_avatar($hash);
        }

        if (!$avatar) {
            $remote = wp_remote_get('https://www.gravatar.com/avatar/' . $hash, ['timeout' => 5]);
            $code = !is_wp_error($remote) ? wp_remote_retrieve_response_code($remote) : 0;
            $content_type = !is_wp_error($remote) ? wp_remote_retrieve_header($remote, 'content-type') : '';
            $body = !is_wp_error($remote) ? wp_remote_retrieve_body($remote) : '';

            if ($code === 200 && is_string($content_type) && stripos($content_type, 'image/') === 0 && $body !== '') {
                $avatar = $body;
                $cache->save_avatar($hash, $avatar);
            } else {
                $default = GRAVATAR_PROXY_PATH . 'assets/images/default-avatar.jpg';
                $avatar = is_readable($default) ? file_get_contents($default) : '';
            }
        }
        $default_expiry = defined('GRAVATAR_CACHE_EXPIRY') ? GRAVATAR_CACHE_EXPIRY : 7 * 24 * 60 * 60;
        $cache_expiry = (int) get_option('gravatar_proxy_cache_expiry', $default_expiry);
        if ($cache_expiry < 60) {
            $cache_expiry = $default_expiry;
        }
        header('Content-Type: image/jpeg');
        header('Cache-Control: public, max-age=' . $cache_expiry);
        header('Expires: ' . gmdate('D, d M Y H:i:s \G\M\T', time() + $cache_expiry));
        echo $avatar;
        exit;
    }
});
?>
