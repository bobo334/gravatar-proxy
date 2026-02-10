<?php
class Cache_Manager {
    private $cache_dir;
    private $expiry;

    public function __construct() {
        $this->cache_dir = GRAVATAR_CACHE_DIR;
        $this->expiry = GRAVATAR_CACHE_EXPIRY;
        if (!is_dir($this->cache_dir)) {
            wp_mkdir_p($this->cache_dir);
        }
    }

    public function get_avatar($hash) {
        $file = $this->cache_dir . '/' . $hash . '.jpg';
        if (file_exists($file) && (time() - filemtime($file)) < $this->expiry) {
            return file_get_contents($file);
        }
        return false;
    }

    public function save_avatar($hash, $data) {
        $file = $this->cache_dir . '/' . $hash . '.jpg';
        file_put_contents($file, $data);
        $this->enforce_limit();
    }

    public function cleanup() {
        $files = glob($this->cache_dir . '/*.jpg');
        foreach ($files as $f) {
            if (is_file($f) && (time() - filemtime($f)) > $this->expiry) {
                unlink($f);
            }
        }
    }

    private function enforce_limit() {
        $max = (int) get_option('gravatar_proxy_cache_size', 1000);
        $files = glob($this->cache_dir . '/*.jpg');
        if (count($files) <= $max) return;
        // 按修改时间升序排序，最旧的先删除
        usort($files, function($a, $b) { return filemtime($a) - filemtime($b); });
        while (count($files) > $max) {
            $old = array_shift($files);
            if (is_file($old)) unlink($old);
        }
    }
}
?>