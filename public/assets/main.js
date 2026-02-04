// Global variables for configuration
var currentLink;
var currentImg;
var nextImg;
var pauseIcon;
var photos;
var totalPhotos;
var duration;
var isPaused = false;
var timeoutId = null;
var screenWidth;
var screenHeight;
var currentIndex = 0;
var isTransitioning = false;

/**
 * Initializes the slideshow
 */
function initSlideshow(config) {
    // Get DOM references
    currentLink = document.getElementById('current-link');
    currentImg = document.getElementById('current-img');
    nextImg = document.getElementById('next-img');
    pauseIcon = document.getElementById('pause-icon');
    
    // Configuration
    photos = config.photos;
    totalPhotos = photos.length;
    duration = config.duration * 1000;
    
    // Initial dimensions
    updateScreenDimensions();
    
    // Initialize slideshow if there are photos
    if (totalPhotos > 0) {
        // Show first image
        currentImg.src = buildProxyUrl(photos[0].id);
        currentImg.className = 'active';
        currentImg.addEventListener("error", function () {
            this.src = '/assets/apple-icon-180.png';
        });

        // Preload second image if available
        if (totalPhotos > 1) {
            nextImg.src = buildProxyUrl(photos[1].id);
            nextImg.addEventListener("error", function () {
                this.src = '/assets/apple-icon-180.png';
            });
        }

        // Add click handler for pause/play
        currentLink.addEventListener('click', togglePause);

        // Start slideshow
        scheduleNextTransition();
    }
}

/**
 * Updates screen dimensions
 */
function updateScreenDimensions() {
    screenWidth = window.innerWidth || document.documentElement.clientWidth || document.body.clientWidth;
    screenHeight = window.innerHeight || document.documentElement.clientHeight || document.body.clientHeight;
}

/**
 * Builds proxy URL with necessary parameters
 */
function buildProxyUrl(assetId) {
    return '/proxy.php?asset=' + encodeURIComponent(assetId) + 
           '&width=' + encodeURIComponent(screenWidth) +
           '&height=' + encodeURIComponent(screenHeight);
}

/**
 * Loads the next image in the slideshow
 */
function previousImage() {
    if (totalPhotos === 0 || isTransitioning) return;
    if (typeof timeoutId !== 'undefined') clearTimeout(timeoutId);
    
    isTransitioning = true;
    
    // Adding totalPhotos ensures the result is always positive
    currentIndex = (currentIndex - 1 + totalPhotos) % totalPhotos;

    // If you want it to reload when hitting the "beginning"
    if (currentIndex === totalPhotos - 1) { 
        // This triggers if they press 'Back' at the very first photo
        // window.location.reload(); 
        // return;
    }

    // Unlike nextImage, we need the "new" (previous) image ready NOW
    nextImg.src = buildProxyUrl(photos[currentIndex].id);

    currentImg.className = '';
    nextImg.className = 'active';

    var temp = currentImg;
    currentImg = nextImg;
    nextImg = temp;

    setTimeout(function () {
        var nextIndex = (currentIndex + 1) % totalPhotos;
        nextImg.src = buildProxyUrl(photos[nextIndex].id);
        isTransitioning = false;
        
        // Resume the timer so it doesn't get stuck forever
        scheduleNextTransition();
    }, 1000);
}

/**
 * Loads the next image in the slideshow
 */
function nextImage() {
    if (totalPhotos === 0 || isTransitioning ) return;
    
    if (typeof timeoutId !== 'undefined') clearTimeout(timeoutId);

    isTransitioning = true;
    
    // Move to next index
    currentIndex = (currentIndex + 1) % totalPhotos;

    // Reload page if we've shown all photos
    if (currentIndex === 0) {
        window.location.reload();
        return;
    }

    // Swap images
    currentImg.className = '';
    nextImg.className = 'active';

    // Update references
    var temp = currentImg;
    currentImg = nextImg;
    nextImg = temp;

    // Preload next image after transition
    setTimeout(function () {
        var nextIndex = (currentIndex + 1) % totalPhotos;
        nextImg.src = buildProxyUrl(photos[nextIndex].id);
        isTransitioning = false;
    }, 1000);
    
    // Schedule next transition
    scheduleNextTransition();
}

/**
 * Schedules the next transition
 */
function scheduleNextTransition() {
    if (!isPaused) {
        timeoutId = setTimeout(nextImage, duration);
    }
}

/**
 * Toggles pause state
 */
function togglePause(e) {
    // If an event was passed, stop the default action (like scrolling)
    if (e && typeof e.preventDefault === 'function') {
        e.preventDefault();
    }
    isPaused = !isPaused;
    
    if (isPaused) {
        pauseIcon.classList.add('visible');
        if (timeoutId) {
            clearTimeout(timeoutId);
        }
    } else {
        pauseIcon.classList.remove('visible');
        scheduleNextTransition();
    }
}

// Resize event handler
window.addEventListener('resize', updateScreenDimensions);
// This makes the functions visible to index.php
window.nextImage = nextImage;
window.previousImage = previousImage;