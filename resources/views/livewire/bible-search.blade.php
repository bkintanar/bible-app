<div class="flex flex-col bg-gray-50 dark:bg-gray-900 h-full">
    <!-- Fixed Header -->
    <nav class="bg-white dark:bg-gray-800 shadow-sm border-b border-gray-200 dark:border-gray-700 flex-shrink-0 z-50">
        <div class="px-4 sm:px-6 w-full max-w-none">
            <!-- Main Navigation Row -->
            <div class="flex items-center h-16 w-full">
                <!-- Left side -->
                <div class="flex-1 flex items-center gap-4">
                    <a href="{{ route('bible.index') }}" class="text-sm text-blue-600 dark:text-blue-400 hover:underline whitespace-nowrap">&larr; Back to reading</a>
                    <h1 class="text-xl font-semibold text-gray-900 dark:text-white">Search the Bible</h1>
                </div>

                <!-- Mobile Actions -->
                <div class="flex items-center space-x-1 sm:hidden">
                    <!-- Dark Mode Toggle (Mobile) -->
                    <button type="button" onclick="toggleDarkMode()" aria-label="Toggle dark mode" title="Toggle dark mode"
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
                    <button type="button" onclick="toggleDarkMode()" aria-label="Toggle dark mode" title="Toggle dark mode"
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
                {{-- A form: Enter and the button both submit, and the query is sent only then
                     (the old live binding ran a search on every pause in typing). --}}
                <form wire:submit="search" class="flex gap-2" role="search">
                    <div class="relative flex-1">
                        <label for="bible-search-q" class="sr-only">Search the Bible</label>
                        <input
                            id="bible-search-q"
                            wire:model="q"
                            type="search"
                            name="q"
                            maxlength="200"
                            autocomplete="off"
                            placeholder="Search for words or phrases..."
                            class="w-full border border-gray-400 dark:border-gray-600 bg-white dark:bg-gray-700 text-gray-900 dark:text-gray-100 rounded-lg pl-10 pr-4 py-3 focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-transparent placeholder-gray-500 dark:placeholder-gray-400"
                        >
                        <div class="absolute left-4 top-1/2 transform -translate-y-1/2 pointer-events-none">
                            <svg class="h-5 w-5 text-gray-500" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"/>
                            </svg>
                        </div>
                    </div>
                    <button
                        type="submit"
                        wire:loading.attr="disabled"
                        class="bg-blue-600 hover:bg-blue-700 text-white px-4 py-3 rounded-lg transition-colors whitespace-nowrap touch-friendly"
                    >
                        Search
                    </button>
                </form>
                @error('q')
                    <p class="mt-2 text-sm text-red-700 dark:text-red-400" role="alert">{{ $message }}</p>
                @enderror
            </div>
        </div>

        <!-- Scrollable Content Area -->
        <div id="search-scroller" class="flex-1 overflow-y-auto p-4 sm:p-8 focus:outline-none focus-visible:ring-2 focus-visible:ring-blue-500" tabindex="0" role="region" aria-label="Search results">
            @if($q)
                <!-- Search Info -->
                <div class="mb-6">
                    <h2 class="text-xl font-semibold text-gray-900 dark:text-gray-100 mb-2">
                        Search Results for "{{ $q }}"
                    </h2>
                    <div class="text-sm text-gray-600 dark:text-gray-400" role="status" aria-live="polite">
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
                            <div wire:key="result-{{ $result['book_osis_id'] }}-{{ $result['chapter'] }}-{{ $result['verse'] }}"
                                id="result-{{ $index }}"
                                class="bg-white dark:bg-gray-800 rounded-lg border border-gray-200 dark:border-gray-700 p-4 hover:shadow-md transition-shadow"
                            >
                                <div class="flex items-start justify-between">
                                    <div class="flex-1">
                                        <div class="text-sm font-medium text-blue-600 dark:text-blue-400 mb-2">
                                            <a href="{{ route('chapters.show', ['bookOsisId' => $result['book_osis_id'], 'chapterNumber' => $result['chapter']]) }}#verse-{{ $result['verse'] }}" class="hover:underline">
                                                {{ $result['reference'] }}
                                            </a>
                                        </div>
                                        <div class="bible-text text-gray-900 dark:text-gray-100">
                                            {!! str_replace(['&lt;mark&gt;', '&lt;/mark&gt;'], ['<mark>', '</mark>'], e($result['text'])) !!}
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
</div>
