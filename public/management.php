<?php
/**
 * REVISED MANAGEMENT UI (management.php)
 * Now purely server-side: Updates config.json for the tablet to pull.
 */

// --- 1. CONFIGURATION ---
$immich_url = rtrim(getenv('IMMICH_URL'), '/');
$api_key = getenv('IMMICH_API_KEY');
$config_file = 'config.json';

// --- 2. IMAGE PROXY LOGIC (For Thumbnails) ---
if (isset($_GET['proxy_id'])) {
    $asset_id = $_GET['proxy_id'];
    $thumb_url = "$immich_url/api/assets/$asset_id/thumbnail?size=thumbnail";
    $opts = ["http" => ["method" => "GET", "header" => "x-api-key: $api_key\r\nAccept: application/octet-stream\r\n", "timeout" => 5]];
    $data = @file_get_contents($thumb_url, false, stream_context_create($opts));
    if ($data) {
        header("Content-Type: image/jpeg");
        echo $data;
    } else {
        header("Content-Type: image/png");
        echo base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mNkYAAAAAYAAjCB0C8AAAAASUVORK5CYII=');
    }
    exit;
}

$message = "";

// --- 3. CONFIG SAVE LOGIC ---
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['action']) && $_POST['action'] === 'update') {
        // Build the settings array from POST data
        $new_settings = [
            'album_id'    => $_POST['album_id'],
            'duration'    => (int)$_POST['duration'],
            'random'      => ($_POST['random'] === 'true'),
            'orientation' => $_POST['orientation'],
            'updated_at'  => time() // Useful for the "Fingerprint" check we discussed
        ];

        // Save to config.json
        if (file_put_contents($config_file, json_encode($new_settings, JSON_PRETTY_PRINT))) {
            $message = "Success! Settings saved. The tablet will update within seconds.";
        } else {
            $message = "Error: Could not write to config.json. Check folder permissions.";
        }
    }
}

// --- 4. FETCH ALBUMS FROM IMMICH ---
$opts = ["http" => ["header" => "x-api-key: $api_key\r\n"]];
$response = @file_get_contents("$immich_url/api/albums", false, stream_context_create($opts));
$albums = json_decode($response, true) ?: [];

// Load current settings to pre-fill the form
$current = file_exists($config_file) ? json_decode(file_get_contents($config_file), true) : [];
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Slideshow Manager</title>
    <style>
        /* All your existing CSS remains the same */
        body { font-family: sans-serif; background: #121212; color: white; padding: 15px; margin: 0; padding-bottom: 180px; }
        .container { max-width: 600px; margin: 0 auto; }
        .alert { padding: 12px; border-radius: 8px; margin-bottom: 20px; text-align: center; }
        .alert-success { background: #2e7d32; }
        .alert-error { background: #d32f2f; }
        .album-grid { display: grid; grid-template-columns: repeat(auto-fill, minmax(120px, 1fr)); gap: 10px; }
        .album-radio { position: absolute; opacity: 0; width: 0; height: 0; }
        .album-card { background: #1e1e1e; border-radius: 8px; overflow: hidden; border: 2px solid transparent; cursor: pointer; }
        .album-card img { width: 100%; aspect-ratio: 1/1; object-fit: cover; display: block; background: #333; }
        .album-card p { margin: 5px; font-size: 12px; text-align: center; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; }
        .album-radio:checked + .album-card { border-color: #2196f3; background: #263238; box-shadow: 0 0 10px #2196f3; }
        .footer { position: fixed; bottom: 0; left: 0; right: 0; background: #1e1e1e; padding: 15px; border-top: 1px solid #333; z-index: 100; }
        .flex { display: flex; gap: 10px; margin-bottom: 10px; }
        select, input, button { background: #333; color: white; border: 1px solid #444; padding: 10px; border-radius: 5px; width: 100%; font-size: 14px; }
        .btn-update { background: #2196f3; font-weight: bold; border: none; height: 45px; cursor: pointer; }
        label { font-size: 11px; color: #888; text-transform: uppercase; font-weight: bold; }
    </style>
</head>
<body>
<div class="container">
    <h1>Select Album</h1>
    
    <?php if ($message): ?>
        <div class="alert <?= (strpos($message, 'Error') !== false) ? 'alert-error' : 'alert-success' ?>">
            <?= htmlspecialchars($message) ?>
        </div>
    <?php endif; ?>

    <form method="POST">
        <div class="album-grid">
            <?php foreach ($albums as $album): 
                $thumbId = $album['albumThumbnailAssetId'] ?? null;
                $proxyUrl = $thumbId ? "management.php?proxy_id=$thumbId" : "";
                $checked = ($current['album_id'] ?? '') === $album['id'] ? 'checked' : '';
            ?>
                <label>
                    <input type="radio" name="album_id" value="<?= $album['id'] ?>" class="album-radio" <?= $checked ?> required>
                    <div class="album-card">
                        <img src="<?= $proxyUrl ?>" loading="lazy" onerror="this.src='/assets/placeholder.png'">
                        <p><?= htmlspecialchars($album['albumName']) ?></p>
                    </div>
                </label>
            <?php endforeach; ?>
        </div>

        <div class="footer">
            <div class="flex">
                <div style="flex:1;">
                    <label>Sec</label>
                    <input type="number" name="duration" value="<?= $current['duration'] ?? 10 ?>">
                </div>
                <div style="flex:1;">
                    <label>Random</label>
                    <select name="random">
                        <option value="true" <?= ($current['random'] ?? true) ? 'selected' : '' ?>>On</option>
                        <option value="false" <?= !($current['random'] ?? true) ? 'selected' : '' ?>>Off</option>
                    </select>
                </div>
                <div style="flex:1;">
                    <label>View</label>
                    <select name="orientation">
                        <option value="all" <?= ($current['orientation'] ?? '') === 'all' ? 'selected' : '' ?>>All</option>
                        <option value="landscape" <?= ($current['orientation'] ?? '') === 'landscape' ? 'selected' : '' ?>>Landscape</option>
                        <option value="portrait" <?= ($current['orientation'] ?? '') === 'portrait' ? 'selected' : '' ?>>Portrait</option>
                    </select>
                </div>
            </div>
            <div class="flex">
                <button type="submit" name="action" value="update" class="btn-update">SAVE SETTINGS</button>
            </div>
        </div>
    </form>
</div>
</body>
</html>