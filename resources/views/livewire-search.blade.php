<!DOCTYPE html>
<html lang="en" class="h-full">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Bible Search</title>
    @vite(['resources/css/app.css', 'resources/js/app.js'])
    @livewireStyles
</head>
<body class="h-full bg-gray-50 dark:bg-gray-900">
    <div class="h-full flex flex-col">
        <!-- Main Search Interface -->
        <main class="flex-1 overflow-hidden">
            <livewire:search-component />
        </main>
    </div>

    @livewireScripts
</body>
</html>
