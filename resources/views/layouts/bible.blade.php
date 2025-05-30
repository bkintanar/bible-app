<!DOCTYPE html>
<html lang="en" class="">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>@yield('title', 'Bible Reader')</title>
    @vite(['resources/css/app.css', 'resources/js/app.js'])
    <script>
        // Dark mode initialization script
        if (localStorage.getItem('darkMode') === 'true' ||
            (!localStorage.getItem('darkMode') && window.matchMedia('(prefers-color-scheme: dark)').matches)) {
            document.documentElement.classList.add('dark');
        }
    </script>
</head>
<body class="bg-gray-50 dark:bg-gray-900 min-h-screen transition-colors">
    <nav class="bg-white dark:bg-gray-800 shadow-sm border-b border-gray-200 dark:border-gray-700">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
            <div class="flex justify-between h-16">
                <div class="flex items-center">
                    <a href="{{ route('bible.index') }}" class="text-xl font-bold text-bible-blue dark:text-blue-400">
                        📖 Bible Reader
                    </a>
                </div>
                <div class="flex items-center space-x-4">
                    <!-- Translation Selector -->
                    @if(isset($availableTranslations) && $availableTranslations->count() > 1)
                        <div class="relative">
                            <form action="{{ route('bible.switch-translation') }}" method="POST" id="translationForm">
                                @csrf
                                <select name="translation"
                                        onchange="document.getElementById('translationForm').submit()"
                                        class="border border-gray-300 dark:border-gray-600 bg-white dark:bg-gray-700 text-gray-900 dark:text-gray-100 rounded-md px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-bible-blue dark:focus:ring-blue-400 focus:border-transparent">
                                    @foreach($availableTranslations as $translation)
                                        <option value="{{ $translation['key'] }}"
                                                {{ (isset($currentTranslation) && $currentTranslation['key'] === $translation['key']) ? 'selected' : '' }}>
                                            {{ $translation['short_name'] }}
                                        </option>
                                    @endforeach
                                </select>
                            </form>
                        </div>
                    @elseif(isset($currentTranslation))
                        <span class="text-sm font-medium text-gray-700 dark:text-gray-300 px-3 py-2 border border-gray-300 dark:border-gray-600 rounded-md bg-gray-50 dark:bg-gray-700">
                            {{ $currentTranslation['short_name'] }}
                        </span>
                    @endif

                    <!-- Dark Mode Toggle -->
                    <button onclick="toggleDarkMode()" id="darkModeToggle"
                            class="p-2 rounded-md text-gray-600 dark:text-gray-300 hover:bg-gray-100 dark:hover:bg-gray-700 transition-colors">
                        <svg id="sunIcon" class="w-5 h-5 hidden dark:block" fill="currentColor" viewBox="0 0 20 20">
                            <path fill-rule="evenodd" d="M10 2a1 1 0 011 1v1a1 1 0 11-2 0V3a1 1 0 011-1zm4 8a4 4 0 11-8 0 4 4 0 018 0zm-.464 4.95l.707.707a1 1 0 001.414-1.414l-.707-.707a1 1 0 00-1.414 1.414zm2.12-10.607a1 1 0 010 1.414l-.706.707a1 1 0 11-1.414-1.414l.707-.707a1 1 0 011.414 0zM17 11a1 1 0 100-2h-1a1 1 0 100 2h1zm-7 4a1 1 0 011 1v1a1 1 0 11-2 0v-1a1 1 0 011-1zM5.05 6.464A1 1 0 106.465 5.05l-.708-.707a1 1 0 00-1.414 1.414l.707.707zm1.414 8.486l-.707.707a1 1 0 01-1.414-1.414l.707-.707a1 1 0 011.414 1.414zM4 11a1 1 0 100-2H3a1 1 0 000 2h1z" clip-rule="evenodd"></path>
                        </svg>
                        <svg id="moonIcon" class="w-5 h-5 block dark:hidden" fill="currentColor" viewBox="0 0 20 20">
                            <path d="M17.293 13.293A8 8 0 016.707 2.707a8.001 8.001 0 1010.586 10.586z"></path>
                        </svg>
                    </button>

                    <form action="{{ route('bible.search') }}" method="GET" class="flex items-center">
                        <input type="text"
                               name="q"
                               placeholder="Search verses..."
                               value="{{ request('q') }}"
                               class="border border-gray-300 dark:border-gray-600 bg-white dark:bg-gray-700 text-gray-900 dark:text-gray-100 rounded-md px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-bible-blue dark:focus:ring-blue-400 focus:border-transparent placeholder-gray-500 dark:placeholder-gray-400">
                        <button type="submit" class="ml-2 bg-bible-blue dark:bg-blue-600 text-white px-4 py-2 rounded-md text-sm hover:bg-blue-700 dark:hover:bg-blue-500 transition-colors">
                            Search
                        </button>
                    </form>
                </div>
            </div>
        </div>
    </nav>

    <main class="max-w-7xl mx-auto py-6 px-4 sm:px-6 lg:px-8">
        <!-- Success/Error Messages -->
        @if(session('success'))
            <div class="mb-4 bg-green-100 dark:bg-green-900 border border-green-400 dark:border-green-600 text-green-700 dark:text-green-300 px-4 py-3 rounded relative" role="alert">
                <span class="block sm:inline">{{ session('success') }}</span>
            </div>
        @endif

        @if(session('error'))
            <div class="mb-4 bg-red-100 dark:bg-red-900 border border-red-400 dark:border-red-600 text-red-700 dark:text-red-300 px-4 py-3 rounded relative" role="alert">
                <span class="block sm:inline">{{ session('error') }}</span>
            </div>
        @endif

        @yield('content')
    </main>

    <footer class="bg-white dark:bg-gray-800 border-t border-gray-200 dark:border-gray-700 mt-12">
        <div class="max-w-7xl mx-auto py-4 px-4 sm:px-6 lg:px-8">
            <p class="text-center text-sm text-gray-500 dark:text-gray-400">
                Built with OSIS 2.1.1 XML Schema | Laravel Bible Reader
            </p>
        </div>
    </footer>

    <script>
        // Dark mode functionality
        function toggleDarkMode() {
            const isDark = document.documentElement.classList.toggle('dark');
            localStorage.setItem('darkMode', isDark);
        }
    </script>

    @stack('scripts')
</body>
</html>
