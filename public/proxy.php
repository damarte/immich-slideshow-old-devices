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
    // Get asset from Immich API. Default to 'preview' (already resized by the
    // Immich server): decoding a full size photo can exceed PHP's memory limit.
    $image_quality = $configuration->get(Configuration::IMAGE_QUALITY);
    if (!in_array($image_quality, ['preview', 'fullsize'])) {
        $image_quality = 'preview';
    }

    $api = new ImmichApi($immich_url, $immich_api_key);
    $data = $api->getAsset($asset_id, $image_quality);

    // Create image from binary data
    $source = imagecreatefromstring($data[1]);
    if ($source === false) {
        throw new Exception("Failed to create image from source");
    }
    $data[1] = '';

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

    // Background color (hex or CSS color name, same values the page accepts)
    list($r, $g, $b) = parseColor($background);

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
    unset($source);

    // Send headers. Cache headers only now, once the image is ready: errors
    // must never be cacheable.
    header('Cache-Control: public, max-age=3600');
    header('Expires: ' . gmdate('D, d M Y H:i:s', time() + 3600) . ' GMT');
    header("Content-Type: {$data[0]}");

    // Output image based on type
    if ($data[0] === 'image/jpeg') {
        imagejpeg($resized, null, 85);
    } elseif ($data[0] === 'image/png') {
        imagepng($resized);
    }
} catch (\Throwable $e) {
    if (!headers_sent()) {
        header('Cache-Control: no-store');
        http_response_code(500);
    }
    echo "Error: Unable to process image. " . $e->getMessage();
}

/**
 * Converts a CSS color (#rgb, #rrggbb or a common color name) to RGB.
 * Without this, names like "black" were parsed as hex and became green.
 */
function parseColor(string $color): array {
    $names = [
        'black' => '000000', 'white' => 'ffffff', 'gray' => '808080', 'grey' => '808080',
        'silver' => 'c0c0c0', 'darkgray' => 'a9a9a9', 'darkgrey' => 'a9a9a9',
        'dimgray' => '696969', 'dimgrey' => '696969', 'lightgray' => 'd3d3d3',
        'lightgrey' => 'd3d3d3', 'gainsboro' => 'dcdcdc', 'whitesmoke' => 'f5f5f5',
        'red' => 'ff0000', 'maroon' => '800000', 'green' => '008000', 'lime' => '00ff00',
        'olive' => '808000', 'yellow' => 'ffff00', 'navy' => '000080', 'blue' => '0000ff',
        'teal' => '008080', 'aqua' => '00ffff', 'cyan' => '00ffff', 'purple' => '800080',
        'fuchsia' => 'ff00ff', 'magenta' => 'ff00ff', 'orange' => 'ffa500',
        'brown' => 'a52a2a', 'pink' => 'ffc0cb', 'beige' => 'f5f5dc', 'ivory' => 'fffff0',
        'midnightblue' => '191970', 'darkslategray' => '2f4f4f', 'darkslategrey' => '2f4f4f',
    ];

    $value = strtolower(trim($color));
    if (isset($names[$value])) {
        $hex = $names[$value];
    } elseif (preg_match('/^#?([0-9a-f]{3}|[0-9a-f]{6})$/', $value, $m)) {
        $hex = strlen($m[1]) === 3
            ? $m[1][0] . $m[1][0] . $m[1][1] . $m[1][1] . $m[1][2] . $m[1][2]
            : $m[1];
    } else {
        error_log("Unknown background color '$color', using black");
        $hex = '000000';
    }

    return [hexdec(substr($hex, 0, 2)), hexdec(substr($hex, 2, 2)), hexdec(substr($hex, 4, 2))];
}
