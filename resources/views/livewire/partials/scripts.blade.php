<!-- JavaScript Functions -->
<script>
    function toggleDarkMode() {
        document.documentElement.classList.toggle('dark');
        localStorage.setItem('darkMode', document.documentElement.classList.contains('dark'));
    }



    // Touch/Swipe handling variables
    let touchStartX = null;
    let touchStartY = null;
    let touchStartTime = null;
    let isSwiping = false;
    let isPeeling = false;
    let peelDirection = null; // 'left' or 'right'

    // Swipe gesture configuration
    const SWIPE_CONFIG = {
        minDistance: 50,        // Minimum distance for a valid swipe (pixels)
        maxVerticalDistance: 100, // Maximum vertical movement allowed (pixels)
        maxTime: 500,           // Maximum time for a swipe gesture (ms)
        minVelocity: 0.3,       // Minimum velocity (distance/time)
        peelThreshold: 20,      // Minimum distance to start peel effect
        completeThreshold: 120  // Distance needed to complete page turn
    };

    // Get current chapter navigation URLs from the page
    function getNavigationUrls() {
        const bookContainer = document.querySelector('.book-container');

        if (!bookContainer) {
            return { prevUrl: null, nextUrl: null };
        }

        const prevUrl = bookContainer.getAttribute('data-prev-url') || null;
        const nextUrl = bookContainer.getAttribute('data-next-url') || null;

        return {
            prevUrl: prevUrl && prevUrl !== '' ? prevUrl : null,
            nextUrl: nextUrl && nextUrl !== '' ? nextUrl : null
        };
    }

    // Touch event handlers
    function handleTouchStart(e) {
        // Only handle single finger touches
        if (e.touches.length !== 1) return;

        const touch = e.touches[0];
        touchStartX = touch.clientX;
        touchStartY = touch.clientY;
        touchStartTime = Date.now();
        isSwiping = false;
    }

    function handleTouchMove(e) {
        if (!touchStartX || !touchStartY) return;

        // Only handle single finger touches
        if (e.touches.length !== 1) return;

        const touch = e.touches[0];
        const deltaX = touch.clientX - touchStartX;
        const deltaY = touch.clientY - touchStartY;
        const distance = Math.abs(deltaX);

        // Check if this looks like a horizontal swipe
        if (Math.abs(deltaX) > Math.abs(deltaY) && distance > 10) {
            isSwiping = true;
            // Prevent vertical scrolling during horizontal swipes
            e.preventDefault();

            // Start peel effect if we've moved far enough
            if (distance > SWIPE_CONFIG.peelThreshold && !isPeeling) {
                startPagePeel(deltaX > 0 ? 'right' : 'left');
            }

            // Update peel progress if we're peeling
            if (isPeeling) {
                updatePagePeel(distance);
            }
        }
    }

    function handleTouchEnd(e) {
        if (!touchStartX || !touchStartY || !touchStartTime) {
            resetTouch();
            return;
        }

        // Handle peel completion
        if (isPeeling) {
            const touch = e.changedTouches[0];
            const deltaX = touch.clientX - touchStartX;
            const distance = Math.abs(deltaX);

            // Complete page turn if swiped far enough
            if (distance >= SWIPE_CONFIG.completeThreshold) {
                completePageTurn(deltaX > 0 ? 'prev' : 'next');
            } else {
                // Snap back to original position
                snapBackPage();
            }
            resetTouch();
            return;
        }

        // Only handle if we were swiping (but didn't trigger peel)
        // If we were peeling, that's already handled above
        if (!isSwiping) {
            resetTouch();
            return;
        }

        // If we get here, it was a swipe but didn't trigger peel
        // This means it was likely a short/fast swipe, so use immediate navigation
        const touch = e.changedTouches[0];
        const deltaX = touch.clientX - touchStartX;
        const deltaY = touch.clientY - touchStartY;
        const deltaTime = Date.now() - touchStartTime;

        // Calculate swipe metrics
        const distance = Math.abs(deltaX);
        const velocity = distance / deltaTime;

        // Check if swipe meets our criteria for immediate navigation
        const isValidQuickSwipe =
            distance >= SWIPE_CONFIG.minDistance &&
            Math.abs(deltaY) <= SWIPE_CONFIG.maxVerticalDistance &&
            deltaTime <= SWIPE_CONFIG.maxTime &&
            velocity >= SWIPE_CONFIG.minVelocity;

        if (isValidQuickSwipe) {
            const direction = deltaX > 0 ? 'prev' : 'next'; // Swipe right = previous, swipe left = next
            const urls = getNavigationUrls();

            if (direction === 'prev' && urls.prevUrl) {
                navigateToChapter(urls.prevUrl);
            } else if (direction === 'next' && urls.nextUrl) {
                navigateToChapter(urls.nextUrl);
            }
        }

        resetTouch();
    }

    function resetTouch() {
        touchStartX = null;
        touchStartY = null;
        touchStartTime = null;
        isSwiping = false;
        isPeeling = false;
        peelDirection = null;

        // Clean up any leftover inline styles
        cleanupPreviewPages();

        // Clean up fallback classes
        const bookContainer = document.querySelector('.book-container');
        if (bookContainer) {
            bookContainer.classList.remove('showing-next', 'showing-prev');
        }
    }

    // Clean up any leftover inline styles on preview pages
    function cleanupPreviewPages() {
        const prevPage = document.querySelector('.page-prev');
        const nextPage = document.querySelector('.page-next');

        // Remove any inline styles
        if (prevPage) {
            prevPage.style.removeProperty('opacity');
            prevPage.style.removeProperty('z-index');
        }
        if (nextPage) {
            nextPage.style.removeProperty('opacity');
            nextPage.style.removeProperty('z-index');
        }
    }

    // Interactive page peel functions
    function startPagePeel(direction) {
        const currentPage = document.querySelector('.page-current');
        const bookContainer = document.querySelector('.book-container');

        if (!currentPage || !bookContainer) return;

        isPeeling = true;
        peelDirection = direction;

        // Add peel classes
        currentPage.classList.add('peeling', `peel-${direction}`);

        // Add fallback classes for browsers without :has() support
        if (direction === 'left') {
            bookContainer.classList.add('showing-next');
            bookContainer.classList.remove('showing-prev');
        } else {
            bookContainer.classList.add('showing-prev');
            bookContainer.classList.remove('showing-next');
        }

        // Reset any existing transitions
        currentPage.classList.remove('peeling-snap-back');
    }

    function updatePagePeel(distance) {
        const bookContainer = document.querySelector('.book-container');

        if (!bookContainer || !isPeeling) return;

        // Calculate progress (0 to 1) based on distance
        const maxDistance = 200; // Maximum distance for full peel
        const progress = Math.min(distance / maxDistance, 1);

        // Update CSS custom property
        bookContainer.style.setProperty('--peel-progress', progress);
    }

        function completePageTurn(direction) {
        const urls = getNavigationUrls();

        // Navigate directly to the URL
        if (direction === 'prev' && urls.prevUrl) {
            navigateToChapter(urls.prevUrl);
        } else if (direction === 'next' && urls.nextUrl) {
            navigateToChapter(urls.nextUrl);
        } else {
            snapBackPage();
        }
    }

        function snapBackPage() {
        const currentPage = document.querySelector('.page-current');
        const bookContainer = document.querySelector('.book-container');

        if (!currentPage || !bookContainer) return;

        // Add snap-back class
        currentPage.classList.add('peeling-snap-back');

        // Reset progress
        bookContainer.style.setProperty('--peel-progress', '0');

        // Clean up after animation
        setTimeout(() => {
            currentPage.classList.remove('peeling', 'peel-left', 'peel-right', 'peeling-snap-back');
            bookContainer.classList.remove('showing-next', 'showing-prev');
            bookContainer.style.setProperty('--peel-progress', '0');
        }, 300);
    }

    // Simple navigation function (replaces flipPage)
    function navigateToChapter(url) {
        // Simple navigation without animation
        window.location.href = url;
    }

    // Page entrance animation
    document.addEventListener('DOMContentLoaded', function() {
        const currentPage = document.querySelector('.page-current');

        if (currentPage) {
            setTimeout(() => {
                currentPage.style.transition = 'opacity 0.4s ease';
                currentPage.style.opacity = '1';
            }, 50);
        }

        // Clean up any conflicting preview page styles on page load
        cleanupPreviewPages();

        // Add touch event listeners to the main content area
        const bookContainer = document.querySelector('.book-container');
        if (bookContainer) {
            // Add passive: false to allow preventDefault on touchmove
            bookContainer.addEventListener('touchstart', handleTouchStart, { passive: true });
            bookContainer.addEventListener('touchmove', handleTouchMove, { passive: false });
            bookContainer.addEventListener('touchend', handleTouchEnd, { passive: true });
            bookContainer.addEventListener('touchcancel', resetTouch, { passive: true });
        }
    });

    // Handle browser back/forward buttons
    window.addEventListener('popstate', function(event) {
        // Page navigation handled by browser, no animation needed
        // Peel animations are only for gesture-based navigation
    });
</script>
