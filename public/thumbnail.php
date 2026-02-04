<?php
require_once './ImmichApi.php';

// Get configuration from environment variables
$immich_url = getenv('IMMICH_URL');
$immich_api_key = getenv('IMMICH_API_KEY');

// Get and validate request parameters
$asset_id = isset($_GET['asset']) ? trim($_GET['asset']) : null;

// Validate asset_id parameter
if (!$asset_id) {
    http_response_code(400);
    echo "Error: Missing required 'asset' parameter";
    exit;
}

try {
    // Set cache headers (1 hour)
    header('Cache-Control: public, max-age=3600');
    header('Expires: ' . gmdate('D, d M Y H:i:s', time() + 3600) . ' GMT');

    // Get asset thumbnail from Immich API
    $api = new ImmichApi($immich_url, $immich_api_key);
    $data = $api->getAsset($asset_id, 'thumbnail');

    // Create image from binary data
    $image = imagecreatefromstring($data[1]);
    if ($image === false) {
        throw new Exception("Failed to create image from source");
    }

    // Send headers
    header("Content-Type: {$data[0]}");
    
    // Output image based on type
    if ($data[0] === 'image/jpeg') {
        imagejpeg($image, null, 85);
    } elseif ($data[0] === 'image/png') {
        imagepng($image);
    }
} catch (\Exception $e) {
    http_response_code(500);
    echo "Error: Unable to process image. " . $e->getMessage();
}
