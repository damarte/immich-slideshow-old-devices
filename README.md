# Immich Slideshow for Old Devices

A simple PHP-based slideshow application for Immich photo albums, designed to work on older devices and browsers (iOS 9, old Android...). It displays photos from a specified Immich album in a full-screen slideshow format.

## Features

- Full-screen slideshow of Immich album photos
- Configurable slide transition time
- Supports different image sizes (thumbnail, preview, fullsize)
- Automatic WebP to JPEG conversion for better compatibility
- Customizable background color

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
IMAGE_SIZE=fullsize
CSS_BACKGROUND_COLOR=black
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
| IMAGE_SIZE | Size of images (thumbnail/preview/fullsize) | fullsize | No |
| CSS_BACKGROUND_COLOR | Background color of the slideshow | black | No |

## License

[MIT License](LICENSE)