<?php
class Gravatar_Proxy {
    private static $instance = null;
    private $cdn;

    public static function get_instance() {
        if (null === self::$instance) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    private function __construct() {
        $this->cdn = new CDN_Manager();
        add_filter('get_avatar_url', [$this, 'proxy_gravatar_url'], 10, 3);
        add_filter('get_avatar', [$this, 'proxy_gravatar_html'], 10, 5);
        add_action('init', [$this, 'register_cron']);
        add_action('gravatar_proxy_cache_cleanup', [$this, 'cache_cleanup']);
        add_action('admin_menu', [$this, 'add_settings_page']);
        add_action('admin_init', [$this, 'register_settings']);
        add_action('admin_init', [$this, 'handle_cache_actions']);
        add_action('admin_init', [$this, 'handle_rewrite_refresh']);
        add_action('admin_notices', [$this, 'maybe_rewrite_notice']);
    }

    public function proxy_gravatar_url($url, $id_or_email, $args) {
        $hash = $this->get_email_hash($id_or_email);
        if ($hash) {
            return $this->cdn->get_avatar_url($hash);
        }
        return $url;
    }

    public function proxy_gravatar_html($avatar, $id_or_email, $size, $default, $alt) {
        $hash = $this->get_email_hash($id_or_email);
        if ($hash) {
            $proxy = $this->cdn->get_avatar_url($hash);
            return '<img src="' . esc_url($proxy) . '" alt="' . esc_attr($alt) . '" class="avatar avatar-' . (int) $size . ' photo" height="' . (int) $size . '" width="' . (int) $size . '">';
        }
        return $avatar;
    }

    private function get_email_hash($id_or_email) {
        if (is_numeric($id_or_email)) {
            $user = get_userdata($id_or_email);
            $email = ($user && !empty($user->user_email)) ? $user->user_email : '';
        } elseif ($id_or_email instanceof WP_Comment) {
            $email = $id_or_email->comment_author_email ?? '';
        } elseif (is_object($id_or_email) && property_exists($id_or_email, 'user_email')) {
            $email = $id_or_email->user_email;
        } elseif (is_object($id_or_email) && property_exists($id_or_email, 'comment_author_email')) {
            $email = $id_or_email->comment_author_email;
        } else {
            $email = $id_or_email;
        }
        return $email ? md5(strtolower(trim($email))) : false;
    }

    public function register_cron() {
        if (!wp_next_scheduled('gravatar_proxy_cache_cleanup')) {
            wp_schedule_event(time(), 'daily', 'gravatar_proxy_cache_cleanup');
        }
    }

    public function cache_cleanup() {
        $cache = new Cache_Manager();
        $cache->cleanup();
    }

    public function handle_cache_actions() {
        if (!isset($_POST['gravatar_proxy_clear_cache'])) {
            return;
        }
        if (!current_user_can('manage_options')) {
            return;
        }
        if (!check_admin_referer('gravatar_proxy_clear_cache')) {
            return;
        }

        $cache = new Cache_Manager();
        $cache->clear_all();
        add_action('admin_notices', [$this, 'cache_cleared_notice']);
    }

    public function handle_rewrite_refresh() {
        if (!isset($_POST['gravatar_proxy_flush_rewrite'])) {
            return;
        }
        if (!current_user_can('manage_options')) {
            return;
        }
        if (!check_admin_referer('gravatar_proxy_flush_rewrite')) {
            return;
        }

        flush_rewrite_rules();
        add_action('admin_notices', [$this, 'rewrite_flushed_notice']);
    }

    public function cache_cleared_notice() {
        echo '<div class="notice notice-success is-dismissible"><p>缓存已清空！</p></div>';
    }

    public function rewrite_flushed_notice() {
        echo '<div class="notice notice-success is-dismissible"><p>重写规则已刷新。</p></div>';
    }

    private function rewrite_rules_missing() {
        $rules = get_option('rewrite_rules');
        if (!is_array($rules)) {
            return true;
        }
        $needle = 'gravatar-proxy/([a-f0-9]{32})/?$';
        if (array_key_exists($needle, $rules)) {
            return false;
        }
        $needle = '^gravatar-proxy/([a-f0-9]{32})/?$';
        return !array_key_exists($needle, $rules);
    }

    public function maybe_rewrite_notice() {
        if (!current_user_can('manage_options')) {
            return;
        }
        if (!$this->rewrite_rules_missing()) {
            return;
        }
        echo '<div class="notice notice-warning"><p>Gravatar Proxy 的重写规则未生效。请在“设置 > 固定链接”保存一次，或点击下方按钮刷新规则。</p>';
        echo '<form method="post" style="margin-top:6px;">';
        wp_nonce_field('gravatar_proxy_flush_rewrite');
        echo '<input type="submit" name="gravatar_proxy_flush_rewrite" class="button button-secondary" value="刷新重写规则">';
        echo '</form></div>';
    }

    private function get_cache_stats() {
        $default_dir = defined('GRAVATAR_CACHE_DIR') ? GRAVATAR_CACHE_DIR : WP_CONTENT_DIR . '/cache/gravatar';
        $cache_dir = get_option('gravatar_proxy_cache_dir', $default_dir);
        $cache_dir = is_string($cache_dir) ? trim($cache_dir) : $default_dir;
        if ($cache_dir === '') {
            $cache_dir = $default_dir;
        }
        $files = glob($cache_dir . '/*.jpg');
        $total_size = 0;
        foreach ($files as $file) {
            $total_size += filesize($file);
        }
        return [
            'count' => count($files),
            'size' => size_format($total_size),
            'size_bytes' => $total_size
        ];
    }

    public function add_settings_page() {
        add_options_page('Gravatar Proxy', 'Gravatar Proxy', 'manage_options', 'gravatar-proxy', [$this, 'settings_page']);
    }

    public function register_settings() {
        register_setting('gravatar_proxy_options', 'gravatar_proxy_cdn_url', [
            'sanitize_callback' => 'esc_url_raw',
        ]);
        register_setting('gravatar_proxy_options', 'gravatar_proxy_cache_size', [
            'sanitize_callback' => 'absint',
        ]);
        register_setting('gravatar_proxy_options', 'gravatar_proxy_cache_dir', [
            'sanitize_callback' => [$this, 'sanitize_cache_dir'],
        ]);
        register_setting('gravatar_proxy_options', 'gravatar_proxy_cache_expiry', [
            'sanitize_callback' => [$this, 'sanitize_cache_expiry'],
        ]);
    }

    public function sanitize_cache_dir($value) {
        if (!is_string($value)) {
            return '';
        }
        $value = trim(str_replace("\0", '', $value));
        return $value;
    }

    public function sanitize_cache_expiry($value) {
        $value = absint($value);
        if ($value < 60) {
            $value = 60;
        }
        return $value;
    }

    public function settings_page() {
        $stats = $this->get_cache_stats();
        $default_dir = defined('GRAVATAR_CACHE_DIR') ? GRAVATAR_CACHE_DIR : WP_CONTENT_DIR . '/cache/gravatar';
        $default_expiry = defined('GRAVATAR_CACHE_EXPIRY') ? GRAVATAR_CACHE_EXPIRY : 7 * 24 * 60 * 60;
        $cache_dir = get_option('gravatar_proxy_cache_dir', $default_dir);
        $cache_expiry = (int) get_option('gravatar_proxy_cache_expiry', $default_expiry);
        ?>
        <div class="wrap">
            <h1>Gravatar Proxy 设置</h1>

            <style>
                .gravatar-proxy-hero {
                    padding: 16px 20px;
                    border: 1px solid #dcdcde;
                    border-radius: 12px;
                    background: linear-gradient(135deg, #f7f4ff 0%, #f0f6ff 100%);
                    box-shadow: 0 2px 6px rgba(0,0,0,0.04);
                    margin: 12px 0 16px;
                }
                .gravatar-proxy-hero-title { margin: 0 0 4px; font-size: 20px; }
                .gravatar-proxy-hero-sub { color: #50575e; margin: 0; }
                .gravatar-proxy-grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(320px, 1fr)); gap: 16px; }
                .gravatar-proxy-card { padding: 16px; border: 1px solid #e2e4e7; border-radius: 12px; background: #fff; box-shadow: 0 1px 2px rgba(0,0,0,0.04); }
                .gravatar-proxy-card h2 { margin: 0 0 12px; font-size: 16px; }
                .gravatar-proxy-card .form-table th { width: 140px; }
                .gravatar-proxy-actions { margin-top: 12px; display: flex; gap: 8px; align-items: center; flex-wrap: wrap; }
                .gravatar-proxy-badges { display: flex; gap: 10px; flex-wrap: wrap; margin: 4px 0 8px; }
                .gravatar-proxy-badge { background: #f6f7f7; border: 1px solid #e2e4e7; border-radius: 999px; padding: 4px 10px; font-size: 12px; color: #50575e; }
                .gravatar-proxy-help { color: #646970; font-size: 12px; margin-top: 4px; }
                .gravatar-proxy-input-note { margin-top: 6px; color: #646970; font-size: 12px; }
                .gravatar-proxy-form .submit { margin-top: 10px; }
            </style>

            <div class="gravatar-proxy-hero">
                <h2 class="gravatar-proxy-hero-title">头像缓存与分发</h2>
                <p class="gravatar-proxy-hero-sub">管理缓存策略、CDN 地址与本地存储位置。</p>
            </div>

            <div class="gravatar-proxy-grid">
            <div class="gravatar-proxy-card">
                <h2>缓存概览</h2>
                <div class="gravatar-proxy-badges">
                    <span class="gravatar-proxy-badge">目录：<?php echo esc_html($cache_dir); ?></span>
                    <span class="gravatar-proxy-badge">过期：<?php echo esc_html($cache_expiry); ?> 秒</span>
                </div>
                <table class="form-table">
                    <tr>
                        <th scope="row">缓存文件数</th>
                        <td><strong><?php echo esc_html($stats['count']); ?></strong> 个</td>
                    </tr>
                    <tr>
                        <th scope="row">缓存总大小</th>
                        <td><strong><?php echo esc_html($stats['size']); ?></strong></td>
                    </tr>
                    <tr>
                        <th scope="row">缓存目录</th>
                        <td><code><?php echo esc_html($cache_dir); ?></code></td>
                    </tr>
                </table>
                <form method="post" class="gravatar-proxy-actions">
                    <?php wp_nonce_field('gravatar_proxy_clear_cache'); ?>
                    <input type="submit" name="gravatar_proxy_clear_cache" class="button button-secondary" value="清空所有缓存" onclick="return confirm('确定要清空所有缓存吗？');">
                </form>
                <form method="post" class="gravatar-proxy-actions">
                    <?php wp_nonce_field('gravatar_proxy_flush_rewrite'); ?>
                    <input type="submit" name="gravatar_proxy_flush_rewrite" class="button" value="刷新重写规则">
                </form>
            </div>

            <div class="gravatar-proxy-card">
            <h2>插件设置</h2>
            <form method="post" action="options.php" class="gravatar-proxy-form">
                <?php
                settings_fields('gravatar_proxy_options');
                do_settings_sections('gravatar-proxy');
                ?>
                <table class="form-table">
                    <tr valign="top">
                        <th scope="row">CDN URL</th>
                        <td><input type="text" name="gravatar_proxy_cdn_url" value="<?php echo esc_attr(get_option('gravatar_proxy_cdn_url')); ?>" class="regular-text" />
                        <p class="description">设置 CDN URL 用于加速头像分发</p></td>
                    </tr>
                    <tr valign="top">
                        <th scope="row">缓存大小</th>
                        <td><input type="number" name="gravatar_proxy_cache_size" value="<?php echo esc_attr(get_option('gravatar_proxy_cache_size', 1000)); ?>" class="small-text" />
                        <p class="description">设置缓存最大文件数</p></td>
                    </tr>
                    <tr valign="top">
                        <th scope="row">缓存目录</th>
                        <td><input type="text" name="gravatar_proxy_cache_dir" value="<?php echo esc_attr($cache_dir); ?>" class="regular-text code" />
                        <p class="description">默认：<?php echo esc_html($default_dir); ?></p></td>
                    </tr>
                    <tr valign="top">
                        <th scope="row">缓存过期</th>
                        <td><input type="number" name="gravatar_proxy_cache_expiry" value="<?php echo esc_attr($cache_expiry); ?>" class="small-text" />
                        <p class="description">单位：秒（最小 60 秒）。默认：<?php echo esc_html($default_expiry); ?></p></td>
                    </tr>
                </table>
                <?php submit_button(); ?>
            </form>
            </div>
            </div>
        </div>
        <?php
    }
}
?>
