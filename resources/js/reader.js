// Reader page behaviour: text size, dark mode, swipe / page peel, keyboard navigation.
// A Vite entry (cached, immutable) instead of ~11 KB of inline script on every chapter page.
// Module scope: the two functions called from inline onclick handlers are exposed on window.

// Reader text size: A-/A+ in the header. Client-side only (it used to be a Livewire round-trip).
const READER_FONT_SIZES = ['sm', 'base', 'lg', 'xl', '2xl'];
function readerFontSize(step) {
    const root = document.documentElement;
    const current = READER_FONT_SIZES.indexOf(root.getAttribute('data-font-size') || 'base');
    const next = READER_FONT_SIZES[Math.min(READER_FONT_SIZES.length - 1, Math.max(0, (current < 0 ? 1 : current) + step))];
    root.setAttribute('data-font-size', next);
    try { localStorage.setItem('fontSize', next); } catch (e) {}
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
    // === null, not falsy: a touch at x = 0 (the screen edge) is a real touch.
    if (touchStartX === null || touchStartY === null) return;

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
    if (touchStartX === null || touchStartY === null || touchStartTime === null) {
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
// Readers who ask for less motion get the same swipe navigation without the page-peel animation.
const REDUCED_MOTION = !!(window.matchMedia && window.matchMedia('(prefers-reduced-motion: reduce)').matches);

function startPagePeel(direction) {
    const currentPage = document.querySelector('.page-current');
    const bookContainer = document.querySelector('.book-container');

    if (!currentPage || !bookContainer) return;

    isPeeling = true;
    peelDirection = direction;

    if (REDUCED_MOTION) return;

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
    if (REDUCED_MOTION) return;
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

            // Keyboard navigation - Left and Right arrow keys
function handleKeyboardNavigation(e) {
    // Only handle left/right arrow keys
    if (e.key !== 'ArrowLeft' && e.key !== 'ArrowRight') {
        return;
    }

    // Skip if typing or on a control that uses the arrow keys itself
    if (['INPUT', 'TEXTAREA', 'SELECT'].includes(e.target.tagName) || e.target.isContentEditable) {
        return;
    }

    // Skip if modifier keys are pressed
    if (e.metaKey || e.ctrlKey || e.shiftKey || e.altKey) {
        return;
    }

    // Skip while a dialog (the book selector) is OPEN. It is always in the DOM since it became
    // client-side (x-show), so presence alone disabled arrow-key navigation entirely.
    const openDialog = [...document.querySelectorAll('[role="dialog"]')].some((el) => el.offsetParent !== null);
    if (openDialog) {
        return;
    }

    // Skip if search input is visible (search mode is active)
    const searchInput = document.querySelector('input[wire\\:model="searchQuery"]');
    if (searchInput && searchInput.offsetParent !== null) {
        return;
    }

    // Only proceed if we have the book container (we're on chapter view)
    const bookContainer = document.querySelector('.book-container');
    if (!bookContainer) {
        return;
    }

    // Only swallow the key when it navigates: on Gen 1 (no previous chapter) ArrowLeft must still
    // scroll the page horizontally like any other.
    const urls = getNavigationUrls();
    const target = e.key === 'ArrowLeft' ? urls.prevUrl : urls.nextUrl;

    if (target) {
        e.preventDefault();
        navigateToChapter(target);
    }
}

document.addEventListener('keydown', handleKeyboardNavigation);

// Strong's details on tap/click. The words carry them in title="", which touch screens never show,
// so mobile readers could not see a Strong's number at all. One delegated listener and one popover
// (appended to <body>, outside Livewire's morphing). Words are deliberately not made focusable: a
// chapter has ~700 of them, which would be ~700 tab stops.
const strongsPopover = (() => {
    let el = null;

    function ensure() {
        if (!el) {
            el = document.createElement('div');
            el.id = 'strongs-popover';
            el.className = 'strongs-popover';
            el.setAttribute('role', 'status');
            el.hidden = true;
            document.body.appendChild(el);
        }
        return el;
    }

    function show(word) {
        const pop = ensure();
        const numbers = (word.dataset.strongs || '').split(',').filter(Boolean).join(', ');
        pop.textContent = `${word.textContent.trim()} \u2014 Strong's ${numbers}` + (word.dataset.morph ? ` \u00b7 ${word.dataset.morph}` : '');
        pop.hidden = false;
        const rect = word.getBoundingClientRect();
        const left = Math.max(8, Math.min(window.innerWidth - pop.offsetWidth - 8, rect.left));
        const below = rect.bottom + 6 + pop.offsetHeight < window.innerHeight;
        pop.style.left = `${left}px`;
        pop.style.top = `${below ? rect.bottom + 6 : rect.top - pop.offsetHeight - 6}px`;
    }

    function hide() {
        if (el) el.hidden = true;
    }

    return { show, hide };
})();

document.addEventListener('click', (e) => {
    const word = e.target.closest('.strong-word[data-strongs]');
    if (word) {
        strongsPopover.show(word);
    } else if (!e.target.closest('#strongs-popover')) {
        strongsPopover.hide();
    }
});
document.addEventListener('keydown', (e) => { if (e.key === 'Escape') strongsPopover.hide(); });
document.addEventListener('scroll', () => strongsPopover.hide(), true);

Object.assign(window, { readerFontSize }); // toggleDarkMode lives in partials/dark-mode-head
