<?php
/**
 * Immich Slideshow - Main entry point
 * 
 * This script creates a slideshow interface for Immich photo albums.
 * It supports various customization options through GET parameters or environment variables.
 */

require_once './ImmichApi.php';

// Configuration parameters with validation
$immich_url = getenv('IMMICH_URL');
$immich_api_key = getenv('IMMICH_API_KEY');

// Get and validate input parameters with defaults
$album_id = $_GET['album_id'] ?? getenv('ALBUM_ID');
$carousel_duration = (int)($_GET['duration'] ?? getenv('CAROUSEL_DURATION') ?? 5);
$background = preg_match('/^[a-zA-Z0-9#]+$/', $_GET['background'] ?? '') 
    ? $_GET['background'] 
    : (getenv('CSS_BACKGROUND_COLOR') ?? 'black');
$random_order = filter_var($_GET['random'] ?? getenv('RANDOM_ORDER') ?? 'false', FILTER_VALIDATE_BOOLEAN);
$status_bar_style = in_array($_GET['status_bar'] ?? '', ['default', 'black-translucent', 'black']) 
    ? $_GET['status_bar'] 
    : (getenv('STATUS_BAR_STYLE') ?? 'black-translucent');
$orientation = in_array($_GET['orientation'] ?? '', ['landscape', 'portrait', 'all']) 
    ? $_GET['orientation'] 
    : (getenv('IMAGES_ORIENTATION') ?? 'all');

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
    <meta name="theme-color" content="black"/>
    <title>Immich Slideshow</title>
    <link rel="shortcut icon" type="image/x-icon" href="/assets/favicon.ico"/>
    <link rel="apple-touch-icon" sizes="180x180" href="/assets/apple-icon-180.png"/>
    <link rel="icon" type="image/png" sizes="32x32" href="/assets/favicon-32.png"/>
    <link rel="icon" type="image/png" sizes="16x16" href="/assets/favicon-16.png"/>
    <style>
        /* Reset and base styles */
        html, body {
            margin: 0;
            padding: 0;
            width: 100%;
            height: 100%;
            overflow: hidden;
            background-color: <?php echo htmlspecialchars($background); ?>;
            -webkit-text-size-adjust: 100%;
        }

        /* Carousel container */
        .carousel {
            position: fixed;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            width: 100%;
            height: 100%;
            overflow: hidden;
            -webkit-transform: translateZ(0);
        }

        /* Image styles with hardware acceleration */
        .carousel img {
            position: absolute;
            top: 50%;
            left: 50%;
            max-width: 100%;
            max-height: 100%;
            min-width: 180px;
            min-height: 180px;
            width: auto;
            height: auto;
            -webkit-transform: translate(-50%, -50%);
            transform: translate(-50%, -50%);
            opacity: 0;
            -webkit-transition: opacity 1s ease-in-out;
            transition: opacity 1s ease-in-out;
            -webkit-backface-visibility: hidden;
            backface-visibility: hidden;
            pointer-events: none;
        }

        .carousel img.active {
            opacity: 1;
        }

        .carousel a {
            display: block;
            width: 100%;
            height: 100%;
            cursor: pointer;
        }
    </style>
</head>
<body>
    <div class="carousel">
        <a href="#" id="current-link" target="_blank">
            <img id="current-img" alt="Current slideshow image" onerror="this.src = '/assets/apple-icon-180.png'"/>
            <img id="next-img" alt="Next slideshow image" onerror="this.src = '/assets/apple-icon-180.png'"/>
        </a>
    </div>
    <script>
        // Configuration
        var currentLink = document.getElementById('current-link');
        var currentImg = document.getElementById('current-img');
        var nextImg = document.getElementById('next-img');
        var photos = <?php echo json_encode($photos); ?>;
        var totalPhotos = photos.length;
        var duration = <?php echo $carousel_duration; ?> * 1000;
        
        // Get screen dimensions
        var screenWidth = window.innerWidth || document.documentElement.clientWidth || document.body.clientWidth;
        var screenHeight = window.innerHeight || document.documentElement.clientHeight || document.body.clientHeight;
        
        var currentIndex = 0;
        var isTransitioning = false;

        /**
         * Construye la URL del proxy con los parámetros necesarios
         */
        function buildProxyUrl(assetId) {
            return '/proxy.php?asset=' + encodeURIComponent(assetId) + 
                   '&width=' + encodeURIComponent(screenWidth) +
                   '&height=' + encodeURIComponent(screenHeight);
        }

        /**
         * Loads the next image in the slideshow
         */
        function nextImage() {
            if (totalPhotos === 0 || isTransitioning) return;
            
            isTransitioning = true;
            
            // Advance to next index
            currentIndex = (currentIndex + 1) % totalPhotos;

            // Reload page if we've shown all photos
            if (currentIndex === 0) {
                window.location.reload();
                return;
            }

            // Swap images
            currentImg.className = '';
            nextImg.className = 'active';

            // Update references
            var temp = currentImg;
            currentImg = nextImg;
            nextImg = temp;
            currentLink.href = currentImg.src;

            // Preload next image after transition
            setTimeout(function () {
                var nextIndex = (currentIndex + 1) % totalPhotos;
                nextImg.src = buildProxyUrl(photos[nextIndex].id);
                isTransitioning = false;
            }, 1000);
            
            // Schedule next transition
            setTimeout(nextImage, duration);
        }

        // Initialize slideshow if we have photos
        if (totalPhotos > 0) {
            // Show first image
            currentImg.src = buildProxyUrl(photos[0].id);
            currentImg.className = 'active';
            currentLink.href = currentImg.src;

            // Preload second image if available
            if (totalPhotos > 1) {
                nextImg.src = buildProxyUrl(photos[1].id);
            }

            // Start slideshow
            setTimeout(nextImage, duration);
        }

        // Update dimensions on resize
        window.addEventListener('resize', function() {
            screenWidth = window.innerWidth || document.documentElement.clientWidth || document.body.clientWidth;
            screenHeight = window.innerHeight || document.documentElement.clientHeight || document.body.clientHeight;
        });
    </script>
</body>
</html>