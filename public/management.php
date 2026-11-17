<?php

require_once './ImmichApi.php';
require_once './Configuration.php';

$message = "";

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['action']) && $_POST['action'] === 'update') {
        
        $selected_albums = $_POST['album_ids'] ?? [];

        // Date/tag sources typed by hand, kept next to the selected albums
        $extra_sources = [];
        $invalid_sources = [];
        foreach (explode(',', $_POST['extra_sources'] ?? '') as $source) {
            $source = trim($source);
            if ($source === '') continue;
            if (isExtraSource($source)) {
                $extra_sources[] = $source;
            } else {
                $invalid_sources[] = $source;
            }
        }

        // Active hours: empty means always on
        $active_hours = '';
        if (($_POST['schedule'] ?? 'off') === 'on') {
            $from = $_POST['active_from'] ?? '';
            $to = $_POST['active_to'] ?? '';
            if (preg_match('/^\d{2}:\d{2}$/', $from) && preg_match('/^\d{2}:\d{2}$/', $to) && $from !== $to) {
                $active_hours = "$from-$to";
            } else {
                $invalid_sources[] = "active hours $from-$to";
            }
        }

        $transition = (float)str_replace(',', '.', $_POST['transition'] ?? '1');
        if ($transition < 0.2 || $transition > 10) {
            $transition = 1;
        }

        if (!empty($invalid_sources)) {
            $message = "Error: Invalid value: " . htmlspecialchars(implode(', ', $invalid_sources))
                . ". Expected since:YYYY-MM-DD, range:YYYY-MM-DD..YYYY-MM-DD or tag:Name.";
        } elseif (empty($selected_albums) && empty($extra_sources)) {
            $message = "Error: You must select at least one album or add a source.";
        } else {
            try {
                $new_configuration = [
                    Configuration::ALBUM_ID   => implode(',', array_merge($selected_albums, $extra_sources)),
                    Configuration::CAROUSEL_DURATION    => (int)$_POST['duration'],
                    Configuration::RANDOM_ORDER      => $_POST['random'],
                    Configuration::CROP        => $_POST['crop'],
                    Configuration::ORIENTATION => $_POST['orientation'],
                    Configuration::BACKGROUND_COLOR => $_POST['background_color'],
                    Configuration::STATUS_BAR_STYLE => $_POST['status_bar_style'],
                    Configuration::TRANSITION_SECONDS => (string)$transition,
                    Configuration::SHOW_INFO => ($_POST['show_info'] ?? 'false') === 'true' ? 'true' : 'false',
                    Configuration::ACTIVE_HOURS => $active_hours,
                ];

                Configuration::save($new_configuration);

                $message = "Settings saved";
            } catch (Exception $e) {
                $message = "Error: Unable to save configuration - " . $e->getMessage();
            }
        }
    }
}

/**
 * since:YYYY-MM-DD, range:YYYY-MM-DD..YYYY-MM-DD or tag:Name.
 * Dates must be real calendar dates, not just the right shape.
 */
function isExtraSource(string $source): bool {
    if (preg_match('/^since:(\d{4})-(\d{2})-(\d{2})$/', $source, $m)) {
        return checkdate((int)$m[2], (int)$m[3], (int)$m[1]);
    }
    if (preg_match('/^range:(\d{4})-(\d{2})-(\d{2})\.\.(\d{4})-(\d{2})-(\d{2})$/', $source, $m)) {
        return checkdate((int)$m[2], (int)$m[3], (int)$m[1])
            && checkdate((int)$m[5], (int)$m[6], (int)$m[4]);
    }
    return (bool)preg_match('/^tag:.+$/', $source);
}

$configuration = new Configuration();

// Configuration parameters with validation
$immich_url = $configuration->get(Configuration::IMMICH_URL);
$immich_api_key = $configuration->get(Configuration::IMMICH_API_KEY);

$albums = [];
$favorites_thumbnail_asset_id = null;

try {
    // Initialize API and fetch albums
    $api = new ImmichApi($immich_url, $immich_api_key);
    $albums = $api->getAlbums();

    try {
        $favorites_thumbnail_asset_id = $api->getFirstFavoriteAssetId();
    } catch (Exception $e) {
        // The favorites card is still shown with a placeholder thumbnail
        error_log("Warning: Unable to fetch favorites thumbnail - " . $e->getMessage());
    }
} catch (Exception $e) {
    $message = "Error: Unable to fetch albums - " . $e->getMessage();
}

// Get configuration
$album_ids = empty($configuration->get(Configuration::ALBUM_ID)) ? [] : explode(',', $configuration->get(Configuration::ALBUM_ID));
$duration = $configuration->get(Configuration::CAROUSEL_DURATION) ?? 10;
$random = $configuration->get(Configuration::RANDOM_ORDER) ?? 'true';
$crop = $configuration->get(Configuration::CROP) ?? 'true';
$orientation = $configuration->get(Configuration::ORIENTATION) ?? 'all';
$background_color = $configuration->get(Configuration::BACKGROUND_COLOR) ?? 'black';
$status_bar_style = $configuration->get(Configuration::STATUS_BAR_STYLE) ?? 'black-translucent';
$extra_sources = implode(', ', array_filter(array_map('trim', $album_ids), 'isExtraSource'));
$transition = $configuration->get(Configuration::TRANSITION_SECONDS) ?? '1';
$show_info = filter_var($configuration->get(Configuration::SHOW_INFO) ?? 'false', FILTER_VALIDATE_BOOLEAN);
$active_from = '07:00';
$active_to = '23:00';
$schedule_on = false;
if (preg_match('/^\s*(\d{1,2}):(\d{2})\s*-\s*(\d{1,2}):(\d{2})\s*$/', $configuration->get(Configuration::ACTIVE_HOURS) ?? '', $m)) {
    $schedule_on = true;
    $active_from = sprintf('%02d:%s', $m[1], $m[2]);
    $active_to = sprintf('%02d:%s', $m[3], $m[4]);
}
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
            <?php
                $favorites_id = Configuration::FAVORITES_ID;
                $favorites_thumbnail = $favorites_thumbnail_asset_id
                    ? "thumbnail.php?asset=$favorites_thumbnail_asset_id"
                    : "assets/apple-icon-180.png";
                $favorites_checked = in_array($favorites_id, $album_ids) ? 'checked' : '';
            ?>
            <label for="<?= $favorites_id ?>">
                <input type="checkbox" name="album_ids[]" id="<?= $favorites_id ?>" value="<?= $favorites_id ?>" class="album-checkbox" <?= $favorites_checked ?>>
                <div class="album-card">
                    <div class="checkmark">✓</div>
                    <img src="<?= $favorites_thumbnail ?>" loading="lazy">
                    <p>⭐ Favorites</p>
                </div>
            </label>
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

        <div class="extra-sources">
            <label for="extra_sources">Other sources (optional, comma separated)</label>
            <input type="text" name="extra_sources" id="extra_sources" value="<?= htmlspecialchars($extra_sources) ?>"
                   placeholder="since:2024-01-01, range:2024-01-01..2024-12-31, tag:Family">
            <small>Photos taken since a date, within a date range, or with an Immich tag. Combined with the albums selected above.</small>
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
                <div class="flex" style="flex:1; flex-direction: column;">
                    <label for="transition">Transition (seconds)</label>
                    <input type="number" name="transition" id="transition" min="0.2" max="10" step="0.1" value="<?= htmlspecialchars($transition) ?>">
                </div>
                <div class="flex" style="flex:1; flex-direction: column;">
                    <label for="show_info">Show date and albums</label>
                    <select name="show_info" id="show_info">
                        <option value="true" <?= $show_info ? 'selected' : '' ?>>On</option>
                        <option value="false" <?= !$show_info ? 'selected' : '' ?>>Off</option>
                    </select>
                </div>
            </div>
            <div class="flex">
                <div class="flex" style="flex:1; flex-direction: column;">
                    <label for="schedule">Active hours</label>
                    <select name="schedule" id="schedule" onchange="toggleSchedule()">
                        <option value="off" <?= !$schedule_on ? 'selected' : '' ?>>Always on</option>
                        <option value="on" <?= $schedule_on ? 'selected' : '' ?>>Only between</option>
                    </select>
                </div>
                <div class="flex" style="flex:1; flex-direction: column;">
                    <label for="active_from">From</label>
                    <input type="time" name="active_from" id="active_from" value="<?= $active_from ?>">
                </div>
                <div class="flex" style="flex:1; flex-direction: column;">
                    <label for="active_to">To</label>
                    <input type="time" name="active_to" id="active_to" value="<?= $active_to ?>">
                </div>
            </div>
            <div class="flex">
                <button type="submit" name="action" value="update" class="btn-update" onclick="return validateSelection()" style="flex-grow: 1;">Save settings</button>
            </div>
        </div>
    </form>
</div>
<script>
    function toggleSchedule() {
        const on = document.getElementById('schedule').value === 'on';
        document.getElementById('active_from').disabled = !on;
        document.getElementById('active_to').disabled = !on;
    }

    function validateSelection() {
        const checkedCount = document.querySelectorAll('.album-checkbox:checked').length;
        const extra = document.getElementById('extra_sources').value.trim();
        if (checkedCount === 0 && extra === '') {
            alert("Please select at least one album or add a source before saving");
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

        toggleSchedule();

        const hex = colorNameToHex("<?= $background_color ?>");
        document.querySelector('#background_color').value = hex;
    });
</script>
</body>
</html>