<!DOCTYPE html>
<html lang="en" class="h-full">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Verse Title Manager - Bible App</title>
    {{-- Livewire 3 bundles Alpine; loading resources/js/app.js here started a SECOND
         Alpine instance on the same document, and shipped an unused axios global. --}}
    @vite(['resources/css/app.css'])
    @livewireStyles
</head>
<body class="h-full bg-gray-100">
    <livewire:verse-title-manager />
    @livewireScripts
</body>
</html>
