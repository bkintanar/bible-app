<!DOCTYPE html>
<html lang="en" class="h-full">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Bible Book</title>
    @vite(['resources/css/app.css'])
    @livewireStyles
</head>
<body class="h-full">
    <livewire:bible-book :book-osis-id="$bookOsisId" />
    @livewireScripts
</body>
</html>
