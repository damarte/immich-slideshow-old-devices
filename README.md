# Immich Slideshow for old Devices with Management UI

> [!IMPORTANT]
> For versions of Immich prior to 3.x, use version 1.x of Immich Slideshow for old Devices.

I added some stuff to this repo in order to get it working with the Fully Kiosk Browser on a Nixplay w10a. Generally this should work on tablets with android 19 (4.4). The main slideshow is is at the root URL while the management is done at /management.php. The tablet itself is running fully kiosk's browser, and Tasker to reboot the browser upon reboot/crash.

A simple PHP-based slideshow application for Immich album photos, designed to work on older devices and browsers (iOS 9, old Android, ECMAScript 2009...). It displays photos from a specified Immich album in a full-screen slideshow format.

One day I decided to dust off two old iPads I had in a drawer (an iPad 2 and an iPad mini) to use them as digital photo frames, so my parents could see their granddaughter's photos from an album of my Immich instance.

I searched for projects already created for this purpose and found the great [Immich Kiosk](https://github.com/damongolding/immich-kiosk), it's a good project but it doesn't work on such old devices, so I decided to create a simple alternative but that works on older browsers and devices.

> [!NOTE]
> If your device supports [Immich Kiosk](https://github.com/damongolding/immich-kiosk), maybe you should use it before this project.

> [!IMPORTANT]
> **This project is not affiliated with [Immich](https://github.com/immich-app/immich)**

## Features

- Full-screen slideshow of Immich album photos
- Configurable slide transition time
- Configurable crossfade length
- Optional overlay with the photo date and the albums it belongs to
- Optional active hours: black screen outside them, like a frame that switches off at night
- Automatic WebP to JPEG conversion for better compatibility
- Customizable background color
- Optional random order for photos
- Configurable status bar style for iOS devices
- Automatic page reload after showing all photos
- Built-in image caching for better performance
- Filter images by orientation (landscape/portrait/all)
- Use your Immich favorites as a source, alone or combined with albums
- Use photos taken since a date, within a date range, or with an Immich tag as a source
- Pause/resume slide
- Management UI [@JosephAntony1](https://github.com/JosephAntony1)
- Remote control support [@JosephAntony1](https://github.com/JosephAntony1)

## Requirements

- Docker and Docker Compose, or a Linux host/LXC/VM with Nginx and PHP-FPM (see [Running without Docker](#running-without-docker))
- Access to an Immich server
- Immich API key
- Album ID from your Immich server

## Installation

1. Clone this repository:

```bash
git clone https://github.com/yourusername/immich-slideshow-old-devices.git
cd immich-slideshow-old-devices
```

2. Copy the environment file and configure it:

```bash
cp .env.example .env
```

3. Edit the `.env` file with your settings:

```env
IMMICH_URL=http://your-immich-server:2283
IMMICH_API_KEY=your_api_key
ALBUM_ID=your_album_id,another_album_id
CAROUSEL_DURATION=5
CSS_BACKGROUND_COLOR=black
RANDOM_ORDER=false
STATUS_BAR_STYLE=black-translucent
IMAGES_ORIENTATION=all
CROP_TO_SCREEN=true
IMAGE_QUALITY=preview
TRANSITION_SECONDS=1
SHOW_INFO=false
```

## Usage

### Development

To run the application in development mode:

```bash
docker-compose up -d
```

### Production

For production deployment:

```bash
docker-compose -f docker-compose.prod.yml up -d
```

The application will be available at `http://localhost:8080`

## Running without Docker

Docker is the recommended way to run the slideshow. If you prefer a plain host, an LXC container (for example on Proxmox) or a VM, the app only needs Nginx and PHP-FPM with the `gd` and `curl` extensions. Tested on Debian 13 (PHP 8.4); a 1 vCPU / 512 MB container is enough.

1. Install the packages:

```bash
apt update
apt install -y nginx php-fpm php-gd php-curl git
```

2. Copy the application:

```bash
git clone https://github.com/damarte/immich-slideshow-old-devices.git /opt/immich-slideshow
rm -rf /var/www/html/*
cp -r /opt/immich-slideshow/public/. /var/www/html/
chown -R www-data:www-data /var/www/html
```

`www-data` must own the folder because the management UI writes `config.json` there.

3. Configure PHP-FPM. It does not pass the system environment to PHP, so the settings go on the pool instead of a `.env` file. Append the lines from [`deploy/php-fpm-env.conf`](deploy/php-fpm-env.conf) to `/etc/php/8.4/fpm/pool.d/www.conf`, with your values, and raise the memory limit like the Docker image does:

```bash
echo "memory_limit=256M" > /etc/php/8.4/fpm/conf.d/99-immich-slideshow.ini
```

4. Configure Nginx with [`deploy/nginx.conf`](deploy/nginx.conf). The `nginx.conf` at the repository root is for the Docker image (PHP-FPM on TCP port 9000); on Debian PHP-FPM listens on a Unix socket:

```bash
cp /opt/immich-slideshow/deploy/nginx.conf /etc/nginx/sites-available/default
```

5. Check and start:

```bash
php-fpm8.4 -t && systemctl restart php8.4-fpm nginx
systemctl enable php8.4-fpm nginx
```

The slideshow is served on port 80 (`http://your-host/`). Replace `8.4` with your PHP version in the paths above (`ls /run/php/`).

To update, `git pull` in `/opt/immich-slideshow` and copy `public/` again, keeping `/var/www/html/config.json` if you use the management UI.

## Environment Variables

| Variable             | Description                                          | Default           | Required |
| -------------------- | ---------------------------------------------------- | ----------------- | -------- |
| IMMICH_URL           | URL of your Immich server                            | -                 | Yes      |
| IMMICH_API_KEY       | Your Immich API key                                  | -                 | Yes      |
| ALBUM_ID             | Source(s) to display, comma separated: album IDs, `favorites`, `since:YYYY-MM-DD`, `range:YYYY-MM-DD..YYYY-MM-DD` or `tag:Name` (see [Photo sources](#photo-sources)) | -                 | Yes      |
| CAROUSEL_DURATION    | Time in seconds between slides                       | 5                 | No       |
| CSS_BACKGROUND_COLOR | Background color of the slideshow: a hex value (`#000000`, `#000`) or a common color name (`black`, `white`, `gray`...). `BACKGROUND_COLOR` is accepted as an alias | black             | No       |
| RANDOM_ORDER         | Show photos in random order                          | false             | No       |
| STATUS_BAR_STYLE     | Style of the iOS status bar                          | black-translucent | No       |
| IMAGES_ORIENTATION   | Orientation of the images (landscape/portrait/all)   | all               | No       |
| CROP_TO_SCREEN       | Crop images to fill the screen (true) or fit (false) | true              | No       |
| IMAGE_QUALITY        | Image size requested from Immich (preview/fullsize). `preview` is server-side resized and recommended; `fullsize` needs much more memory for large photos | preview           | No       |
| TRANSITION_SECONDS   | Length of the crossfade between photos, in seconds (0.2 to 10). Keep it shorter than CAROUSEL_DURATION | 1                 | No       |
| SHOW_INFO            | Show the photo date (dd/mm/yyyy) and the albums it belongs to in the bottom-left corner | false             | No       |
| ACTIVE_HOURS         | Show photos only between these hours (`HH:MM-HH:MM`, device clock). Outside them the screen stays black and nothing is loaded; the page reloads when the period starts. Ranges past midnight work (`18:00-02:00`). Empty means always on | (empty)           | No       |

## Photo sources

`ALBUM_ID` (and the `album_id` query parameter) accepts a comma separated list of sources. Photos from all sources are combined:

| Source | Shows |
| ------ | ----- |
| `<album id>` | Photos in that album |
| `favorites` | Your Immich favorites |
| `since:2024-01-01` | Photos taken on or after that date |
| `range:2024-01-01..2024-12-31` | Photos taken within that range (both days included) |
| `tag:Family` | Photos with that Immich tag. Use the full path for nested tags (`tag:Trips/Beach`), case-insensitive |

Example: `ALBUM_ID=tag:Family,since:2025-06-01,your_album_id`

Date and tag sources only return images (no videos) and leave out archived and hidden photos. Tag names containing commas are not supported. If your API key has restricted permissions, tag sources need `tag.read`.

## Management UI

You can override the environment variables using management UI, navigate to:

```
http://localhost:8080/management.php
```

The album grid includes a special "⭐ Favorites" card that shows your Immich favorites in the slideshow, selectable like any other album.

## Query Parameters

You can override the environment variables using query parameters in the URL:

- `album_id`: Override the ALBUM_ID (can be comma separated). Use the special value `favorites` to show your Immich favorites, e.g. `?album_id=favorites` or `?album_id=favorites,your_album_id`
- `duration`: Override the CAROUSEL_DURATION
- `background`: Override the CSS_BACKGROUND_COLOR
- `random`: Override the RANDOM_ORDER (use 'true' or 'false')
- `status_bar`: Override the STATUS_BAR_STYLE (use 'default', 'black-translucent', or 'black')
- `orientation`: Override the IMAGES_ORIENTATION (use 'landscape', 'portrait', or 'all')
- `transition`: Override the TRANSITION_SECONDS
- `info`: Override the SHOW_INFO (use 'true' or 'false')
- `hours`: Override the ACTIVE_HOURS (e.g. `07:00-23:00`)

Example:

```
http://localhost:8080/?random=true&duration=3
```

## Docker

```bash
docker compose up -d --build
```

## License

[MIT License](LICENSE)
