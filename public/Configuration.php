<?php

class Configuration {
    private const CONFIG_FILE = 'config.json';
    private array $fileConfig;

    // Synthetic album ID used to select Immich favorites as a slideshow source
    const FAVORITES_ID = 'favorites';

    const IMMICH_URL = 'IMMICH_URL';
    const IMMICH_API_KEY = 'IMMICH_API_KEY';
    const ALBUM_ID = 'ALBUM_ID';
    const CAROUSEL_DURATION = 'CAROUSEL_DURATION';
    const RANDOM_ORDER = 'RANDOM_ORDER';
    const CROP = 'CROP_TO_SCREEN';
    const IMAGE_QUALITY = 'IMAGE_QUALITY';
    const ORIENTATION = 'IMAGES_ORIENTATION';
    const BACKGROUND_COLOR = 'BACKGROUND_COLOR';
    const STATUS_BAR_STYLE = 'STATUS_BAR_STYLE';

    // Alternative environment variable names, checked when the main one is unset.
    // README and docker-compose use CSS_BACKGROUND_COLOR, the code uses BACKGROUND_COLOR.
    private const ENV_ALIASES = [
        self::BACKGROUND_COLOR => 'CSS_BACKGROUND_COLOR',
    ];

    public function __construct() {
        if (file_exists(self::CONFIG_FILE)) {
            $this->fileConfig = json_decode(file_get_contents(self::CONFIG_FILE), true);
        }
    }

    public function get(string $key) {
        if (isset($this->fileConfig[$key])) {
            return $this->fileConfig[$key];
        }
        // getenv() returns false for missing variables, which would defeat the
        // null coalescing (?? ) defaults used by the callers
        $value = getenv($key);
        if ($value === false && isset(self::ENV_ALIASES[$key])) {
            $value = getenv(self::ENV_ALIASES[$key]);
        }
        return $value === false ? null : $value;
    }

    public static function save(array $config) {
        file_put_contents(self::CONFIG_FILE, json_encode($config, JSON_PRETTY_PRINT));
    }
}