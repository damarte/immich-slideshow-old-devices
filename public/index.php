<?php
require_once './ImmichApi.php';

$immich_url = getenv('IMMICH_URL');
$immich_api_key = getenv('IMMICH_API_KEY');
$album_id = $_GET['album_id'] ?? getenv('ALBUM_ID');
$carousel_duration = (int)($_GET['duration'] ?? getenv('CAROUSEL_DURATION') ?? 5);
$image_size = $_GET['size'] ?? getenv('IMAGE_SIZE') ?? 'fullsize';
$background = $_GET['background'] ?? getenv('CSS_BACKGROUND_COLOR') ?? 'black';
$random_order = filter_var($_GET['random'] ?? getenv('RANDOM_ORDER') ?? 'false', FILTER_VALIDATE_BOOLEAN);
$status_bar_style = $_GET['status_bar'] ?? getenv('STATUS_BAR_STYLE') ?? 'black-translucent';

if (!$album_id) {
    http_response_code(400);
    echo "Invalid parameter 'album_id'";
    exit;
}

try {
    $api = new ImmichApi($immich_url, $immich_api_key);
    $photos = $api->getAlbumAssets($album_id);
    
    if ($random_order) {
        shuffle($photos);
    }
} catch (\Exception $e) {
    http_response_code(500);
    echo "Error: " . $e->getMessage();
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
	<meta name="apple-mobile-web-app-status-bar-style" content="<?php echo $status_bar_style; ?>"/>
	<meta name="apple-mobile-web-app-status-bar" content="<?php echo $status_bar_style; ?>"/>
	<meta name="theme-color" content="black"/>
    <title>Immich Slideshow</title>
    <link rel="shortcut icon" type="image/x-icon" href="/assets/favicon.ico"/>
    <link rel="apple-touch-icon" sizes="180x180" href="/assets/apple-icon-180.png"/>
	<link rel="icon" type="image/png" sizes="32x32" href="/assets/favicon-32.png"/>
	<link rel="icon" type="image/png" sizes="16x16" href="/assets/favicon-16.png"/>
    <style>
        html, body {
            margin: 0;
            padding: 0;
            width: 100%;
            height: 100%;
            overflow: hidden;
            background-color: <?php echo $background; ?>;
            -webkit-text-size-adjust: 100%;
        }
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
        .carousel img {
            position: absolute;
            top: 50%;
            left: 50%;
            max-width: 100%;
            max-height: 100%;
            width: auto;
            height: auto;
            -webkit-transform: translate(-50%, -50%);
            transform: translate(-50%, -50%);
            opacity: 0;
            -webkit-transition: opacity 1s ease-in-out;
            transition: opacity 1s ease-in-out;
            -webkit-backface-visibility: hidden;
            backface-visibility: hidden;
        }
        .carousel img.active {
            opacity: 1;
        }
        .carousel a {
            display: block;
            width: 100%;
            height: 100%;
        }
    </style>
</head>
<body>
    <div class="carousel">
        <a href="#" id="current-link" target="_blank">
            <img id="current-img" alt=""/>
            <img id="next-img" alt=""/>
        </a>
    </div>
    <script>
        var currentLink = document.getElementById('current-link');
        var currentImg = document.getElementById('current-img');
        var nextImg = document.getElementById('next-img');
        var photos = <?php echo json_encode($photos); ?>;
        var totalPhotos = photos.length;
        var currentIndex = 0;
        var duration = <?php echo $carousel_duration; ?> * 1000;
        var imageSize = "<?php echo $image_size; ?>";

        // Iniciar el carrusel si hay fotos
        if (totalPhotos > 0) {
            // Mostrar la primera imagen
            currentImg.src = '/proxy.php?asset=' + encodeURIComponent(photos[0].id) + '&size=' + encodeURIComponent(imageSize);
            currentImg.className = 'active';
            currentLink.href = currentImg.src;

            // Precargar la siguiente imagen si existe
            if (totalPhotos > 1) {
                nextImg.src = '/proxy.php?asset=' + encodeURIComponent(photos[1].id) + '&size=' + encodeURIComponent(imageSize);
            }

            // Iniciar el cambio de imágenes
            setTimeout(nextImage, duration);
        }

        // Cambiar a la siguiente imagen
        function nextImage() {
            if (totalPhotos === 0) return;

            // Avanzar al siguiente índice
            currentIndex = (currentIndex + 1) % totalPhotos;

            // Si hemos vuelto al principio, recargar la página
            if (currentIndex === 0) {
                window.location.reload();
                return;
            }

            // Intercambiar imágenes
            currentImg.className = '';
            nextImg.className = 'active';

            // Actualizar referencias
            var temp = currentImg;
            currentImg = nextImg;
            nextImg = temp;
            currentLink.href = currentImg.src;

            // Precargar la siguiente imagen cuando se haya ocultado la anterior
            setTimeout(function () {
                var nextIndex = (currentIndex + 1) % totalPhotos;
                nextImg.src = '/proxy.php?asset=' + encodeURIComponent(photos[nextIndex].id) + '&size=' + encodeURIComponent(imageSize);
            }, 1000);
            
            // Programar el próximo cambio
            setTimeout(nextImage, duration);
        }
    </script>
</body>
</html>