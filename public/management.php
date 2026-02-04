<?php

require_once './ImmichApi.php';
require_once './Configuration.php';

$message = "";

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['action']) && $_POST['action'] === 'update') {
        
        $selected_albums = $_POST['album_ids'] ?? [];

        if (empty($selected_albums)) {
            $message = "Error: You must select at least one album.";
        } else {
            try {
                $new_configuration = [
                    Configuration::ALBUM_ID   => implode(',', $selected_albums),
                    Configuration::CAROUSEL_DURATION    => (int)$_POST['duration'],
                    Configuration::RANDOM_ORDER      => $_POST['random'],
                    Configuration::CROP        => $_POST['crop'],
                    Configuration::ORIENTATION => $_POST['orientation'],
                    Configuration::BACKGROUND_COLOR => $_POST['background_color'],
                    Configuration::STATUS_BAR_STYLE => $_POST['status_bar_style'],
                ];

                Configuration::save($new_configuration);

                $message = "Settings saved";
            } catch (Exception $e) {
                $message = "Error: Unable to save configuration - " . $e->getMessage();
            }
        }
    }
}

$configuration = new Configuration();

// Configuration parameters with validation
$immich_url = $configuration->get(Configuration::IMMICH_URL);
$immich_api_key = $configuration->get(Configuration::IMMICH_API_KEY);

try {
    // Initialize API and fetch albums
    $api = new ImmichApi($immich_url, $immich_api_key);
    $albums = $api->getAlbums();
} catch (Exception $e) {
    $message = "Error: Unable to fetch albums - " . $e->getMessage();
}

// Get configuration
$album_ids = explode(',', $configuration->get(Configuration::ALBUM_ID)) ?? [];
$duration = $configuration->get(Configuration::CAROUSEL_DURATION) ?? 10;
$random = $configuration->get(Configuration::RANDOM_ORDER) ?? 'true';
$crop = $configuration->get(Configuration::CROP) ?? 'true';
$orientation = $configuration->get(Configuration::ORIENTATION) ?? 'all';
$background_color = $configuration->get(Configuration::BACKGROUND_COLOR) ?? 'black';
$status_bar_style = $configuration->get(Configuration::STATUS_BAR_STYLE) ?? 'black-translucent';
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, height=device-height, initial-scale=1, maximum-scale=1, minimum-scale=1, user-scalable=no, minimal-ui"/>
    <title>Immich Slideshow Management</title>
    <link rel="shortcut icon" type="image/x-icon" href="assets/favicon.ico?v=<?php echo filemtime('assets/favicon.ico'); ?>"/>
    <link rel="apple-touch-icon" sizes="180x180" href="assets/apple-icon-180.png?v=<?php echo filemtime('assets/apple-icon-180.png'); ?>"/>
    <link rel="icon" type="image/png" sizes="32x32" href="assets/favicon-32.png?v=<?php echo filemtime('assets/favicon-32.png'); ?>"/>
    <link rel="icon" type="image/png" sizes="16x16" href="assets/favicon-16.png?v=<?php echo filemtime('assets/favicon-16.png'); ?>"/>
    <link rel="stylesheet" href="assets/management.css?v=<?php echo filemtime('assets/management.css'); ?>"/>
</head>
<body>
<div class="container">
    <?php if ($message): ?>
        <div id="status-message" class="alert <?= (strpos($message, 'Error') !== false) ? 'alert-error' : 'alert-success' ?>">
            <?= htmlspecialchars($message) ?>
        </div>
    <?php endif; ?>

    <h1>Select albums</h1>
    <form method="POST">
        <div class="album-grid">
            <?php foreach ($albums as $album): 
                $assetId = $album['albumThumbnailAssetId'] ?? null;
                $thumbnailUrl = $assetId ? "thumbnail.php?asset=$assetId" : "";
                
                // Check if THIS album ID exists in our saved array
                $checked = in_array($album['id'], $album_ids) ? 'checked' : '';
            ?>
                <label for="<?= $album['id'] ?>">
                    <input type="checkbox" name="album_ids[]" id="<?= $album['id'] ?>" value="<?= $album['id'] ?>" class="album-checkbox" <?= $checked ?>>
                    <div class="album-card">
                        <div class="checkmark">✓</div>
                        <img src="<?= $thumbnailUrl ?>" loading="lazy">
                        <p><?= htmlspecialchars($album['albumName']) ?></p>
                    </div>
                </label>
            <?php endforeach; ?>
        </div>

        <div class="footer">
            <div class="flex">
                <div class="flex" style="flex:1; flex-direction: column;">
                    <label for="duration">Duration (seconds)</label>
                    <input type="number" name="duration" id="duration" value="<?= $duration ?>">
                </div>
                <div class="flex" style="flex:1; flex-direction: column;">
                    <label for="random">Random order</label>
                    <select name="random" id="random">
                        <option value="true" <?= ($random === 'true') ? 'selected' : '' ?>>On</option>
                        <option value="false" <?= ($random === 'false') ? 'selected' : '' ?>>Off</option>
                    </select>
                </div>
            </div>
            <div class="flex">
                <div class="flex" style="flex:1; flex-direction: column;">
                    <label for="crop">Crop to screen</label>
                    <select name="crop" id="crop">
                        <option value="true" <?= ($crop === 'true') ? 'selected' : '' ?>>On</option>
                        <option value="false" <?= ($crop === 'false') ? 'selected' : '' ?>>Off</option>
                    </select>
                </div>
                <div class="flex" style="flex:1; flex-direction: column;">
                    <label for="orientation">Orientation</label>
                    <select name="orientation" id="orientation">
                        <option value="all" <?= ($orientation) === 'all' ? 'selected' : '' ?>>All</option>
                        <option value="landscape" <?= ($orientation) === 'landscape' ? 'selected' : '' ?>>Landscape</option>
                        <option value="portrait" <?= ($orientation) === 'portrait' ? 'selected' : '' ?>>Portrait</option>
                    </select>
                </div>
            </div>
            <div class="flex">
                <div class="flex" style="flex:1; flex-direction: column;">
                    <label for="background_color">Background color</label>
                    <input type="color" name="background_color" id="background_color" />
                </div>
                <div class="flex" style="flex:1; flex-direction: column;">
                    <label for="status_bar_style">iOS status bar style</label>
                    <select name="status_bar_style" id="status_bar_style">
                        <option value="black-translucent" <?= ($status_bar_style) === 'black-translucent' ? 'selected' : '' ?>>Black translucent</option>
                        <option value="black" <?= ($status_bar_style) === 'black' ? 'selected' : '' ?>>Black</option>
                        <option value="default" <?= ($status_bar_style) === 'default' ? 'selected' : '' ?>>Default</option>
                    </select>
                </div>
            </div>
            <div class="flex">
                <button type="submit" name="action" value="update" class="btn-update" onclick="return validateSelection()" style="flex-grow: 1;">Save settings</button>
            </div>
        </div>
    </form>
</div>
<script>
    function validateSelection() {
        const checkedCount = document.querySelectorAll('.album-checkbox:checked').length;
        if (checkedCount === 0) {
            alert("Please select at least one album before saving");
            return false; // Prevents the form from submitting
        }
        return true;
    }

    function colorNameToHex(color) {
        const temp = document.createElement("div");
        temp.style.color = color;
        document.body.appendChild(temp);
        
        const style = window.getComputedStyle(temp).color;
        document.body.removeChild(temp);
        
        const rgb = style.match(/\d+/g).map(Number);
        return "#" + rgb.map(x => x.toString(16).padStart(2, '0')).join('');
    }

    // Wait for the page to load
    window.addEventListener('load', function() {
        const message = document.getElementById('status-message');
        if (message) {
            // Wait 3 seconds, then start fading
            setTimeout(() => {
                message.style.transition = "opacity 1s ease";
                message.style.opacity = "0";
                
                // Fully remove it from the layout after the fade
                setTimeout(() => {
                    message.style.display = "none";
                }, 1000); 
            }, 3000);
        }

        const hex = colorNameToHex("<?= $background_color ?>");
        document.querySelector('#background_color').value = hex;
    });
</script>
</body>
</html>