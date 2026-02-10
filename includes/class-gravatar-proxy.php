<?php
class Gravatar_Proxy {
    private static $instance = null;

    public static function get_instance() {
        if (null === self::$instance) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    private function __construct() {
        add_filter('get_avatar_url', [$this, 'proxy_gravatar_url'], 10, 3);
        add_filter('get_avatar', [$this, 'proxy_gravatar_html'], 10, 5);
        add_action('init', [$this, 'register_cron']);
        add_action('gravatar_proxy_cache_cleanup', [$this, 'cache_cleanup']);
        add_action('admin_menu', [$this, 'add_settings_page']);
        add_action('admin_init', [$this, 'register_settings']);
    }

    public function proxy_gravatar_url($url, $id_or_email, $args) {
        $hash = $this->get_email_hash($id_or_email);
        if ($hash) {
            return home_url('/gravatar-proxy/?hash=' . $hash);
        }
        return $url;
    }

    public function proxy_gravatar_html($avatar, $id_or_email, $size, $default, $alt) {
        $hash = $this->get_email_hash($id_or_email);
        if ($hash) {
            $proxy = home_url('/gravatar-proxy/?hash=' . $hash);
            return '<img src="' . esc_url($proxy) . '" alt="' . esc_attr($alt) . '" class="avatar avatar-' . $size . ' photo" height="' . $size . '" width="' . $size . '">';
        }
        return $avatar;
    }

    private function get_email_hash($id_or_email) {
        if (is_numeric($id_or_email)) {
            $user = get_userdata($id_or_email);
            $email = $user->user_email ?? '';
        } elseif (is_object($id_or_email) && property_exists($id_or_email, 'user_email')) {
            $email = $id_or_email->user_email;
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

    public function add_settings_page() {
        add_options_page('Gravatar Proxy', 'Gravatar Proxy', 'manage_options', 'gravatar-proxy', [$this, 'settings_page']);
    }

    public function register_settings() {
        register_setting('gravatar_proxy_options', 'gravatar_proxy_cdn_url');
        register_setting('gravatar_proxy_options', 'gravatar_proxy_cache_size');
    }

    public function settings_page() {
        ?>
        <div class="wrap">
            <h1>Gravatar Proxy 设置</h1>
            <form method="post" action="options.php">
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
                </table>
                <?php submit_button(); ?>
            </form>
        </div>
        <?php
    }
}
?>