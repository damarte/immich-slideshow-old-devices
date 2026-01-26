<?php
/**
 * FINAL MANAGEMENT UI (management.php)
 * Consistently handles: Logging, Waking, Updating, and Quitting.
 */

// --- 1. CONFIGURATION ---
$tablet_ip = getenv('TABLET_IP',''); 
$kiosk_password = getenv('KIOSK_PASSWORD', ''); 
$slideshow_base_url = getenv('SLIDESHOW_BASE_URL','/');

$immich_url = rtrim(getenv('IMMICH_URL'), '/');
$api_key = getenv('IMMICH_API_KEY');

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

// --- 3. REMOTE COMMAND LOGIC ---
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    
    // Log incoming phone request
    file_put_contents('debug_kiosk.log', "[" . date('Y-m-d H:i:s') . "] PHONE REQUEST: " . json_encode($_POST) . PHP_EOL, FILE_APPEND);

    // Helper function to send commands and check for errors
    function sendKioskCommand($url) {
        $request_log = "[" . date('Y-m-d H:i:s') . "] TABLET REQUEST: " . json_encode($url, JSON_UNESCAPED_SLASHES) . PHP_EOL;
        file_put_contents('debug_kiosk.log', $request_log, FILE_APPEND);

        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_TIMEOUT, 5);
        $response = curl_exec($ch);
        $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        file_put_contents('debug_kiosk.log', "[" . date('Y-m-d H:i:s') . "] TABLET RESPONSE | Code: $http_code | Body: " . substr($response, 0, 600) . "..." . PHP_EOL, FILE_APPEND);

        if ($http_code !== 200) return "Error: Tablet unreachable (HTTP $http_code)";
        if (stripos($response, 'Wrong password') !== false) return "Error: Invalid Tablet Password";
        if (stripos($response, '<!DOCTYPE html>') !== false) return true; // Ignore Admin Panel HTML

        $json = json_decode($response, true);
        if (isset($json['status']) && $json['status'] === 'Error') {
            return "Error from Tablet: " . ($json['statustext'] ?? 'Unknown API Error');
        }
        return true; 
    }

    if (isset($_POST['action']) && $_POST['action'] === 'quit') {
        $quitUrl = "http://$tablet_ip:2323/?cmd=exitKiosk&password=" . urlencode($kiosk_password);
        $result = sendKioskCommand($quitUrl);
        $message = ($result === true) ? "Command sent: Closing Fully Kiosk." : $result;

    } elseif (isset($_POST['action']) && $_POST['action'] ===  'update'){
        // --- START UPDATE SEQUENCE (Nuclear Restart) ---
        
        // A. BUILD THE FINAL SLIDESHOW URL
        $params = http_build_query([
            'album_id' => $_POST['album_id'],
            'duration' => $_POST['duration'],
            'random'   => $_POST['random'],
            'orientation' => $_POST['orientation']
        ]);
        $finalUrl = $slideshow_base_url . '?' . $params;

        $common = ['password' => $kiosk_password];

        // B. THE RESTART SEQUENCE
        // 1. Force Screen On (Ensure hardware is awake)
        sendKioskCommand("http://$tablet_ip:2323/?" . http_build_query(array_merge($common, ['cmd' => 'screenOn'])));
        
        // 2. Restart App (Kills and relaunches Fully Kiosk fresh)
        $restartResult = sendKioskCommand("http://$tablet_ip:2323/?" . http_build_query(array_merge($common, ['cmd' => 'restartApp'])));
        
        if ($restartResult !== true) {
            $message = $restartResult; // Stop if the tablet didn't respond to restart
        } else {
            // C. WAIT FOR COLD BOOT
            // Android 4.4 needs about 10 seconds to fully relaunch the app and connect to Wi-Fi
            sleep(10);

            // D. LOAD THE SLIDESHOW
            $loadUrl = "http://$tablet_ip:2323/?cmd=loadURL&url=" . urlencode($finalUrl) . "&password=" . urlencode($kiosk_password);
            $result = sendKioskCommand($loadUrl);
            $message = ($result === true) ? "Success! Tablet restarted and slideshow loaded." : $result;
        }

        if ($result !== true) {
            $message = $result;
        } else {
            // D. WAIT FOR HARDWARE
            sleep(2);

            // E. LOAD THE SLIDESHOW
            $loadUrl = "http://$tablet_ip:2323/?cmd=loadURL&url=" . urlencode($finalUrl) . "&password=" . urlencode($kiosk_password);
            $result = sendKioskCommand($loadUrl);
            $message = ($result === true) ? "Success! Tablet is awake and updated." : $result;
        }
    }
}

// --- 4. FETCH ALBUMS ---
$opts = ["http" => ["header" => "x-api-key: $api_key\r\n"]];
$response = @file_get_contents("$immich_url/api/albums", false, stream_context_create($opts));
$albums = json_decode($response, true) ?: [];
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Remote Control</title>
    <link rel="icon" type="image/x-icon" href="/favicon.ico">
    <?php if ($message && stripos($message, 'Error') === false): ?>
        <meta http-equiv="refresh" content="5;url=management.php">
    <?php endif; ?>
    <style>
        body { font-family: sans-serif; background: #121212; color: white; padding: 15px; margin: 0; padding-bottom: 180px; }
        .container { max-width: 600px; margin: 0 auto; }
        .alert { padding: 12px; border-radius: 8px; margin-bottom: 20px; text-align: center; }
        .alert-success { background: #2e7d32; animation: fadeOut 1s ease-in 3s forwards; }
        .alert-error { background: #d32f2f; }
        @keyframes fadeOut { from { opacity: 1; } to { opacity: 0; visibility: hidden; height: 0; margin: 0; padding: 0; } }
        #loading-overlay { display: none; text-align: center; padding: 20px; }
        .spinner { width: 40px; height: 40px; margin: 10px auto; border: 4px solid #333; border-top: 4px solid #2196f3; border-radius: 50%; animation: spin 1s linear infinite; }
        @keyframes spin { 0% { transform: rotate(0deg); } 100% { transform: rotate(360deg); } }
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
        .btn-quit { background: #d32f2f; font-weight: bold; border: none; height: 45px; cursor: pointer; }
        label { font-size: 11px; color: #888; text-transform: uppercase; font-weight: bold; }
    </style>
</head>
<body>
<div class="container">
    <h1>Select Album</h1>
    <?php if ($message): 
        $class = (stripos($message, 'Error') !== false) ? 'alert-error' : 'alert-success';
    ?>
        <div class="alert <?= $class ?>"><?= htmlspecialchars($message) ?></div>
    <?php endif; ?>

    <div id="loading-overlay">
        <div class="spinner"></div>
        <p>Waking tablet & loading photos...</p>
    </div>

    <form id="manage-form" method="POST" onsubmit="showLoading()">
        <div class="album-grid">
            <?php foreach ($albums as $album): 
                $thumbId = $album['albumThumbnailAssetId'] ?? null;
                $proxyUrl = $thumbId ? "management.php?proxy_id=$thumbId" : "";
            ?>
                <label>
                    <input type="radio" name="album_id" value="<?= $album['id'] ?>" class="album-radio" required>
                    <div class="album-card">
                        <img src="<?= $proxyUrl ?>" loading="lazy" onerror="this.style.display='none'">
                        <p><?= htmlspecialchars($album['albumName']) ?></p>
                    </div>
                </label>
            <?php endforeach; ?>
        </div>

        <div class="footer">
            <div class="flex">
                <div style="flex:1;"><label>Sec</label><input type="number" name="duration" value="10"></div>
                <div style="flex:1;"><label>Random</label>
                    <select name="random"><option value="true">On</option><option value="false">Off</option></select>
                </div>
                <div style="flex:1;"><label>View</label>
                    <select name="orientation"><option value="all">All</option><option value="landscape">Landscape</option><option value="portrait">Portrait</option></select>
                </div>
            </div>
            <div class="flex">
                <button type="submit" name="action" value="update" class="btn-update">UPDATE TABLET</button>
            </div>
        </div>
    </form>
</div>
<script>
    function showLoading() {
        document.getElementById('loading-overlay').style.display = 'block';
        window.scrollTo(0,0);
    }
</script>
</body>
</html>
