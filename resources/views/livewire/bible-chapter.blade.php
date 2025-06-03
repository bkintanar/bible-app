<div class="h-screen bg-gray-50 dark:bg-gray-900 flex flex-col overflow-hidden">
    <!-- Sticky Header -->
    <nav class="bg-white dark:bg-gray-800 shadow-sm border-b border-gray-200 dark:border-gray-700 sticky top-0 z-50 flex-shrink-0">
        <div class="px-4 sm:px-6 py-4">
            <div class="flex items-center justify-between">
                <div class="flex items-center space-x-4">
                    <button wire:click="openBookSelector" class="text-xl font-semibold text-gray-900 dark:text-white hover:text-blue-600 dark:hover:text-blue-400 transition-colors font-serif" style="font-family: 'Charter', 'Source Serif Pro', 'Crimson Text', 'Libre Baskerville', 'PT Serif', 'Georgia', 'Times New Roman', serif;">
                        {{ $currentBook['name'] ?? $bookOsisId }} {{ $chapterNumber }}
                    </button>
                </div>

                <div class="flex items-center space-x-2">
                    <!-- Translation Selector -->
                    <x-translation-dropdown
                        :available-translations="$availableTranslations"
                        :current-translation="$currentTranslation"
                        position="left"
                    />

                    <!-- Search Toggle -->
                    <button wire:click="toggleSearch"
                            class="p-2 rounded-lg text-gray-600 dark:text-gray-300 hover:bg-gray-100 dark:hover:bg-gray-700 transition-colors {{ $showSearch ? 'bg-blue-100 dark:bg-blue-900 text-blue-600 dark:text-blue-400' : '' }}">
                        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"/>
                        </svg>
                    </button>

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

    <!-- Main Content - Scrollable -->
    <div class="flex-1 overflow-y-auto min-h-0">
        <div class="p-4 sm:p-8 pb-20">
            <div class="max-w-4xl mx-auto">
                <!-- Verses -->
                <div class="bg-white dark:bg-gray-900 dark:border-gray-700 p-4 sm:p-8">
                    <div class="prose prose-lg max-w-none dark:prose-invert">
                        <!-- Display verses grouped by paragraphs -->
                        @foreach($verses as $paragraph)
                            <div>
                                @if(isset($paragraph['verses']) && !empty($paragraph['verses']))
                                    @if(isset($paragraph['type']) && $paragraph['type'] === 'individual_verse')
                                        <!-- Individual verse display (for Psalm 119 with acrostic titles) -->
                                        @foreach($paragraph['verses'] as $verse)
                                            <!-- Display any titles for this verse -->
                                            @if(isset($verse['chapter_titles']) && !empty($verse['chapter_titles']))
                                                <div class="mb-4 block w-full">
                                                    {!! $verse['chapter_titles'] !!}
                                                </div>
                                            @endif

                                            <div class="mb-3 text-gray-900 dark:text-gray-100 leading-relaxed {{ $this->getFontSizeClass() }} font-serif" style="font-family: 'Charter', 'Source Serif Pro', 'Crimson Text', 'Libre Baskerville', 'PT Serif', 'Georgia', 'Times New Roman', serif; line-height: 1.7; letter-spacing: 0.01em;" id="verse-{{ $verse['verse_number'] }}">
                                                <span class="text-xs font-medium text-gray-500 dark:text-gray-400 mr-2 align-super font-sans">{{ $verse['verse_number'] }}</span>{!! strip_tags($verse['text'], '<em><strong><sup><sub>') !!}
                                            </div>
                                        @endforeach
                                    @else
                                        <!-- Paragraph with multiple verses (default behavior) -->
                                        <div class="mb-3 text-gray-900 dark:text-gray-100 leading-relaxed {{ $this->getFontSizeClass() }} text-justify font-serif" style="font-family: 'Charter', 'Source Serif Pro', 'Crimson Text', 'Libre Baskerville', 'PT Serif', 'Georgia', 'Times New Roman', serif; line-height: 1.7; letter-spacing: 0.01em;">
                                            @foreach($paragraph['verses'] as $verse)
                                                <!-- Display any titles for this verse -->
                                                @if(isset($verse['chapter_titles']) && !empty($verse['chapter_titles']))
                                                    <div class="mb-4 block w-full">
                                                        {!! $verse['chapter_titles'] !!}
                                                    </div>
                                                @endif

                                                <span class="inline" id="verse-{{ $verse['verse_number'] }}">
                                                    <span class="text-xs font-medium text-gray-500 dark:text-gray-400 mr-1 align-super font-sans">{{ $verse['verse_number'] }}</span><!--
                                                    -->{!! strip_tags($verse['text'], '<em><strong><sup><sub>') !!}<!--
                                                    -->@if(!$loop->last) @endif
                                                </span>
                                            @endforeach
                                        </div>
                                    @endif
                                @elseif(isset($paragraph['combined_text']))
                                    <!-- Combined paragraph text (fallback) -->
                                    <p class="temb-3 xt-gray-900 dark:text-gray-100 leading-relaxed {{ $this->getFontSizeClass() }} text-justify font-serif" style="font-family: 'Charter', 'Source Serif Pro', 'Crimson Text', 'Libre Baskerville', 'PT Serif', 'Georgia', 'Times New Roman', serif; line-height: 1.7; letter-spacing: 0.01em;">
                                        {!! strip_tags($paragraph['combined_text'], '<em><strong><sup><sub>') !!}
                                    </p>
                                @endif
                            </div>
                        @endforeach
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Floating Previous Chapter Button (Left Side) -->
    @if($chapterNumber > 1)
        <div class="fixed left-0 top-1/2 transform -translate-y-1/2 z-40">
            <a href="/{{ $bookOsisId }}/{{ $chapterNumber - 1 }}"
               class="bg-blue-600 hover:bg-blue-700 dark:bg-gray-700 dark:hover:bg-gray-600 text-white w-[30px] h-[60px] shadow-lg dark:shadow-gray-900 transition-all duration-200 hover:scale-110 flex items-center justify-center group"
               title="Previous Chapter: {{ $chapterNumber - 1 }}">
                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7"/>
                </svg>
            </a>
        </div>
    @endif

    <!-- Floating Next Chapter Button (Right Side) -->
    @if($chapterNumber < count($chapters))
        <div class="fixed right-0 top-1/2 transform -translate-y-1/2 z-40">
            <a href="/{{ $bookOsisId }}/{{ $chapterNumber + 1 }}"
               class="bg-blue-600 hover:bg-blue-700 dark:bg-gray-700 dark:hover:bg-gray-600 text-white w-[30px] h-[60px] shadow-lg dark:shadow-gray-900 transition-all duration-200 hover:scale-110 flex items-center justify-center group"
               title="Next Chapter: {{ $chapterNumber + 1 }}">
                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"/>
                </svg>
            </a>
        </div>
    @endif

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
                        <div class="max-w-4xl mx-auto">
                            <div class="grid grid-cols-4 sm:grid-cols-6 md:grid-cols-8 lg:grid-cols-10 xl:grid-cols-12 gap-3">
                                @if(isset($selectedBookForChapters['chapters']))
                                    @foreach($selectedBookForChapters['chapters'] as $chapter)
                                        <button wire:click="goToChapter('{{ $selectedBookForChapters['osis_id'] }}', {{ $chapter['chapter_number'] }})"
                                                class="aspect-square flex items-center justify-center bg-white dark:bg-gray-800 border border-gray-200 dark:border-gray-700 rounded-lg hover:bg-blue-50 dark:hover:bg-gray-700 hover:border-blue-200 dark:hover:border-gray-600 transition-colors {{ $chapter['chapter_number'] == $chapterNumber && $selectedBookForChapters['osis_id'] === $bookOsisId ? 'bg-blue-100 dark:bg-blue-900 border-blue-300 dark:border-blue-600' : '' }}">
                                            <span class="font-medium text-gray-900 dark:text-white font-serif">
                                                {{ $chapter['chapter_number'] }}
                                            </span>
                                        </button>
                                    @endforeach
                                @endif
                            </div>
                        </div>
                    </div>
                @endif
            </div>
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
