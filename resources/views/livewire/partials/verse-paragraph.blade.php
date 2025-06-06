{{--
    Partial for displaying verse paragraphs
    Parameters:
    - $paragraph: The paragraph data
    - $chapterNumber: Current chapter number (for drop cap)
    - $loop: Loop data from parent foreach
    - $getFontSizeClass: Method to get font size class
--}}

<div class="{{ isset($paragraph['has_paragraph_marker']) ? 'mb-8 osis-paragraph' : 'mb-3 artificial-paragraph' }} {{ isset($paragraph['type']) && $paragraph['type'] === 'individual_verse' ? 'mb-3' : 'mb-8' }}">
    @if(isset($paragraph['verses']) && !empty($paragraph['verses']))
        @if(isset($paragraph['type']) && $paragraph['type'] === 'individual_verse')
            <!-- Individual verse display (for Psalm 119 with acrostic titles) -->
            @php $titlesDisplayed = false; @endphp
            @foreach($paragraph['verses'] as $verse)
                <!-- Display titles only once per paragraph -->
                @if(!$titlesDisplayed && isset($verse['chapter_titles']) && !empty($verse['chapter_titles']))
                    <div class="mb-4 block w-full">
                        <div class="acrostic-title text-center text-lg font-semibold text-gray-700 dark:text-gray-300 mb-3 font-serif border-b border-gray-200 dark:border-gray-600 pb-2">{!! strip_tags($verse['chapter_titles'], '<em><strong><sup><sub><foreign>') !!}</div>
                    </div>
                    @php $titlesDisplayed = true; @endphp
                @endif

                @if($verse['verse_number'] === 1)
                    <!-- Display verse title if it exists for verse 1 -->
                    @if(isset($verse['verse_titles']) && !empty($verse['verse_titles']))
                        <div class="mb-4 block w-full clear-both">
                            <div class="verse-title text-center text-md font-semibold text-blue-700 dark:text-blue-400 mb-3 font-serif italic">{!! strip_tags($verse['verse_titles'], '<div><em><strong><sup><sub><foreign>') !!}</div>
                        </div>
                    @endif

                    <!-- Proper drop cap implementation -->
                    <div class="mb-2 text-gray-900 dark:text-gray-100 leading-relaxed {{ $getFontSizeClass() }} font-serif verse-content" style="font-family: 'Charter', 'Source Serif Pro', 'Crimson Text', 'Libre Baskerville', 'PT Serif', 'Georgia', 'Times New Roman', serif; line-height: 1.7; letter-spacing: 0.01em;">
                        <span class="text-gray-700 dark:text-gray-300 font-serif" style="float: left; font-size: 4rem; line-height: 3.2rem; margin-right: 0.5rem; margin-top: 0rem; font-weight: bold;">{{ $chapterNumber }}</span><span class="inline" id="verse-{{ $verse['verse_number'] }}">{!! strip_tags($verse['text'], '<em><strong><sup><sub><span>') !!}</span>
                    </div>
                @else
                    <!-- Display verse title if it exists for other verses -->
                    @if(isset($verse['verse_titles']) && !empty($verse['verse_titles']))
                        <div class="mt-6 mb-4 block w-full">
                            <div class="verse-title text-center text-md font-semibold text-blue-700 dark:text-blue-400 mb-3 font-serif italic">{!! strip_tags($verse['verse_titles'], '<div><em><strong><sup><sub><foreign>') !!}</div>
                        </div>
                    @endif

                    <div class="mb-2 text-gray-900 dark:text-gray-100 leading-relaxed {{ $getFontSizeClass() }} font-serif verse-content" style="font-family: 'Charter', 'Source Serif Pro', 'Crimson Text', 'Libre Baskerville', 'PT Serif', 'Georgia', 'Times New Roman', serif; line-height: 1.7; letter-spacing: 0.01em;" id="verse-{{ $verse['verse_number'] }}">
                        <!-- Regular verse number for other verses -->
                        <span class="verse-number text-xs font-medium text-gray-600 dark:text-gray-400 align-super font-serif">{{ $verse['verse_number'] }}</span>{!! strip_tags($verse['text'], '<em><strong><sup><sub><span>') !!}
                    </div>
                @endif
            @endforeach
        @else
            <!-- Bible-style paragraph formatting -->
            <div class="mb-6 text-gray-900 dark:text-gray-100 leading-relaxed {{ $getFontSizeClass() }} text-justify font-serif {{ $loop->first ? '' : 'first-line:indent-8' }} after:content-[''] after:table after:clear-both verse-content" style="font-family: 'Charter', 'Source Serif Pro', 'Crimson Text', 'Libre Baskerville', 'PT Serif', 'Georgia', 'Times New Roman', serif; line-height: 1.8; letter-spacing: 0.01em;">
                @php $titlesDisplayed = false; @endphp
                @foreach($paragraph['verses'] as $verse)
                    <!-- Display titles only once per paragraph (from first verse) -->
                    @if(!$titlesDisplayed && isset($verse['chapter_titles']) && !empty($verse['chapter_titles']))
                        <div class="mb-4 block w-full -indent-8">
                            {!! $verse['chapter_titles'] !!}
                        </div>
                        @php $titlesDisplayed = true; @endphp
                    @endif

                    @if($verse['verse_number'] === 1)
                        <!-- Display verse title if it exists for verse 1 -->
                        @if(isset($verse['verse_titles']) && !empty($verse['verse_titles']))
                            <div class="mb-4 block w-full -indent-8 clear-both">
                                <div class="verse-title text-center text-md font-semibold text-blue-700 dark:text-blue-400 mb-3 font-serif italic">{!! strip_tags($verse['verse_titles'], '<div><em><strong><sup><sub><foreign>') !!}</div>
                            </div>
                        @endif

                        <!-- Proper drop cap implementation -->
                        <span class="text-gray-700 dark:text-gray-300 font-serif" style="float: left; font-size: 4rem; line-height: 3.2rem; margin-right: 0.5rem; margin-top: 0rem; font-weight: bold;">{{ $chapterNumber }}</span><span class="inline" id="verse-{{ $verse['verse_number'] }}">{!! strip_tags($verse['text'], '<em><strong><sup><sub><span>') !!}</span>
                    @else
                        <!-- Display verse title if it exists for other verses -->
                        @if(isset($verse['verse_titles']) && !empty($verse['verse_titles']))
                            <div class="mt-6 mb-4 block w-full -indent-8">
                                <div class="verse-title text-center text-md font-semibold text-blue-700 dark:text-blue-400 mb-3 font-serif italic">{!! strip_tags($verse['verse_titles'], '<div><em><strong><sup><sub><foreign>') !!}</div>
                            </div>
                        @endif

                        <span class="inline" id="verse-{{ $verse['verse_number'] }}">
                            <!-- Regular verse number for other verses -->
                            <span class="verse-number text-xs font-medium text-gray-500 dark:text-gray-400 align-super font-serif">{{ $verse['verse_number'] }}</span>{!! strip_tags($verse['text'], '<em><strong><sup><sub><span>') !!}
                        </span>
                    @endif
                @endforeach
            </div>
        @endif
    @elseif(isset($paragraph['combined_text']))
        <!-- Combined paragraph text (fallback) -->
        <div class="mb-6 text-gray-900 dark:text-gray-100 leading-relaxed {{ $getFontSizeClass() }} text-justify font-serif {{ $loop->first ? '' : 'first-line:indent-8' }} verse-content" style="font-family: 'Charter', 'Source Serif Pro', 'Crimson Text', 'Libre Baskerville', 'PT Serif', 'Georgia', 'Times New Roman', serif; line-height: 1.8; letter-spacing: 0.01em;">
            {!! strip_tags($paragraph['combined_text'], '<em><strong><sup><sub><span>') !!}
        </div>
    @endif
</div>
