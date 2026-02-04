<?php
require_once './ImmichApi.php';
require_once './Configuration.php';

$configuration = new Configuration();

// Get configuration from environment variables
$immich_url = $configuration->get(Configuration::IMMICH_URL);
$immich_api_key = $configuration->get(Configuration::IMMICH_API_KEY);

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

    // Get cropping configuration
    $crop_to_screen = $configuration->get(Configuration::CROP) !== 'false'; // Default to true

    // Get background color
    $background = preg_match('/^[a-zA-Z0-9#]+$/', $_GET['background'] ?? '') 
    ? $_GET['background'] 
    : ($configuration->get(Configuration::BACKGROUND_COLOR) ?? '#000000');

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
    
    // Create the new image with exact screen dimensions
    $resized = imagecreatetruecolor($screen_width, $screen_height);

    // Background color
    $hex = ltrim($background, '#');

    if (strlen($hex) == 3) {
        $r = hexdec(str_repeat(substr($hex, 0, 1), 2));
        $g = hexdec(str_repeat(substr($hex, 1, 1), 2));
        $b = hexdec(str_repeat(substr($hex, 2, 1), 2));
    } else {
        $r = hexdec(substr($hex, 0, 2));
        $g = hexdec(substr($hex, 2, 2));
        $b = hexdec(substr($hex, 4, 2));
    }

    $background = imagecolorallocate($resized, $r, $g, $b);
    imagefill($resized, 0, 0, $background);

    // Resize and crop the image
    imagecopyresampled(
        $resized,
        $source,
        (int)$dst_x, (int)$dst_y,  // Destination x, y
        (int)$src_x, (int)$src_y,  // Source x, y
        (int)$dst_w, (int)$dst_h,  // Destination width, height
        (int)$src_w, (int)$src_h   // Source width, height
    );

    // Send headers
    header("Content-Type: {$data[0]}");
    
    // Output image based on type
    if ($data[0] === 'image/jpeg') {
        imagejpeg($resized, null, 85);
    } elseif ($data[0] === 'image/png') {
        imagepng($resized);
    }
} catch (\Exception $e) {
    http_response_code(500);
    echo "Error: Unable to process image. " . $e->getMessage();
}