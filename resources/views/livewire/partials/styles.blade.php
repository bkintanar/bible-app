<!-- Dark Mode Initialization - Must run immediately to prevent flash -->
<script>
    // Initialize dark mode IMMEDIATELY to prevent flash
    (function() {
        if (localStorage.getItem('darkMode') === 'true') {
            document.documentElement.classList.add('dark');
        }
    })();
</script>

<!-- Page Peel Effect Styles -->
<style>
    .book-container {
        perspective: 1000px;
        transform-style: preserve-3d;
    }

    .page {
        transform-style: preserve-3d;
    }

    /* Book spine shadow for visual depth */
    .book-spine-shadow {
        position: absolute;
        left: 0;
        top: 0;
        bottom: 0;
        width: 3px;
        background: linear-gradient(to right,
            rgba(0,0,0,0.15) 0%,
            rgba(0,0,0,0.05) 50%,
            transparent 100%);
        pointer-events: none;
    }
</style>
