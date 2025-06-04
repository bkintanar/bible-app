<!-- Dark Mode Initialization - Must run immediately to prevent flash -->
<script>
    // Initialize dark mode IMMEDIATELY to prevent flash
    (function() {
        if (localStorage.getItem('darkMode') === 'true') {
            document.documentElement.classList.add('dark');
        }
    })();
</script>

<!-- Book Flip Effect Styles -->
<style>
    .book-container {
        perspective: 1000px;
        transform-style: preserve-3d;
    }

    .page {
        transform-style: preserve-3d;
        transition: transform 0.3s ease;
    }

    /* Debug: Make preview pages slightly visible for testing */
    .page-prev, .page-next {
        opacity: 0.1;
    }

    /* During flip animation, make preview pages fully visible */
    .page-current.flipping-next ~ .page-next,
    .page-current.flipping-prev ~ .page-prev {
        opacity: 1 !important;
        z-index: 1 !important;
    }

    .page-current.flipping-next {
        animation: flipCurrentToNext 0.8s cubic-bezier(0.25, 0.46, 0.45, 0.94) forwards;
        transform-origin: left center;
        z-index: 3;
    }

    .page-current.flipping-prev {
        animation: flipCurrentToPrev 0.8s cubic-bezier(0.25, 0.46, 0.45, 0.94) forwards;
        transform-origin: right center;
        z-index: 3;
    }

    @keyframes flipCurrentToNext {
        0% {
            transform: rotateY(0deg);
            box-shadow: 0 0 20px rgba(0,0,0,0.1);
        }
        50% {
            transform: rotateY(-90deg);
            box-shadow: 10px 0 30px rgba(0,0,0,0.3);
        }
        100% {
            transform: rotateY(-180deg);
            box-shadow: 0 0 20px rgba(0,0,0,0.1);
        }
    }

    @keyframes flipCurrentToPrev {
        0% {
            transform: rotateY(0deg);
            box-shadow: 0 0 20px rgba(0,0,0,0.1);
        }
        50% {
            transform: rotateY(90deg);
            box-shadow: -10px 0 30px rgba(0,0,0,0.3);
        }
        100% {
            transform: rotateY(180deg);
            box-shadow: 0 0 20px rgba(0,0,0,0.1);
        }
    }

    .page-back {
        position: absolute;
        top: 0;
        left: 0;
        width: 100%;
        height: 100%;
        backface-visibility: hidden;
        transform: rotateY(180deg);
        background: linear-gradient(45deg, #f9fafb 0%, #f3f4f6 100%);
    }

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

    .page-content {
        transition: transform 0.3s ease;
    }
</style>
