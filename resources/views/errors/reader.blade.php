{{-- Error page shell in the reader's style. Deliberately standalone: no database, no Livewire, no
     session - the 500 and maintenance (503) pages must render when those are what failed. --}}
<!DOCTYPE html>
<html lang="en" class="h-full">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="robots" content="noindex">
    <title>@yield('title') — Bible</title>
    @include('partials.dark-mode-head')
    @if (file_exists(public_path('build/manifest.json')))
        @vite(['resources/css/app.css'])
    @endif
</head>
<body class="h-full bg-gray-50 dark:bg-gray-900">
    <main class="min-h-full flex items-center justify-center px-4 py-16">
        <div class="max-w-md text-center">
            <p class="text-sm font-semibold text-gray-500 dark:text-gray-400 tracking-widest">@yield('code')</p>
            <h1 class="mt-2 text-3xl font-serif font-semibold text-gray-900 dark:text-gray-100">@yield('title')</h1>
            <p class="mt-4 text-gray-600 dark:text-gray-300 font-serif leading-relaxed">@yield('message')</p>
            <div class="mt-8 flex flex-wrap items-center justify-center gap-4">
                <a href="{{ route('chapters.show', ['bookOsisId' => 'Gen', 'chapterNumber' => 1]) }}"
                   class="bg-blue-600 hover:bg-blue-700 text-white px-5 py-2.5 rounded-lg">Go to Genesis 1</a>
                @unless ($hideSearch ?? false)
                    <a href="{{ route('searches.index') }}" class="text-blue-600 dark:text-blue-400 hover:underline">Search the Bible</a>
                @endunless
            </div>
        </div>
    </main>
</body>
</html>
