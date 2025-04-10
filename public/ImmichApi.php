<?php

class ImmichApi {
    private $immich_url;
    private $api_key;

    public function __construct($immich_url, $api_key) {
        $this->immich_url = rtrim($immich_url, '/');
        $this->api_key = $api_key;
    }

    public function getAlbumAssets($album_id) {
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
            if (!isset($asset['id'])) {
                continue;
            }
            $photos[] = ['id' => $asset['id']];
        }

        return $photos;
    }

    public function getAsset($asset_id, $size) {
        $url = "{$this->immich_url}/api/assets/{$asset_id}/thumbnail?size={$size}";

        $ch = curl_init($url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
        curl_setopt($ch, CURLOPT_HTTPHEADER, [
            "x-api-key: {$this->api_key}",
            "Accept: application/octet-stream"
        ]);

        $image_data = curl_exec($ch);
        if ($image_data === false) {
            $error = "Error: " . curl_error($ch);
            error_log($error);
            curl_close($ch);
            throw new Exception($error);
        }

        $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $content_type = curl_getinfo($ch, CURLINFO_CONTENT_TYPE);
        curl_close($ch);

        if ($http_code !== 200) {
            $error = "HTTP error $http_code when connecting to Immich: $response";
            error_log($error);
            throw new Exception($error);
        }

        if ($content_type === 'image/webp') {
            // If the content type is webp, convert to jpg for compatibility
            $temp = tmpfile();
            fwrite($temp, $image_data);
            $image = imagecreatefromwebp(stream_get_meta_data($temp)['uri']);
            fclose($temp);
            if ($image === false) {
                $error = "Error converting image to JPG";
                error_log($error);
                throw new Exception($error);
            }
            ob_start();
            imagejpeg($image);
            $image_data = ob_get_clean();
            imagedestroy($image);
            $content_type = 'image/jpeg';
        }

        return [$content_type, $image_data];
    }
}