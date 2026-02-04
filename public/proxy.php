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

    // Get cropping configuration
    $crop_to_screen = getenv('CROP_TO_SCREEN') !== 'false'; // Default to true

    // Calculate scale factors for both dimensions
    $scale_w = $screen_width / $source_width;
    $scale_h = $screen_height / $source_height;
    
    if ($crop_to_screen) {
        // CROP: Use the larger scaling factor to ensure the image covers the screen
        $scale = max($scale_w, $scale_h);
        
        // CROP: Logic - Crop source, fill destination
        $dst_x = 0;
        $dst_y = 0;
        $dst_w = $screen_width;
        $dst_h = $screen_height;
        
        $src_w = $screen_width / $scale;
        $src_h = $screen_height / $scale;
        $src_x = ($source_width - $src_w) / 2;
        $src_y = ($source_height - $src_h) / 2;
    } else {
        // FIT: Use the smaller scaling factor to ensure the image fits within the screen
        $scale = min($scale_w, $scale_h);
        
        // FIT: Logic - Full source, center in destination
        $dst_w = $source_width * $scale;
        $dst_h = $source_height * $scale;
        $dst_x = ($screen_width - $dst_w) / 2;
        $dst_y = ($screen_height - $dst_h) / 2;
        
        $src_x = 0;
        $src_y = 0;
        $src_w = $source_width;
        $src_h = $source_height;
    }
    
    // 3. Create a canvas the EXACT size of the Nixplay screen
    $resized = imagecreatetruecolor($screen_width, $screen_height);
    
    // 4. Fill background with Black (RGB 0,0,0)
    $black = imagecolorallocate($resized, 0, 0, 0);
    imagefill($resized, 0, 0, $black);

    // 5. Place the scaled photo onto the black canvas
    imagecopyresampled(
        $resized,
        $source,
        (int)$dst_x, (int)$dst_y,  // Destination x, y
        (int)$src_x, (int)$src_y,  // Source x, y
        (int)$dst_w, (int)$dst_h,  // Destination width, height
        (int)$src_w, (int)$src_h   // Source width, height
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