<div class="flex flex-col bg-gray-50 dark:bg-gray-900 h-full">
    <!-- Fixed Header -->
    <nav class="bg-white dark:bg-gray-800 shadow-sm border-b border-gray-200 dark:border-gray-700 flex-shrink-0 z-50">
        <div class="px-4 sm:px-6 w-full max-w-none">
            <!-- Main Navigation Row -->
            <div class="flex items-center h-16 w-full">
                <!-- Left side -->
                <div class="flex-1">
                    <h1 class="text-xl font-semibold text-gray-900 dark:text-white">Bible Search</h1>
                </div>

                <!-- Mobile Actions -->
                <div class="flex items-center space-x-1 sm:hidden">
                    <!-- Dark Mode Toggle (Mobile) -->
                    <button onclick="toggleDarkMode()"
                            class="p-2 rounded-lg text-gray-600 dark:text-gray-300 hover:bg-gray-100 dark:hover:bg-gray-700 transition-colors flex items-center justify-center">
                        <svg class="w-5 h-5 dark:hidden" fill="currentColor" viewBox="0 0 20 20">
                            <path d="M17.293 13.293A8 8 0 016.707 2.707a8.001 8.001 0 1010.586 10.586z"></path>
                        </svg>
                        <svg class="w-5 h-5 hidden dark:block" fill="currentColor" viewBox="0 0 20 20">
                            <path fill-rule="evenodd" d="M10 2a1 1 0 011 1v1a1 1 0 11-2 0V3a1 1 0 011-1zm4 8a4 4 0 11-8 0 4 4 0 018 0zm-.464 4.95l.707.707a1 1 0 001.414-1.414l-.707-.707a1 1 0 00-1.414 1.414zm2.12-10.607a1 1 0 010 1.414l-.706.707a1 1 0 11-1.414-1.414l.707-.707a1 1 0 011.414 0zM17 11a1 1 0 100-2h-1a1 1 0 100 2h1zm-7 4a1 1 0 011 1v1a1 1 0 11-2 0v-1a1 1 0 011-1zM5.05 6.464A1 1 0 106.465 5.05l-.708-.707a1 1 0 00-1.414 1.414l.707.707zm1.414 8.486l-.707.707a1 1 0 01-1.414-1.414l.707-.707a1 1 0 011.414 1.414zM4 11a1 1 0 100-2H3a1 1 0 000 2h1z" clip-rule="evenodd"></path>
                        </svg>
                    </button>
                </div>

                <!-- Desktop Controls -->
                <div class="hidden sm:flex items-center space-x-4">
                    <!-- Translation Selector -->
                    <x-translation-dropdown
                        :available-translations="$availableTranslations"
                        :current-translation="$currentTranslation"
                        position="left"
                    />

                    <!-- Dark Mode Toggle (Desktop) -->
                    <button onclick="toggleDarkMode()"
                            class="p-2 rounded-lg text-gray-600 dark:text-gray-300 hover:bg-gray-100 dark:hover:bg-gray-700 transition-colors flex items-center justify-center">
                        <svg class="w-5 h-5 dark:hidden" fill="currentColor" viewBox="0 0 20 20">
                            <path d="M17.293 13.293A8 8 0 016.707 2.707a8.001 8.001 0 1010.586 10.586z"></path>
                        </svg>
                        <svg class="w-5 h-5 hidden dark:block" fill="currentColor" viewBox="0 0 20 20">
                            <path fill-rule="evenodd" d="M10 2a1 1 0 011 1v1a1 1 0 11-2 0V3a1 1 0 011-1zm4 8a4 4 0 11-8 0 4 4 0 018 0zm-.464 4.95l.707.707a1 1 0 001.414-1.414l-.707-.707a1 1 0 00-1.414 1.414zm2.12-10.607a1 1 0 010 1.414l-.706.707a1 1 0 11-1.414-1.414l.707-.707a1 1 0 011.414 0zM17 11a1 1 0 100-2h-1a1 1 0 100 2h1zm-7 4a1 1 0 011 1v1a1 1 0 11-2 0v-1a1 1 0 011-1zM5.05 6.464A1 1 0 106.465 5.05l-.708-.707a1 1 0 00-1.414 1.414l.707.707zm1.414 8.486l-.707.707a1 1 0 01-1.414-1.414l.707-.707a1 1 0 011.414 1.414zM4 11a1 1 0 100-2H3a1 1 0 000 2h1z" clip-rule="evenodd"></path>
                        </svg>
                    </button>
                </div>
            </div>
        </div>
    </nav>

    <!-- Main Content -->
    <div class="flex flex-col flex-1 overflow-hidden">
        <!-- Fixed Search Form -->
        <div class="flex-shrink-0 bg-gray-50 dark:bg-gray-900 border-b border-gray-200 dark:border-gray-700 p-4 sm:p-8">
            <div class="max-w-2xl mx-auto">
                <div class="flex gap-2">
                    <div class="relative flex-1">
                        <input
                            wire:model.live.debounce.300ms="q"
                            wire:keydown.enter="search"
                            type="text"
                            placeholder="Search for verses, words, or references..."
                            class="w-full border border-gray-300 dark:border-gray-600 bg-white dark:bg-gray-700 text-gray-900 dark:text-gray-100 rounded-lg pl-10 pr-4 py-3 focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-transparent placeholder-gray-500 dark:placeholder-gray-400"
                        >
                        <div class="absolute left-4 top-1/2 transform -translate-y-1/2 pointer-events-none">
                            <svg class="h-5 w-5 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"/>
                            </svg>
                        </div>
                    </div>
                    <button
                        wire:click="search"
                        class="bg-blue-600 hover:bg-blue-700 text-white px-4 py-3 rounded-lg transition-colors whitespace-nowrap touch-friendly"
                    >
                        Search
                    </button>
                </div>
            </div>
        </div>

        <!-- Scrollable Content Area -->
        <div class="flex-1 overflow-y-auto p-4 sm:p-8">
            @if($q)
                <!-- Search Info -->
                <div class="mb-6">
                    <h2 class="text-xl font-semibold text-gray-900 dark:text-gray-100 mb-2">
                        Search Results for "{{ $q }}"
                    </h2>
                    <div class="text-sm text-gray-600 dark:text-gray-400">
                        @if(!empty($searchInfo) && $searchInfo['count'] > 0)
                            Found {{ number_format($searchInfo['count']) }} result{{ $searchInfo['count'] !== 1 ? 's' : '' }}
                            @if($hasMoreResults)(showing first {{ $limit }})@endif
                            in {{ $searchInfo['time_ms'] }}ms
                        @elseif(!empty($searchInfo))
                            No results found in {{ $searchInfo['time_ms'] }}ms
                        @endif
                    </div>
                </div>

                <!-- Results -->
                @if(!empty($results))
                    <div class="space-y-4">
                        @foreach($results as $index => $result)
                            <div
                                id="result-{{ $index }}"
                                class="bg-white dark:bg-gray-800 rounded-lg border border-gray-200 dark:border-gray-700 p-4 hover:shadow-md transition-shadow"
                            >
                                <div class="flex items-start justify-between">
                                    <div class="flex-1">
                                        <div class="text-sm font-medium text-blue-600 dark:text-blue-400 mb-2">
                                            <a href="/{{ $result['book_osis_id'] }}/{{ $result['chapter'] }}" class="hover:underline">
                                                {{ $result['reference'] }}
                                            </a>
                                        </div>
                                        <div class="bible-text text-gray-900 dark:text-gray-100">
                                            {!! $result['text'] !!}
                                        </div>
                                    </div>
                                </div>
                            </div>
                        @endforeach
                    </div>

                    <!-- Load More Results -->
                    @if($hasMoreResults)
                        <div class="mt-8 text-center">
                            <button
                                wire:click="loadMore"
                                class="bg-blue-600 hover:bg-blue-700 text-white px-6 py-2 rounded-lg transition-colors inline-block"
                            >
                                Load More Results
                            </button>
                        </div>
                    @endif
                @else
                    <!-- No Results -->
                    <div class="text-center py-12">
                        <div class="text-gray-500 dark:text-gray-400">
                            <svg class="mx-auto h-12 w-12 mb-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"/>
                            </svg>
                            <h3 class="text-lg font-medium mb-2">No results found</h3>
                            <p>Try searching with different keywords or check your spelling.</p>
                        </div>
                    </div>
                @endif
            @else
                <!-- Initial State -->
                <div class="text-center py-12">
                    <div class="text-gray-500 dark:text-gray-400">
                        <svg class="mx-auto h-16 w-16 mb-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"/>
                        </svg>
                        <h3 class="text-xl font-medium mb-2">Search the Bible</h3>
                        <p class="max-w-md mx-auto">
                            Search for verses, words, phrases, or Bible references. Try searching for "love", "John 3:16", or "peace".
                        </p>
                    </div>
                </div>
            @endif
        </div>
    </div>

    <!-- Scroll to element after Livewire update -->
    @if($scrollToIndex !== null)
        <script>
            document.addEventListener('livewire:updated', function () {
                setTimeout(function() {
                    const targetElement = document.getElementById('result-{{ $scrollToIndex }}');
                    if (targetElement) {
                        try {
                            targetElement.scrollIntoView({
                                behavior: 'smooth',
                                block: 'start'
                            });

                            setTimeout(() => {
                                const currentScrollTop = window.pageYOffset || document.documentElement.scrollTop;
                                window.scrollTo({
                                    top: currentScrollTop - 120,
                                    behavior: 'smooth'
                                });
                            }, 100);
                        } catch (error) {
                            const offsetTop = targetElement.offsetTop - 120;
                            window.scrollTo(0, offsetTop);
                        }
                    }
                }, 100);
            });
        </script>
    @endif

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
