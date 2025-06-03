<!DOCTYPE html>
<html lang="en" class="h-full">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Livewire Search POC</title>
    @vite(['resources/css/app.css', 'resources/js/app.js'])
    @livewireStyles
</head>
<body class="h-full bg-gray-50 dark:bg-gray-900">
    <div class="h-full flex flex-col">
        <!-- Simple Header -->
        <header class="bg-white dark:bg-gray-800 shadow-sm border-b border-gray-200 dark:border-gray-700">
            <div class="px-4 sm:px-6 py-4">
                <div class="flex items-center justify-between">
                    <h1 class="text-xl font-semibold text-gray-900 dark:text-gray-100">
                        🧪 Livewire vs Href POC
                    </h1>
                    <a href="/search" class="text-blue-600 hover:text-blue-700 text-sm">
                        ← Back to Original Search
                    </a>
                </div>
            </div>
        </header>

        <!-- Livewire Component -->
        <main class="flex-1 overflow-hidden">
            <livewire:search-component />
        </main>
    </div>

    @livewireScripts
</body>
</html>
