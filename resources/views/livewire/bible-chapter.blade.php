<div class="h-screen bg-gray-50 dark:bg-gray-900 flex flex-col">
    <!-- Dark Mode Initialization - Must run immediately to prevent flash -->
    <script>
        // Initialize dark mode IMMEDIATELY to prevent flash
        (function() {
            if (localStorage.getItem('darkMode') === 'true') {
                document.documentElement.classList.add('dark');
            }
        })();
    </script>

    <!-- Book Flip Effect Styles -->
    <style>
        .book-container {
            perspective: 1000px;
            transform-style: preserve-3d;
        }

        .page {
            transform-style: preserve-3d;
            transition: transform 0.3s ease;
        }

        .page-current.flipping-next {
            animation: flipCurrentToNext 0.8s cubic-bezier(0.25, 0.46, 0.45, 0.94) forwards;
            transform-origin: left center;
            z-index: 3;
        }

        .page-current.flipping-prev {
            animation: flipCurrentToPrev 0.8s cubic-bezier(0.25, 0.46, 0.45, 0.94) forwards;
            transform-origin: right center;
            z-index: 3;
        }

        @keyframes flipCurrentToNext {
            0% {
                transform: rotateY(0deg);
                box-shadow: 0 0 20px rgba(0,0,0,0.1);
            }
            50% {
                transform: rotateY(-90deg);
                box-shadow: 10px 0 30px rgba(0,0,0,0.3);
            }
            100% {
                transform: rotateY(-180deg);
                box-shadow: 0 0 20px rgba(0,0,0,0.1);
            }
        }

        @keyframes flipCurrentToPrev {
            0% {
                transform: rotateY(0deg);
                box-shadow: 0 0 20px rgba(0,0,0,0.1);
            }
            50% {
                transform: rotateY(90deg);
                box-shadow: -10px 0 30px rgba(0,0,0,0.3);
            }
            100% {
                transform: rotateY(180deg);
                box-shadow: 0 0 20px rgba(0,0,0,0.1);
            }
        }

        .page-back {
            position: absolute;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            backface-visibility: hidden;
            transform: rotateY(180deg);
            background: linear-gradient(45deg, #f9fafb 0%, #f3f4f6 100%);
        }

        .book-spine-shadow {
            position: absolute;
            left: 0;
            top: 0;
            bottom: 0;
            width: 3px;
            background: linear-gradient(to right,
                rgba(0,0,0,0.15) 0%,
                rgba(0,0,0,0.05) 50%,
                transparent 100%);
            pointer-events: none;
        }

        .page-content {
            transition: transform 0.3s ease;
        }
    </style>

    <!-- Sticky Header -->
    <nav class="bg-white dark:bg-gray-800 shadow-sm border-b border-gray-200 dark:border-gray-700 sticky top-0 z-50 flex-shrink-0">
        <div class="px-4 sm:px-6 py-4">
            @if($showSearchResults)
                <!-- Search Results Header -->
                <div class="flex items-center justify-between">
                    <div class="flex items-center space-x-4">
                        <button wire:click="backToChapter"
                                class="flex items-center gap-2 text-blue-600 dark:text-blue-400 hover:text-blue-700 dark:hover:text-blue-300 font-medium">
                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7"/>
                            </svg>
                            {{ $returnToChapter['book_name'] ?? 'Chapter' }} {{ $returnToChapter['chapter_number'] ?? '' }}
                        </button>
                        <div class="text-xl font-semibold text-gray-900 dark:text-white font-serif">
                            Search Results
                            @if(!empty($searchStats))
                                <span class="text-sm font-normal text-gray-600 dark:text-gray-400 ml-2">
                                    ({{ $searchStats['total_found'] }} results)
                                </span>
                            @endif
                        </div>
                    </div>

                    <div class="flex items-center space-x-2">
                        <!-- Dark Mode Toggle -->
                        <button onclick="toggleDarkMode()"
                                class="p-2 rounded-lg text-gray-600 dark:text-gray-300 hover:bg-gray-100 dark:hover:bg-gray-700 transition-colors">
                            <svg class="w-5 h-5 dark:hidden" fill="currentColor" viewBox="0 0 20 20">
                                <path d="M17.293 13.293A8 8 0 0 1 6.707 2.707a8.001 8.001 0 1 0 10.586 10.586z"></path>
                            </svg>
                            <svg class="w-5 h-5 hidden dark:block" fill="currentColor" viewBox="0 0 20 20">
                                <path fill-rule="evenodd" d="M10 2a1 1 0 011 1v1a1 1 0 11-2 0V3a1 1 0 011-1zm4 8a4 4 0 11-8 0 4 4 0 018 0zm-.464 4.95l.707.707a1 1 0 001.414-1.414l-.707-.707a1 1 0 00-1.414 1.414zm2.12-10.607a1 1 0 010 1.414l-.706.707a1 1 0 11-1.414-1.414l.707-.707a1 1 0 011.414 0zM17 11a1 1 0 100-2h-1a1 1 0 100 2h1zm-7 4a1 1 0 011 1v1a1 1 0 11-2 0v-1a1 1 0 011-1zM5.05 6.464A1 1 0 106.465 5.05l-.708-.707a1 1 0 00-1.414 1.414l.707.707zm1.414 8.486l-.707.707a1 1 0 01-1.414-1.414l.707-.707a1 1 0 011.414 1.414zM4 11a1 1 0 100-2H3a1 1 0 000 2h1z" clip-rule="evenodd"></path>
                            </svg>
                        </button>
                    </div>
                </div>
            @elseif(!$showSearch)
                <!-- Normal Chapter Header -->
                <div class="flex items-center justify-between">
                    <div class="flex flex-col space-y-1">
                        <button wire:click="openBookSelector" class="text-xl font-semibold text-gray-900 dark:text-white hover:text-blue-600 dark:hover:text-blue-400 transition-colors font-serif text-left" style="font-family: 'Charter', 'Source Serif Pro', 'Crimson Text', 'Libre Baskerville', 'PT Serif', 'Georgia', 'Times New Roman', serif;">
                            {{ $currentBook['name'] ?? $bookOsisId }} {{ $chapterNumber }}
                        </button>
                        <!-- Translation Selector - Click to show dropdown -->
                        <div class="relative" x-data="{ open: false }" @click.away="open = false">
                            <button @click="open = !open" class="text-sm text-gray-600 dark:text-gray-400 font-medium font-serif hover:text-blue-600 dark:hover:text-blue-400 transition-colors text-left flex items-center whitespace-nowrap" style="font-family: 'Charter', 'Source Serif Pro', 'Crimson Text', 'Libre Baskerville', 'PT Serif', 'Georgia', 'Times New Roman', serif;">
                                {{ $currentTranslation['name'] ?? $currentTranslation['abbreviation'] ?? 'Unknown Version' }}
                                <svg class="w-1.5 h-1.5 ml-1 transition-transform duration-200"
                                     :class="{ 'rotate-180': open }"
                                     fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M19 9l-7 7-7-7"/>
                                </svg>
                            </button>

                            <!-- Dropdown Menu -->
                            <div x-show="open"
                                 x-transition:enter="transition ease-out duration-150"
                                 x-transition:enter-start="opacity-0 scale-95"
                                 x-transition:enter-end="opacity-100 scale-100"
                                 x-transition:leave="transition ease-in duration-100"
                                 x-transition:leave-start="opacity-100 scale-100"
                                 x-transition:leave-end="opacity-0 scale-95"
                                 class="absolute z-50 mt-1 w-64 bg-white dark:bg-gray-800 rounded-lg shadow-lg border border-gray-200 dark:border-gray-600 py-1 left-0"
                                 style="display: none;">

                                @if(!empty($availableTranslations))
                                    @foreach($availableTranslations as $translation)
                                        <button wire:click="switchTranslation('{{ $translation['key'] }}')"
                                                @click="open = false"
                                                class="w-full text-left px-3 py-2 text-sm hover:bg-gray-100 dark:hover:bg-gray-700 transition-colors font-serif {{ $currentTranslation && $currentTranslation['key'] === $translation['key'] ? 'bg-blue-600 dark:bg-blue-600 text-white dark:text-white' : 'text-gray-700 dark:text-gray-200' }}" style="font-family: 'Charter', 'Source Serif Pro', 'Crimson Text', 'Libre Baskerville', 'PT Serif', 'Georgia', 'Times New Roman', serif;">
                                            <div class="flex items-center justify-between">
                                                <span class="font-medium whitespace-nowrap">{{ $translation['name'] ?? $translation['short_name'] }}</span>
                                                @if($currentTranslation && $currentTranslation['key'] === $translation['key'])
                                                    <svg class="w-4 h-4 text-white" fill="currentColor" viewBox="0 0 20 20">
                                                        <path fill-rule="evenodd" d="M16.707 5.293a1 1 0 010 1.414l-8 8a1 1 0 01-1.414 0l-4-4a1 1 0 011.414-1.414L8 12.586l7.293-7.293a1 1 0 011.414 0z" clip-rule="evenodd"/>
                                                    </svg>
                                                @endif
                                            </div>
                                        </button>
                                    @endforeach
                                @endif
                            </div>
                        </div>
                    </div>

                    <div class="flex items-center space-x-2">
                        <!-- Search Toggle -->
                        <button wire:click="toggleSearch"
                                class="p-2 rounded-lg text-gray-600 dark:text-gray-300 hover:bg-gray-100 dark:hover:bg-gray-700 transition-colors">
                            <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"/>
                            </svg>
                        </button>

                        <!-- Dark Mode Toggle -->
                        <button onclick="toggleDarkMode()"
                                class="p-2 rounded-lg text-gray-600 dark:text-gray-300 hover:bg-gray-100 dark:hover:bg-gray-700 transition-colors">
                            <svg class="w-5 h-5 dark:hidden" fill="currentColor" viewBox="0 0 20 20">
                                <path d="M17.293 13.293A8 8 0 0 1 6.707 2.707a8.001 8.001 0 1 0 10.586 10.586z"></path>
                            </svg>
                            <svg class="w-5 h-5 hidden dark:block" fill="currentColor" viewBox="0 0 20 20">
                                <path fill-rule="evenodd" d="M10 2a1 1 0 011 1v1a1 1 0 11-2 0V3a1 1 0 011-1zm4 8a4 4 0 11-8 0 4 4 0 018 0zm-.464 4.95l.707.707a1 1 0 001.414-1.414l-.707-.707a1 1 0 00-1.414 1.414zm2.12-10.607a1 1 0 010 1.414l-.706.707a1 1 0 11-1.414-1.414l.707-.707a1 1 0 011.414 0zM17 11a1 1 0 100-2h-1a1 1 0 100 2h1zm-7 4a1 1 0 011 1v1a1 1 0 11-2 0v-1a1 1 0 011-1zM5.05 6.464A1 1 0 106.465 5.05l-.708-.707a1 1 0 00-1.414 1.414l.707.707zm1.414 8.486l-.707.707a1 1 0 01-1.414-1.414l.707-.707a1 1 0 011.414 1.414zM4 11a1 1 0 100-2H3a1 1 0 000 2h1z" clip-rule="evenodd"></path>
                            </svg>
                        </button>
                    </div>
                </div>
            @else
                <!-- Search Input Header -->
                <div class="flex items-center justify-between h-[38px]">
                    <!-- Search Input -->
                    <div class="relative flex-1 mr-2">
                        <input
                            wire:model="searchQuery"
                            wire:keydown.enter="search"
                            type="text"
                            placeholder="Search verses, words, or references..."
                            class="w-full h-9 border border-gray-300 dark:border-gray-600 bg-white dark:bg-gray-700 text-gray-900 dark:text-gray-100 rounded-lg pl-10 pr-4 py-2 focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-transparent placeholder-gray-500 dark:placeholder-gray-400 transition-all duration-300"
                            x-data="{ mounted: false }"
                            x-init="
                                if (!mounted) {
                                    mounted = true;
                                    // Simple expand animation
                                    $el.style.transform = 'scaleX(0.3)';
                                    $el.style.transformOrigin = 'right center';
                                    $el.style.opacity = '0.5';

                                    setTimeout(() => {
                                        $el.style.transition = 'all 0.3s cubic-bezier(0.25, 0.46, 0.45, 0.94)';
                                        $el.style.transform = 'scaleX(1)';
                                        $el.style.opacity = '1';
                                        setTimeout(() => $el.focus(), 100);
                                    }, 50);
                                }
                            "
                        >
                        <div class="absolute left-3 top-1/2 transform -translate-y-1/2 pointer-events-none">
                            <svg class="h-4 w-4 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"/>
                            </svg>
                        </div>
                    </div>

                    <!-- Cancel Button -->
                    <button
                        wire:click="toggleSearch"
                        class="text-blue-600 dark:text-blue-400 hover:text-blue-700 dark:hover:text-blue-300 font-medium py-2 px-3 transition-colors whitespace-nowrap"
                    >
                        Cancel
                    </button>
                </div>
            @endif
        </div>
    </nav>

    <!-- Main Content - Scrollable -->
    <div class="flex-1 overflow-hidden book-container">
        <!-- Previous Chapter (Preloaded) -->
        <div class="page page-prev absolute inset-0 h-full overflow-y-auto" style="transform: rotateY(180deg); z-index: 1;">
            <div class="book-spine-shadow"></div>
            <div class="page-content h-full overflow-y-auto bg-gray-50 dark:bg-gray-900" id="prev-chapter-content" style="transform: rotateY(180deg);">
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
                                                                <div class="mb-2 text-gray-900 dark:text-gray-100 leading-relaxed {{ $this->getFontSizeClass() }} font-serif" style="font-family: 'Charter', 'Source Serif Pro', 'Crimson Text', 'Libre Baskerville', 'PT Serif', 'Georgia', 'Times New Roman', serif; line-height: 1.7; letter-spacing: 0.01em;">
                                                                    <span class="text-gray-700 dark:text-gray-300 font-serif" style="float: left; font-size: 4rem; line-height: 3.2rem; margin-right: 0.5rem; margin-top: 0rem; font-weight: bold;">{{ $chapterNumber - 1 }}</span><span class="inline">{!! strip_tags($verse['text'], '<em><strong><sup><sub>') !!}</span>
                                                                </div>
                                                            @else
                                                                <div class="mb-2 text-gray-900 dark:text-gray-100 leading-relaxed {{ $this->getFontSizeClass() }} font-serif" style="font-family: 'Charter', 'Source Serif Pro', 'Crimson Text', 'Libre Baskerville', 'PT Serif', 'Georgia', 'Times New Roman', serif; line-height: 1.7; letter-spacing: 0.01em;">
                                                                    <span class="text-xs font-medium text-gray-600 dark:text-gray-400 mr-2 align-super font-serif">{{ $verse['verse_number'] }}</span>
                                                                    {!! strip_tags($verse['text'], '<em><strong><sup><sub>') !!}
                                                                </div>
                                                            @endif
                                                        @endforeach
                                                    @else
                                                        <!-- Bible-style paragraph formatting -->
                                                        <div class="mb-6 text-gray-900 dark:text-gray-100 leading-relaxed {{ $this->getFontSizeClass() }} text-justify font-serif {{ $loop->first ? '' : 'first-line:indent-8' }} after:content-[''] after:table after:clear-both" style="font-family: 'Charter', 'Source Serif Pro', 'Crimson Text', 'Libre Baskerville', 'PT Serif', 'Georgia', 'Times New Roman', serif; line-height: 1.8; letter-spacing: 0.01em;">
                                                            @php $titlesDisplayed = false; @endphp
                                                            @foreach($paragraph['verses'] as $verse)
                                                                @if(!$titlesDisplayed && isset($verse['chapter_titles']) && !empty($verse['chapter_titles']))
                                                                    <div class="mb-4 block w-full -indent-8">
                                                                        {!! $verse['chapter_titles'] !!}
                                                                    </div>
                                                                    @php $titlesDisplayed = true; @endphp
                                                                @endif

                                                                @if($verse['verse_number'] === 1)
                                                                    <span class="text-gray-700 dark:text-gray-300 font-serif" style="float: left; font-size: 4rem; line-height: 3.2rem; margin-right: 0.5rem; margin-top: 0rem; font-weight: bold;">{{ $chapterNumber - 1 }}</span><span class="inline">{!! strip_tags($verse['text'], '<em><strong><sup><sub>') !!}</span>
                                                                @else
                                                                    <span class="inline">
                                                                        <span class="text-xs font-medium text-gray-500 dark:text-gray-400 mr-2 align-super font-serif">{{ $verse['verse_number'] }}</span>
                                                                        {!! strip_tags($verse['text'], '<em><strong><sup><sub>') !!}
                                                                    </span>
                                                                @endif
                                                            @endforeach
                                                        </div>
                                                    @endif
                                                @elseif(isset($paragraph['combined_text']))
                                                    <div class="mb-6 text-gray-900 dark:text-gray-100 leading-relaxed {{ $this->getFontSizeClass() }} text-justify font-serif {{ $loop->first ? '' : 'first-line:indent-8' }}" style="font-family: 'Charter', 'Source Serif Pro', 'Crimson Text', 'Libre Baskerville', 'PT Serif', 'Georgia', 'Times New Roman', serif; line-height: 1.8; letter-spacing: 0.01em;">
                                                        {!! strip_tags($paragraph['combined_text'], '<em><strong><sup><sub>') !!}
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
                                                            $text = strip_tags($result['text'], '<em><strong><sup><sub>');
                                                            if (!empty($searchQuery)) {
                                                                $text = str_ireplace($searchQuery, '<mark class="bg-yellow-300 text-black px-1">' . $searchQuery . '</mark>', $text);
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
                                                            <div class="mb-2 text-gray-900 dark:text-gray-100 leading-relaxed {{ $this->getFontSizeClass() }} font-serif" style="font-family: 'Charter', 'Source Serif Pro', 'Crimson Text', 'Libre Baskerville', 'PT Serif', 'Georgia', 'Times New Roman', serif; line-height: 1.7; letter-spacing: 0.01em;">
                                                                <span class="text-gray-700 dark:text-gray-300 font-serif" style="float: left; font-size: 4rem; line-height: 3.2rem; margin-right: 0.5rem; margin-top: 0rem; font-weight: bold;">{{ $chapterNumber }}</span><span class="inline" id="verse-{{ $verse['verse_number'] }}">{!! strip_tags($verse['text'], '<em><strong><sup><sub>') !!}</span>
                                                            </div>
                                                        @else
                                                            <div class="mb-2 text-gray-900 dark:text-gray-100 leading-relaxed {{ $this->getFontSizeClass() }} font-serif" style="font-family: 'Charter', 'Source Serif Pro', 'Crimson Text', 'Libre Baskerville', 'PT Serif', 'Georgia', 'Times New Roman', serif; line-height: 1.7; letter-spacing: 0.01em;" id="verse-{{ $verse['verse_number'] }}">
                                                                <!-- Regular verse number for other verses -->
                                                                <span class="text-xs font-medium text-gray-600 dark:text-gray-400 mr-2 align-super font-serif">{{ $verse['verse_number'] }}</span>
                                                                {!! strip_tags($verse['text'], '<em><strong><sup><sub>') !!}
                                                            </div>
                                                        @endif
                                                    @endforeach
                                                @else
                                                    <!-- Bible-style paragraph formatting -->
                                                    <div class="mb-6 text-gray-900 dark:text-gray-100 leading-relaxed {{ $this->getFontSizeClass() }} text-justify font-serif {{ $loop->first ? '' : 'first-line:indent-8' }} after:content-[''] after:table after:clear-both" style="font-family: 'Charter', 'Source Serif Pro', 'Crimson Text', 'Libre Baskerville', 'PT Serif', 'Georgia', 'Times New Roman', serif; line-height: 1.8; letter-spacing: 0.01em;">
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
                                                                <span class="text-gray-700 dark:text-gray-300 font-serif" style="float: left; font-size: 4rem; line-height: 3.2rem; margin-right: 0.5rem; margin-top: 0rem; font-weight: bold;">{{ $chapterNumber }}</span><span class="inline" id="verse-{{ $verse['verse_number'] }}">{!! strip_tags($verse['text'], '<em><strong><sup><sub>') !!}</span>
                                                            @else
                                                                <span class="inline" id="verse-{{ $verse['verse_number'] }}">
                                                                    <!-- Regular verse number for other verses -->
                                                                    <span class="text-xs font-medium text-gray-500 dark:text-gray-400 mr-2 align-super font-serif">{{ $verse['verse_number'] }}</span>
                                                                    {!! strip_tags($verse['text'], '<em><strong><sup><sub>') !!}
                                                                </span>
                                                            @endif
                                                            @if(!$loop->last) @endif
                                                        @endforeach
                                                    </div>
                                                @endif
                                            @elseif(isset($paragraph['combined_text']))
                                                <!-- Combined paragraph text (fallback) -->
                                                <div class="mb-6 text-gray-900 dark:text-gray-100 leading-relaxed {{ $this->getFontSizeClass() }} text-justify font-serif {{ $loop->first ? '' : 'first-line:indent-8' }}" style="font-family: 'Charter', 'Source Serif Pro', 'Crimson Text', 'Libre Baskerville', 'PT Serif', 'Georgia', 'Times New Roman', serif; line-height: 1.8; letter-spacing: 0.01em;">
                                                    {!! strip_tags($paragraph['combined_text'], '<em><strong><sup><sub>') !!}
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
        <div class="page page-next absolute inset-0 h-full overflow-y-auto" style="transform: rotateY(-180deg); z-index: 1;">
            <div class="book-spine-shadow"></div>
            <div class="page-content h-full overflow-y-auto bg-gray-50 dark:bg-gray-900" id="next-chapter-content" style="transform: rotateY(180deg);">
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
                                                                <div class="mb-2 text-gray-900 dark:text-gray-100 leading-relaxed {{ $this->getFontSizeClass() }} font-serif" style="font-family: 'Charter', 'Source Serif Pro', 'Crimson Text', 'Libre Baskerville', 'PT Serif', 'Georgia', 'Times New Roman', serif; line-height: 1.7; letter-spacing: 0.01em;">
                                                                    <span class="text-gray-700 dark:text-gray-300 font-serif" style="float: left; font-size: 4rem; line-height: 3.2rem; margin-right: 0.5rem; margin-top: 0rem; font-weight: bold;">{{ $chapterNumber + 1 }}</span><span class="inline">{!! strip_tags($verse['text'], '<em><strong><sup><sub>') !!}</span>
                                                                </div>
                                                            @else
                                                                <div class="mb-2 text-gray-900 dark:text-gray-100 leading-relaxed {{ $this->getFontSizeClass() }} font-serif" style="font-family: 'Charter', 'Source Serif Pro', 'Crimson Text', 'Libre Baskerville', 'PT Serif', 'Georgia', 'Times New Roman', serif; line-height: 1.7; letter-spacing: 0.01em;">
                                                                    <span class="text-xs font-medium text-gray-600 dark:text-gray-400 mr-2 align-super font-serif">{{ $verse['verse_number'] }}</span>
                                                                    {!! strip_tags($verse['text'], '<em><strong><sup><sub>') !!}
                                                                </div>
                                                            @endif
                                                        @endforeach
                                                    @else
                                                        <!-- Bible-style paragraph formatting -->
                                                        <div class="mb-6 text-gray-900 dark:text-gray-100 leading-relaxed {{ $this->getFontSizeClass() }} text-justify font-serif {{ $loop->first ? '' : 'first-line:indent-8' }} after:content-[''] after:table after:clear-both" style="font-family: 'Charter', 'Source Serif Pro', 'Crimson Text', 'Libre Baskerville', 'PT Serif', 'Georgia', 'Times New Roman', serif; line-height: 1.8; letter-spacing: 0.01em;">
                                                            @php $titlesDisplayed = false; @endphp
                                                            @foreach($paragraph['verses'] as $verse)
                                                                @if(!$titlesDisplayed && isset($verse['chapter_titles']) && !empty($verse['chapter_titles']))
                                                                    <div class="mb-4 block w-full -indent-8">
                                                                        {!! $verse['chapter_titles'] !!}
                                                                    </div>
                                                                    @php $titlesDisplayed = true; @endphp
                                                                @endif

                                                                @if($verse['verse_number'] === 1)
                                                                    <span class="text-gray-700 dark:text-gray-300 font-serif" style="float: left; font-size: 4rem; line-height: 3.2rem; margin-right: 0.5rem; margin-top: 0rem; font-weight: bold;">{{ $chapterNumber + 1 }}</span><span class="inline">{!! strip_tags($verse['text'], '<em><strong><sup><sub>') !!}</span>
                                                                @else
                                                                    <span class="inline">
                                                                        <span class="text-xs font-medium text-gray-500 dark:text-gray-400 mr-2 align-super font-serif">{{ $verse['verse_number'] }}</span>
                                                                        {!! strip_tags($verse['text'], '<em><strong><sup><sub>') !!}
                                                                    </span>
                                                                @endif
                                                            @endforeach
                                                        </div>
                                                    @endif
                                                @elseif(isset($paragraph['combined_text']))
                                                    <div class="mb-6 text-gray-900 dark:text-gray-100 leading-relaxed {{ $this->getFontSizeClass() }} text-justify font-serif {{ $loop->first ? '' : 'first-line:indent-8' }}" style="font-family: 'Charter', 'Source Serif Pro', 'Crimson Text', 'Libre Baskerville', 'PT Serif', 'Georgia', 'Times New Roman', serif; line-height: 1.8; letter-spacing: 0.01em;">
                                                        {!! strip_tags($paragraph['combined_text'], '<em><strong><sup><sub>') !!}
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

    <!-- Floating Previous Chapter Button (Left Side) -->
    @if($chapterNumber > 1)
        <div class="fixed left-0 top-1/2 transform -translate-y-1/2 z-40">
            <button onclick="flipPage('prev', '/{{ $bookOsisId }}/{{ $chapterNumber - 1 }}')"
               class="bg-blue-600 hover:bg-blue-700 dark:bg-gray-700 dark:hover:bg-gray-600 text-white w-[30px] h-[60px] shadow-lg dark:shadow-gray-900 transition-all duration-200 hover:scale-110 flex items-center justify-center group"
               title="Previous Chapter: {{ $chapterNumber - 1 }}">
                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7"/>
                </svg>
            </button>
        </div>
    @endif

    <!-- Floating Next Chapter Button (Right Side) -->
    @if($chapterNumber < ($chapters->max('chapter_number') ?? 0))
        <div class="fixed right-0 top-1/2 transform -translate-y-1/2 z-40">
            <button onclick="flipPage('next', '/{{ $bookOsisId }}/{{ $chapterNumber + 1 }}')"
               class="bg-blue-600 hover:bg-blue-700 dark:bg-gray-700 dark:hover:bg-gray-600 text-white w-[30px] h-[60px] shadow-lg dark:shadow-gray-900 transition-all duration-200 hover:scale-110 flex items-center justify-center group"
               title="Next Chapter: {{ $chapterNumber + 1 }}">
                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"/>
                </svg>
            </button>
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

    <!-- Dark Mode Script -->
    <script>
        function toggleDarkMode() {
            document.documentElement.classList.toggle('dark');
            localStorage.setItem('darkMode', document.documentElement.classList.contains('dark'));
        }

        // Book flip animation function
        function flipPage(direction, url) {
            const currentPage = document.querySelector('.page-current');
            const prevPage = document.querySelector('.page-prev');
            const nextPage = document.querySelector('.page-next');

            if (!currentPage) return;

            // Prevent multiple clicks during animation
            if (currentPage.classList.contains('flipping-next') || currentPage.classList.contains('flipping-prev')) {
                return;
            }

            // Show only the appropriate preview page and completely hide the other
            if (direction === 'prev' && prevPage) {
                // Show previous page preview, completely hide next page
                prevPage.style.display = 'block';
                prevPage.style.zIndex = '10';
                prevPage.style.transform = 'rotateY(0deg)';
                prevPage.querySelector('.page-content').style.transform = 'rotateY(0deg)';

                if (nextPage) {
                    nextPage.style.display = 'none';
                }
            } else if (direction === 'next' && nextPage) {
                // Show next page preview, completely hide previous page
                nextPage.style.display = 'block';
                nextPage.style.zIndex = '10';
                nextPage.style.transform = 'rotateY(0deg)';
                nextPage.querySelector('.page-content').style.transform = 'rotateY(0deg)';

                if (prevPage) {
                    prevPage.style.display = 'none';
                }
            }

            // Start flip animation on current page
            currentPage.classList.add(`flipping-${direction}`);

            // Navigate after 400ms (when pages meet at 90 degrees)
            setTimeout(() => {
                window.location.href = url;
            }, 400);
        }

        // Add hover effects for page previews
        document.addEventListener('DOMContentLoaded', function() {
            const currentPage = document.querySelector('.page-current');
            const prevPage = document.querySelector('.page-prev');
            const nextPage = document.querySelector('.page-next');
            const prevButton = document.querySelector('button[onclick*="prev"]');
            const nextButton = document.querySelector('button[onclick*="next"]');

            if (currentPage) {
                setTimeout(() => {
                    currentPage.style.transition = 'opacity 0.4s ease';
                    currentPage.style.opacity = '1';
                }, 50);
            }

            // Previous button hover effects
            if (prevButton && prevPage) {
                prevButton.addEventListener('mouseenter', () => {
                    // Show previous page preview
                    prevPage.style.display = 'block';
                    prevPage.style.zIndex = '5';
                    prevPage.style.transform = 'rotateY(20deg)';
                    prevPage.querySelector('.page-content').style.transform = 'rotateY(-20deg)';
                    currentPage.style.transform = 'rotateY(-5deg)';

                    // Completely hide next page
                    if (nextPage) {
                        nextPage.style.display = 'none';
                    }
                });

                prevButton.addEventListener('mouseleave', () => {
                    // Reset previous page
                    prevPage.style.display = 'block';
                    prevPage.style.zIndex = '1';
                    prevPage.style.transform = 'rotateY(180deg)';
                    prevPage.querySelector('.page-content').style.transform = 'rotateY(180deg)';
                    currentPage.style.transform = 'rotateY(0deg)';

                    // Restore next page
                    if (nextPage) {
                        nextPage.style.display = 'block';
                    }
                });
            }

            // Next button hover effects
            if (nextButton && nextPage) {
                nextButton.addEventListener('mouseenter', () => {
                    // Show next page preview
                    nextPage.style.display = 'block';
                    nextPage.style.zIndex = '5';
                    nextPage.style.transform = 'rotateY(-20deg)';
                    nextPage.querySelector('.page-content').style.transform = 'rotateY(20deg)';
                    currentPage.style.transform = 'rotateY(5deg)';

                    // Completely hide previous page
                    if (prevPage) {
                        prevPage.style.display = 'none';
                    }
                });

                nextButton.addEventListener('mouseleave', () => {
                    // Reset next page
                    nextPage.style.display = 'block';
                    nextPage.style.zIndex = '1';
                    nextPage.style.transform = 'rotateY(-180deg)';
                    nextPage.querySelector('.page-content').style.transform = 'rotateY(180deg)';
                    currentPage.style.transform = 'rotateY(0deg)';

                    // Restore previous page
                    if (prevPage) {
                        prevPage.style.display = 'block';
                    }
                });
            }
        });

        // Handle browser back/forward buttons
        window.addEventListener('popstate', function(event) {
            const currentPage = document.querySelector('.page-current');
            if (currentPage) {
                currentPage.classList.add('flipping-prev');
                setTimeout(() => {
                    currentPage.classList.remove('flipping-prev');
                }, 800);
            }
        });
    </script>
</div>
