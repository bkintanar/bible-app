<!DOCTYPE html>
<html lang="en" class="h-full">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>{{ $meta['title'] }}</title>
    <meta name="description" content="{{ $meta['description'] }}">
    <link rel="canonical" href="{{ $meta['url'] }}">
    <meta property="og:type" content="article">
    <meta property="og:site_name" content="Bible">
    <meta property="og:title" content="{{ $meta['title'] }}">
    <meta property="og:description" content="{{ $meta['description'] }}">
    <meta property="og:url" content="{{ $meta['url'] }}">
    @include('partials.dark-mode-head')

    {{-- Only the web font the reader actually falls back to (Charter is a system font; the stack
         continues with Source Serif Pro). Three more families were downloaded and never used. --}}
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Source+Serif+Pro:ital,wght@0,400;0,600;1,400&display=swap" rel="stylesheet">

    @vite(['resources/css/app.css', 'resources/js/reader.js'])
    @livewireStyles
</head>
<body class="h-full">
    <livewire:bible-chapter :book-osis-id="$bookOsisId" :chapter-number="$chapterNumber" />
    @livewireScripts
</body>
</html>
