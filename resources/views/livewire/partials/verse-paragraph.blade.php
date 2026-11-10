{{--
    Partial for displaying verse paragraphs
    Parameters:
    - $paragraph: The paragraph data
    - $chapterNumber: Current chapter number (for drop cap)
    - $loop: Loop data from parent foreach
    - $emitIds: (bool, default true) emit id="verse-N" anchors. False for the preloaded previous/next
      chapters, which share the page with the current one - duplicate ids broke #verse-N links.

    Title allow-lists include <span>: canonical psalm titles carry the small-caps LORD as a <span>
    (e.g. Ps 18:title "the servant of the LORD"); without it the title read "Lord". Title HTML is
    produced by our importer and admin-typed title text is entity-escaped, so <span> is safe here.
--}}
@php $emitIds = $emitIds ?? true; @endphp

<div wire:key="{{ $wireKey ?? '' }}" class="{{ isset($paragraph['has_paragraph_marker']) ? 'mb-8 osis-paragraph' : 'mb-3 artificial-paragraph' }} {{ isset($paragraph['type']) && $paragraph['type'] === 'individual_verse' ? 'mb-3' : 'mb-8' }}">
    @if(isset($paragraph['verses']) && !empty($paragraph['verses']))
        @if(isset($paragraph['type']) && $paragraph['type'] === 'individual_verse')
            <!-- Individual verse display (for Psalm 119 with acrostic titles) -->
            @php $titlesDisplayed = false; @endphp
            @foreach($paragraph['verses'] as $verse)
                <!-- Display titles only once per paragraph -->
                @if(!$titlesDisplayed && isset($verse['chapter_titles']) && !empty($verse['chapter_titles']))
                    <div class="block w-full">
                        <div class="acrostic-title text-lg font-semibold text-gray-700 dark:text-gray-300 mb-3 font-serif border-b border-gray-200 dark:border-gray-600 pb-2">{!! strip_tags($verse['chapter_titles'], '<span><em><strong><sup><sub><foreign>') !!}</div>
                    </div>
                    @php $titlesDisplayed = true; @endphp
                @endif

                @if($verse['verse_number'] === 1)
                    <!-- Display verse title if it exists for verse 1 -->
                    @if(isset($verse['verse_titles']) && !empty($verse['verse_titles']))
                        <div class="mb-4 block w-full clear-both">
                            <div class="verse-title text-xs font-semibold text-blue-700 dark:text-blue-400 font-serif">{!! strip_tags($verse['verse_titles'], '<div><h2><p><span><em><strong><sup><sub><foreign>') !!}</div>
                        </div>
                    @endif

                    <!-- Proper drop cap implementation -->
                    <p class="mb-2 text-gray-900 dark:text-gray-100 leading-relaxed reader-text font-serif verse-content" wire:key="v-{{ $chapterNumber }}-{{ $verse['verse_number'] }}" style="font-family: 'Charter', 'Source Serif Pro', 'Crimson Text', 'Libre Baskerville', 'PT Serif', 'Georgia', 'Times New Roman', serif; line-height: 1.7; letter-spacing: 0.01em;">
                        <span class="text-gray-700 dark:text-gray-300 font-serif" style="float: left; font-size: 4rem; line-height: 3.2rem; margin-right: 0.5rem; margin-top: 0rem; font-weight: bold;">{{ $chapterNumber }}</span><span class="inline" wire:key="v-{{ $chapterNumber }}-{{ $verse['verse_number'] }}" @if($emitIds) id="verse-{{ $verse['verse_number'] }}" @endif>{!! $this->parseEnhancedVerseText($verse) !!}</span>
                    </p>
                @else
                    <!-- Display verse title if it exists for other verses -->
                    @if(isset($verse['verse_titles']) && !empty($verse['verse_titles']))
                        <div class="mt-6 block w-full">
                            <div class="verse-title text-xs font-semibold text-blue-700 dark:text-blue-400 font-serif">{!! strip_tags($verse['verse_titles'], '<div><h2><p><span><em><strong><sup><sub><foreign>') !!}</div>
                        </div>
                    @endif

                    <p class="mb-2 text-gray-900 dark:text-gray-100 leading-relaxed reader-text font-serif verse-content" wire:key="v-{{ $chapterNumber }}-{{ $verse['verse_number'] }}" style="font-family: 'Charter', 'Source Serif Pro', 'Crimson Text', 'Libre Baskerville', 'PT Serif', 'Georgia', 'Times New Roman', serif; line-height: 1.7; letter-spacing: 0.01em;" @if($emitIds) id="verse-{{ $verse['verse_number'] }}" @endif>
                        <!-- Regular verse number for other verses -->
                        <span class="verse-number text-xs font-medium text-gray-600 dark:text-gray-400 align-super font-serif">{{ $verse['verse_number'] }}</span>{!! $this->parseEnhancedVerseText($verse) !!}
                    </p>
                @endif
            @endforeach
        @else
            {{-- Bible-style paragraphs: the verses form a <p>; headings and psalm titles sit BETWEEN the
                 paragraphs. A block element (h2, p, div) inside a <p> makes the HTML parser close it, so
                 the browser's DOM would no longer match the HTML Livewire morphs against. A title on a
                 later verse closes the open paragraph; the next verse opens a new one. --}}
            @php
                $pClass = 'mb-6 text-gray-900 dark:text-gray-100 leading-relaxed reader-text text-justify font-serif ' . ($loop->first ? '' : 'first-line:indent-8') . " after:content-[''] after:table after:clear-both verse-content";
                $pStyle = "font-family: 'Charter', 'Source Serif Pro', 'Crimson Text', 'Libre Baskerville', 'PT Serif', 'Georgia', 'Times New Roman', serif; line-height: 1.8; letter-spacing: 0.01em;";
                $pOpen = false;
                $titlesDisplayed = false;
            @endphp
            @foreach($paragraph['verses'] as $verse)
                @php
                    $hasChapterTitles = ! $titlesDisplayed && ! empty($verse['chapter_titles']);
                    $hasVerseTitles = ! empty($verse['verse_titles']);
                @endphp
                @if(($hasChapterTitles || $hasVerseTitles) && $pOpen)
                    </p>
                    @php $pOpen = false; @endphp
                @endif
                @if($hasChapterTitles)
                    <div class="mb-4 block w-full">
                        {!! strip_tags($verse['chapter_titles'], '<div><h2><p><span><em><strong><sup><sub><foreign>') !!}
                    </div>
                    @php $titlesDisplayed = true; @endphp
                @endif
                @if($hasVerseTitles)
                    <div class="{{ $verse['verse_number'] === 1 ? '' : 'mt-6' }} mb-2 block w-full clear-both">
                        <div class="verse-title text-xs font-semibold text-blue-700 dark:text-blue-400 font-serif">{!! strip_tags($verse['verse_titles'], '<div><h2><p><span><em><strong><sup><sub><foreign>') !!}</div>
                    </div>
                @endif
                @if(! $pOpen)
                    <p class="{{ $pClass }}" style="{{ $pStyle }}">
                    @php $pOpen = true; @endphp
                @endif
                @if($verse['verse_number'] === 1)
                    <span class="text-gray-700 dark:text-gray-300 font-serif" style="float: left; font-size: 4rem; line-height: 3.2rem; margin-right: 0.5rem; margin-top: 0rem; font-weight: bold;">{{ $chapterNumber }}</span><span class="inline" wire:key="v-{{ $chapterNumber }}-{{ $verse['verse_number'] }}" @if($emitIds) id="verse-{{ $verse['verse_number'] }}" @endif>{!! $this->parseEnhancedVerseText($verse) !!}</span>
                @else
                    <span class="inline" wire:key="v-{{ $chapterNumber }}-{{ $verse['verse_number'] }}" @if($emitIds) id="verse-{{ $verse['verse_number'] }}" @endif><span class="verse-number text-xs font-medium text-gray-500 dark:text-gray-400 align-super font-serif">{{ $verse['verse_number'] }}</span>{!! $this->parseEnhancedVerseText($verse) !!}</span>
                @endif
            @endforeach
            @if($pOpen)
                </p>
            @endif
        @endif
    @elseif(isset($paragraph['combined_text']))
        <!-- Combined paragraph text (fallback) -->
        <p class="mb-6 text-gray-900 dark:text-gray-100 leading-relaxed reader-text text-justify font-serif {{ $loop->first ? '' : 'first-line:indent-8' }} verse-content" style="font-family: 'Charter', 'Source Serif Pro', 'Crimson Text', 'Libre Baskerville', 'PT Serif', 'Georgia', 'Times New Roman', serif; line-height: 1.8; letter-spacing: 0.01em;">
            {!! strip_tags($paragraph['combined_text'], '<em><strong><sup><sub><span>') !!}
        </p>
    @endif
</div>
