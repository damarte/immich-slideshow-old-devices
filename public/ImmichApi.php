<?php

/**
 * Immich API Client
 */
class ImmichApi {
    private string $immich_url;
    private string $api_key;

    /**
     * @param string $immich_url Base URL for Immich API
     * @param string $api_key API key for authentication
     */
    public function __construct(string $immich_url, string $api_key) {
        if (empty($immich_url) || empty($api_key)) {
            throw new InvalidArgumentException('Immich URL and API key are required');
        }
        $this->immich_url = rtrim($immich_url, '/');
        $this->api_key = $api_key;
    }

    /**
     * Get albums
     * 
     * @return array List of albums
     * @throws Exception If there's an error in the request
     */
    public function getAlbums(): array {
        $url = "{$this->immich_url}/api/albums";
        $ch = curl_init($url);
        
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_HTTPHEADER, [
            "x-api-key: {$this->api_key}",
            "Accept: application/json"
        ]);

        $response = curl_exec($ch);
        if ($response === false) {
            $error = "Error: " . curl_error($ch);
            error_log($error);
            throw new Exception($error);
        }

        $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);

        if ($http_code !== 200 || $response === false) {
            $error = "HTTP error $http_code when connecting to Immich $response";
            error_log($error);
            throw new Exception($error);
        }

        $data = json_decode($response, true);
        
        if (!is_array($data)) {
            $error = "Invalid response from Immich: $response";
            error_log($error);
            throw new Exception($error);
        }

        return $data;
    }

    /**
     * Get album assets
     *
     * @param string $album_id Album ID
     * @return array List of album assets
     * @throws Exception If there's an error in the request
     */
    public function getAlbumAssets(string $album_id): array {
        if (empty($album_id)) {
            throw new InvalidArgumentException('Album ID is required');
        }

        return $this->searchAssets(['albumIds' => [$album_id]]);
    }

    /**
     * Get favorite assets
     *
     * @return array List of favorite assets
     * @throws Exception If there's an error in the request
     */
    public function getFavoriteAssets(): array {
        return $this->searchAssets(['isFavorite' => true]);
    }

    /**
     * Get photos taken within a date range (either end may be null)
     *
     * @param string|null $after  Start date, YYYY-MM-DD (inclusive)
     * @param string|null $before End date, YYYY-MM-DD (inclusive)
     * @return array List of assets
     * @throws Exception If there's an error in the request
     */
    public function getAssetsByDate(?string $after, ?string $before): array {
        // visibility=timeline leaves out archived, hidden and locked assets
        $filters = ['type' => 'IMAGE', 'visibility' => 'timeline'];
        if (!empty($after)) {
            $filters['takenAfter'] = $after . 'T00:00:00.000Z';
        }
        if (!empty($before)) {
            $filters['takenBefore'] = $before . 'T23:59:59.999Z';
        }
        return $this->searchAssets($filters);
    }

    /**
     * Get photos with a given tag
     *
     * @param string $tag Tag name, full path (Parent/Child) or tag ID
     * @return array List of assets
     * @throws Exception If the tag does not exist or the request fails
     */
    public function getAssetsByTag(string $tag): array {
        return $this->searchAssets([
            'tagIds' => [$this->resolveTagId($tag)],
            'type' => 'IMAGE',
            'visibility' => 'timeline',
        ]);
    }

    /**
     * Find a tag ID by name or full path (case-insensitive); IDs pass through
     */
    private function resolveTagId(string $tag): string {
        $tag = trim($tag);
        if (preg_match('/^[0-9a-f]{8}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{12}$/i', $tag)) {
            return $tag;
        }

        $ch = curl_init("{$this->immich_url}/api/tags");
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_HTTPHEADER, [
            "x-api-key: {$this->api_key}",
            "Accept: application/json"
        ]);
        $response = curl_exec($ch);
        $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        if ($response === false || $http_code !== 200) {
            $error = "HTTP error $http_code when listing Immich tags (the API key needs tag.read)";
            error_log($error);
            throw new Exception($error);
        }

        // mbstring is not always installed; fall back to strtolower
        $lower = function_exists('mb_strtolower') ? 'mb_strtolower' : 'strtolower';
        $wanted = $lower($tag);
        foreach (json_decode($response, true) ?: [] as $t) {
            if ($lower($t['value'] ?? '') === $wanted || $lower($t['name'] ?? '') === $wanted) {
                return $t['id'];
            }
        }

        $error = "Tag not found in Immich: $tag";
        error_log($error);
        throw new Exception($error);
    }

    /**
     * Get the ID of one favorite asset, for use as a thumbnail
     *
     * @return string|null Asset ID, or null if there are no favorites
     * @throws Exception If there's an error in the request
     */
    public function getFirstFavoriteAssetId(): ?string {
        $photos = $this->searchAssets(['isFavorite' => true], 1, false);
        return $photos[0]['id'] ?? null;
    }

    /**
     * Search assets with the given filters
     *
     * Immich v3 removed assets from GET /api/albums/{id}.
     * Use POST /api/search/metadata instead, paginating until done.
     *
     * @param array $filters Search filters (e.g. albumIds, isFavorite)
     * @param int $size Page size
     * @param bool $paginate Whether to fetch all pages or just the first one
     * @return array List of assets
     * @throws Exception If there's an error in the request
     */
    private function searchAssets(array $filters, int $size = 1000, bool $paginate = true): array {
        $photos = [];
        $page = 1;

        do {
            $url = "{$this->immich_url}/api/search/metadata";
            $body = json_encode($filters + [
                'size' => $size,
                'page' => $page,
            ]);

            $ch = curl_init($url);
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
            curl_setopt($ch, CURLOPT_POST, true);
            curl_setopt($ch, CURLOPT_POSTFIELDS, $body);
            curl_setopt($ch, CURLOPT_HTTPHEADER, [
                "x-api-key: {$this->api_key}",
                "Accept: application/json",
                "Content-Type: application/json"
            ]);

            $response = curl_exec($ch);
            if ($response === false) {
                $error = "Error: " . curl_error($ch);
                error_log($error);
                throw new Exception($error);
            }

            $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);

            if ($http_code !== 200) {
                $error = "HTTP error $http_code when connecting to Immich $response";
                error_log($error);
                throw new Exception($error);
            }

            $data = json_decode($response, true);

            if (!is_array($data) || !isset($data['assets']['items'])) {
                $error = "Invalid response from Immich: $response";
                error_log($error);
                throw new Exception($error);
            }

            foreach ($data['assets']['items'] as $asset) {
                if (!isset($asset['id']) || $asset['isArchived'] || $asset['isTrashed']) {
                    continue;
                }

                // Determine orientation based on image dimensions (v3 exposes these as top-level fields)
                $orientation = 'landscape';
                if (isset($asset['height']) && isset($asset['width'])) {
                    if ($asset['height'] > $asset['width']) {
                        $orientation = 'portrait';
                    }
                }

                $photos[] = [
                    'id' => $asset['id'],
                    'orientation' => $orientation
                ];
            }

            $nextPage = $data['assets']['nextPage'] ?? null;
            $page = ($paginate && $nextPage !== null) ? (int)$nextPage : null;
        } while ($page !== null);

        return $photos;
    }

    /**
     * Get a specific asset
     * 
     * @param string $asset_id Asset ID
     * @param string $size Thumbnail size
     * @return array [string $content_type, string $image_data]
     * @throws Exception If there's an error in the request or conversion
     */
    public function getAsset(string $asset_id, string $size): array {
        if (empty($asset_id) || empty($size)) {
            throw new InvalidArgumentException('Asset ID and size are required');
        }

        $sizes = ['thumbnail', 'preview', 'fullsize'];

        if (!in_array($size, $sizes)) {
            throw new InvalidArgumentException('Size must be one of: ' . implode(', ', $sizes));
        }

        $url = "{$this->immich_url}/api/assets/{$asset_id}/thumbnail?size={$size}";

        $ch = curl_init($url);
        if ($ch === false) {
            throw new Exception('Could not initialize cURL');
        }

        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_HTTPHEADER => [
                "x-api-key: {$this->api_key}",
                "Accept: application/octet-stream"
            ]
        ]);

        $image_data = curl_exec($ch);
        if ($image_data === false) {
            $error = curl_error($ch);
            throw new Exception("Error cURL: {$error}");
        }

        $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $content_type = curl_getinfo($ch, CURLINFO_CONTENT_TYPE);

        if ($http_code !== 200) {
            throw new Exception("Error HTTP {$http_code} when connecting to Immich");
        }

        if ($content_type === 'image/webp') {
            // If content is webp, convert to jpg for compatibility
            $temp = tmpfile();
            if ($temp === false) {
                throw new Exception('Could not create temporary file');
            }

            try {
                fwrite($temp, $image_data);
                $image_data = '';
                $meta = stream_get_meta_data($temp);
                $image = @imagecreatefromwebp($meta['uri']);

                if ($image === false) {
                    throw new Exception('Error converting WebP image');
                }

                ob_start();
                imagejpeg($image, null, 85); // Add compression quality
                $image_data = ob_get_clean();
                unset($image);
                $content_type = 'image/jpeg';
            } finally {
                fclose($temp);
            }
        }

        return [$content_type, $image_data];
    }

    /**
     * Capture date and album names of one asset, for the photo info overlay
     *
     * @param string $asset_id Asset ID
     * @return array ['date' => string|null, 'albums' => string[]]
     * @throws Exception If there's an error in the request
     */
    public function getAssetInfo(string $asset_id): array {
        $asset = $this->getJson("/api/assets/" . rawurlencode($asset_id));
        $albums = $this->getJson("/api/albums?assetId=" . rawurlencode($asset_id));

        $names = [];
        foreach ($albums as $album) {
            if (!empty($album['albumName'])) {
                $names[] = $album['albumName'];
            }
        }

        return [
            // localDateTime is the capture time in the photo's own time zone
            'date' => $asset['localDateTime'] ?? ($asset['fileCreatedAt'] ?? null),
            'albums' => $names,
        ];
    }

    /**
     * GET a JSON endpoint of the Immich API
     */
    private function getJson(string $path): array {
        $ch = curl_init($this->immich_url . $path);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_TIMEOUT, 15);
        curl_setopt($ch, CURLOPT_HTTPHEADER, [
            "x-api-key: {$this->api_key}",
            "Accept: application/json"
        ]);
        $response = curl_exec($ch);
        $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        if ($response === false || $http_code !== 200) {
            $error = "HTTP error $http_code on GET $path";
            error_log($error);
            throw new Exception($error);
        }
        $data = json_decode($response, true);
        return is_array($data) ? $data : [];
    }
}
