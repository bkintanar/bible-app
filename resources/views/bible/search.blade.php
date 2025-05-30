@extends('layouts.bible')

@section('title', 'Search: ' . $searchTerm . ' - Bible Reader')

@section('content')
<div class="space-y-6">
    <!-- Search Header -->
    <div class="bg-white dark:bg-gray-800 rounded-lg shadow-sm p-6">
        <div class="flex items-center justify-between mb-2">
            <h1 class="text-2xl font-bold text-gray-900 dark:text-gray-100">
                Search Results for "{{ $searchTerm }}"
            </h1>

            <div class="flex flex-wrap items-center gap-2">
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

        @if(isset($error))
            <div class="bg-red-50 dark:bg-red-900/20 border border-red-200 dark:border-red-800 text-red-700 dark:text-red-300 px-4 py-3 rounded mb-4">
                {{ $error }}
            </div>
        @elseif(isset($totalFound))
            <p class="text-gray-600 dark:text-gray-400">
                Found {{ $totalFound }} verse{{ $totalFound !== 1 ? 's' : '' }}
                @if($totalFound > 0)
                    in {{ $groupedResults->count() }} book{{ $groupedResults->count() !== 1 ? 's' : '' }}
                @endif
                @if(isset($hasMoreResults) && $hasMoreResults)
                    <span class="text-amber-600 dark:text-amber-400 font-medium">(limited to first {{ $limit }} results)</span>
                @endif
            </p>
        @endif
    </div>

    <!-- Search Form -->
    <div class="bg-white dark:bg-gray-800 rounded-lg shadow-sm p-6">
        <form action="{{ route('bible.search') }}" method="GET" class="space-y-4">
            <div class="flex gap-4">
                <input type="text"
                       name="q"
                       value="{{ $searchTerm }}"
                       placeholder="Search for verses..."
                       class="flex-1 border border-gray-300 dark:border-gray-600 bg-white dark:bg-gray-700 text-gray-900 dark:text-gray-100 rounded-md px-4 py-2 focus:outline-none focus:ring-2 focus:ring-bible-blue dark:focus:ring-blue-400 focus:border-transparent placeholder-gray-500 dark:placeholder-gray-400">
                <button type="submit"
                        class="bg-bible-blue dark:bg-blue-600 text-white px-6 py-2 rounded-md hover:bg-blue-700 dark:hover:bg-blue-500 transition-colors">
                    Search
                </button>
            </div>

            <div class="flex items-center gap-4 text-sm">
                <label class="flex items-center gap-2">
                    <span class="text-gray-600 dark:text-gray-400">Results limit:</span>
                    <select name="limit" class="border border-gray-300 dark:border-gray-600 bg-white dark:bg-gray-700 text-gray-900 dark:text-gray-100 rounded px-2 py-1">
                        <option value="50" {{ ($limit ?? 100) == 50 ? 'selected' : '' }}>50</option>
                        <option value="100" {{ ($limit ?? 100) == 100 ? 'selected' : '' }}>100</option>
                        <option value="200" {{ ($limit ?? 100) == 200 ? 'selected' : '' }}>200</option>
                        <option value="500" {{ ($limit ?? 100) == 500 ? 'selected' : '' }}>500</option>
                    </select>
                </label>
            </div>
        </form>
    </div>

    <!-- Performance Info -->
    @if(isset($hasMoreResults) && $hasMoreResults)
    <div class="bg-amber-50 dark:bg-amber-900/20 border border-amber-200 dark:border-amber-800 rounded-lg p-4">
        <div class="flex items-start">
            <div class="flex-shrink-0">
                <svg class="h-5 w-5 text-amber-400 dark:text-amber-300" fill="currentColor" viewBox="0 0 20 20">
                    <path fill-rule="evenodd" d="M8.257 3.099c.765-1.36 2.722-1.36 3.486 0l5.58 9.92c.75 1.334-.213 2.98-1.742 2.98H4.42c-1.53 0-2.493-1.646-1.743-2.98l5.58-9.92zM11 13a1 1 0 11-2 0 1 1 0 012 0zm-1-8a1 1 0 00-1 1v3a1 1 0 002 0V6a1 1 0 00-1-1z" clip-rule="evenodd"/>
                </svg>
            </div>
            <div class="ml-3">
                <p class="text-sm text-amber-700 dark:text-amber-300">
                    <strong>Many results found!</strong> Showing first {{ $limit }} results.
                    Try a more specific search term or increase the limit above for more results.
                </p>
            </div>
        </div>
    </div>
    @endif

    <!-- Results -->
    @if(isset($groupedResults) && $groupedResults->isNotEmpty())
        @foreach($groupedResults as $bookId => $bookResults)
            <div class="bg-white dark:bg-gray-800 rounded-lg shadow-sm overflow-hidden">
                <!-- Book Header -->
                <div class="bg-gray-50 dark:bg-gray-700 px-6 py-4 border-b border-gray-200 dark:border-gray-600">
                    <div class="flex items-center justify-between">
                        <div>
                            <h2 class="text-lg font-semibold text-gray-900 dark:text-gray-100">
                                <a href="{{ route('bible.book', $bookResults['book']['osis_id']) }}"
                                   class="text-bible-blue dark:text-blue-400 hover:text-blue-800 dark:hover:text-blue-300">
                                    {{ $bookResults['book']['name'] }}
                                </a>
                            </h2>
                            <p class="text-sm text-gray-600 dark:text-gray-400">{{ $bookResults['book']['testament'] }}</p>
                        </div>
                        <div class="text-right">
                            <div class="text-lg font-bold text-bible-blue dark:text-blue-400">{{ $bookResults['verses']->count() }}</div>
                            <div class="text-xs text-gray-500 dark:text-gray-400">matches</div>
                        </div>
                    </div>
                </div>

                <!-- Verses -->
                <div class="p-6">
                    <div class="verseContainer space-y-4" style="font-size: 1.125rem;">
                        @foreach($bookResults['verses'] as $verse)
                            <div class="border-l-4 border-bible-blue dark:border-blue-400 pl-4 py-2 verse-hoverable">
                                <div class="flex items-start justify-between mb-2">
                                    <a href="{{ route('bible.chapter', [$verse['book_id'], $verse['chapter']]) }}"
                                       class="text-sm font-semibold text-bible-blue dark:text-blue-400 hover:text-blue-800 dark:hover:text-blue-300">
                                        {{ $bookResults['book']['short_name'] }} {{ $verse['chapter'] }}:{{ $verse['verse'] }}
                                    </a>
                                </div>
                                <div class="text-gray-800 dark:text-gray-200 leading-relaxed">
                                    {!! $verse['context'] !!}
                                </div>
                            </div>
                        @endforeach
                    </div>
                </div>
            </div>
        @endforeach
    @elseif(!isset($error))
        <div class="bg-white dark:bg-gray-800 rounded-lg shadow-sm p-8 text-center">
            <div class="text-gray-400 dark:text-gray-500 text-6xl mb-4">🔍</div>
            <h2 class="text-xl font-semibold text-gray-900 dark:text-gray-100 mb-2">No results found</h2>
            <p class="text-gray-600 dark:text-gray-400 mb-4">
                No verses were found containing "{{ $searchTerm }}". Try:
            </p>
            <ul class="text-sm text-gray-500 dark:text-gray-400 space-y-1">
                <li>• Checking your spelling</li>
                <li>• Using different keywords</li>
                <li>• Using fewer words</li>
                <li>• Searching for partial words</li>
            </ul>
        </div>
    @endif

    <!-- Search Tips -->
    <div class="bg-blue-50 dark:bg-blue-900/20 rounded-lg p-6">
        <h3 class="text-lg font-semibold text-bible-blue dark:text-blue-400 mb-3">💡 Search Tips</h3>
        <div class="grid md:grid-cols-2 gap-4 text-sm text-gray-700 dark:text-gray-300">
            <div>
                <h4 class="font-semibold mb-2">Text Search Examples:</h4>
                <ul class="space-y-1">
                    <li>• "love" - Find verses about love</li>
                    <li>• "faith hope" - Find verses with both words</li>
                    <li>• "Jesus Christ" - Find exact phrase</li>
                </ul>
            </div>
            <div>
                <h4 class="font-semibold mb-2">Quick Navigation:</h4>
                <ul class="space-y-1">
                    <li>• "Acts 2:38" - Jump to specific verse</li>
                    <li>• "acts2:38" - Also works without spaces</li>
                    <li>• "Genesis 1" - Go to chapter</li>
                    <li>• "gen1" - Abbreviated book names</li>
                </ul>
            </div>
        </div>
        <div class="mt-4 p-3 bg-blue-100 dark:bg-blue-800/50 rounded text-sm">
            <strong>💡 Pro tip:</strong> Try typing a verse reference like "John 3:16" for instant navigation!
        </div>
        <div class="bg-blue-50 dark:bg-blue-800/30 border border-blue-200 dark:border-blue-700 rounded-lg p-4 text-sm text-blue-800 dark:text-blue-300">
            <p class="font-medium mb-2">💡 Search Tips:</p>
            <div class="space-y-1">
                <p><strong>For specific verses:</strong> Try "Acts 2:38", "John 3:16", "genesis1:1", "ps23"</p>
                <p><strong>For verse ranges:</strong> Try "deut 6:1-4", "1cor 13:4-8", "matt5:3-12"</p>
                <p><strong>For text search:</strong> Try "love", "faith", "salvation", "shepherd"</p>
            </div>
        </div>
    </div>
</div>
@endsection

@push('scripts')
<script>
// Font size control functionality for search results
let currentFontSize = parseFloat(localStorage.getItem('bibleFontSize')) || 1.125; // Default 1.125rem (18px)
const minFontSize = 0.875; // 14px
const maxFontSize = 1.875; // 30px
const fontSizeStep = 0.125; // 2px increments

function updateFontSize() {
    const containers = document.querySelectorAll('.verseContainer');
    containers.forEach(container => {
        container.style.fontSize = currentFontSize + 'rem';

        // Update line height proportionally for better readability
        const lineHeight = Math.max(1.5, currentFontSize * 1.4);
        container.style.lineHeight = lineHeight;
    });

    // Save to localStorage
    localStorage.setItem('bibleFontSize', currentFontSize);

    // Update button states
    updateFontButtons();
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
