<?php

class Configuration {
    private const CONFIG_FILE = 'config.json';
    private array $fileConfig;

    const IMMICH_URL = 'IMMICH_URL';
    const IMMICH_API_KEY = 'IMMICH_API_KEY';
    const ALBUM_ID = 'ALBUM_ID';
    const CAROUSEL_DURATION = 'CAROUSEL_DURATION';
    const RANDOM_ORDER = 'RANDOM_ORDER';
    const CROP = 'CROP';
    const ORIENTATION = 'ORIENTATION';
    const BACKGROUND_COLOR = 'BACKGROUND_COLOR';
    const STATUS_BAR_STYLE = 'STATUS_BAR_STYLE';

    public function __construct() {
        if (file_exists(self::CONFIG_FILE)) {
            $this->fileConfig = json_decode(file_get_contents(self::CONFIG_FILE), true);
        }
    }

    public function get(string $key) {
        if (isset($this->fileConfig[$key])) {
            return $this->fileConfig[$key];
        }
        return getenv($key);
    }

    public static function save(array $config) {
        file_put_contents(self::CONFIG_FILE, json_encode($config, JSON_PRETTY_PRINT));
    }
}