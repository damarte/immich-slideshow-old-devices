<?php
// Returns the date and album names of one photo, for the slideshow overlay
require_once './Configuration.php';
require_once './ImmichApi.php';

header('Content-Type: application/json; charset=utf-8');

$asset_id = $_GET['asset'] ?? '';
if (!preg_match('/^[0-9a-f-]{36}$/i', $asset_id)) {
    http_response_code(400);
    echo json_encode(['error' => 'invalid asset']);
    exit;
}

try {
    $configuration = new Configuration();
    $api = new ImmichApi(
        $configuration->get(Configuration::IMMICH_URL) ?? '',
        $configuration->get(Configuration::IMMICH_API_KEY) ?? ''
    );
    $info = $api->getAssetInfo($asset_id);

    // localDateTime is the capture time in the photo's own time zone
    $date = '';
    if (!empty($info['date']) && preg_match('/^(\d{4})-(\d{2})-(\d{2})/', $info['date'], $m)) {
        $date = "$m[3]/$m[2]/$m[1]";
    }

    header('Cache-Control: public, max-age=3600');
    echo json_encode(['date' => $date, 'albums' => $info['albums']]);
} catch (Exception $e) {
    http_response_code(502);
    echo json_encode(['error' => 'immich']);
}
