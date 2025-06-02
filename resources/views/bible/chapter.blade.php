@extends('layouts.bible')

@section('title', $currentBook['short_name'] . ' ' . $chapterNumber . ' - Bible Reader')

@section('content')
<div class="space-y-4">
    <!-- Floating Navigation Buttons -->
    @if($chapterNumber > 1)
        <button onclick="navigateToChapter('{{ $currentBook['osis_id'] }}', {{ $chapterNumber - 1 }})"
           id="prevChapterBtn"
           class="fixed z-40 bg-white dark:bg-gray-800 hover:bg-blue-50 dark:hover:bg-blue-900/20 text-gray-800 dark:text-gray-200 flex items-center justify-center border border-gray-200 dark:border-gray-700 hover:border-blue-200 dark:hover:border-blue-800"
           style="left: 0; top: 50%; width: 30px; min-width: 30px; height: 64px; transform: translateY(-50%); box-shadow: 0 1px 3px 0 rgb(0 0 0 / 0.1), 0 1px 2px -1px rgb(0 0 0 / 0.1);"
           title="Previous Chapter">
            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M15 19l-7-7 7-7"></path>
            </svg>
        </button>
    @endif

    @if($chapterNumber < $chapters->count())
        <button onclick="navigateToChapter('{{ $currentBook['osis_id'] }}', {{ $chapterNumber + 1 }})"
           id="nextChapterBtn"
           class="fixed z-40 bg-white dark:bg-gray-800 hover:bg-blue-50 dark:hover:bg-blue-900/20 text-gray-800 dark:text-gray-200 flex items-center justify-center border border-gray-200 dark:border-gray-700 hover:border-blue-200 dark:hover:border-blue-800"
           style="right: 0; top: 50%; width: 30px; min-width: 30px; height: 64px; transform: translateY(-50%); box-shadow: 0 1px 3px 0 rgb(0 0 0 / 0.1), 0 1px 2px -1px rgb(0 0 0 / 0.1);"
           title="Next Chapter">
            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M9 5l7 7-7 7"></path>
            </svg>
        </button>
    @endif

    <!-- Mobile-Optimized Chapter Text -->
    <div class="ios-card shadow-sm p-4 sm:p-8">
        <div id="verseContainer" class="bible-text prose prose-lg dark:prose-invert max-w-none" style="font-size: 1.125rem;">
            @if($formatStyle === 'paragraph' && $paragraphs)
                <!-- Display chapter titles if they exist (before paragraphs) -->
                @if($paragraphs->isNotEmpty() && isset($paragraphs->first()['verses'][0]['chapter_titles']) && !empty($paragraphs->first()['verses'][0]['chapter_titles']))
                    <div class="mb-6">
                        {!! $paragraphs->first()['verses'][0]['chapter_titles'] !!}
                    </div>
                @endif

                @foreach($paragraphs as $paragraph)
                    @if(isset($paragraph['type']) && $paragraph['type'] === 'line_break')
                        <!-- Render line break -->
                        <div class="my-6">
                            {!! $paragraph['combined_text'] !!}
                        </div>
                    @else
                        <!-- Render normal paragraph -->
                        <div class="mb-6">
                            <p class="mb-4 leading-relaxed"
                               id="paragraph-{{ $paragraph['verses'][0]['verse_number'] ?? '' }}">
                                @foreach($paragraph['verse_groups'] as $group)
                                    @if($group['highlighted'])
                                        <span class="bg-yellow-100 dark:bg-yellow-900 px-2 py-1 rounded" style="display: inline;">
                                            @foreach($group['verses'] as $verse)
                                                <span class="paragraph-verse-hoverable verse-content">
                                                    <span class="verse-number text-blue-600 dark:text-blue-400"
                                                          id="verse-{{ $verse['verse_number'] }}">
                                                        {{ $verse['verse_number'] }}
                                                    </span><span class="text-gray-900 dark:text-gray-100">{!! $verse['text'] !!}</span>
                                                </span>@if(!$loop->last) @endif
                                            @endforeach
                                        </span>
                                    @else
                                        @foreach($group['verses'] as $verse)
                                            <span class="paragraph-verse-hoverable verse-content">
                                                <span class="verse-number text-blue-600 dark:text-blue-400"
                                                      id="verse-{{ $verse['verse_number'] }}">
                                                    {{ $verse['verse_number'] }}
                                                </span><span class="text-gray-800 dark:text-gray-200">{!! $verse['text'] !!}</span>
                                            </span>@if(!$loop->last) @endif
                                        @endforeach
                                    @endif
                                @endforeach
                            </p>
                        </div>
                    @endif
                @endforeach
            @else
                <!-- Display chapter titles if they exist (before verses) -->
                @if($verses->isNotEmpty() && !empty($verses->first()['chapter_titles']))
                    <div class="mb-6">
                        {!! $verses->first()['chapter_titles'] !!}
                    </div>
                @endif

                @foreach($verses as $verse)
                    <p class="mb-4 leading-relaxed {{ $verse['highlight_class'] }} verse-hoverable verse-content"
                       id="verse-{{ $verse['verse_number'] }}">
                        <span class="verse-number text-blue-600 dark:text-blue-400">
                            {{ $verse['verse_number'] }}
                        </span><span class="text-gray-800 dark:text-gray-200">{!! $verse['text'] !!}</span>
                    </p>
                @endforeach
            @endif
        </div>
    </div>

    <!-- Mobile-Optimized Formatting Guide Modal -->
    <div id="formattingGuide" class="fixed inset-0 bg-black bg-opacity-50 dark:bg-black dark:bg-opacity-70 hidden z-50">
        <div class="flex items-center justify-center min-h-screen p-4">
            <div class="ios-card rounded-2xl max-w-sm w-full p-6 max-h-[80vh] overflow-y-auto">
                <div class="flex justify-between items-center mb-4">
                    <h3 class="text-lg font-semibold text-gray-900 dark:text-gray-100">📖 Bible Text Guide</h3>
                    <button onclick="toggleFormattingGuide()" class="p-1 text-gray-400 dark:text-gray-500 hover:text-gray-600 dark:hover:text-gray-300">✕</button>
                </div>

                <div class="space-y-4 text-sm">
                    <div>
                        <h4 class="font-medium text-gray-900 dark:text-gray-100 mb-2">Text Styles:</h4>
                        <div class="space-y-2">
                            <div class="flex items-start gap-2">
                                <span class="text-red-600 dark:text-red-400 font-medium">Red text</span>
                                <span class="text-gray-600 dark:text-gray-400">- Jesus' words</span>
                            </div>
                            <div class="flex items-start gap-2">
                                <em class="text-gray-600 dark:text-gray-400 font-normal">Italic text</em>
                                <span class="text-gray-600 dark:text-gray-400">- Added words</span>
                            </div>
                            <div class="flex items-start gap-2">
                                <span class="text-xs font-bold text-blue-600 dark:text-blue-400">1</span>
                                <span class="text-gray-600 dark:text-gray-400">- Verse numbers</span>
                            </div>
                        </div>
                    </div>

                    <div>
                        <h4 class="font-medium text-gray-900 dark:text-gray-100 mb-2">Format Options:</h4>
                        <div class="space-y-2">
                            <div class="flex items-start gap-2">
                                <span class="text-sm">📖</span>
                                <span class="text-gray-600 dark:text-gray-400">Paragraph - verses flow together</span>
                            </div>
                            <div class="flex items-start gap-2">
                                <span class="text-sm">📝</span>
                                <span class="text-gray-600 dark:text-gray-400">Verse - each verse separate</span>
                            </div>
                            <div class="flex items-start gap-2">
                                <span class="text-sm">🔴</span>
                                <span class="text-gray-600 dark:text-gray-400">Toggle red letter text</span>
                            </div>
                            <div class="flex items-start gap-2">
                                <span class="text-sm">🔍</span>
                                <span class="text-gray-600 dark:text-gray-400">Adjust text size</span>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="mt-6 text-center">
                    <button onclick="toggleFormattingGuide()"
                            class="touch-friendly px-6 py-3 bg-blue-500 hover:bg-blue-600 text-white rounded-xl font-medium transition-colors w-full">
                        Got it!
                    </button>
                </div>
            </div>
        </div>
    </div>
</div>

@push('scripts')
<script>
// AJAX Chapter Navigation
let isNavigating = false;

function navigateToChapter(bookOsisId, chapterNumber) {
    if (isNavigating) return; // Prevent multiple simultaneous requests

    isNavigating = true;

    // Show loading state on buttons
    showNavigationLoading();

    // Fetch the new chapter content
    fetch(`/${bookOsisId}/${chapterNumber}?ajax=1`)
        .then(response => {
            if (!response.ok) {
                throw new Error(`HTTP error! status: ${response.status}`);
            }
            return response.text();
        })
        .then(html => {
            // Parse the response to extract the main content
            const parser = new DOMParser();
            const doc = parser.parseFromString(html, 'text/html');

            // Update the page content
            updatePageContent(doc, bookOsisId, chapterNumber);

            // Update browser history
            const newUrl = `/${bookOsisId}/${chapterNumber}`;
            window.history.pushState({bookOsisId, chapterNumber}, '', newUrl);

            // Update page title
            const newTitle = doc.title;
            document.title = newTitle;

            // Scroll to top smoothly
            window.scrollTo({ top: 0, behavior: 'smooth' });

            isNavigating = false;
            hideNavigationLoading();
        })
        .catch(error => {
            console.error('Navigation error:', error);
            // Fallback to regular navigation
            window.location.href = `/${bookOsisId}/${chapterNumber}`;
            isNavigating = false;
        });
}

function updatePageContent(doc, bookOsisId, chapterNumber) {
    // Update the main content area
    const newContent = doc.querySelector('.space-y-4').innerHTML;
    document.querySelector('.space-y-4').innerHTML = newContent;

    // Update the header with new book/chapter info
    const headerButtons = document.querySelectorAll('button[onclick*="showBookSelector"]');
    const newHeaderButton = doc.querySelector('button[onclick*="showBookSelector"]');
    if (headerButtons.length > 0 && newHeaderButton) {
        headerButtons.forEach(button => {
            button.innerHTML = newHeaderButton.innerHTML;
        });
    }

    // Re-run initialization scripts
    if (typeof preventVerseNumberOrphaning === 'function') {
        preventVerseNumberOrphaning();
    }

    // Re-initialize font size
    if (typeof updateFontSize === 'function') {
        updateFontSize();
    }

    // Clear any existing highlights from session
    clearSessionHighlights();
}

function showNavigationLoading() {
    const prevBtn = document.getElementById('prevChapterBtn');
    const nextBtn = document.getElementById('nextChapterBtn');

    [prevBtn, nextBtn].forEach(btn => {
        if (btn) {
            btn.disabled = true;
            btn.style.opacity = '0.7';
            const svg = btn.querySelector('svg');
            if (svg) {
                svg.style.animation = 'pulse 1s infinite';
            }
        }
    });
}

function hideNavigationLoading() {
    const prevBtn = document.getElementById('prevChapterBtn');
    const nextBtn = document.getElementById('nextChapterBtn');

    [prevBtn, nextBtn].forEach(btn => {
        if (btn) {
            btn.disabled = false;
            btn.style.opacity = '1';
            const svg = btn.querySelector('svg');
            if (svg) {
                svg.style.animation = '';
            }
        }
    });
}

function clearSessionHighlights() {
    // Remove any existing highlight classes that might persist
    document.querySelectorAll('.bg-yellow-100, .dark\\:bg-yellow-900').forEach(el => {
        el.classList.remove('bg-yellow-100', 'dark:bg-yellow-900');
    });
}

// Handle browser back/forward buttons
window.addEventListener('popstate', function(e) {
    if (e.state && e.state.bookOsisId && e.state.chapterNumber) {
        navigateToChapter(e.state.bookOsisId, e.state.chapterNumber);
    } else {
        // Fallback: reload the page
        window.location.reload();
    }
});

// Prevent verse number orphaning
function preventVerseNumberOrphaning() {
    // Find all verse containers
    const verseContainers = document.querySelectorAll('.verse-content');

    verseContainers.forEach(container => {
        const verseNumber = container.querySelector('.verse-number');
        if (!verseNumber) return;

        const textSpan = verseNumber.nextElementSibling;
        if (!textSpan) return;

        // Use textContent to safely get the first word without breaking HTML
        const textContent = textSpan.textContent || textSpan.innerText;
        const firstWordMatch = textContent.match(/^(\s*)(\S+)(\s*)/);

        if (firstWordMatch) {
            const [fullMatch, leadingSpace, firstWord, trailingSpace] = firstWordMatch;

            // Create a non-breaking wrapper for verse number + first word
            const wrapper = document.createElement('span');
            wrapper.style.whiteSpace = 'nowrap';
            wrapper.style.display = 'inline';

            // Move verse number into wrapper
            wrapper.appendChild(verseNumber.cloneNode(true));

            // Create a styled span for the first word that inherits text styling
            const firstWordSpan = document.createElement('span');
            firstWordSpan.textContent = leadingSpace + firstWord + trailingSpace;
            // Copy all classes from the original text span to preserve styling
            firstWordSpan.className = textSpan.className;
            // Also copy inline styles if any
            if (textSpan.style.cssText) {
                firstWordSpan.style.cssText = textSpan.style.cssText;
            }
            wrapper.appendChild(firstWordSpan);

            // Remove the first word from the original text span
            // This approach preserves all HTML formatting
            const walker = document.createTreeWalker(
                textSpan,
                NodeFilter.SHOW_TEXT,
                null,
                false
            );

            let firstTextNode = walker.nextNode();
            if (firstTextNode) {
                const nodeText = firstTextNode.textContent;
                // Remove the exact match we found
                if (nodeText.startsWith(fullMatch)) {
                    firstTextNode.textContent = nodeText.substring(fullMatch.length);
                }
            }

            // Replace original verse number with wrapper
            container.insertBefore(wrapper, textSpan);
            verseNumber.remove();
        }
    });
}

// Run on page load and after any dynamic content changes
document.addEventListener('DOMContentLoaded', preventVerseNumberOrphaning);

// Also run when window resizes (text reflows)
let resizeTimeout;
window.addEventListener('resize', () => {
    clearTimeout(resizeTimeout);
    resizeTimeout = setTimeout(preventVerseNumberOrphaning, 100);
});

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
        // Show mobile-friendly feedback
        const button = event.target;
        const originalText = button.innerHTML;
        button.innerHTML = '✅ Copied!';
        setTimeout(() => {
            button.innerHTML = originalText;
        }, 2000);
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
    }
});
@endif

// Red Letter Bible toggle functionality
let redLettersEnabled = true;

function toggleRedLetters() {
    redLettersEnabled = !redLettersEnabled;
    const redSpans = document.querySelectorAll('.text-red-600');
    const toggleButtonMobile = document.getElementById('redLetterToggleMobile');

    redSpans.forEach(span => {
        if (redLettersEnabled) {
            span.style.color = '#dc2626'; // red-600
            span.style.fontWeight = '500'; // font-medium
        } else {
            span.style.color = '#374151'; // gray-700 (normal text)
            span.style.fontWeight = '400'; // normal
        }
    });

    // Update mobile button appearance
    if (toggleButtonMobile) {
        if (redLettersEnabled) {
            toggleButtonMobile.className = toggleButtonMobile.className.replace(/bg-gray-\d+/g, 'bg-red-50').replace(/text-gray-\d+/g, 'text-red-800');
            toggleButtonMobile.innerHTML = '🔴 Red Letters';
        } else {
            toggleButtonMobile.className = toggleButtonMobile.className.replace(/bg-red-\d+/g, 'bg-gray-50').replace(/text-red-\d+/g, 'text-gray-800');
            toggleButtonMobile.innerHTML = '⚫ Red Letters';
        }
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
    const toggleButtonMobile = document.getElementById('formattingGuideToggleMobile');

    if (modal && !modal.querySelector('.ios-card').contains(e.target) &&
        e.target !== toggleButtonMobile && !toggleButtonMobile?.contains(e.target)) {
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
    const buttons = [
        { decrease: document.getElementById('fontDecreaseBtnMobile'), increase: document.getElementById('fontIncreaseBtnMobile') }
    ];

    buttons.forEach(({ decrease, increase }) => {
        if (decrease && increase) {
            decrease.disabled = currentFontSize <= minFontSize;
            increase.disabled = currentFontSize >= maxFontSize;

            // Update styling based on disabled state
            if (currentFontSize <= minFontSize) {
                decrease.className = decrease.className.replace(/hover:bg-\S+/g, '').replace(/bg-gray-\d+/g, 'bg-gray-200') + ' cursor-not-allowed opacity-50';
            } else {
                decrease.className = decrease.className.replace(/cursor-not-allowed|opacity-50/g, '').replace(/bg-gray-200/g, 'bg-gray-50') + ' hover:bg-gray-100 dark:hover:bg-gray-600';
            }

            if (currentFontSize >= maxFontSize) {
                increase.className = increase.className.replace(/hover:bg-\S+/g, '').replace(/bg-gray-\d+/g, 'bg-gray-200') + ' cursor-not-allowed opacity-50';
            } else {
                increase.className = increase.className.replace(/cursor-not-allowed|opacity-50/g, '').replace(/bg-gray-200/g, 'bg-gray-50') + ' hover:bg-gray-100 dark:hover:bg-gray-600';
            }
        }
    });
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
