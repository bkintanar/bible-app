<div class="min-h-screen bg-gray-50 dark:bg-gray-900 flex flex-col">
    <!-- Sticky Header -->
    <nav class="bg-white dark:bg-gray-800 shadow-sm border-b border-gray-200 dark:border-gray-700 sticky top-0 z-50">
        <div class="px-4 sm:px-6 py-4">
            <div class="flex items-center justify-between">
                <div class="flex items-center space-x-4">
                    <a href="/" class="text-sm text-gray-600 dark:text-gray-400 hover:text-gray-900 dark:hover:text-white">
                        ← Home
                    </a>
                    <div class="text-xl font-semibold text-gray-900 dark:text-white">
                        {{ $currentBook['name'] ?? $bookOsisId }}
                    </div>
                    <div class="text-sm text-gray-500 dark:text-gray-400">
                        {{ count($chapters) }} chapters
                    </div>
                </div>

                <div class="flex items-center space-x-2">
                    <!-- Search Toggle -->
                    <button wire:click="toggleSearch"
                            class="p-2 rounded-lg text-gray-600 dark:text-gray-300 hover:bg-gray-100 dark:hover:bg-gray-700 transition-colors {{ $showSearch ? 'bg-blue-100 dark:bg-blue-900 text-blue-600 dark:text-blue-400' : '' }}">
                        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"/>
                        </svg>
                    </button>

                    <!-- Translation Selector -->
                    <x-translation-dropdown
                        :available-translations="$availableTranslations"
                        :current-translation="$currentTranslation"
                        position="left"
                    />

                    <!-- Dark Mode Toggle -->
                    <button onclick="toggleDarkMode()"
                            class="p-2 rounded-lg text-gray-600 dark:text-gray-300 hover:bg-gray-100 dark:hover:bg-gray-700 transition-colors">
                        <svg class="w-5 h-5 dark:hidden" fill="currentColor" viewBox="0 0 20 20">
                            <path d="M17.293 13.293A8 8 0 016.707 2.707a8.001 8.001 0 1010.586 10.586z"></path>
                        </svg>
                        <svg class="w-5 h-5 hidden dark:block" fill="currentColor" viewBox="0 0 20 20">
                            <path fill-rule="evenodd" d="M10 2a1 1 0 011 1v1a1 1 0 11-2 0V3a1 1 0 011-1zm4 8a4 4 0 11-8 0 4 4 0 018 0zm-.464 4.95l.707.707a1 1 0 001.414-1.414l-.707-.707a1 1 0 00-1.414 1.414zm2.12-10.607a1 1 0 010 1.414l-.706.707a1 1 0 11-1.414-1.414l.707-.707a1 1 0 011.414 0zM17 11a1 1 0 100-2h-1a1 1 0 100 2h1zm-7 4a1 1 0 011 1v1a1 1 0 11-2 0v-1a1 1 0 011-1zM5.05 6.464A1 1 0 106.465 5.05l-.708-.707a1 1 0 00-1.414 1.414l.707.707zm1.414 8.486l-.707.707a1 1 0 01-1.414-1.414l.707-.707a1 1 0 011.414 1.414zM4 11a1 1 0 100-2H3a1 1 0 000 2h1z" clip-rule="evenodd"></path>
                        </svg>
                    </button>
                </div>
            </div>

            <!-- Search Bar (when toggled) -->
            @if($showSearch)
                <div class="mt-4 border-t border-gray-200 dark:border-gray-600 pt-4">
                    <div class="flex gap-2">
                        <div class="relative flex-1">
                            <input
                                wire:model="searchQuery"
                                wire:keydown.enter="search"
                                type="text"
                                placeholder="Search for verses, words, or references..."
                                class="w-full border border-gray-300 dark:border-gray-600 bg-white dark:bg-gray-700 text-gray-900 dark:text-gray-100 rounded-lg pl-10 pr-4 py-2 focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-transparent placeholder-gray-500 dark:placeholder-gray-400"
                            >
                            <div class="absolute left-3 top-1/2 transform -translate-y-1/2 pointer-events-none">
                                <svg class="h-4 w-4 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"/>
                                </svg>
                            </div>
                        </div>
                        <button
                            wire:click="search"
                            class="bg-blue-600 hover:bg-blue-700 text-white px-4 py-2 rounded-lg transition-colors whitespace-nowrap"
                        >
                            Search
                        </button>
                    </div>
                </div>
            @endif
        </div>
    </nav>

    <!-- Main Content - Scrollable with fixed height -->
    <div class="flex-1 overflow-y-auto" style="height: calc(100vh - 80px);">
        <div class="p-4 sm:p-8 pb-20">
            <div class="max-w-6xl mx-auto">

                <!-- Book Info -->
                <div class="bg-white dark:bg-gray-800 rounded-lg border border-gray-200 dark:border-gray-700 p-6 mb-8">
                    <div class="flex items-center justify-between">
                        <div>
                            <h1 class="text-2xl font-bold text-gray-900 dark:text-white mb-2">
                                {{ $currentBook['name'] ?? $bookOsisId }}
                            </h1>
                            <p class="text-gray-600 dark:text-gray-400">
                                {{ count($chapters) }} {{ count($chapters) === 1 ? 'chapter' : 'chapters' }}
                                @if($currentBook)
                                    • {{ $currentBook['testament'] === 'OT' ? 'Old Testament' : 'New Testament' }}
                                @endif
                            </p>
                        </div>
                        <div class="text-right">
                            <div class="text-sm text-gray-500 dark:text-gray-400 mb-1">Start Reading</div>
                            <a href="/{{ $bookOsisId }}/1"
                               class="bg-blue-600 hover:bg-blue-700 text-white px-4 py-2 rounded-lg transition-colors">
                                Chapter 1 →
                            </a>
                        </div>
                    </div>
                </div>

                <!-- Popular Chapters -->
                @if(!empty($this->getPopularChapters()))
                    <div class="bg-blue-50 dark:bg-blue-900/20 rounded-lg border border-blue-200 dark:border-blue-800 p-6 mb-8">
                        <h2 class="text-lg font-semibold text-blue-900 dark:text-blue-100 mb-4 flex items-center">
                            <svg class="w-5 h-5 mr-2" fill="currentColor" viewBox="0 0 20 20">
                                <path d="M9.049 2.927c.3-.921 1.603-.921 1.902 0l1.07 3.292a1 1 0 00.95.69h3.462c.969 0 1.371 1.24.588 1.81l-2.8 2.034a1 1 0 00-.364 1.118l1.07 3.292c.3.921-.755 1.688-1.54 1.118l-2.8-2.034a1 1 0 00-1.175 0l-2.8 2.034c-.784.57-1.838-.197-1.539-1.118l1.07-3.292a1 1 0 00-.364-1.118L2.98 8.72c-.783-.57-.38-1.81.588-1.81h3.461a1 1 0 00.951-.69l1.07-3.292z"/>
                            </svg>
                            Popular Chapters
                        </h2>
                        <div class="grid grid-cols-3 sm:grid-cols-6 gap-3">
                            @foreach($this->getPopularChapters() as $chapterNum)
                                @if($chapterNum <= count($chapters))
                                    <a href="/{{ $bookOsisId }}/{{ $chapterNum }}"
                                       class="bg-blue-600 hover:bg-blue-700 text-white text-center py-3 px-4 rounded-lg transition-colors font-medium">
                                        {{ $chapterNum }}
                                    </a>
                                @endif
                            @endforeach
                        </div>
                    </div>
                @endif

                <!-- All Chapters Grid -->
                <div class="bg-white dark:bg-gray-800 rounded-lg border border-gray-200 dark:border-gray-700 p-6">
                    <h2 class="text-lg font-semibold text-gray-900 dark:text-white mb-6">All Chapters</h2>

                    <div class="grid grid-cols-5 sm:grid-cols-8 md:grid-cols-10 lg:grid-cols-12 gap-3">
                        @foreach($chapters as $chapter)
                            <a href="/{{ $bookOsisId }}/{{ $chapter['chapter_number'] }}"
                               class="bg-gray-100 dark:bg-gray-700 hover:bg-blue-600 hover:text-white text-gray-900 dark:text-gray-100 text-center py-3 px-2 rounded-lg transition-colors font-medium group">
                                <div class="text-lg">{{ $chapter['chapter_number'] }}</div>
                                @if(isset($chapter['verse_count']))
                                    <div class="text-xs text-gray-500 dark:text-gray-400 group-hover:text-blue-100 mt-1">
                                        {{ $chapter['verse_count'] }} v
                                    </div>
                                @endif
                            </a>
                        @endforeach
                    </div>
                </div>

                <!-- Quick Navigation -->
                <div class="mt-8 bg-gray-100 dark:bg-gray-800 rounded-lg p-6">
                    <h3 class="text-lg font-semibold text-gray-900 dark:text-white mb-4">Quick Navigation</h3>
                    <div class="grid grid-cols-2 sm:grid-cols-4 gap-4">
                        <a href="/" class="text-center p-4 bg-white dark:bg-gray-700 rounded-lg hover:bg-gray-50 dark:hover:bg-gray-600 transition-colors">
                            <div class="text-2xl mb-2">📖</div>
                            <div class="text-sm font-medium text-gray-900 dark:text-white">All Books</div>
                        </a>
                        <a href="/search" class="text-center p-4 bg-white dark:bg-gray-700 rounded-lg hover:bg-gray-50 dark:hover:bg-gray-600 transition-colors">
                            <div class="text-2xl mb-2">🔍</div>
                            <div class="text-sm font-medium text-gray-900 dark:text-white">Search</div>
                        </a>
                        @if(count($chapters) > 0)
                            <a href="/{{ $bookOsisId }}/1" class="text-center p-4 bg-white dark:bg-gray-700 rounded-lg hover:bg-gray-50 dark:hover:bg-gray-600 transition-colors">
                                <div class="text-2xl mb-2">▶️</div>
                                <div class="text-sm font-medium text-gray-900 dark:text-white">Start Reading</div>
                            </a>
                            <a href="/{{ $bookOsisId }}/{{ count($chapters) }}" class="text-center p-4 bg-white dark:bg-gray-700 rounded-lg hover:bg-gray-50 dark:hover:bg-gray-600 transition-colors">
                                <div class="text-2xl mb-2">⏭️</div>
                                <div class="text-sm font-medium text-gray-900 dark:text-white">Last Chapter</div>
                            </a>
                        @endif
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Floating Previous Book Button (Left Side) -->
    @php
        $prevBook = null;
        $nextBook = null;
        $currentIndex = null;

        // Find current book index and get prev/next books
        foreach($books as $index => $book) {
            if($book['osis_id'] === $bookOsisId) {
                $currentIndex = $index;
                if($index > 0) {
                    $prevBook = $books[$index - 1];
                }
                if($index < count($books) - 1) {
                    $nextBook = $books[$index + 1];
                }
                break;
            }
        }
    @endphp

    @if($prevBook)
        <div class="fixed left-4 top-1/2 transform -translate-y-1/2 z-40">
            <a href="/{{ $prevBook['osis_id'] }}"
               class="bg-white dark:bg-gray-800 hover:bg-gray-50 dark:hover:bg-gray-700 border border-gray-200 dark:border-gray-600 text-gray-700 dark:text-gray-300 p-3 rounded-full shadow-lg transition-colors group"
               title="Previous Book: {{ $prevBook['name'] }}">
                <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7"/>
                </svg>
            </a>
        </div>
    @endif

    <!-- Floating Next Book Button (Right Side) -->
    @if($nextBook)
        <div class="fixed right-4 top-1/2 transform -translate-y-1/2 z-40">
            <a href="/{{ $nextBook['osis_id'] }}"
               class="bg-white dark:bg-gray-800 hover:bg-gray-50 dark:hover:bg-gray-700 border border-gray-200 dark:border-gray-600 text-gray-700 dark:text-gray-300 p-3 rounded-full shadow-lg transition-colors group"
               title="Next Book: {{ $nextBook['name'] }}">
                <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"/>
                </svg>
            </a>
        </div>
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
