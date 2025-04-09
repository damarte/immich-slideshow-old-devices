<?php
require_once './ImmichApi.php';

$immich_url = getenv('IMMICH_URL');
$immich_api_key = getenv('IMMICH_API_KEY');
$album_id = $_GET['album_id'] ?? getenv('ALBUM_ID');
$carousel_duration = (int)($_GET['duration'] ?? getenv('CAROUSEL_DURATION') ?? 5);
$image_size = $_GET['size'] ?? getenv('IMAGE_SIZE') ?? 'fullsize';
$background = $_GET['background'] ?? getenv('CSS_BACKGROUND_COLOR') ?? 'black';

if (!$album_id) {
    http_response_code(400);
    echo "Invalid parameter 'album_id'";
    exit;
}

try {
    $api = new ImmichApi($immich_url, $immich_api_key);
    $photos = $api->getAlbumAssets($album_id);
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
    <meta name="viewport" content="width=device-width, initial-scale=1.0"/>
    <meta name="mobile-web-app-capable" content="yes"/>
	<meta name="apple-mobile-web-app-capable" content="yes"/>
	<meta name="apple-mobile-web-app-status-bar-style" content="black-translucent"/>
	<meta name="apple-mobile-web-app-status-bar" content="black-translucent"/>
	<meta name="theme-color" content="black"/>
    <title>Immich slideshow</title>
    <link rel="shortcut icon" type="image/x-icon" href="/assets/favicon.ico"/>
    <link rel="apple-touch-icon" sizes="180x180" href="/assets/apple-touch-icon.png"/>
	<link rel="icon" type="image/png" sizes="32x32" href="/assets/favicon-32.png"/>
	<link rel="icon" type="image/png" sizes="16x16" href="/assets/favicon-16.png"/>
    <style>
        body {
            margin: 0;
            padding: 0;
            overflow: hidden;
            background-color: <?php echo $background; ?>;
        }
        .carousel {
            position: relative;
            width: 100%;
            height: 100vh;
            overflow: hidden;
        }
        .carousel img {
            position: absolute;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            object-fit: contain;
            opacity: 0;
            transition: opacity 1s ease-in-out;
        }
        .carousel img.active {
            opacity: 1;
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

            // Intercambiar imágenes
            currentImg.className = '';
            nextImg.className = 'active';

            // Actualizar referencias
            var temp = currentImg;
            currentImg = nextImg;
            nextImg = temp;
            currentLink.href = currentImg.src;

            // Precargar la siguiente imagen
            var nextIndex = (currentIndex + 1) % totalPhotos;
            nextImg.src = '/proxy.php?asset=' + encodeURIComponent(photos[nextIndex].id) + '&size=' + encodeURIComponent(imageSize);

            // Programar el próximo cambio
            setTimeout(nextImage, duration);
        }
    </script>
</body>
</html>