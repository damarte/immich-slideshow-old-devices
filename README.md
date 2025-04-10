# Immich Slideshow for Old Devices

A simple PHP-based slideshow application for Immich album photos, designed to work on older devices and browsers (iOS 9, old Android...). It displays photos from a specified Immich album in a full-screen slideshow format.

## Features

- Full-screen slideshow of Immich album photos
- Configurable slide transition time
- Automatic WebP to JPEG conversion for better compatibility
- Customizable background color
- Optional random order for photos
- Configurable status bar style for iOS devices
- Hardware-accelerated transitions for smooth performance
- Click/tap on image to view full resolution version
- Automatic page reload after showing all photos
- Built-in image caching for better performance
- Filter images by orientation (landscape/portrait/all)

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

## Docker Hub

The application is available as a Docker image on Docker Hub:

```bash
docker pull damarte/immich-slideshow-old-devices:latest
```

## License

[MIT License](LICENSE)