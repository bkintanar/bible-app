<div class="flex flex-col h-full">
    <!-- iOS-style Search Header -->
    <div class="flex-shrink-0 bg-gray-50 dark:bg-gray-900 border-b border-gray-200 dark:border-gray-700">

        <!-- Collapsed Search State (Search Icon Only) -->
        @if(!$searchExpanded)
            <div class="flex items-center justify-between p-4 sm:p-6 transition-all duration-300 ease-in-out">
                <h1 class="text-xl font-semibold text-gray-900 dark:text-gray-100">
                    🧪 Bible Search POC
                </h1>
                <button
                    wire:click="expandSearch"
                    class="p-2 rounded-full hover:bg-gray-200 dark:hover:bg-gray-700 transition-colors"
                >
                    <svg class="h-6 w-6 text-gray-600 dark:text-gray-300" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0z"/>
                    </svg>
                </button>
            </div>
        @endif

        <!-- Expanded Search State (Full Search Bar) -->
        @if($searchExpanded)
            <div class="p-4 sm:p-6 transition-all duration-300 ease-in-out">
                <div class="flex items-center gap-3">
                    <!-- Search Input -->
                    <div class="relative flex-1">
                        <input
                            wire:model.live.debounce.300ms="query"
                            wire:keydown.enter="search"
                            type="text"
                            placeholder="Search verses, words, or references..."
                            class="w-full border border-gray-300 dark:border-gray-600 bg-white dark:bg-gray-700 text-gray-900 dark:text-gray-100 rounded-lg pl-10 pr-4 py-3 focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-transparent placeholder-gray-500 dark:placeholder-gray-400 transition-all duration-200"
                        >
                        <div class="absolute left-3 top-1/2 transform -translate-y-1/2 pointer-events-none">
                            <svg class="h-5 w-5 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0z"/>
                            </svg>
                        </div>
                    </div>

                    <!-- Cancel Button -->
                    <button
                        wire:click="cancelSearch"
                        class="text-blue-600 dark:text-blue-400 hover:text-blue-700 dark:hover:text-blue-300 font-medium py-3 px-2 transition-colors whitespace-nowrap"
                    >
                        Cancel
                    </button>
                </div>
            </div>
        @endif
    </div>

    <!-- Scrollable Content Area -->
    <div class="flex-1 overflow-y-auto p-4 sm:p-8">
        @if($query && $searchExpanded)
            <!-- Search Info -->
            <div class="mb-6">
                <h2 class="text-xl font-semibold text-gray-900 dark:text-gray-100 mb-2">
                    Search Results for "{{ $query }}"
                </h2>
                <div class="text-sm text-gray-600 dark:text-gray-400">
                    @if(!empty($searchInfo) && $searchInfo['count'] > 0)
                        Found {{ $searchInfo['count'] }} result{{ $searchInfo['count'] !== 1 ? 's' : '' }}
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

                <!-- Load More Results - COMPARISON SECTION -->
                @if($hasMoreResults)
                    <div class="mt-8 space-y-4">
                        <h3 class="text-lg font-semibold text-gray-900 dark:text-gray-100 text-center">
                            🧪 POC: Test Both Approaches
                        </h3>

                        <!-- Option 1: Livewire Navigation -->
                        <div class="text-center p-4 bg-blue-50 dark:bg-blue-900/20 rounded-lg">
                            <h4 class="font-medium text-blue-900 dark:text-blue-100 mb-2">
                                Option 1: Livewire Navigation (wire:click)
                            </h4>
                            <button
                                wire:click="loadMore"
                                class="bg-blue-600 hover:bg-blue-700 text-white px-6 py-2 rounded-lg transition-colors inline-block"
                            >
                                Load More Results (Livewire)
                            </button>
                        </div>

                        <!-- Option 2: Traditional Href -->
                        <div class="text-center p-4 bg-green-50 dark:bg-green-900/20 rounded-lg">
                            <h4 class="font-medium text-green-900 dark:text-green-100 mb-2">
                                Option 2: Traditional Navigation (href)
                            </h4>
                            <a
                                href="/livewire-search?query={{ urlencode($query) }}&limit={{ $limit + 50 }}&scroll_to={{ $limit - 10 }}"
                                class="bg-green-600 hover:bg-green-700 text-white px-6 py-2 rounded-lg transition-colors inline-block"
                            >
                                Load More Results (Href)
                            </a>
                        </div>

                        <!-- Option 3: Livewire Navigate -->
                        <div class="text-center p-4 bg-purple-50 dark:bg-purple-900/20 rounded-lg">
                            <h4 class="font-medium text-purple-900 dark:text-purple-100 mb-2">
                                Option 3: Livewire Navigate (wire:navigate)
                            </h4>
                            <a
                                wire:navigate
                                href="/livewire-search?query={{ urlencode($query) }}&limit={{ $limit + 50 }}&scroll_to={{ $limit - 10 }}"
                                class="bg-purple-600 hover:bg-purple-700 text-white px-6 py-2 rounded-lg transition-colors inline-block"
                            >
                                Load More Results (wire:navigate)
                            </a>
                        </div>
                    </div>
                @endif
            @else
                <!-- No Results -->
                <div class="text-center py-12">
                    <div class="text-gray-500 dark:text-gray-400">
                        <svg class="mx-auto h-12 w-12 mb-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0z"/>
                        </svg>
                        <h3 class="text-lg font-medium mb-2">No results found</h3>
                        <p>Try searching with different keywords or check your spelling.</p>
                    </div>
                </div>
            @endif
        @elseif($searchExpanded && !$query)
            <!-- Search Active but No Query -->
            <div class="text-center py-12">
                <div class="text-gray-500 dark:text-gray-400">
                    <svg class="mx-auto h-16 w-16 mb-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0z"/>
                    </svg>
                    <h3 class="text-xl font-medium mb-2">Start searching</h3>
                    <p class="max-w-md mx-auto">
                        Enter keywords, phrases, or verse references to search the Bible.
                    </p>
                </div>
            </div>
        @else
            <!-- Initial State - Search Collapsed -->
            <div class="text-center py-12">
                <div class="text-gray-500 dark:text-gray-400">
                    <svg class="mx-auto h-16 w-16 mb-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 6.253v13m0-13C10.832 5.477 9.246 5 7.5 5S4.168 5.477 3 6.253v13C4.168 18.477 5.754 18 7.5 18s3.332.477 4.5 1.253m0-13C13.168 5.477 14.754 5 16.5 5c1.746 0 3.332.477 4.5 1.253v13C19.832 18.477 18.246 18 16.5 18c-1.746 0-3.332.477-4.5 1.253"/>
                    </svg>
                    <h3 class="text-xl font-medium mb-2">🧪 Livewire Search POC</h3>
                    <p class="max-w-md mx-auto mb-4">
                        Test Livewire vs href navigation in NativePHP with iOS-style search.
                    </p>
                    <button
                        wire:click="expandSearch"
                        class="bg-blue-600 hover:bg-blue-700 text-white px-6 py-3 rounded-lg transition-colors inline-flex items-center gap-2"
                    >
                        <svg class="h-5 w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0z"/>
                        </svg>
                        Start Search
                    </button>
                </div>
            </div>
        @endif
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
</div>
