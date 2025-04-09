<?php
require_once './ImmichApi.php';

$sizes = ['thumbnail', 'preview', 'fullsize'];

$immich_url = getenv('IMMICH_URL');
$immich_api_key = getenv('IMMICH_API_KEY');
$asset_id = isset($_GET['asset']) ? $_GET['asset'] : null;
$size = isset($_GET['size']) && in_array($_GET['size'], $sizes) ? $_GET['size'] : 'fullsize';

if (!$asset_id) {
    http_response_code(400);
    echo "Falta el parámetro 'asset'";
    exit;
}

try {
    $api = new ImmichApi($immich_url, $immich_api_key);
    $data = $api->getAsset($asset_id, $size);
} catch (\Exception $e) {
    http_response_code(500);
    echo "Error: " . $e->getMessage();
    exit;
}

// Enviar la imagen al cliente
header("Content-Type: $data[0]");
header("Content-Length: " . strlen($data[1]));
echo $data[1];
exit;