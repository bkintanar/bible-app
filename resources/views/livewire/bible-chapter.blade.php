<div class="h-screen bg-gray-50 dark:bg-gray-900 flex flex-col">
    {{-- STYLES & DARK MODE INITIALIZATION --}}
    @include('livewire.partials.styles')

    {{-- NAVIGATION HEADER --}}
    @include('livewire.partials.header')

    {{-- MAIN BOOK CONTENT AREA --}}
    <!-- Main Content - Scrollable -->
    <div class="flex-1 overflow-hidden book-container"
         data-prev-url="{{ $chapterNumber > 1 ? '/' . $bookOsisId . '/' . ($chapterNumber - 1) : '' }}"
         data-next-url="{{ $chapterNumber < ($chapters->max('chapter_number') ?? 0) ? '/' . $bookOsisId . '/' . ($chapterNumber + 1) : '' }}"
         style="--peel-progress: 0;">
        <!-- Previous Chapter (Preloaded) -->
        <div class="page page-prev absolute inset-0 h-full overflow-y-auto" style="z-index: 0;">
            <div class="book-spine-shadow"></div>
            <div class="page-content h-full overflow-y-auto bg-gray-50 dark:bg-gray-900" id="prev-chapter-content">
                @if($chapterNumber > 1)
                    <!-- Full Previous Chapter Content -->
                    <div class="p-4 sm:p-8 pb-20">
                        <div class="max-w-4xl mx-auto">
                            <div class="bg-white dark:bg-gray-900 dark:border-gray-700 p-4 sm:p-8">
                                <div class="prose prose-lg max-w-none dark:prose-invert">
                                    @if(isset($previousChapterVerses) && !empty($previousChapterVerses))
                                        <!-- Display previous chapter verses -->
                                        @foreach($previousChapterVerses as $paragraph)
                                            <div class="{{ isset($paragraph['has_paragraph_marker']) ? 'mb-8 osis-paragraph' : 'mb-3 artificial-paragraph' }} {{ isset($paragraph['type']) && $paragraph['type'] === 'individual_verse' ? 'mb-3' : 'mb-8' }}">
                                                @if(isset($paragraph['verses']) && !empty($paragraph['verses']))
                                                    @if(isset($paragraph['type']) && $paragraph['type'] === 'individual_verse')
                                                        <!-- Individual verse display -->
                                                        @php $titlesDisplayed = false; @endphp
                                                        @foreach($paragraph['verses'] as $verse)
                                                            @if(!$titlesDisplayed && isset($verse['chapter_titles']) && !empty($verse['chapter_titles']))
                                                                <div class="mb-4 block w-full">
                                                                    <div class="acrostic-title text-center text-lg font-semibold text-gray-700 dark:text-gray-300 mb-3 font-serif border-b border-gray-200 dark:border-gray-600 pb-2">{!! strip_tags($verse['chapter_titles'], '<em><strong><sup><sub><foreign>') !!}</div>
                                                                </div>
                                                                @php $titlesDisplayed = true; @endphp
                                                            @endif

                                                            @if($verse['verse_number'] === 1)
                                                                <div class="mb-2 text-gray-900 dark:text-gray-100 leading-relaxed {{ $this->getFontSizeClass() }} font-serif verse-content" style="font-family: 'Charter', 'Source Serif Pro', 'Crimson Text', 'Libre Baskerville', 'PT Serif', 'Georgia', 'Times New Roman', serif; line-height: 1.7; letter-spacing: 0.01em;">
                                                                    <span class="text-gray-700 dark:text-gray-300 font-serif" style="float: left; font-size: 4rem; line-height: 3.2rem; margin-right: 0.5rem; margin-top: 0rem; font-weight: bold;">{{ $chapterNumber - 1 }}</span><span class="inline">{!! strip_tags($verse['text'], '<em><strong><sup><sub><span>') !!}</span>
                                                                </div>
                                                            @else
                                                                <div class="mb-2 text-gray-900 dark:text-gray-100 leading-relaxed {{ $this->getFontSizeClass() }} font-serif verse-content" style="font-family: 'Charter', 'Source Serif Pro', 'Crimson Text', 'Libre Baskerville', 'PT Serif', 'Georgia', 'Times New Roman', serif; line-height: 1.7; letter-spacing: 0.01em;">
                                                                    <span class="verse-number text-xs font-medium text-gray-600 dark:text-gray-400 align-super font-serif">{{ $verse['verse_number'] }}</span>{!! strip_tags($verse['text'], '<em><strong><sup><sub><span>') !!}
                                                                </div>
                                                            @endif
                                                        @endforeach
                                                    @else
                                                        <!-- Bible-style paragraph formatting -->
                                                        <div class="mb-6 text-gray-900 dark:text-gray-100 leading-relaxed {{ $this->getFontSizeClass() }} text-justify font-serif {{ $loop->first ? '' : 'first-line:indent-8' }} after:content-[''] after:table after:clear-both verse-content" style="font-family: 'Charter', 'Source Serif Pro', 'Crimson Text', 'Libre Baskerville', 'PT Serif', 'Georgia', 'Times New Roman', serif; line-height: 1.8; letter-spacing: 0.01em;">
                                                            @php $titlesDisplayed = false; @endphp
                                                            @foreach($paragraph['verses'] as $verse)
                                                                @if(!$titlesDisplayed && isset($verse['chapter_titles']) && !empty($verse['chapter_titles']))
                                                                    <div class="mb-4 block w-full -indent-8">
                                                                        {!! $verse['chapter_titles'] !!}
                                                                    </div>
                                                                    @php $titlesDisplayed = true; @endphp
                                                                @endif

                                                                @if($verse['verse_number'] === 1)
                                                                    <span class="text-gray-700 dark:text-gray-300 font-serif" style="float: left; font-size: 4rem; line-height: 3.2rem; margin-right: 0.5rem; margin-top: 0rem; font-weight: bold;">{{ $chapterNumber - 1 }}</span><span class="inline">{!! strip_tags($verse['text'], '<em><strong><sup><sub><span>') !!}</span>
                                                                @else
                                                                    <span class="inline">
                                                                        <span class="verse-number text-xs font-medium text-gray-500 dark:text-gray-400 align-super font-serif">{{ $verse['verse_number'] }}</span>{!! strip_tags($verse['text'], '<em><strong><sup><sub><span>') !!}
                                                                    </span>
                                                                @endif
                                                            @endforeach
                                                        </div>
                                                    @endif
                                                @elseif(isset($paragraph['combined_text']))
                                                    <div class="mb-6 text-gray-900 dark:text-gray-100 leading-relaxed {{ $this->getFontSizeClass() }} text-justify font-serif {{ $loop->first ? '' : 'first-line:indent-8' }} verse-content" style="font-family: 'Charter', 'Source Serif Pro', 'Crimson Text', 'Libre Baskerville', 'PT Serif', 'Georgia', 'Times New Roman', serif; line-height: 1.8; letter-spacing: 0.01em;">
                                                        {!! strip_tags($paragraph['combined_text'], '<em><strong><sup><sub><span>') !!}
                                                    </div>
                                                @endif
                                            </div>
                                        @endforeach
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
                            @if(!empty($searchStats))
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
                                                    <a href="/{{ $result['book_osis_id'] }}/{{ $result['chapter'] }}"
                                                       class="text-blue-600 dark:text-blue-400 hover:text-blue-700 dark:hover:text-blue-300 font-semibold font-serif text-lg mb-3 block">
                                                        {{ $result['reference'] ?? ($result['book_osis_id'] . ' ' . $result['chapter'] . ':' . $result['verse']) }}
                                                    </a>
                                                    <div class="text-gray-900 dark:text-gray-100 leading-relaxed text-lg font-serif" style="font-family: 'Charter', 'Source Serif Pro', 'Crimson Text', 'Libre Baskerville', 'PT Serif', 'Georgia', 'Times New Roman', serif; line-height: 1.7;">
                                                        @php
                                                            $text = strip_tags($result['text'], '<em><strong><sup><sub><span><mark>');
                                                            if (!empty($searchQuery)) {
                                                                $text = str_ireplace($searchQuery, '<mark>' . $searchQuery . '</mark>', $text);
                                                            }
                                                        @endphp
                                                        {!! $text !!}
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
                                    @foreach($verses as $paragraph)
                                        <div class="{{ isset($paragraph['has_paragraph_marker']) ? 'mb-8  osis-paragraph' : 'mb-3 artificial-paragraph' }} {{ isset($paragraph['type']) && $paragraph['type'] === 'individual_verse' ? 'mb-3' : 'mb-8' }}">
                                            @if(isset($paragraph['verses']) && !empty($paragraph['verses']))
                                                @if(isset($paragraph['type']) && $paragraph['type'] === 'individual_verse')
                                                    <!-- Individual verse display (for Psalm 119 with acrostic titles) -->
                                                    @php
                                                        $titlesDisplayed = false;
                                                    @endphp
                                                    @foreach($paragraph['verses'] as $verse)
                                                        <!-- Display titles only once per paragraph -->
                                                        @if(!$titlesDisplayed && isset($verse['chapter_titles']) && !empty($verse['chapter_titles']))
                                                            <div class="mb-4 block w-full">
                                                                <div class="acrostic-title text-center text-lg font-semibold text-gray-700 dark:text-gray-300 mb-3 font-serif border-b border-gray-200 dark:border-gray-600 pb-2">{!! strip_tags($verse['chapter_titles'], '<em><strong><sup><sub><foreign>') !!}</div>
                                                            </div>
                                                            @php $titlesDisplayed = true; @endphp
                                                        @endif

                                                        @if($verse['verse_number'] === 1)
                                                            <!-- Proper drop cap implementation -->
                                                            <div class="mb-2 text-gray-900 dark:text-gray-100 leading-relaxed {{ $this->getFontSizeClass() }} font-serif verse-content" style="font-family: 'Charter', 'Source Serif Pro', 'Crimson Text', 'Libre Baskerville', 'PT Serif', 'Georgia', 'Times New Roman', serif; line-height: 1.7; letter-spacing: 0.01em;">
                                                                <span class="text-gray-700 dark:text-gray-300 font-serif" style="float: left; font-size: 4rem; line-height: 3.2rem; margin-right: 0.5rem; margin-top: 0rem; font-weight: bold;">{{ $chapterNumber }}</span><span class="inline" id="verse-{{ $verse['verse_number'] }}">{!! strip_tags($verse['text'], '<em><strong><sup><sub><span>') !!}</span>
                                                            </div>
                                                        @else
                                                            <div class="mb-2 text-gray-900 dark:text-gray-100 leading-relaxed {{ $this->getFontSizeClass() }} font-serif verse-content" style="font-family: 'Charter', 'Source Serif Pro', 'Crimson Text', 'Libre Baskerville', 'PT Serif', 'Georgia', 'Times New Roman', serif; line-height: 1.7; letter-spacing: 0.01em;" id="verse-{{ $verse['verse_number'] }}">
                                                                <!-- Regular verse number for other verses -->
                                                                <span class="verse-number text-xs font-medium text-gray-600 dark:text-gray-400 align-super font-serif">{{ $verse['verse_number'] }}</span>{!! strip_tags($verse['text'], '<em><strong><sup><sub><span>') !!}
                                                            </div>
                                                        @endif
                                                    @endforeach
                                                @else
                                                    <!-- Bible-style paragraph formatting -->
                                                    <div class="mb-6 text-gray-900 dark:text-gray-100 leading-relaxed {{ $this->getFontSizeClass() }} text-justify font-serif {{ $loop->first ? '' : 'first-line:indent-8' }} after:content-[''] after:table after:clear-both verse-content" style="font-family: 'Charter', 'Source Serif Pro', 'Crimson Text', 'Libre Baskerville', 'PT Serif', 'Georgia', 'Times New Roman', serif; line-height: 1.8; letter-spacing: 0.01em;">
                                                        @php
                                                            $titlesDisplayed = false;
                                                        @endphp
                                                        @foreach($paragraph['verses'] as $verse)
                                                            <!-- Display titles only once per paragraph (from first verse) -->
                                                            @if(!$titlesDisplayed && isset($verse['chapter_titles']) && !empty($verse['chapter_titles']))
                                                                <div class="mb-4 block w-full -indent-8">
                                                                    {!! $verse['chapter_titles'] !!}
                                                                </div>
                                                                @php $titlesDisplayed = true; @endphp
                                                            @endif

                                                            @if($verse['verse_number'] === 1)
                                                                <!-- Proper drop cap implementation -->
                                                                <span class="text-gray-700 dark:text-gray-300 font-serif" style="float: left; font-size: 4rem; line-height: 3.2rem; margin-right: 0.5rem; margin-top: 0rem; font-weight: bold;">{{ $chapterNumber }}</span><span class="inline" id="verse-{{ $verse['verse_number'] }}">{!! strip_tags($verse['text'], '<em><strong><sup><sub><span>') !!}</span>
                                                            @else
                                                                <span class="inline" id="verse-{{ $verse['verse_number'] }}">
                                                                    <!-- Regular verse number for other verses -->
                                                                    <span class="verse-number text-xs font-medium text-gray-500 dark:text-gray-400 align-super font-serif">{{ $verse['verse_number'] }}</span>{!! strip_tags($verse['text'], '<em><strong><sup><sub><span>') !!}
                                                                </span>
                                                            @endif
                                                            @if(!$loop->last) @endif
                                                        @endforeach
                                                    </div>
                                                @endif
                                            @elseif(isset($paragraph['combined_text']))
                                                <!-- Combined paragraph text (fallback) -->
                                                <div class="mb-6 text-gray-900 dark:text-gray-100 leading-relaxed {{ $this->getFontSizeClass() }} text-justify font-serif {{ $loop->first ? '' : 'first-line:indent-8' }} verse-content" style="font-family: 'Charter', 'Source Serif Pro', 'Crimson Text', 'Libre Baskerville', 'PT Serif', 'Georgia', 'Times New Roman', serif; line-height: 1.8; letter-spacing: 0.01em;">
                                                    {!! strip_tags($paragraph['combined_text'], '<em><strong><sup><sub><span>') !!}
                                                </div>
                                            @endif
                                        </div>
                                    @endforeach
                                </div>
                            </div>
                        </div>
                    </div>
                @endif
            </div>
        </div>

        <!-- Next Chapter (Preloaded) -->
        <div class="page page-next absolute inset-0 h-full overflow-y-auto" style="z-index: 0;">
            <div class="book-spine-shadow"></div>
            <div class="page-content h-full overflow-y-auto bg-gray-50 dark:bg-gray-900" id="next-chapter-content">
                @if($chapterNumber < ($chapters->max('chapter_number') ?? 0))
                    <!-- Full Next Chapter Content -->
                    <div class="p-4 sm:p-8 pb-20">
                        <div class="max-w-4xl mx-auto">
                            <div class="bg-white dark:bg-gray-900 dark:border-gray-700 p-4 sm:p-8">
                                <div class="prose prose-lg max-w-none dark:prose-invert">
                                    @if(isset($nextChapterVerses) && !empty($nextChapterVerses))
                                        <!-- Display next chapter verses -->
                                        @foreach($nextChapterVerses as $paragraph)
                                            <div class="{{ isset($paragraph['has_paragraph_marker']) ? 'mb-8 osis-paragraph' : 'mb-3 artificial-paragraph' }} {{ isset($paragraph['type']) && $paragraph['type'] === 'individual_verse' ? 'mb-3' : 'mb-8' }}">
                                                @if(isset($paragraph['verses']) && !empty($paragraph['verses']))
                                                    @if(isset($paragraph['type']) && $paragraph['type'] === 'individual_verse')
                                                        <!-- Individual verse display -->
                                                        @php $titlesDisplayed = false; @endphp
                                                        @foreach($paragraph['verses'] as $verse)
                                                            @if(!$titlesDisplayed && isset($verse['chapter_titles']) && !empty($verse['chapter_titles']))
                                                                <div class="mb-4 block w-full">
                                                                    <div class="acrostic-title text-center text-lg font-semibold text-gray-700 dark:text-gray-300 mb-3 font-serif border-b border-gray-200 dark:border-gray-600 pb-2">{!! strip_tags($verse['chapter_titles'], '<em><strong><sup><sub><foreign>') !!}</div>
                                                                </div>
                                                                @php $titlesDisplayed = true; @endphp
                                                            @endif

                                                            @if($verse['verse_number'] === 1)
                                                                <div class="mb-2 text-gray-900 dark:text-gray-100 leading-relaxed {{ $this->getFontSizeClass() }} font-serif verse-content" style="font-family: 'Charter', 'Source Serif Pro', 'Crimson Text', 'Libre Baskerville', 'PT Serif', 'Georgia', 'Times New Roman', serif; line-height: 1.7; letter-spacing: 0.01em;">
                                                                    <span class="text-gray-700 dark:text-gray-300 font-serif" style="float: left; font-size: 4rem; line-height: 3.2rem; margin-right: 0.5rem; margin-top: 0rem; font-weight: bold;">{{ $chapterNumber + 1 }}</span><span class="inline">{!! strip_tags($verse['text'], '<em><strong><sup><sub><span>') !!}</span>
                                                                </div>
                                                            @else
                                                                <div class="mb-2 text-gray-900 dark:text-gray-100 leading-relaxed {{ $this->getFontSizeClass() }} font-serif verse-content" style="font-family: 'Charter', 'Source Serif Pro', 'Crimson Text', 'Libre Baskerville', 'PT Serif', 'Georgia', 'Times New Roman', serif; line-height: 1.7; letter-spacing: 0.01em;">
                                                                    <span class="verse-number text-xs font-medium text-gray-600 dark:text-gray-400 align-super font-serif">{{ $verse['verse_number'] }}</span>{!! strip_tags($verse['text'], '<em><strong><sup><sub><span>') !!}
                                                                </div>
                                                            @endif
                                                        @endforeach
                                                    @else
                                                        <!-- Bible-style paragraph formatting -->
                                                        <div class="mb-6 text-gray-900 dark:text-gray-100 leading-relaxed {{ $this->getFontSizeClass() }} text-justify font-serif {{ $loop->first ? '' : 'first-line:indent-8' }} after:content-[''] after:table after:clear-both verse-content" style="font-family: 'Charter', 'Source Serif Pro', 'Crimson Text', 'Libre Baskerville', 'PT Serif', 'Georgia', 'Times New Roman', serif; line-height: 1.8; letter-spacing: 0.01em;">
                                                            @php $titlesDisplayed = false; @endphp
                                                            @foreach($paragraph['verses'] as $verse)
                                                                @if(!$titlesDisplayed && isset($verse['chapter_titles']) && !empty($verse['chapter_titles']))
                                                                    <div class="mb-4 block w-full -indent-8">
                                                                        {!! $verse['chapter_titles'] !!}
                                                                    </div>
                                                                    @php $titlesDisplayed = true; @endphp
                                                                @endif

                                                                @if($verse['verse_number'] === 1)
                                                                    <span class="text-gray-700 dark:text-gray-300 font-serif" style="float: left; font-size: 4rem; line-height: 3.2rem; margin-right: 0.5rem; margin-top: 0rem; font-weight: bold;">{{ $chapterNumber + 1 }}</span><span class="inline">{!! strip_tags($verse['text'], '<em><strong><sup><sub><span>') !!}</span>
                                                                @else
                                                                    <span class="inline">
                                                                        <span class="verse-number text-xs font-medium text-gray-500 dark:text-gray-400 align-super font-serif">{{ $verse['verse_number'] }}</span>{!! strip_tags($verse['text'], '<em><strong><sup><sub><span>') !!}
                                                                    </span>
                                                                @endif
                                                            @endforeach
                                                        </div>
                                                    @endif
                                                @elseif(isset($paragraph['combined_text']))
                                                    <div class="mb-6 text-gray-900 dark:text-gray-100 leading-relaxed {{ $this->getFontSizeClass() }} text-justify font-serif {{ $loop->first ? '' : 'first-line:indent-8' }} verse-content" style="font-family: 'Charter', 'Source Serif Pro', 'Crimson Text', 'Libre Baskerville', 'PT Serif', 'Georgia', 'Times New Roman', serif; line-height: 1.8; letter-spacing: 0.01em;">
                                                        {!! strip_tags($paragraph['combined_text'], '<em><strong><sup><sub><span>') !!}
                                                    </div>
                                                @endif
                                            </div>
                                        @endforeach
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
    @if(false)
    @if($chapterNumber > 1)
        <div class="fixed left-0 top-1/2 transform -translate-y-1/2 z-40">
                                        <button onclick="navigateToChapter('/{{ $bookOsisId }}/{{ $chapterNumber - 1 }}')"
               class="bg-blue-600 hover:bg-blue-700 dark:bg-gray-700 dark:hover:bg-gray-600 text-white w-[30px] h-[60px] shadow-lg dark:shadow-gray-900 transition-all duration-200 hover:scale-110 flex items-center justify-center group"
               title="Previous Chapter: {{ $chapterNumber - 1 }}">
                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7"/>
                </svg>
            </button>
        </div>
    @endif
    @endif

    <!-- Floating Next Chapter Button (Right Side) -->
    @if(false)
    @if($chapterNumber < ($chapters->max('chapter_number') ?? 0))
        <div class="fixed right-0 top-1/2 transform -translate-y-1/2 z-40">
                                        <button onclick="navigateToChapter('/{{ $bookOsisId }}/{{ $chapterNumber + 1 }}')"
               class="bg-blue-600 hover:bg-blue-700 dark:bg-gray-700 dark:hover:bg-gray-600 text-white w-[30px] h-[60px] shadow-lg dark:shadow-gray-900 transition-all duration-200 hover:scale-110 flex items-center justify-center group"
               title="Next Chapter: {{ $chapterNumber + 1 }}">
                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"/>
                </svg>
            </button>
        </div>
    @endif
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
                                        <button wire:click="goToChapter('{{ $selectedBookForChapters['osis_id'] }}', {{ $chapter['chapter_number'] }})"
                                                class="aspect-square p-2 flex items-center justify-center bg-white dark:bg-gray-800 border border-gray-200 dark:border-gray-700 rounded-lg text-center hover:bg-blue-50 dark:hover:bg-gray-700 hover:border-blue-200 dark:hover:border-gray-600 transition-colors {{ $chapter['chapter_number'] == $chapterNumber && $selectedBookForChapters['osis_id'] === $bookOsisId ? 'bg-blue-100 dark:bg-blue-900 border-blue-300 dark:border-blue-600' : '' }}">
                                            <span class="font-medium text-gray-900 dark:text-white font-serif text-sm">
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

    {{-- JAVASCRIPT FUNCTIONS --}}
    @include('livewire.partials.scripts')
</div>
