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
var transitionMs = 1000;
var showInfo = false;
var infoCache = {};

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
    if (config.transition) transitionMs = config.transition * 1000;
    showInfo = !!config.showInfo;
    
    // Initial dimensions
    updateScreenDimensions();
    
    // Initialize slideshow if there are photos
    if (totalPhotos > 0) {
        // Show first image
        currentImg.src = buildProxyUrl(photos[0].id);
        currentImg.className = 'active';
        showPhotoInfo(photos[0].id);
        currentImg.addEventListener("error", function () {
            this.src = '/assets/apple-icon-180.png';
        });

        // Preload second image if available
        if (totalPhotos > 1) {
            nextImg.src = buildProxyUrl(photos[1].id);
            loadPhotoInfo(photos[1].id);
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
    showPhotoInfo(photos[currentIndex].id);

    var temp = currentImg;
    currentImg = nextImg;
    nextImg = temp;

    setTimeout(function () {
        var nextIndex = (currentIndex + 1) % totalPhotos;
        nextImg.src = buildProxyUrl(photos[nextIndex].id);
        loadPhotoInfo(photos[nextIndex].id);
        isTransitioning = false;
        
        // Resume the timer so it doesn't get stuck forever
        scheduleNextTransition();
    }, transitionMs);
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
    showPhotoInfo(photos[currentIndex].id);

    // Update references
    var temp = currentImg;
    currentImg = nextImg;
    nextImg = temp;

    // Preload next image after transition
    setTimeout(function () {
        var nextIndex = (currentIndex + 1) % totalPhotos;
        nextImg.src = buildProxyUrl(photos[nextIndex].id);
        loadPhotoInfo(photos[nextIndex].id);
        isTransitioning = false;
    }, transitionMs);
    
    // Schedule next transition
    scheduleNextTransition();
}

/**
 * Fetches date/albums of a photo (cached), then calls back
 */
function loadPhotoInfo(assetId, callback) {
    if (!showInfo) return;
    if (infoCache[assetId]) {
        if (callback) callback(infoCache[assetId]);
        return;
    }
    var xhr = new XMLHttpRequest();
    xhr.open('GET', '/info.php?asset=' + encodeURIComponent(assetId), true);
    xhr.onreadystatechange = function () {
        if (xhr.readyState !== 4) return;
        var data = null;
        if (xhr.status === 200) {
            try { data = JSON.parse(xhr.responseText); } catch (err) { data = null; }
        }
        if (data) infoCache[assetId] = data;
        if (callback) callback(data);
    };
    xhr.send(null);
}

/**
 * Fades the overlay out, swaps its text and fades it back in
 */
function showPhotoInfo(assetId) {
    if (!showInfo) return;
    var box = document.getElementById('photo-info');
    if (!box) return;
    box.className = 'photo-info';
    loadPhotoInfo(assetId, function (data) {
        // Ignore late answers for a photo that is no longer on screen
        if (photos[currentIndex].id !== assetId) return;
        if (!data || (!data.date && !(data.albums && data.albums.length))) return;
        document.getElementById('photo-date').innerHTML = escapeHtml(data.date || '');
        document.getElementById('photo-albums').innerHTML = escapeHtml((data.albums || []).join(' \u00b7 '));
        setTimeout(function () { box.className = 'photo-info visible'; }, transitionMs / 2);
    });
}

function escapeHtml(text) {
    return String(text).replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;');
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