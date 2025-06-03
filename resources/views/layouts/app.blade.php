<!DOCTYPE html>
<html lang="en" class="h-full">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Bible App</title>
    @vite(['resources/css/app.css'])
    @livewireStyles
</head>
<body class="h-full">
    {{ $slot }}
    @livewireScripts
</body>
</html>
