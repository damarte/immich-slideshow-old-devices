<?php
/**
 * Immich Slideshow - Main entry point
 * 
 * This script creates a slideshow interface for Immich photo albums.
 * It supports various customization options through GET parameters or environment variables.
 */

require_once './ImmichApi.php';
require_once './Configuration.php';

$configuration = new Configuration();

// Configuration parameters with validation
$immich_url = $configuration->get(Configuration::IMMICH_URL);
$immich_api_key = $configuration->get(Configuration::IMMICH_API_KEY);

// Get and validate input parameters with defaults
$album_id = $_GET['album_id'] ?? $configuration->get(Configuration::ALBUM_ID);
$carousel_duration = (int)($_GET['duration'] ?? $configuration->get(Configuration::CAROUSEL_DURATION) ?? 5);
$background = preg_match('/^[a-zA-Z0-9#]+$/', $_GET['background'] ?? '') 
    ? $_GET['background'] 
    : ($configuration->get(Configuration::BACKGROUND_COLOR) ?? '#000000');
$random_order = filter_var($_GET['random'] ?? $configuration->get(Configuration::RANDOM_ORDER) ?? 'false', FILTER_VALIDATE_BOOLEAN);
$status_bar_style = in_array($_GET['status_bar'] ?? '', ['default', 'black-translucent', 'black']) 
    ? $_GET['status_bar'] 
    : ($configuration->get(Configuration::STATUS_BAR_STYLE) ?? 'black-translucent');
$orientation = in_array($_GET['orientation'] ?? '', ['landscape', 'portrait', 'all']) 
    ? $_GET['orientation'] 
    : ($configuration->get(Configuration::ORIENTATION) ?? 'all');

// Validate required parameters
if (!$album_id) {
    http_response_code(400);
    echo "Error: Missing required parameter 'album_id'";
    exit;
}

// Validate carousel duration
if ($carousel_duration < 1) {
    $carousel_duration = 5;
}

try {
    // Initialize API and fetch photos
    $api = new ImmichApi($immich_url, $immich_api_key);
    
    // Support multiple album IDs separated by comma
    $album_ids = explode(',', $album_id);
    $photos = [];

    foreach ($album_ids as $id) {
        $id = trim($id);
        if (empty($id)) continue;
        
        try {
            $album_photos = $api->getAlbumAssets($id);
            $photos = array_merge($photos, $album_photos);
        } catch (Exception $e) {
            // Log error but continue with other albums
            error_log("Warning: Failed to fetch photos from album $id: " . $e->getMessage());
        }
    }
    
    if (empty($photos)) {
        throw new Exception("No photos found in the specified album(s)");
    }

    // Filter photos by orientation if needed
    if ($orientation !== 'all') {
        $photos = array_values(array_filter($photos, function($photo) use ($orientation) {
            return $photo['orientation'] === $orientation;
        }));

        if (empty($photos)) {
            throw new Exception("No photos found with the specified orientation");
        }
    }
    
    if ($random_order) {
        shuffle($photos);
    }
} catch (\Exception $e) {
    http_response_code(500);
    echo "Error: Unable to fetch photos - " . $e->getMessage();
    exit;
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, height=device-height, initial-scale=1, maximum-scale=1, minimum-scale=1, user-scalable=no, minimal-ui"/>
    <meta name="mobile-web-app-capable" content="yes"/>
    <meta name="apple-mobile-web-app-capable" content="yes"/>
    <meta name="apple-mobile-web-app-status-bar-style" content="<?php echo htmlspecialchars($status_bar_style); ?>"/>
    <meta name="apple-mobile-web-app-status-bar" content="<?php echo htmlspecialchars($status_bar_style); ?>"/>
    <meta name="theme-color" content="<?php echo htmlspecialchars($background); ?>"/>
    <title>Immich Slideshow</title>
    <link rel="shortcut icon" type="image/x-icon" href="assets/favicon.ico?v=<?php echo filemtime('assets/favicon.ico'); ?>"/>
    <link rel="apple-touch-icon" sizes="180x180" href="assets/apple-icon-180.png?v=<?php echo filemtime('assets/apple-icon-180.png'); ?>"/>
    <link rel="icon" type="image/png" sizes="32x32" href="assets/favicon-32.png?v=<?php echo filemtime('assets/favicon-32.png'); ?>"/>
    <link rel="icon" type="image/png" sizes="16x16" href="assets/favicon-16.png?v=<?php echo filemtime('assets/favicon-16.png'); ?>"/>
    <link rel="stylesheet" href="assets/main.css?v=<?php echo filemtime('assets/main.css'); ?>"/>
    <script src="assets/main.js?v=<?php echo filemtime('assets/main.js'); ?>"></script>
    <style>
        html, body {
            background-color: <?php echo htmlspecialchars($background); ?>;
        }
    </style>
</head>
<body>
    <div class="carousel">
        <a href="#" id="current-link">
            <img src="assets/apple-icon-180.png" id="current-img" alt="Current slideshow image"/>
            <img src="assets/apple-icon-180.png" id="next-img" alt="Next slideshow image"/>
        </a>
    </div>
    <img src="assets/pause.png" alt="Pause icon" class="pause-icon" id="pause-icon"/>
    <script>
        initSlideshow({
            photos: <?php echo json_encode($photos); ?>,
            duration: <?php echo $carousel_duration; ?>
        });

        document.onkeydown = function(e) {
        e = e || window.event;
        var keyCode = e.keyCode || e.which;

        switch(keyCode) {
            // --- REFRESH (Up Arrow) ---
            case 38: 
                // Remote: Manual Refresh
                window.location.reload();
                break;

            // --- FORWARD (Right Arrow & Down Arrow) ---
            case 39: // Right Arrow
            case 40: // Down Arrow
                if (typeof nextImage === 'function') {
                    nextImage();
                }
                break;

            // --- BACKWARD (Left Arrow) ---
            case 37: // Left Arrow
                if (typeof previousImage === 'function') {
                    previousImage();
                } else {
                    window.location.reload();
                }
                break;

            // --- CENTER (Enter / OK) ---
            case 13: // Enter
                if (typeof togglePause === 'function') {
                    togglePause();
                }
                break;
        }
    };
    </script>
</body>
</html>