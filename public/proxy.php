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

    // Get asset from Immich API (always get the full size image)
    $api = new ImmichApi($immich_url, $immich_api_key);
    $data = $api->getAsset($asset_id, 'fullsize');

    // Create image from binary data
    $source = imagecreatefromstring($data[1]);
    if ($source === false) {
        throw new Exception("Failed to create image from source");
    }

    // Get original dimensions
    $source_width = imagesx($source);
    $source_height = imagesy($source);

    // Calculate scale factors for both dimensions
    $scale_w = $screen_width / $source_width;
    $scale_h = $screen_height / $source_height;
    
    // Use the larger scaling factor to ensure the image covers the screen
    $scale = max($scale_w, $scale_h);
    
    // Calculate dimensions after scaling
    $scaled_width = $source_width * $scale;
    $scaled_height = $source_height * $scale;
    
    // Calculate cropping positions to center the image
    $crop_x = ($scaled_width - $screen_width) / 2 / $scale;
    $crop_y = ($scaled_height - $screen_height) / 2 / $scale;
    
    // Create the new image with exact screen dimensions
    $resized = imagecreatetruecolor($screen_width, $screen_height);
    
    // Preserve transparency for PNG images
    if ($data[0] === 'image/png') {
        imagealphablending($resized, false);
        imagesavealpha($resized, true);
    }

    // Resize and crop the image
    imagecopyresampled(
        $resized,
        $source,
        0, 0,                    // Destination x, y
        (int)$crop_x, (int)$crop_y,  // Source x, y
        $screen_width, $screen_height,         // Destination width, height
        (int)($screen_width / $scale),        // Source width
        (int)($screen_height / $scale)        // Source height
    );

    // Send headers
    header("Content-Type: {$data[0]}");
    
    // Output image based on type
    if ($data[0] === 'image/jpeg') {
        imagejpeg($resized, null, 85);
    } elseif ($data[0] === 'image/png') {
        imagepng($resized);
    }

    // Free memory
    imagedestroy($source);
    imagedestroy($resized);
    
} catch (\Exception $e) {
    http_response_code(500);
    echo "Error: Unable to process image. " . $e->getMessage();
}