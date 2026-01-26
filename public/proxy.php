<?php
require_once './ImmichApi.php';

// Get configuration from environment variables
$immich_url = getenv('IMMICH_URL');
$immich_api_key = getenv('IMMICH_API_KEY');

// Get and validate request parameters
$asset_id = isset($_GET['asset']) ? trim($_GET['asset']) : null;
$screen_width = isset($_GET['width']) ? (int)$_GET['width'] : 1920;
$screen_height = isset($_GET['height']) ? (int)$_GET['height'] : 1080;

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

    $api = new ImmichApi($immich_url, $immich_api_key);
    $data = $api->getAsset($asset_id, 'fullsize');

    $source = imagecreatefromstring($data[1]);
    if ($source === false) {
        throw new Exception("Failed to create image from source");
    }

    $source_width = imagesx($source);
    $source_height = imagesy($source);

    // 1. Calculate the scale to FIT the image inside the screen bounds
    $scale = min($screen_width / $source_width, $screen_height / $source_height);
    
    $scaled_width = (int)($source_width * $scale);
    $scaled_height = (int)($source_height * $scale);
    
    // 2. Calculate the "Letterbox" padding to center it
    $dest_x = (int)(($screen_width - $scaled_width) / 2);
    $dest_y = (int)(($screen_height - $scaled_height) / 2);
    
    // 3. Create a canvas the EXACT size of the Nixplay screen
    $resized = imagecreatetruecolor($screen_width, $screen_height);
    
    // 4. Fill background with Black (RGB 0,0,0)
    $black = imagecolorallocate($resized, 0, 0, 0);
    imagefill($resized, 0, 0, $black);

    // 5. Place the scaled photo onto the black canvas
    imagecopyresampled(
        $resized, $source,
        $dest_x, $dest_y,    // Move to center
        0, 0,                // Source start
        $scaled_width, $scaled_height, 
        $source_width, $source_height
    );

    header("Content-Type: {$data[0]}");
    
    if ($data[0] === 'image/jpeg') {
        imagejpeg($resized, null, 85);
    } elseif ($data[0] === 'image/png') {
        imagepng($resized);
    }

    imagedestroy($source);
    imagedestroy($resized);
    
} catch (\Exception $e) {
    http_response_code(500);
    echo "Error: " . $e->getMessage();
}