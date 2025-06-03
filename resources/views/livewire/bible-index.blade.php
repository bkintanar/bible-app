<div class="min-h-full bg-gray-50 dark:bg-gray-900">
    <!-- Header -->
    <nav class="bg-white dark:bg-gray-800 shadow-sm border-b border-gray-200 dark:border-gray-700">
        <div class="px-4 sm:px-6 py-4">
            <div class="flex items-center justify-between">
                <h1 class="text-xl font-semibold text-gray-900 dark:text-white">
                    📖 Bible App
                </h1>

                <!-- Translation Selector -->
                <x-translation-dropdown
                    :available-translations="$availableTranslations"
                    :current-translation="$currentTranslation"
                    position="left"
                />
            </div>
        </div>
    </nav>

    <!-- Main Content -->
    <div class="p-4 sm:p-8">
        <div class="max-w-6xl mx-auto">

            <!-- Welcome Section -->
            <div class="text-center mb-12">
                <h2 class="text-3xl font-bold text-gray-900 dark:text-white mb-4">
                    Welcome to Bible Study
                </h2>
                <p class="text-lg text-gray-600 dark:text-gray-400 mb-8">
                    Search, read, and study the Bible with powerful tools and resources.
                </p>

                <!-- Quick Actions -->
                <div class="flex flex-col sm:flex-row gap-4 justify-center">
                    <a href="/search"
                       class="bg-blue-600 hover:bg-blue-700 text-white px-6 py-3 rounded-lg transition-colors font-medium">
                        🔍 Search Bible
                    </a>
                </div>
            </div>

            <!-- Last Visited -->
            @if($lastVisited)
                <div class="bg-blue-50 dark:bg-blue-900/20 rounded-lg p-6 mb-8">
                    <div class="flex items-center justify-between">
                        <div>
                            <h3 class="text-lg font-semibold text-blue-900 dark:text-blue-100 mb-2">
                                Continue Reading
                            </h3>
                            <p class="text-blue-700 dark:text-blue-300">
                                Last visited: {{ $lastVisited['reference'] ?? 'Unknown' }}
                            </p>
                        </div>
                        <div class="flex gap-2">
                            @if(isset($lastVisited['url']))
                                <a href="{{ $lastVisited['url'] }}"
                                   class="bg-blue-600 hover:bg-blue-700 text-white px-4 py-2 rounded-lg transition-colors">
                                    Continue
                                </a>
                            @endif
                            <button wire:click="clearLastVisited"
                                    class="bg-gray-500 hover:bg-gray-600 text-white px-4 py-2 rounded-lg transition-colors">
                                Clear
                            </button>
                        </div>
                    </div>
                </div>
            @endif

            <!-- Books Grid -->
            @if(!empty($testamentBooks))
                <div class="grid grid-cols-1 lg:grid-cols-2 gap-8">
                    <!-- Old Testament -->
                    <div class="bg-white dark:bg-gray-800 rounded-lg border border-gray-200 dark:border-gray-700 p-6">
                        <h3 class="text-xl font-semibold text-gray-900 dark:text-white mb-4 flex items-center">
                            📜 Old Testament
                            <span class="ml-2 text-sm text-gray-500 dark:text-gray-400">
                                ({{ count($testamentBooks['oldTestament'] ?? []) }} books)
                            </span>
                        </h3>
                        <div class="grid grid-cols-3 gap-2">
                            @foreach($testamentBooks['oldTestament'] ?? [] as $book)
                                <a href="/{{ $book['osis_id'] }}"
                                   class="text-sm p-2 text-gray-700 dark:text-gray-300 hover:bg-gray-100 dark:hover:bg-gray-700 rounded transition-colors text-center">
                                    {{ $book['name'] }}
                                </a>
                            @endforeach
                        </div>
                    </div>

                    <!-- New Testament -->
                    <div class="bg-white dark:bg-gray-800 rounded-lg border border-gray-200 dark:border-gray-700 p-6">
                        <h3 class="text-xl font-semibold text-gray-900 dark:text-white mb-4 flex items-center">
                            ✝️ New Testament
                            <span class="ml-2 text-sm text-gray-500 dark:text-gray-400">
                                ({{ count($testamentBooks['newTestament'] ?? []) }} books)
                            </span>
                        </h3>
                        <div class="grid grid-cols-3 gap-2">
                            @foreach($testamentBooks['newTestament'] ?? [] as $book)
                                <a href="/{{ $book['osis_id'] }}"
                                   class="text-sm p-2 text-gray-700 dark:text-gray-300 hover:bg-gray-100 dark:hover:bg-gray-700 rounded transition-colors text-center">
                                    {{ $book['name'] }}
                                </a>
                            @endforeach
                        </div>
                    </div>
                </div>
            @endif
        </div>
    </div>

    <!-- Dark Mode Script -->
    <script>
        function toggleDarkMode() {
            document.documentElement.classList.toggle('dark');
            localStorage.setItem('darkMode', document.documentElement.classList.contains('dark'));
        }

        // Initialize dark mode from localStorage
        if (localStorage.getItem('darkMode') === 'true') {
            document.documentElement.classList.add('dark');
        }
    </script>
</div>
