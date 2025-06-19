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

                    <!-- Strong's Numbers Toggle -->
                    <button wire:click="toggleStrongsNumbers"
                            class="p-2 rounded-lg transition-colors {{ $showStrongsNumbers ? 'text-blue-600 dark:text-blue-400 bg-blue-50 dark:bg-blue-900/20' : 'text-gray-600 dark:text-gray-300 hover:bg-gray-100 dark:hover:bg-gray-700' }}"
                            title="Toggle Strong's Numbers">
                        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M7 20l4-16m2 16l4-16M6 9h14M4 15h14"/>
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
