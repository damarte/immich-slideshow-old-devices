# Immich Slideshow for old Devices with Management UI

I added some stuff to this repo in order to get it working with the Fully Kiosk Browser on a Nixplay w10a. Generally this should work on tablets with android 19 (4.4). The main slideshow is is at the root URL while the management is done at /management.php. The tablet itself is running fully kiosk's browser, and Tasker to reboot the browser upon reboot/crash.

A simple PHP-based slideshow application for Immich album photos, designed to work on older devices and browsers (iOS 9, old Android, ECMAScript 2009...). It displays photos from a specified Immich album in a full-screen slideshow format.

One day I decided to dust off two old iPads I had in a drawer (an iPad 2 and an iPad mini) to use them as digital photo frames, so my parents could see their granddaughter's photos from an album of my Immich instance.

I searched for projects already created for this purpose and found the great [Immich Kiosk](https://github.com/damongolding/immich-kiosk), it's a good project but it doesn't work on such old devices, so I decided to create a simple alternative but that works on older browsers and devices.

> [!NOTE]
> If your device supports [Immich Kiosk](https://github.com/damongolding/immich-kiosk), maybe you should use it before this project.

> [!IMPORTANT]
> **This project is not affiliated with [Immich][immich-github-url]**

## Features

- Full-screen slideshow of Immich album photos
- Configurable slide transition time
- Automatic WebP to JPEG conversion for better compatibility
- Customizable background color
- Optional random order for photos
- Configurable status bar style for iOS devices
- Automatic page reload after showing all photos
- Built-in image caching for better performance
- Filter images by orientation (landscape/portrait/all)
- Pause/resume slide

## Requirements

- Docker and Docker Compose
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
ALBUM_ID=your_album_id
CAROUSEL_DURATION=5
CSS_BACKGROUND_COLOR=black
RANDOM_ORDER=false
STATUS_BAR_STYLE=black-translucent
IMAGES_ORIENTATION=all
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

## Environment Variables

| Variable | Description | Default | Required |
|----------|------------|---------|----------|
| IMMICH_URL | URL of your Immich server | - | Yes |
| IMMICH_API_KEY | Your Immich API key | - | Yes |
| ALBUM_ID | ID of the album to display | - | Yes |
| CAROUSEL_DURATION | Time in seconds between slides | 5 | No |
| CSS_BACKGROUND_COLOR | Background color of the slideshow | black | No |
| RANDOM_ORDER | Show photos in random order | false | No |
| STATUS_BAR_STYLE | Style of the iOS status bar | black-translucent | No |
| IMAGES_ORIENTATION | Orientation of the images (landscape/portrait/all) | all | No |

## Query Parameters

You can override the environment variables using query parameters in the URL:

- `album_id`: Override the ALBUM_ID
- `duration`: Override the CAROUSEL_DURATION
- `background`: Override the CSS_BACKGROUND_COLOR
- `random`: Override the RANDOM_ORDER (use 'true' or 'false')
- `status_bar`: Override the STATUS_BAR_STYLE (use 'default', 'black-translucent', or 'black')
- `orientation`: Override the IMAGES_ORIENTATION (use 'landscape', 'portrait', or 'all')

Example:
```
http://localhost:8080/?random=true&duration=3
```

## Management

http://localhost:8080/management.php

## Docker 

```bash
docker compose up -d --build
```

## License

[MIT License](LICENSE)
