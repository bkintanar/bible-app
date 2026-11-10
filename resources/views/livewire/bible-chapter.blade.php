<div class="h-screen bg-gray-50 dark:bg-gray-900 flex flex-col">
    {{-- STYLES & DARK MODE INITIALIZATION --}}
    @include('livewire.partials.styles')

    {{-- NAVIGATION HEADER --}}
    @include('livewire.partials.header')

    {{-- MAIN BOOK CONTENT AREA --}}
    {{-- Prev/next cross book boundaries (Mal 4 -> Matt 1) and point at the canonical chapter URL,
         so a page turn is one request instead of a /Book/N -> /books/Book/chapters/N redirect. --}}
    @php $nav = $this->getNavigationTargets(); @endphp
    <!-- Main Content - Scrollable -->
    <div class="flex-1 overflow-hidden book-container"
         data-prev-url="{{ $nav['prev']['url'] ?? '' }}"
         data-next-url="{{ $nav['next']['url'] ?? '' }}"
         style="--peel-progress: 0;">
        <!-- Previous Chapter (Preloaded). Visual only (page-peel preview): hidden from assistive
             tech, focus and find-in-page, and emits no verse ids. -->
        <div class="page page-prev absolute inset-0 h-full overflow-y-auto" style="z-index: 0;" aria-hidden="true" inert>
            <div class="book-spine-shadow"></div>
            <div class="page-content h-full overflow-y-auto bg-gray-50 dark:bg-gray-900" id="prev-chapter-content">
                @if($chapterNumber > 1)
                    <!-- Full Previous Chapter Content -->
                    <div class="p-4 sm:p-8 pb-20">
                        <div class="max-w-4xl mx-auto">
                            <div class="bg-white dark:bg-gray-900 dark:border-gray-700 p-4 sm:p-8">
                                <div class="prose prose-lg max-w-none dark:prose-invert">
                                    @if($this->previousChapterVerses->isNotEmpty())
                                        @include('livewire.partials.chapter-verses', [
                                            'verses' => $this->previousChapterVerses,
                                            'chapterNumber' => $chapterNumber - 1,
                                            'getFontSizeClass' => function() { return $this->getFontSizeClass(); },
                                            'emitIds' => false,
                                        ])
                                    @else
                                        <!-- Fallback if previous chapter data not available -->
                                        <div class="text-center py-12">
                                            <div class="text-gray-600 dark:text-gray-400 font-serif text-lg mb-4">
                                                {{ $currentBook['name'] ?? $bookOsisId }} {{ $chapterNumber - 1 }}
                                            </div>
                                            <div class="text-gray-500 dark:text-gray-500 font-serif">
                                                Previous chapter content loading...
                                            </div>
                                        </div>
                                    @endif
                                </div>
                            </div>
                        </div>
                    </div>
                @else
                    <!-- First Chapter -->
                    <div class="p-4 sm:p-8 pb-20">
                        <div class="max-w-4xl mx-auto">
                            <div class="bg-white dark:bg-gray-900 dark:border-gray-700 p-4 sm:p-8">
                                <div class="text-center py-16">
                                    <div class="text-gray-400 dark:text-gray-600 font-serif text-xl mb-4">
                                        Beginning of {{ $currentBook['name'] ?? $bookOsisId }}
                                    </div>
                                    <div class="text-gray-500 dark:text-gray-500 font-serif">
                                        This is the first chapter
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                @endif
            </div>
        </div>

        <!-- Current Chapter -->
        <div class="page page-current absolute inset-0 h-full overflow-y-auto" style="z-index: 2;">
            <!-- Book spine shadow -->
            <div class="book-spine-shadow"></div>

            <!-- Main page content -->
            <div class="page-content h-full overflow-y-auto bg-gray-50 dark:bg-gray-900">
                @if($showSearchResults)
                    <!-- Dedicated Search Results Page -->
                    <div class="p-4 sm:p-8 pb-20">
                        <div class="max-w-4xl mx-auto">
                            <!-- Search Stats -->
                            @if(!empty($searchStats['throttled']))
                                <div class="text-amber-700 dark:text-amber-400 mb-6" role="status">
                                    Too many searches in the last minute. Please wait a moment and try again.
                                </div>
                            @elseif(!empty($searchStats['invalid']))
                                <div class="text-red-700 dark:text-red-400 mb-6" role="alert">{{ $searchStats['invalid'] }}</div>
                            @elseif(!empty($searchStats))
                                <div class="text-gray-600 dark:text-gray-400 mb-6">
                                    Found {{ $searchStats['total_found'] }} results for "<strong>{{ $searchStats['query'] }}</strong>"
                                    <span class="text-gray-500">({{ $searchStats['search_time_ms'] }}ms)</span>
                                </div>
                            @endif

                            @if($isSearching)
                                <!-- Loading State -->
                                <div class="bg-white dark:bg-gray-800 border border-gray-200 dark:border-gray-700 rounded-lg p-6">
                                    <div class="flex items-center justify-center py-12">
                                        <svg class="animate-spin -ml-1 mr-3 h-6 w-6 text-blue-600" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24">
                                            <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                                            <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                                        </svg>
                                        <span class="text-gray-600 dark:text-gray-400 text-lg">Searching...</span>
                                    </div>
                                </div>
                            @elseif(!empty($searchResults))
                                <!-- Search Results List -->
                                <div class="space-y-4">
                                    @foreach($searchResults as $result)
                                        <div class="bg-white dark:bg-gray-800 border border-gray-200 dark:border-gray-700 rounded-lg p-6 hover:border-blue-300 dark:hover:border-blue-600 transition-colors">
                                            <div class="flex items-start justify-between">
                                                <div class="flex-1">
                                                    <a href="{{ route('chapters.show', ['bookOsisId' => $result['book_osis_id'], 'chapterNumber' => $result['chapter']]) }}#verse-{{ $result['verse'] }}"
                                                       class="text-blue-600 dark:text-blue-400 hover:text-blue-700 dark:hover:text-blue-300 font-semibold font-serif text-lg mb-3 block">
                                                        {{ $result['reference'] ?? ($result['book_osis_id'] . ' ' . $result['chapter'] . ':' . $result['verse']) }}
                                                    </a>
                                                    <div class="text-gray-900 dark:text-gray-100 leading-relaxed text-lg font-serif" style="font-family: 'Charter', 'Source Serif Pro', 'Crimson Text', 'Libre Baskerville', 'PT Serif', 'Georgia', 'Times New Roman', serif; line-height: 1.7;">
                                                        {{-- FTS5's snippet() already wraps each match in <mark>, in the scripture's own
                                                             casing. Escape everything, then re-admit only those tags (as the search page
                                                             does). Splicing the query into the HTML broke markup (a search for "em"
                                                             matched inside <em>) and replaced the scripture's casing with the reader's. --}}
                                                        {!! str_replace(['&lt;mark&gt;', '&lt;/mark&gt;'], ['<mark>', '</mark>'], e($result['text'])) !!}
                                                    </div>
                                                </div>
                                            </div>
                                        </div>
                                    @endforeach
                                </div>
                            @else
                                <!-- No Results -->
                                <div class="bg-white dark:bg-gray-800 border border-gray-200 dark:border-gray-700 rounded-lg p-6">
                                    <div class="text-center py-12">
                                        <svg class="mx-auto h-12 w-12 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"/>
                                        </svg>
                                        <h3 class="mt-4 text-lg font-medium text-gray-900 dark:text-white">No results found</h3>
                                        <p class="mt-2 text-gray-500 dark:text-gray-400">Try searching with different keywords.</p>
                                    </div>
                                </div>
                            @endif
                        </div>
                    </div>
                @else
                    <!-- Chapter Content -->
                    <div class="p-4 sm:p-8 pb-20">
                        <div class="max-w-4xl mx-auto">
                            <!-- Verses -->
                            <div class="bg-white dark:bg-gray-900 dark:border-gray-700 p-4 sm:p-8">
                                <div class="prose prose-lg max-w-none dark:prose-invert">
                                    <!-- Display verses grouped by paragraphs -->
                                    @include('livewire.partials.chapter-verses', [
                                        'verses' => $verses,
                                        'chapterNumber' => $chapterNumber,
                                        'getFontSizeClass' => function() { return $this->getFontSizeClass(); }
                                    ])
                                </div>
                            </div>
                        </div>
                    </div>
                @endif
            </div>
        </div>

        <!-- Next Chapter (Preloaded). Visual only, like the previous one. -->
        <div class="page page-next absolute inset-0 h-full overflow-y-auto" style="z-index: 0;" aria-hidden="true" inert>
            <div class="book-spine-shadow"></div>
            <div class="page-content h-full overflow-y-auto bg-gray-50 dark:bg-gray-900" id="next-chapter-content">
                @if($chapterNumber < ($chapters->max('chapter_number') ?? 0))
                    <!-- Full Next Chapter Content -->
                    <div class="p-4 sm:p-8 pb-20">
                        <div class="max-w-4xl mx-auto">
                            <div class="bg-white dark:bg-gray-900 dark:border-gray-700 p-4 sm:p-8">
                                <div class="prose prose-lg max-w-none dark:prose-invert">
                                    @if($this->nextChapterVerses->isNotEmpty())
                                        @include('livewire.partials.chapter-verses', [
                                            'verses' => $this->nextChapterVerses,
                                            'chapterNumber' => $chapterNumber + 1,
                                            'getFontSizeClass' => function() { return $this->getFontSizeClass(); },
                                            'emitIds' => false,
                                        ])
                                    @else
                                        <!-- Fallback if next chapter data not available -->
                                        <div class="text-center py-12">
                                            <div class="text-gray-600 dark:text-gray-400 font-serif text-lg mb-4">
                                                {{ $currentBook['name'] ?? $bookOsisId }} {{ $chapterNumber + 1 }}
                                            </div>
                                            <div class="text-gray-500 dark:text-gray-500 font-serif">
                                                Next chapter content loading...
                                            </div>
                                        </div>
                                    @endif
                                </div>
                            </div>
                        </div>
                    </div>
                @else
                    <!-- Last Chapter -->
                    <div class="p-4 sm:p-8 pb-20">
                        <div class="max-w-4xl mx-auto">
                            <div class="bg-white dark:bg-gray-900 dark:border-gray-700 p-4 sm:p-8">
                                <div class="text-center py-16">
                                    <div class="text-gray-400 dark:text-gray-600 font-serif text-xl mb-4">
                                        End of {{ $currentBook['name'] ?? $bookOsisId }}
                                    </div>
                                    <div class="text-gray-500 dark:text-gray-500 font-serif">
                                        This is the last chapter
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                @endif
            </div>
        </div>
    </div>

    {{-- FLOATING NAVIGATION BUTTONS --}}
    <!-- Floating Previous Chapter Button (Left Side) -->
    @if($nav['prev'])
        <div class="fixed left-0 top-1/2 transform -translate-y-1/2 z-40">
            <a href="{{ $nav['prev']['url'] }}"
               class="bg-blue-600 hover:bg-blue-700 dark:bg-gray-700 dark:hover:bg-gray-600 text-white w-[44px] h-[60px] shadow-lg dark:shadow-gray-900 transition-all duration-200 hover:scale-110 flex items-center justify-center group"
               title="Previous: {{ $nav['prev']['label'] }}"
               aria-label="Previous chapter: {{ $nav['prev']['label'] }}">
                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7"/>
                </svg>
            </a>
        </div>
    @endif

    <!-- Floating Next Chapter Button (Right Side) -->
    @if($nav['next'])
        <div class="fixed right-0 top-1/2 transform -translate-y-1/2 z-40">
            <a href="{{ $nav['next']['url'] }}"
               class="bg-blue-600 hover:bg-blue-700 dark:bg-gray-700 dark:hover:bg-gray-600 text-white w-[44px] h-[60px] shadow-lg dark:shadow-gray-900 transition-all duration-200 hover:scale-110 flex items-center justify-center group"
               title="Next: {{ $nav['next']['label'] }}"
               aria-label="Next chapter: {{ $nav['next']['label'] }}">
                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"/>
                </svg>
            </a>
        </div>
    @endif

    {{-- BOOK/CHAPTER SELECTOR POPUP --}}
    <!-- Full-Screen Book/Chapter Selector Popup -->
    @if($showBookSelector)
        <div class="fixed inset-0 z-50 bg-white dark:bg-gray-900">
            <!-- Header -->
            <div class="bg-white dark:bg-gray-800 shadow-sm border-b border-gray-200 dark:border-gray-700 sticky top-0 z-10">
                <div class="px-4 sm:px-6 py-4">
                    <div class="flex items-center justify-between">
                        <div class="flex items-center space-x-4">
                            @if($selectorMode === 'chapters' && $selectedBookForChapters)
                                <button wire:click="backToBooks" class="p-2 rounded-lg text-gray-600 dark:text-gray-300 hover:bg-gray-100 dark:hover:bg-gray-700 transition-colors">
                                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7"/>
                                    </svg>
                                </button>
                                <h2 class="text-xl font-semibold text-gray-900 dark:text-white font-serif">
                                    {{ $selectedBookForChapters['name'] }} - Select Chapter
                                </h2>
                            @else
                                <h2 class="text-xl font-semibold text-gray-900 dark:text-white font-serif">
                                    Select Book
                                </h2>
                            @endif
                        </div>
                        <button wire:click="hideBookSelector" class="p-2 rounded-lg text-gray-600 dark:text-gray-300 hover:bg-gray-100 dark:hover:bg-gray-700 transition-colors">
                            <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/>
                            </svg>
                        </button>
                    </div>
                </div>
            </div>

            <!-- Content -->
            <div class="h-full overflow-y-auto pb-20">
                @if($selectorMode === 'books')
                    <!-- Books List -->
                    <div class="p-4 sm:p-6">
                        <div class="max-w-4xl mx-auto">
                            <!-- Old Testament -->
                            <div class="mb-8">
                                <h3 class="text-lg font-semibold text-gray-900 dark:text-white mb-4 font-serif">Old Testament</h3>
                                <div class="grid grid-cols-2 sm:grid-cols-3 md:grid-cols-4 lg:grid-cols-5 gap-3">
                                    @foreach($testamentBooks['oldTestament'] as $book)
                                        <button wire:click="selectBookForChapters('{{ $book['osis_id'] }}')"
                                                class="p-3 text-left bg-white dark:bg-gray-800 border border-gray-200 dark:border-gray-700 rounded-lg hover:bg-blue-50 dark:hover:bg-gray-700 hover:border-blue-200 dark:hover:border-gray-600 transition-colors {{ $book['osis_id'] === $bookOsisId ? 'bg-blue-100 dark:bg-blue-900 border-blue-300 dark:border-blue-600' : '' }}">
                                            <div class="font-medium text-gray-900 dark:text-white font-serif text-sm">
                                                {{ $book['name'] }}
                                            </div>
                                        </button>
                                    @endforeach
                                </div>
                            </div>

                            <!-- New Testament -->
                            <div class="mb-8">
                                <h3 class="text-lg font-semibold text-gray-900 dark:text-white mb-4 font-serif">New Testament</h3>
                                <div class="grid grid-cols-2 sm:grid-cols-3 md:grid-cols-4 lg:grid-cols-5 gap-3">
                                    @foreach($testamentBooks['newTestament'] as $book)
                                        <button wire:click="selectBookForChapters('{{ $book['osis_id'] }}')"
                                                class="p-3 text-left bg-white dark:bg-gray-800 border border-gray-200 dark:border-gray-700 rounded-lg hover:bg-blue-50 dark:hover:bg-gray-700 hover:border-blue-200 dark:hover:border-gray-600 transition-colors {{ $book['osis_id'] === $bookOsisId ? 'bg-blue-100 dark:bg-blue-900 border-blue-300 dark:border-blue-600' : '' }}">
                                            <div class="font-medium text-gray-900 dark:text-white font-serif text-sm">
                                                {{ $book['name'] }}
                                            </div>
                                        </button>
                                    @endforeach
                                </div>
                            </div>
                        </div>
                    </div>
                @elseif($selectorMode === 'chapters' && $selectedBookForChapters)
                    <!-- Chapters List -->
                    <div class="p-4 sm:p-6">
                        <div class="max-w-7xl mx-auto">
                            <div class="grid grid-cols-5 sm:grid-cols-6 md:grid-cols-8 lg:grid-cols-10 xl:grid-cols-12 gap-3">
                                @if(isset($selectedBookForChapters['chapters']))
                                    @foreach($selectedBookForChapters['chapters'] as $chapter)
                                        <a href="{{ route('chapters.show', ['bookOsisId' => $selectedBookForChapters['osis_id'], 'chapterNumber' => $chapter['chapter_number']]) }}"
                                                @if($chapter['chapter_number'] == $chapterNumber && $selectedBookForChapters['osis_id'] === $bookOsisId) aria-current="page" @endif
                                                class="aspect-square p-2 flex items-center justify-center bg-white dark:bg-gray-800 border border-gray-200 dark:border-gray-700 rounded-lg text-center hover:bg-blue-50 dark:hover:bg-gray-700 hover:border-blue-200 dark:hover:border-gray-600 transition-colors {{ $chapter['chapter_number'] == $chapterNumber && $selectedBookForChapters['osis_id'] === $bookOsisId ? 'bg-blue-100 dark:bg-blue-900 border-blue-300 dark:border-blue-600' : '' }}">
                                            <span class="font-medium text-gray-900 dark:text-white font-serif text-sm">
                                                {{ $chapter['chapter_number'] }}
                                            </span>
                                        </a>
                                    @endforeach
                                @endif
                            </div>
                        </div>
                    </div>
                @endif
            </div>
        </div>
    @endif

    {{-- JAVASCRIPT FUNCTIONS --}}
    @include('livewire.partials.scripts')
</div>
