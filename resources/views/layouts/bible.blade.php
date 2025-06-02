<!DOCTYPE html>
<html lang="en" class="">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, user-scalable=no, viewport-fit=cover">
    <meta name="apple-mobile-web-app-capable" content="yes">
    <meta name="apple-mobile-web-app-status-bar-style" content="black-translucent">
    <meta name="apple-mobile-web-app-title" content="Bible Reader">
    <meta name="theme-color" content="#2563eb">
    <title>@yield('title', 'Bible Reader')</title>
    @vite(['resources/css/app.css', 'resources/js/app.js'])

    <!-- iOS App Icons -->
    <link rel="apple-touch-icon" sizes="180x180" href="/apple-touch-icon.png">
    <link rel="icon" type="image/png" sizes="32x32" href="/favicon-32x32.png">
    <link rel="icon" type="image/png" sizes="16x16" href="/favicon-16x16.png">
    <link rel="manifest" href="/site.webmanifest">

    <script>
        // Dark mode initialization script
        if (localStorage.getItem('darkMode') === 'true' ||
            (!localStorage.getItem('darkMode') && window.matchMedia('(prefers-color-scheme: dark)').matches)) {
            document.documentElement.classList.add('dark');
        }
    </script>

    <style>
        /* iOS safe area support - proper implementation */
        :root {
            --header-height: 4rem; /* 64px */
            --safe-area-top: env(safe-area-inset-top, 0);
            --safe-area-bottom: env(safe-area-inset-bottom, 0);
            --safe-area-left: env(safe-area-inset-left, 0);
            --safe-area-right: env(safe-area-inset-right, 0);
            --total-header-height: calc(var(--header-height) + var(--safe-area-top));
        }

        /* Fixed layout - prevent body from scrolling */
        html, body {
            height: 100%;
            overflow: hidden;
        }

        body {
            padding-left: var(--safe-area-left);
            padding-right: var(--safe-area-right);
            padding-bottom: var(--safe-area-bottom);
            padding-top: 0;
        }

        /* Fixed header with proper safe area handling */
        .fixed-header {
            position: fixed;
            top: 0;
            left: 0;
            right: 0;
            z-index: 50;
            height: var(--total-header-height);
            padding-top: var(--safe-area-top);
            padding-left: var(--safe-area-left);
            padding-right: var(--safe-area-right);
        }

        /* Main container that takes remaining space and scrolls */
        .main-container {
            position: fixed;
            top: var(--total-header-height);
            left: 0;
            right: 0;
            bottom: 0;
            overflow-y: auto;
            overflow-x: hidden;
            -webkit-overflow-scrolling: touch;
            /* Prevent rubber band effect */
            overscroll-behavior-y: none;
        }

        /* Main content no longer needs margin-top since container handles positioning */
        main {
            margin-top: 0;
            min-height: calc(100vh - var(--total-header-height));
        }

        /* Touch-friendly styles */
        .touch-friendly {
            min-height: 44px;
            min-width: 44px;
        }

        /* iOS-style cards */
        .ios-card {
            background: rgba(255, 255, 255, 0.95);
            backdrop-filter: blur(20px);
            border: 1px solid rgba(255, 255, 255, 0.18);
        }

        .dark .ios-card {
            background: rgba(28, 28, 30, 0.95);
            border: 1px solid rgba(255, 255, 255, 0.1);
        }

        /* Smooth scrolling */
        .main-container {
            scroll-behavior: smooth;
        }

        /* Hide scrollbars but keep functionality */
        .hide-scrollbar {
            -ms-overflow-style: none;
            scrollbar-width: none;
        }

        .hide-scrollbar::-webkit-scrollbar {
            display: none;
        }
    </style>
</head>
<body class="bg-gray-50 dark:bg-gray-900 min-h-screen transition-colors">
    <!-- Include Header Component -->
    @include('components.bible-header')

    <div class="main-container">
        <!-- Main Content -->
        <main class="px-4 sm:px-6 lg:px-8 py-4 max-w-7xl mx-auto">
            <!-- Success/Error Messages -->
            @if(session('success'))
                <div class="mb-4 bg-green-50 dark:bg-green-900/20 border border-green-200 dark:border-green-700 text-green-800 dark:text-green-300 px-4 py-3 rounded-lg" role="alert">
                    <span class="block sm:inline">{{ session('success') }}</span>
                </div>
            @endif

            @if(session('error'))
                <div class="mb-4 bg-red-50 dark:bg-red-900/20 border border-red-200 dark:border-red-700 text-red-800 dark:text-red-300 px-4 py-3 rounded-lg" role="alert">
                    <span class="block sm:inline">{{ session('error') }}</span>
                </div>
            @endif

            @yield('content')
        </main>
    </div>

    <script>
        // Handle iOS viewport height issues
        function setViewportHeight() {
            const vh = window.innerHeight * 0.01;
            document.documentElement.style.setProperty('--vh', `${vh}px`);
        }

        window.addEventListener('resize', setViewportHeight);
        setViewportHeight();
    </script>

    @stack('scripts')
</body>
</html>
