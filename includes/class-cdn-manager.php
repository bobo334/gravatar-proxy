<?php
class CDN_Manager {
    private $cdn_url;

    public function __construct() {
        $this->cdn_url = get_option('gravatar_proxy_cdn_url', '');
    }

    public function get_avatar_url($hash) {
        if ($this->cdn_url) {
            return rtrim($this->cdn_url, '/') . '/' . $hash . '.jpg';
        }
        return home_url('/gravatar-proxy/' . $hash . '/');
    }

    public function purge($hash) {
        if (!$this->cdn_url) return false;
        $url = rtrim($this->cdn_url, '/') . '/' . $hash . '.jpg';
        $response = wp_remote_request($url, ['method' => 'PURGE']);
        return !is_wp_error($response);
    }
}
?>
