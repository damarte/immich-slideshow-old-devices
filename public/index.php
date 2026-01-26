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

// Configuration parameters with validation
$immich_url = getenv('IMMICH_URL');
$immich_api_key = getenv('IMMICH_API_KEY');

// Get and validate input parameters with defaults
$album_id = $current_settings['album_id'] ?? $_GET['album_id'] ?? getenv('ALBUM_ID');

$carousel_duration = (int)($current_settings['duration'] ?? $_GET['duration'] ?? getenv('CAROUSEL_DURATION') ?? 5);

$background = preg_match('/^[a-zA-Z0-9#]+$/', $_GET['background'] ?? '') 
    ? $_GET['background'] 
    : (getenv('CSS_BACKGROUND_COLOR') ?? 'black');

$random_order = filter_var($current_settings['random'] ?? $_GET['random'] ?? getenv('RANDOM_ORDER') ?? 'false', FILTER_VALIDATE_BOOLEAN);

$status_bar_style = in_array($_GET['status_bar'] ?? '', ['default', 'black-translucent', 'black']) 
    ? $_GET['status_bar'] 
    : (getenv('STATUS_BAR_STYLE') ?? 'black-translucent');

$orientation = in_array($_GET['orientation'] ?? '', ['landscape', 'portrait', 'all']) 
    ? $_GET['orientation'] 
    : ($current_settings['orientation'] ?? getenv('IMAGES_ORIENTATION') ?? 'all');

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
    $photos = $api->getAlbumAssets($album_id);
    
    if (empty($photos)) {
        throw new Exception("No photos found in the specified album");
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
    <link rel="shortcut icon" type="image/x-icon" href="/assets/favicon.ico"/>
    <link rel="apple-touch-icon" sizes="180x180" href="/assets/apple-icon-180.png"/>
    <link rel="icon" type="image/png" sizes="32x32" href="/assets/favicon-32.png"/>
    <link rel="icon" type="image/png" sizes="16x16" href="/assets/favicon-16.png"/>
    <link rel="stylesheet" href="/assets/main.css"/>
    <script src="/assets/main.js"></script>
    <style>
        html, body {
            background-color: <?php echo htmlspecialchars($background); ?>;
        }
    </style>
</head>
<body>
    <div class="carousel">
        <a href="#" id="current-link">
            <img src="/assets/apple-icon-180.png" id="current-img" alt="Current slideshow image" onerror="this.src = '/assets/apple-icon-180.png'"/>
            <img src="/assets/apple-icon-180.png" id="next-img" alt="Next slideshow image" onerror="this.src = '/assets/apple-icon-180.png'"/>
        </a>
    </div>
    <img src="/assets/pause.png" alt="Pause icon" class="pause-icon" id="pause-icon"/>
    <script>
        initSlideshow({
            photos: <?php echo json_encode($photos); ?>,
            duration: <?php echo $carousel_duration; ?>
        });
    </script>
    <script>
        // Store the ID that the page was loaded with
        const activeAlbumId = "<?php echo $album_id; ?>";

        // Every 5 seconds, check if the server-side config has changed
        setInterval(async () => {
            try {
                // Fetch the config file from the server
                const response = await fetch('config.json', { 
                    cache: "no-store", // Ensure we don't get a cached version
                    headers: { 'Cache-Control': 'no-cache' }
                });
                
                if (!response.ok) return;

                const remoteConfig = await response.json();

                // If the album ID has changed on the server, reload the page
                if (remoteConfig.album_id && remoteConfig.album_id !== activeAlbumId) {
                    console.log("Album change detected! Switching to: " + remoteConfig.album_id);
                    window.location.reload();
                }
            } catch (e) {
                console.error("Background config check failed", e);
            }
        }, 5000);
    </script>
</body>
</html>