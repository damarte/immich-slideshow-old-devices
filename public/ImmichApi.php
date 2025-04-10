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

        $url = "{$this->immich_url}/api/albums/{$album_id}";
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
            curl_close($ch);
            throw new Exception($error);
        }

        $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        if ($http_code !== 200 || $response === false) {
            $error = "HTTP error $http_code when connecting to Immich";
            error_log($error);
            throw new Exception($error);
        }

        $data = json_decode($response, true);
        
        if (!is_array($data) || !isset($data['assets'])) {
            $error = "Invalid response from Immich: $response";
            error_log($error);
            throw new Exception($error);
        }

        $photos = [];
        foreach ($data['assets'] as $asset) {
            if (!isset($asset['id']) || $asset['isArchived'] || $asset['isTrashed']) {
                continue;
            }

            // Determinar la orientación basada en las dimensiones de la imagen
            $orientation = 'landscape';
            if (isset($asset['exifInfo']['exifImageHeight']) && isset($asset['exifInfo']['exifImageWidth'])) {
                if ($asset['exifInfo']['exifImageHeight'] > $asset['exifInfo']['exifImageWidth']) {
                    $orientation = 'portrait';
                }
            }

            $photos[] = [
                'id' => $asset['id'],
                'orientation' => $orientation
            ];
        }

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
            curl_close($ch);
            throw new Exception("Error cURL: {$error}");
        }

        $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $content_type = curl_getinfo($ch, CURLINFO_CONTENT_TYPE);
        curl_close($ch);

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
                $meta = stream_get_meta_data($temp);
                $image = @imagecreatefromwebp($meta['uri']);
                
                if ($image === false) {
                    throw new Exception('Error converting WebP image');
                }

                ob_start();
                imagejpeg($image, null, 85); // Add compression quality
                $image_data = ob_get_clean();
                imagedestroy($image);
                $content_type = 'image/jpeg';
            } finally {
                fclose($temp);
            }
        }

        return [$content_type, $image_data];
    }
}