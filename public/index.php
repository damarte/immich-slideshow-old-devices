<?php
/**
 * Immich Slideshow - Main entry point
 * 
 * This script creates a slideshow interface for Immich photo albums.
 * It supports various customization options through GET parameters or environment variables.
 */

require_once './ImmichApi.php';

// Read the server-side state
$config_file = 'config.json';
$current_settings = json_decode(file_get_contents($config_file), true);

$immich_url = getenv('IMMICH_URL');
$immich_api_key = getenv('IMMICH_API_KEY');

// --- Updated for Multi-Album ---
// If 'album_ids' exists in config, use it. Otherwise, fallback to single 'album_id'
$album_ids = $current_settings['album_ids'] ?? (isset($current_settings['album_id']) ? [$current_settings['album_id']] : []);

$carousel_duration = (int)($current_settings['duration'] ?? 5);
$random_order = filter_var($current_settings['random'] ?? true, FILTER_VALIDATE_BOOLEAN);
$orientation = $current_settings['orientation'] ?? 'all';
$background = getenv('CSS_BACKGROUND_COLOR') ?? 'black';
$status_bar_style = getenv('STATUS_BAR_STYLE') ?? 'black-translucent';

// Validate required parameters
if (empty($album_ids)) {
    http_response_code(400);
    echo "Error: No albums selected in config.json";
    exit;
}

try {
    $api = new ImmichApi($immich_url, $immich_api_key);
    $all_photos = [];

    // --- NEW: Loop through all selected albums ---
    foreach ($album_ids as $id) {
        $album_photos = $api->getAlbumAssets($id);
        if (!empty($album_photos)) {
            $all_photos = array_merge($all_photos, $album_photos);
        }
    }

    // --- NEW: De-duplicate based on asset ID ---
    $unique_photos = [];
    foreach ($all_photos as $p) {
        $unique_photos[$p['id']] = $p;
    }
    $photos = array_values($unique_photos);
    
    if (empty($photos)) {
        throw new Exception("No photos found in the selected albums");
    }

    // Filter photos by orientation
    if ($orientation !== 'all') {
        $photos = array_values(array_filter($photos, function($photo) use ($orientation) {
            // Respecting your ImmichApi orientation mapping
            return $photo['orientation'] === $orientation;
        }));
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
    <title>Immich Slideshow</title>
    <link rel="stylesheet" href="/assets/main.css"/>
    <script src="assets/main.js?v=<?php echo filemtime('assets/main.js'); ?>"></script>
    <style>html, body { background-color: <?php echo htmlspecialchars($background); ?>; }</style>
    <link rel="shortcut icon" type="image/x-icon" href="/assets/favicon.ico"/>
    <link rel="apple-touch-icon" sizes="180x180" href="/assets/apple-icon-180.png"/>
    <link rel="icon" type="image/png" sizes="32x32" href="/assets/favicon-32.png"/>
    <link rel="icon" type="image/png" sizes="16x16" href="/assets/favicon-16.png"/>
</head>
<body>
    <div class="carousel">
        <a href="#" id="current-link">
            <img src="/assets/apple-icon-180.png" id="current-img" />
            <img src="/assets/apple-icon-180.png" id="next-img" />
        </a>
    </div>
    <img src="/assets/pause.png" alt="Pause icon" class="pause-icon" id="pause-icon"/>

    <script>
    // Store the fingerprint of currently active albums
    var activeAlbumsJson = '<?php echo json_encode($album_ids); ?>';
    
    initSlideshow({
        photos: <?php echo json_encode($photos); ?>,
        duration: <?php echo $carousel_duration; ?>
    });

    // 3. IR Remote (Keyboard Mode) Listener
    document.onkeydown = function(e) {
        e = e || window.event;
        var keyCode = e.keyCode || e.which;

        switch(keyCode) {
            // --- REFRESH (Up Arrow) ---
            case 38: 
                console.log("Remote: Manual Refresh");
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

    // UPDATED POLLER: Compares the entire array
    function checkConfig() {
        var xhr = new XMLHttpRequest();
        xhr.open('GET', 'config.json?t=' + new Date().getTime(), true);
        xhr.onreadystatechange = function() {
            if (xhr.readyState === 4 && xhr.status === 200) {
                try {
                    var remoteConfig = JSON.parse(xhr.responseText);
                    var remoteAlbums = JSON.stringify(remoteConfig.album_ids || [remoteConfig.album_id]);
                    
                    // If the list of IDs has changed, reload everything
                    if (remoteAlbums !== activeAlbumsJson) {
                        window.location.reload();
                    }
                } catch (e) {}
            }
        };
        xhr.send();
    }
    setInterval(checkConfig, 10000);
</script>
</body>
</html>