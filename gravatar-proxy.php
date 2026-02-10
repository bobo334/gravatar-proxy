<?php
/*
Plugin Name: Gravatar Proxy
Plugin URI: https://github.com/bobo334/gravatar-proxy
Description: 本地代理 Gravatar 头像，提升加载速度
Version: 1.0.0
Author: bobo
License: GPL v2 or later
*/

if (!defined('ABSPATH')) {
    exit; // 防止直接访问
}

// 常量定义
define('GRAVATAR_PROXY_VERSION', '1.0.0');
define('GRAVATAR_PROXY_PATH', plugin_dir_path(__FILE__));
define('GRAVATAR_PROXY_URL', plugin_dir_url(__FILE__));
define('GRAVATAR_CACHE_DIR', WP_CONTENT_DIR . '/cache/gravatar');
define('GRAVATAR_CACHE_EXPIRY', 30 * 24 * 60 * 60); // 30 天

// 加载依赖类
require_once GRAVATAR_PROXY_PATH . 'includes/class-gravatar-proxy.php';
require_once GRAVATAR_PROXY_PATH . 'includes/class-cache-manager.php';
require_once GRAVATAR_PROXY_PATH . 'includes/class-cdn-manager.php';

// 初始化插件实例
function gravatar_proxy_init() {
    return Gravatar_Proxy::get_instance();
}
add_action('plugins_loaded', 'gravatar_proxy_init');

// 代理请求处理
add_action('init', function () {
    if (isset($_GET['hash']) && preg_match('/^[a-f0-9]{32}$/', $_GET['hash'])) {
        $hash = $_GET['hash'];
        $cache = new Cache_Manager();
        $avatar = $cache->get_avatar($hash);
        if (!$avatar) {
            $remote = wp_remote_get('https://www.gravatar.com/avatar/' . $hash);
            if (!is_wp_error($remote) && isset($remote['body'])) {
                $avatar = $remote['body'];
                $cache->save_avatar($hash, $avatar);
            } else {
                $default = GRAVATAR_PROXY_PATH . 'assets/images/default-avatar.jpg';
                $avatar = file_get_contents($default);
            }
        }
        header('Content-Type: image/jpeg');
        header('Cache-Control: public, max-age=' . GRAVATAR_CACHE_EXPIRY);
        header('Expires: ' . gmdate('D, d M Y H:i:s \G\M\T', time() + GRAVATAR_CACHE_EXPIRY));
        echo $avatar;
        exit;
    }
});
?>