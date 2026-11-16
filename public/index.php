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
// Crossfade length in seconds, and whether to show the date/albums overlay
$transition = (float)($_GET['transition'] ?? $configuration->get(Configuration::TRANSITION_SECONDS) ?? 1);
if ($transition < 0.2 || $transition > 10) {
    $transition = 1;
}
$show_info = filter_var($_GET['info'] ?? $configuration->get(Configuration::SHOW_INFO) ?? 'false', FILTER_VALIDATE_BOOLEAN);
// Hours when photos are shown, e.g. 07:00-23:00 (overnight ranges like 18:00-02:00 work).
// Outside them the screen stays black. Empty means always on.
$active_start = null;
$active_end = null;
$active_hours = trim($_GET['hours'] ?? $configuration->get(Configuration::ACTIVE_HOURS) ?? '');
if (preg_match('/^([01]?\d|2[0-3]):([0-5]\d)\s*-\s*([01]?\d|2[0-3]):([0-5]\d)$/', $active_hours, $m)) {
    $active_start = (int)$m[1] * 60 + (int)$m[2];
    $active_end = (int)$m[3] * 60 + (int)$m[4];
    if ($active_start === $active_end) {
        $active_start = $active_end = null;
    }
} elseif ($active_hours !== '') {
    error_log("Ignoring invalid ACTIVE_HOURS '$active_hours', expected HH:MM-HH:MM");
}
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
            if ($id === Configuration::FAVORITES_ID) {
                $album_photos = $api->getFavoriteAssets();
            } elseif (preg_match('/^since:(\d{4}-\d{2}-\d{2})$/', $id, $m)) {
                // since:2024-01-01 -> photos taken on or after that date
                $album_photos = $api->getAssetsByDate($m[1], null);
            } elseif (preg_match('/^range:(\d{4}-\d{2}-\d{2})\.\.(\d{4}-\d{2}-\d{2})$/', $id, $m)) {
                // range:2024-01-01..2024-12-31 -> photos taken within the range
                $album_photos = $api->getAssetsByDate($m[1], $m[2]);
            } elseif (strpos($id, 'tag:') === 0) {
                // tag:Family or tag:Trips/Beach -> photos with that tag
                $album_photos = $api->getAssetsByTag(substr($id, 4));
            } else {
                $album_photos = $api->getAlbumAssets($id);
            }
            $photos = array_merge($photos, $album_photos);
        } catch (Exception $e) {
            // Log error but continue with other albums
            error_log("Warning: Failed to fetch photos from album $id: " . $e->getMessage());
        }
    }
    
    // Sources can overlap (e.g. an album and a date range): keep each photo once
    $seen = [];
    $photos = array_values(array_filter($photos, function ($photo) use (&$seen) {
        if (isset($seen[$photo['id']])) {
            return false;
        }
        $seen[$photo['id']] = true;
        return true;
    }));

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
} catch (\Throwable $e) {
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
        .carousel img {
            -webkit-transition: opacity <?php echo $transition; ?>s ease-in-out;
            transition: opacity <?php echo $transition; ?>s ease-in-out;
        }
        .photo-info {
            -webkit-transition: opacity <?php echo min($transition, 1.5); ?>s ease-in-out;
            transition: opacity <?php echo min($transition, 1.5); ?>s ease-in-out;
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
    <div class="sleep-screen" id="sleep-screen"></div>
    <div class="photo-info" id="photo-info"><span id="photo-date"></span><span id="photo-albums"></span></div>
    <script>
        initSlideshow({
            photos: <?php echo json_encode($photos); ?>,
            duration: <?php echo $carousel_duration; ?>,
            transition: <?php echo $transition; ?>,
            showInfo: <?php echo $show_info ? 'true' : 'false'; ?>,
            activeStart: <?php echo $active_start === null ? 'null' : $active_start; ?>,
            activeEnd: <?php echo $active_end === null ? 'null' : $active_end; ?>
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