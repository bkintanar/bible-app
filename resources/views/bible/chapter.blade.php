@extends('layouts.bible')

@section('title', $currentBook['short_name'] . ' ' . $chapterNumber . ' - Bible Reader')

@section('content')
<div class="space-y-6">
    <!-- Breadcrumb -->
    <nav class="flex" aria-label="Breadcrumb">
        <ol class="flex items-center space-x-4">
            <li>
                <a href="{{ route('bible.index') }}" class="text-bible-blue hover:text-blue-800">
                    📖 Bible
                </a>
            </li>
            <li>
                <span class="text-gray-400">/</span>
            </li>
            <li>
                <a href="{{ route('bible.book', $currentBook['osis_id']) }}" class="text-bible-blue hover:text-blue-800">
                    {{ $currentBook['short_name'] }}
                </a>
            </li>
            <li>
                <span class="text-gray-400">/</span>
            </li>
            <li>
                <span class="text-gray-600">Chapter {{ $chapterNumber }}</span>
            </li>
        </ol>
    </nav>

    <!-- Chapter Header -->
    <div class="bg-white dark:bg-gray-800 rounded-lg shadow-sm p-6">
        <div class="flex items-center justify-between mb-4">
            <div>
                <h1 class="text-3xl font-bold text-gray-900 dark:text-gray-100">{{ $currentBook['name'] }} {{ $chapterNumber }}</h1>
                <p class="text-gray-600 dark:text-gray-400 mt-1">{{ $currentBook['testament'] }}</p>
            </div>
            <div class="text-right">
                <div class="text-2xl font-bold text-bible-blue dark:text-blue-400">
                    {{ $formatStyle === 'paragraph' && $paragraphs ? $paragraphs->sum(function($p) { return count($p['verses']); }) : ($verses ? $verses->count() : 0) }}
                </div>
                <div class="text-sm text-gray-500 dark:text-gray-400">Verses</div>
            </div>
        </div>

        <!-- Chapter Navigation -->
        <div class="flex items-center justify-between">
            <div class="flex space-x-2">
                @if($chapterNumber > 1)
                    <a href="{{ route('bible.chapter', [$currentBook['osis_id'], $chapterNumber - 1]) }}"
                       class="flex items-center px-4 py-2 bg-gray-100 dark:bg-gray-700 hover:bg-bible-blue dark:hover:bg-blue-600 hover:text-white rounded-md text-sm transition-colors">
                        ← Previous
                    </a>
                @endif
            </div>

            <div class="flex items-center space-x-2">
                <span class="text-sm text-gray-500 dark:text-gray-400">Chapter:</span>
                <select onchange="window.location.href=this.value"
                        class="border border-gray-300 dark:border-gray-600 bg-white dark:bg-gray-700 text-gray-900 dark:text-gray-100 rounded-md px-3 py-1 text-sm focus:outline-none focus:ring-2 focus:ring-bible-blue dark:focus:ring-blue-400 focus:border-transparent">
                    @foreach($chapters as $chapter)
                        <option value="{{ route('bible.chapter', [$currentBook['osis_id'], $chapter['chapter_number']]) }}"
                                {{ $chapter['chapter_number'] == $chapterNumber ? 'selected' : '' }}>
                            {{ $chapter['chapter_number'] }}
                        </option>
                    @endforeach
                </select>
            </div>

            <div class="flex space-x-2">
                @if($chapterNumber < $chapters->count())
                    <a href="{{ route('bible.chapter', [$currentBook['osis_id'], $chapterNumber + 1]) }}"
                       class="flex items-center px-4 py-2 bg-gray-100 dark:bg-gray-700 hover:bg-bible-blue dark:hover:bg-blue-600 hover:text-white rounded-md text-sm transition-colors">
                        Next →
                    </a>
                @endif
            </div>
        </div>
    </div>

    <!-- Reading Controls -->
    <div class="bg-white dark:bg-gray-800 rounded-lg shadow-sm p-4">
        <div class="flex flex-wrap items-center justify-between gap-4">
            <div class="flex flex-wrap items-center gap-2">
                <span class="text-sm font-medium text-gray-700 dark:text-gray-300">Display Options:</span>

                <button onclick="toggleRedLetters()" id="redLetterToggle"
                        class="flex items-center px-3 py-2 bg-red-100 dark:bg-red-900 hover:bg-red-200 dark:hover:bg-red-800 text-red-800 dark:text-red-200 rounded-md text-sm transition-colors">
                    🔴 Red Letters
                </button>

                <a href="{{ request()->fullUrlWithQuery(['style' => $formatStyle === 'paragraph' ? 'verse' : 'paragraph']) }}"
                   class="flex items-center px-3 py-2 bg-blue-100 dark:bg-blue-900 hover:bg-blue-200 dark:hover:bg-blue-800 text-blue-800 dark:text-blue-200 rounded-md text-sm transition-colors">
                    {{ $formatStyle === 'paragraph' ? '📖 Paragraph Style' : '📝 Verse Style' }}
                </a>

                <button onclick="toggleFormattingGuide()" id="formattingGuideToggle"
                        class="flex items-center px-3 py-2 bg-green-100 dark:bg-green-900 hover:bg-green-200 dark:hover:bg-green-800 text-green-800 dark:text-green-200 rounded-md text-sm transition-colors">
                    ❓ Text Guide
                </button>
            </div>

            <div class="flex items-center gap-2">
                <span class="text-sm font-medium text-gray-700 dark:text-gray-300">Font Size:</span>
                <button onclick="decreaseFontSize()" id="fontDecreaseBtn"
                        class="flex items-center px-3 py-2 bg-gray-100 dark:bg-gray-700 hover:bg-gray-200 dark:hover:bg-gray-600 text-gray-800 dark:text-gray-200 rounded-md text-sm transition-colors">
                    🔍− Smaller
                </button>
                <button onclick="increaseFontSize()" id="fontIncreaseBtn"
                        class="flex items-center px-3 py-2 bg-gray-100 dark:bg-gray-700 hover:bg-gray-200 dark:hover:bg-gray-600 text-gray-800 dark:text-gray-200 rounded-md text-sm transition-colors">
                    🔍+ Larger
                </button>
                <button onclick="resetFontSize()" id="fontResetBtn"
                        class="flex items-center px-2 py-2 bg-gray-100 dark:bg-gray-700 hover:bg-gray-200 dark:hover:bg-gray-600 text-gray-800 dark:text-gray-200 rounded-md text-sm transition-colors">
                    Reset
                </button>
            </div>
        </div>
    </div>

    <!-- Verses -->
    <div class="bg-white dark:bg-gray-800 rounded-lg shadow-sm p-8">
        <div id="verseContainer" class="prose prose-xl dark:prose-invert max-w-none" style="font-size: 1.125rem;">
            @if($formatStyle === 'paragraph' && $paragraphs)
                @foreach($paragraphs as $paragraph)
                    @if(isset($paragraph['type']) && $paragraph['type'] === 'line_break')
                        <!-- Render line break -->
                        <div class="my-6">
                            {!! $paragraph['combined_text'] !!}
                        </div>
                    @else
                        <!-- Render normal paragraph -->
                        <div class="mb-6">
                            @php
                                $paragraphVerseNumbers = collect($paragraph['verses'])->pluck('verse_number');
                                $highlightClass = '';

                                // Check if any verse in this paragraph should be highlighted
                                $shouldHighlight = false;

                                // Single verse highlighting
                                if (session('highlightVerse') && $paragraphVerseNumbers->contains(session('highlightVerse'))) {
                                    $shouldHighlight = true;
                                    $highlightClass = 'bg-yellow-100 border-l-4 border-yellow-400 pl-4 py-2';
                                }

                                // Verse range highlighting
                                $verseRange = session('highlightVerseRange');
                                if ($verseRange) {
                                    $rangeOverlap = $paragraphVerseNumbers->filter(function($num) use ($verseRange) {
                                        return $num >= $verseRange['start'] && $num <= $verseRange['end'];
                                    });
                                    if ($rangeOverlap->isNotEmpty()) {
                                        $shouldHighlight = true;
                                        $highlightClass = 'bg-blue-50 border-l-4 border-blue-400 pl-4 py-2';
                                    }
                                }
                            @endphp

                            <p class="mb-4 leading-relaxed {{ $highlightClass }}"
                               id="paragraph-{{ $paragraph['verses'][0]['verse_number'] ?? '' }}">
                                @foreach($paragraph['verses'] as $verse)
                                    <span class="paragraph-verse-hoverable">
                                        <span class="inline-block align-top text-xs font-bold text-bible-blue dark:text-blue-400 mr-1 mt-1 min-w-[1.5rem]"
                                              id="verse-{{ $verse['verse_number'] }}">
                                            {{ $verse['verse_number'] }}
                                        </span><!--
                                        --><span class="text-gray-800 dark:text-gray-200">{!! $verse['text'] !!}</span>
                                    </span>@if(!$loop->last) @endif
                                @endforeach
                            </p>
                        </div>
                    @endif
                @endforeach
            @else
                @foreach($verses as $verse)
                    @php
                        $isHighlighted = false;
                        $highlightClass = '';

                        // Single verse highlighting
                        if (session('highlightVerse') == $verse['verse_number']) {
                            $isHighlighted = true;
                            $highlightClass = 'bg-yellow-100 border-l-4 border-yellow-400 pl-4 py-2';
                        }

                        // Verse range highlighting
                        $verseRange = session('highlightVerseRange');
                        if ($verseRange &&
                            $verse['verse_number'] >= $verseRange['start'] &&
                            $verse['verse_number'] <= $verseRange['end']) {
                            $isHighlighted = true;
                            $highlightClass = 'bg-blue-50 border-l-4 border-blue-400 pl-4 py-2';
                        }
                    @endphp

                    <p class="mb-4 leading-relaxed {{ $highlightClass }} verse-hoverable"
                       id="verse-{{ $verse['verse_number'] }}">
                        <span class="inline-block align-top text-xs font-bold text-bible-blue dark:text-blue-400 mr-1 mt-1 min-w-[1.5rem]">
                            {{ $verse['verse_number'] }}
                        </span>
                        <span class="text-gray-800 dark:text-gray-200">{!! $verse['text'] !!}</span>
                    </p>
                @endforeach
            @endif
        </div>
    </div>

    <!-- Copy Chapter -->
    <div class="bg-gray-50 dark:bg-gray-700 rounded-lg p-4">
        <button onclick="copyChapter()"
                class="flex items-center px-4 py-2 bg-bible-blue dark:bg-blue-600 text-white rounded-md text-sm hover:bg-blue-700 dark:hover:bg-blue-500 transition-colors">
            📋 Copy Chapter Text
        </button>
    </div>

    <!-- Formatting Guide Modal -->
    <div id="formattingGuide" class="fixed inset-0 bg-black bg-opacity-50 dark:bg-black dark:bg-opacity-70 hidden z-50">
        <div class="flex items-center justify-center min-h-screen p-4">
            <div class="bg-white dark:bg-gray-800 rounded-lg max-w-md w-full p-6">
                <div class="flex justify-between items-center mb-4">
                    <h3 class="text-lg font-semibold text-gray-900 dark:text-gray-100">📖 Bible Text Guide</h3>
                    <button onclick="toggleFormattingGuide()" class="text-gray-400 dark:text-gray-500 hover:text-gray-600 dark:hover:text-gray-300">✕</button>
                </div>

                <div class="space-y-4 text-sm">
                    <div>
                        <h4 class="font-medium text-gray-900 dark:text-gray-100 mb-2">Text Styles:</h4>
                        <div class="space-y-2">
                            <div class="flex items-start gap-2">
                                <span class="text-red-600 dark:text-red-400 font-medium">Red text</span>
                                <span class="text-gray-600 dark:text-gray-400">- Jesus' words (Red Letter Bible)</span>
                            </div>
                            <div class="flex items-start gap-2">
                                <em class="text-gray-600 dark:text-gray-400 font-normal">Italic text</em>
                                <span class="text-gray-600 dark:text-gray-400">- Words added by translators for clarity</span>
                            </div>
                            <div class="flex items-start gap-2">
                                <span class="text-xs font-bold text-bible-blue dark:text-blue-400">1</span>
                                <span class="text-gray-600 dark:text-gray-400">- Verse numbers (traditional style)</span>
                            </div>
                        </div>
                    </div>

                    <div>
                        <h4 class="font-medium text-gray-900 dark:text-gray-100 mb-2">Special Elements:</h4>
                        <div class="space-y-2">
                            <div class="flex items-start gap-2">
                                <div class="text-center text-sm font-medium text-gray-700 dark:text-gray-300 italic border-b border-gray-200 dark:border-gray-600 pb-1 px-2">Psalm Title</div>
                                <span class="text-gray-600 dark:text-gray-400">- Psalm headings and attributions</span>
                            </div>
                        </div>
                    </div>

                    <div>
                        <h4 class="font-medium text-gray-900 dark:text-gray-100 mb-2">Format Options:</h4>
                        <div class="space-y-2">
                            <div class="flex items-start gap-2">
                                <span class="text-sm">📖</span>
                                <span class="text-gray-600 dark:text-gray-400">Paragraph Style - verses flow naturally together</span>
                            </div>
                            <div class="flex items-start gap-2">
                                <span class="text-sm">📝</span>
                                <span class="text-gray-600 dark:text-gray-400">Verse Style - each verse on its own line</span>
                            </div>
                            <div class="flex items-start gap-2">
                                <span class="text-sm">🔴</span>
                                <span class="text-gray-600 dark:text-gray-400">Toggle Jesus' words between red and normal</span>
                            </div>
                            <div class="flex items-start gap-2">
                                <span class="text-sm">🔍</span>
                                <span class="text-gray-600 dark:text-gray-400">Font size controls - adjust text size to your preference</span>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="mt-6 text-center">
                    <button onclick="toggleFormattingGuide()"
                            class="px-4 py-2 bg-bible-blue dark:bg-blue-600 text-white rounded-md text-sm hover:bg-blue-700 dark:hover:bg-blue-500 transition-colors">
                        Got it!
                    </button>
                </div>
            </div>
        </div>
    </div>
</div>

@push('scripts')
<script>
function copyChapter() {
    @if($formatStyle === 'paragraph' && $paragraphs)
        const paragraphs = @json($paragraphs);
        const bookName = "{{ $currentBook['name'] }}";
        const chapterNum = {{ $chapterNumber }};

        let text = `${bookName} ${chapterNum}\n\n`;

        paragraphs.forEach(paragraph => {
            paragraph.verses.forEach(verse => {
                text += `${verse.verse_number}. ${verse.text.replace(/<[^>]*>/g, '')} `;
            });
            text += '\n\n';
        });
    @else
        const verses = @json($verses);
        const bookName = "{{ $currentBook['name'] }}";
        const chapterNum = {{ $chapterNumber }};

        let text = `${bookName} ${chapterNum}\n\n`;

        verses.forEach(verse => {
            text += `${verse.verse_number}. ${verse.text.replace(/<[^>]*>/g, '')}\n`;
        });
    @endif

    navigator.clipboard.writeText(text).then(() => {
        alert('Chapter copied to clipboard!');
    }).catch(err => {
        console.error('Failed to copy text: ', err);
    });
}

// Auto-scroll to highlighted verse if redirected from verse search
@if(session('highlightVerse'))
document.addEventListener('DOMContentLoaded', function() {
    const verseElement = document.getElementById('verse-{{ session('highlightVerse') }}');
    if (verseElement) {
        // Smooth scroll to the verse
        verseElement.scrollIntoView({
            behavior: 'smooth',
            block: 'center'
        });

        // Flash effect to draw attention
        setTimeout(() => {
            verseElement.style.transition = 'background-color 0.3s ease';
            verseElement.style.backgroundColor = '#fef3c7';
            setTimeout(() => {
                verseElement.style.backgroundColor = '#fef9e7';
            }, 300);
        }, 1000);
    }
});
@endif

@if(session('highlightVerseRange'))
document.addEventListener('DOMContentLoaded', function() {
    const verseRange = @json(session('highlightVerseRange'));
    const firstVerseElement = document.getElementById('verse-' + verseRange.start);

    if (firstVerseElement) {
        // Smooth scroll to the first verse in the range
        firstVerseElement.scrollIntoView({
            behavior: 'smooth',
            block: 'start'
        });

        // Flash effect for the entire range
        setTimeout(() => {
            for (let i = verseRange.start; i <= verseRange.end; i++) {
                const verseEl = document.getElementById('verse-' + i);
                if (verseEl) {
                    verseEl.style.transition = 'background-color 0.3s ease';
                    verseEl.style.backgroundColor = '#dbeafe';
                    setTimeout(() => {
                        verseEl.style.backgroundColor = '#eff6ff';
                    }, 300);
                }
            }
        }, 1000);
    }
});
@endif

// Red Letter Bible toggle functionality
let redLettersEnabled = true;

function toggleRedLetters() {
    redLettersEnabled = !redLettersEnabled;
    const redSpans = document.querySelectorAll('.text-red-600');
    const toggleButton = document.getElementById('redLetterToggle');

    redSpans.forEach(span => {
        if (redLettersEnabled) {
            span.style.color = '#dc2626'; // red-600
            span.style.fontWeight = '500'; // font-medium
        } else {
            span.style.color = '#374151'; // gray-700 (normal text)
            span.style.fontWeight = '400'; // normal
        }
    });

    // Update button appearance
    if (redLettersEnabled) {
        toggleButton.className = 'flex items-center px-3 py-2 bg-red-100 dark:bg-red-900 hover:bg-red-200 dark:hover:bg-red-800 text-red-800 dark:text-red-200 rounded-md text-sm transition-colors';
        toggleButton.innerHTML = '🔴 Red Letters';
    } else {
        toggleButton.className = 'flex items-center px-3 py-2 bg-gray-100 dark:bg-gray-700 hover:bg-gray-200 dark:hover:bg-gray-600 text-gray-800 dark:text-gray-200 rounded-md text-sm transition-colors';
        toggleButton.innerHTML = '⚫ Red Letters';
    }
}

// Formatting guide toggle functionality
function toggleFormattingGuide() {
    const modal = document.getElementById('formattingGuide');
    modal.classList.toggle('hidden');
}

// Close modal when clicking outside
document.addEventListener('click', function(e) {
    const modal = document.getElementById('formattingGuide');
    const toggleButton = document.getElementById('formattingGuideToggle');

    if (modal && !modal.querySelector('.bg-white').contains(e.target) && e.target !== toggleButton && !toggleButton.contains(e.target)) {
        modal.classList.add('hidden');
    }
});

// Font size control functionality
let currentFontSize = parseFloat(localStorage.getItem('bibleFontSize')) || 1.125; // Default 1.125rem (18px)
const minFontSize = 0.875; // 14px
const maxFontSize = 1.875; // 30px
const fontSizeStep = 0.125; // 2px increments

function updateFontSize() {
    const container = document.getElementById('verseContainer');
    if (container) {
        container.style.fontSize = currentFontSize + 'rem';

        // Update line height proportionally for better readability
        const lineHeight = Math.max(1.5, currentFontSize * 1.4);
        container.style.lineHeight = lineHeight;

        // Save to localStorage
        localStorage.setItem('bibleFontSize', currentFontSize);

        // Update button states
        updateFontButtons();
    }
}

function updateFontButtons() {
    const decreaseBtn = document.getElementById('fontDecreaseBtn');
    const increaseBtn = document.getElementById('fontIncreaseBtn');

    if (decreaseBtn && increaseBtn) {
        decreaseBtn.disabled = currentFontSize <= minFontSize;
        increaseBtn.disabled = currentFontSize >= maxFontSize;

        // Update button styling based on disabled state
        if (currentFontSize <= minFontSize) {
            decreaseBtn.className = 'flex items-center px-3 py-2 bg-gray-50 text-gray-400 rounded-md text-sm cursor-not-allowed';
        } else {
            decreaseBtn.className = 'flex items-center px-3 py-2 bg-gray-100 dark:bg-gray-700 hover:bg-gray-200 dark:hover:bg-gray-600 text-gray-800 dark:text-gray-200 rounded-md text-sm transition-colors';
        }

        if (currentFontSize >= maxFontSize) {
            increaseBtn.className = 'flex items-center px-3 py-2 bg-gray-50 text-gray-400 rounded-md text-sm cursor-not-allowed';
        } else {
            increaseBtn.className = 'flex items-center px-3 py-2 bg-gray-100 dark:bg-gray-700 hover:bg-gray-200 dark:hover:bg-gray-600 text-gray-800 dark:text-gray-200 rounded-md text-sm transition-colors';
        }
    }
}

function increaseFontSize() {
    if (currentFontSize < maxFontSize) {
        currentFontSize = Math.min(maxFontSize, currentFontSize + fontSizeStep);
        updateFontSize();
    }
}

function decreaseFontSize() {
    if (currentFontSize > minFontSize) {
        currentFontSize = Math.max(minFontSize, currentFontSize - fontSizeStep);
        updateFontSize();
    }
}

function resetFontSize() {
    currentFontSize = 1.125; // Reset to default 18px
    updateFontSize();
}

// Initialize font size on page load
document.addEventListener('DOMContentLoaded', function() {
    updateFontSize();
});
</script>
@endpush
@endsection
