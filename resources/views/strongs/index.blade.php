@extends('layouts.bible')

@section('title', 'Strong\'s Concordance & Lexicon')

@section('content')
<div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-8">
    <!-- Header -->
    <div class="bg-gradient-to-r from-blue-600 to-indigo-700 rounded-lg p-6 mb-8 text-white">
        <div class="flex flex-col md:flex-row md:items-center md:justify-between">
            <div>
                <h1 class="text-3xl font-bold mb-2">🔤 Strong's Concordance</h1>
                <p class="text-blue-100">Explore Hebrew and Greek words with detailed definitions, etymology, and Biblical usage</p>
            </div>
            <div class="mt-4 md:mt-0 flex flex-col sm:flex-row gap-2">
                <a href="{{ route('strongs.stats') }}" class="bg-white/20 hover:bg-white/30 px-4 py-2 rounded-lg transition-colors text-center">
                    📊 Statistics
                </a>
                <a href="{{ route('strongs.compare') }}" class="bg-white/20 hover:bg-white/30 px-4 py-2 rounded-lg transition-colors text-center">
                    🔍 Compare Words
                </a>
                <button onclick="getRandomWord()" class="bg-white/20 hover:bg-white/30 px-4 py-2 rounded-lg transition-colors">
                    🎲 Random Word
                </button>
            </div>
        </div>
    </div>

    <!-- Search & Filters -->
    <div class="bg-white dark:bg-gray-800 rounded-lg shadow-md p-6 mb-6">
        <form method="GET" class="space-y-4">
            <!-- Search Bar -->
            <div class="relative">
                <div class="absolute inset-y-0 left-0 pl-3 flex items-center pointer-events-none">
                    <svg class="h-5 w-5 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"/>
                    </svg>
                </div>
                <input
                    type="text"
                    name="search"
                    value="{{ $searchQuery }}"
                    placeholder="Search Strong's numbers, definitions, or transliterations..."
                    class="block w-full pl-10 pr-3 py-3 border border-gray-300 dark:border-gray-600 rounded-md leading-5 bg-white dark:bg-gray-700 text-gray-900 dark:text-gray-100 placeholder-gray-500 focus:outline-none focus:placeholder-gray-400 focus:ring-1 focus:ring-blue-500 focus:border-blue-500"
                >
            </div>

            <!-- Filters Row -->
            <div class="grid grid-cols-1 md:grid-cols-4 gap-4">
                <!-- Language Filter -->
                <div>
                    <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Language</label>
                    <select name="language" class="block w-full border border-gray-300 dark:border-gray-600 rounded-md px-3 py-2 bg-white dark:bg-gray-700 text-gray-900 dark:text-gray-100">
                        <option value="all" {{ $language === 'all' ? 'selected' : '' }}>All Languages</option>
                        @foreach($languages as $lang)
                            <option value="{{ $lang }}" {{ $language === $lang ? 'selected' : '' }}>
                                {{ $lang === 'Hebrew' ? '🇮🇱' : ($lang === 'Greek' ? '🇬🇷' : '📜') }} {{ $lang }}
                            </option>
                        @endforeach
                    </select>
                </div>

                <!-- Part of Speech Filter -->
                <div>
                    <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Part of Speech</label>
                    <select name="pos" class="block w-full border border-gray-300 dark:border-gray-600 rounded-md px-3 py-2 bg-white dark:bg-gray-700 text-gray-900 dark:text-gray-100">
                        <option value="all" {{ $partOfSpeech === 'all' ? 'selected' : '' }}>All Types</option>
                        @foreach($partsOfSpeech as $pos)
                            <option value="{{ $pos }}" {{ $partOfSpeech === $pos ? 'selected' : '' }}>{{ ucfirst($pos) }}</option>
                        @endforeach
                    </select>
                </div>

                <!-- Results Limit -->
                <div>
                    <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Results</label>
                    <select name="limit" class="block w-full border border-gray-300 dark:border-gray-600 rounded-md px-3 py-2 bg-white dark:bg-gray-700 text-gray-900 dark:text-gray-100">
                        <option value="25" {{ $limit === 25 ? 'selected' : '' }}>25</option>
                        <option value="50" {{ $limit === 50 ? 'selected' : '' }}>50</option>
                        <option value="100" {{ $limit === 100 ? 'selected' : '' }}>100</option>
                        <option value="200" {{ $limit === 200 ? 'selected' : '' }}>200</option>
                    </select>
                </div>

                <!-- Search Button -->
                <div class="flex items-end">
                    <button type="submit" class="w-full bg-blue-600 hover:bg-blue-700 text-white font-medium py-2 px-4 rounded-md transition-colors">
                        Search
                    </button>
                </div>
            </div>
        </form>
    </div>

    <!-- Quick Start Guide -->
    @if(empty($searchQuery) && $language === 'all' && $partOfSpeech === 'all')
    <div class="bg-gradient-to-r from-green-50 to-blue-50 dark:from-gray-800 dark:to-gray-800 rounded-lg p-6 mb-6 border border-green-200 dark:border-gray-600">
        <h3 class="text-lg font-semibold text-green-800 dark:text-green-300 mb-3">🚀 Quick Start Guide</h3>
        <div class="grid md:grid-cols-2 gap-4 text-sm text-gray-700 dark:text-gray-200">
            <div>
                <h4 class="font-medium mb-2">Try these popular searches:</h4>
                <ul class="space-y-1">
                    <li>• <a href="?search=love" class="text-blue-600 dark:text-blue-400 hover:underline">love</a> - Find words about love</li>
                    <li>• <a href="?search=G2316" class="text-blue-600 dark:text-blue-400 hover:underline">G2316</a> - Greek word for "God"</li>
                    <li>• <a href="?search=H03068" class="text-blue-600 dark:text-blue-400 hover:underline">H03068</a> - Hebrew "YHWH"</li>
                    <li>• <a href="?search=theos" class="text-blue-600 dark:text-blue-400 hover:underline">theos</a> - Search by transliteration</li>
                </ul>
            </div>
            <div>
                <h4 class="font-medium mb-2">Filter by language:</h4>
                <ul class="space-y-1">
                    <li>• <a href="?language=Hebrew" class="text-blue-600 dark:text-blue-400 hover:underline">🇮🇱 Hebrew words</a></li>
                    <li>• <a href="?language=Greek" class="text-blue-600 dark:text-blue-400 hover:underline">🇬🇷 Greek words</a></li>
                    <li>• <a href="?pos=verb" class="text-blue-600 dark:text-blue-400 hover:underline">⚡ Verbs only</a></li>
                    <li>• <a href="?pos=noun" class="text-blue-600 dark:text-blue-400 hover:underline">📋 Nouns only</a></li>
                </ul>
            </div>
        </div>
    </div>
    @endif

    <!-- Results -->
    @if($lexiconEntries->count() > 0)
        <div class="bg-white dark:bg-gray-800 rounded-lg shadow-md overflow-hidden">
            <!-- Results Header -->
            <div class="bg-gray-50 dark:bg-gray-700 px-6 py-3 border-b border-gray-200 dark:border-gray-600">
                <div class="flex justify-between items-center">
                    <h2 class="text-lg font-semibold text-gray-900 dark:text-gray-100">
                        Found {{ $lexiconEntries->count() }} entries
                        @if(!empty($searchQuery))
                            for "{{ $searchQuery }}"
                        @endif
                    </h2>
                    <div class="flex gap-2">
                        <a href="{{ route('strongs.export', ['format' => 'json']) }}" class="text-sm bg-blue-100 dark:bg-blue-900 text-blue-800 dark:text-blue-200 px-3 py-1 rounded-md hover:bg-blue-200 dark:hover:bg-blue-800 transition-colors">
                            📁 Export JSON
                        </a>
                        <a href="{{ route('strongs.export', ['format' => 'csv']) }}" class="text-sm bg-green-100 dark:bg-green-900 text-green-800 dark:text-green-200 px-3 py-1 rounded-md hover:bg-green-200 dark:hover:bg-green-800 transition-colors">
                            📊 Export CSV
                        </a>
                    </div>
                </div>
            </div>

            <!-- Lexicon Entries -->
            <div class="divide-y divide-gray-200 dark:divide-gray-600">
                @foreach($lexiconEntries as $entry)
                    <div class="p-6 hover:bg-gray-50 dark:hover:bg-gray-750 transition-colors">
                        <div class="flex flex-col lg:flex-row lg:items-start lg:justify-between gap-4">
                            <!-- Main Content -->
                            <div class="flex-1">
                                <!-- Header Row -->
                                <div class="flex items-center gap-3 mb-3">
                                    <a href="{{ route('strongs.show', $entry->strongs_number) }}" class="text-xl font-bold text-blue-600 dark:text-blue-400 hover:underline">
                                        {{ $entry->strongs_number }}
                                    </a>
                                    <span class="text-lg">{{ $entry->language_emoji }}</span>
                                    <span class="text-sm bg-gray-100 dark:bg-gray-700 text-gray-600 dark:text-gray-400 px-2 py-1 rounded">
                                        {{ $entry->language }}
                                    </span>
                                    @if($entry->part_of_speech)
                                        <span class="text-sm bg-blue-100 dark:bg-blue-900 text-blue-800 dark:text-blue-200 px-2 py-1 rounded">
                                            {{ $entry->part_of_speech_emoji }} {{ $entry->part_of_speech }}
                                        </span>
                                    @endif
                                    @if($entry->occurrence_count > 0)
                                        <span class="text-sm bg-green-100 dark:bg-green-900 text-green-800 dark:text-green-200 px-2 py-1 rounded">
                                            {{ $entry->occurrence_count }}x in Bible
                                        </span>
                                    @endif
                                </div>

                                <!-- Original Word & Transliteration -->
                                <div class="mb-3">
                                    @if($entry->original_word)
                                        <div class="text-2xl font-bold text-gray-900 dark:text-gray-100 mb-1" style="font-family: 'Noto Sans Hebrew', 'Noto Sans', serif;">
                                            {{ $entry->original_word }}
                                        </div>
                                    @endif
                                    @if($entry->transliteration)
                                        <div class="text-lg text-gray-600 dark:text-gray-400 italic">
                                            {{ $entry->transliteration }}
                                            @if($entry->pronunciation)
                                                <span class="text-sm ml-2">[{{ $entry->pronunciation }}]</span>
                                            @endif
                                        </div>
                                    @endif
                                </div>

                                <!-- Definition -->
                                <div class="mb-3">
                                    <div class="text-lg font-medium text-gray-900 dark:text-gray-100 mb-1">
                                        {{ $entry->short_definition }}
                                    </div>
                                    @if($entry->detailed_definition && $entry->detailed_definition !== $entry->short_definition)
                                        <div class="text-gray-700 dark:text-gray-300">
                                            {{ Str::limit($entry->detailed_definition, 200) }}
                                        </div>
                                    @endif
                                </div>

                                <!-- Etymology (if available) -->
                                @if($entry->etymology)
                                    <div class="text-sm text-gray-600 dark:text-gray-400 italic">
                                        <strong>Etymology:</strong> {{ Str::limit($entry->etymology, 150) }}
                                    </div>
                                @endif
                            </div>

                            <!-- Action Buttons -->
                            <div class="flex flex-row lg:flex-col gap-2 lg:w-40">
                                <a href="{{ route('strongs.show', $entry->strongs_number) }}" class="flex-1 lg:flex-none bg-blue-600 hover:bg-blue-700 text-white text-center px-3 py-2 rounded-md text-sm font-medium transition-colors">
                                    📚 Full Study
                                </a>
                                <button onclick="loadVerses('{{ $entry->strongs_number }}')" class="flex-1 lg:flex-none bg-green-600 hover:bg-green-700 text-white text-center px-3 py-2 rounded-md text-sm font-medium transition-colors">
                                    📖 Show Verses
                                </button>
                                @if($entry->occurrence_count > 1)
                                    <a href="{{ route('strongs.family', $entry->strongs_number) }}" class="flex-1 lg:flex-none bg-purple-600 hover:bg-purple-700 text-white text-center px-3 py-2 rounded-md text-sm font-medium transition-colors">
                                        🌳 Word Family
                                    </a>
                                @endif
                            </div>
                        </div>
                    </div>
                @endforeach
            </div>
        </div>
    @else
        <!-- No Results -->
        <div class="bg-white dark:bg-gray-800 rounded-lg shadow-md p-8 text-center">
            <div class="text-6xl mb-4">🔍</div>
            <h3 class="text-xl font-semibold text-gray-900 dark:text-gray-100 mb-2">No entries found</h3>
            <p class="text-gray-600 dark:text-gray-400 mb-4">
                @if(!empty($searchQuery))
                    No Strong's numbers match your search "{{ $searchQuery }}".
                @else
                    No entries match your current filters.
                @endif
            </p>
            <a href="{{ route('strongs.index') }}" class="inline-block bg-blue-600 hover:bg-blue-700 text-white px-4 py-2 rounded-md transition-colors">
                Clear Filters
            </a>
        </div>
    @endif
</div>

<!-- Verses Modal -->
<div id="versesModal" class="fixed inset-0 bg-black bg-opacity-50 hidden z-50">
    <div class="flex items-center justify-center min-h-screen p-4">
        <div class="bg-white dark:bg-gray-800 rounded-lg max-w-4xl w-full max-h-[80vh] overflow-hidden">
            <div class="p-6 border-b border-gray-200 dark:border-gray-600">
                <div class="flex justify-between items-center">
                    <h3 id="versesModalTitle" class="text-lg font-semibold text-gray-900 dark:text-gray-100">Loading...</h3>
                    <button onclick="closeVersesModal()" class="text-gray-500 hover:text-gray-700 dark:text-gray-400 dark:hover:text-gray-200">
                        <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/>
                        </svg>
                    </button>
                </div>
            </div>
            <div id="versesModalContent" class="p-6 overflow-y-auto max-h-96">
                <div class="text-center py-8">
                    <div class="animate-spin inline-block w-8 h-8 border-4 border-current border-t-transparent text-blue-600 rounded-full"></div>
                    <p class="mt-2 text-gray-600 dark:text-gray-400">Loading verses...</p>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
// Load verses for a Strong's number
async function loadVerses(strongsNumber) {
    const modal = document.getElementById('versesModal');
    const title = document.getElementById('versesModalTitle');
    const content = document.getElementById('versesModalContent');

    title.textContent = `Verses containing ${strongsNumber}`;
    modal.classList.remove('hidden');

    try {
        const response = await fetch(`/strongs/${strongsNumber}/verses?limit=25`);
        const data = await response.json();

        if (data.verses.length === 0) {
            content.innerHTML = '<p class="text-center text-gray-600 dark:text-gray-400">No verses found for this Strong\'s number.</p>';
            return;
        }

        const versesHtml = data.verses.map(verse => `
            <div class="border-b border-gray-200 dark:border-gray-600 pb-4 mb-4 last:border-b-0">
                <div class="flex justify-between items-start mb-2">
                    <a href="/${verse.osis_id.replace(/\./g, '/')}" class="text-blue-600 dark:text-blue-400 hover:underline font-medium">
                        ${verse.reference}
                    </a>
                    <span class="text-sm bg-blue-100 dark:bg-blue-900 text-blue-800 dark:text-blue-200 px-2 py-1 rounded">
                        "${verse.word_text}"
                    </span>
                </div>
                <p class="text-gray-700 dark:text-gray-300">${verse.text}</p>
            </div>
        `).join('');

        content.innerHTML = `
            <div class="mb-4 text-sm text-gray-600 dark:text-gray-400">
                Showing ${data.verses.length} verses containing <strong>${strongsNumber}</strong>
            </div>
            ${versesHtml}
        `;
    } catch (error) {
        content.innerHTML = '<p class="text-center text-red-600 dark:text-red-400">Error loading verses. Please try again.</p>';
    }
}

function closeVersesModal() {
    document.getElementById('versesModal').classList.add('hidden');
}

// Get random word
async function getRandomWord() {
    try {
        const response = await fetch('/strongs/random');
        const data = await response.json();

        if (data.strongs_number) {
            window.location.href = data.url;
        }
    } catch (error) {
        console.error('Error getting random word:', error);
    }
}

// Close modal on escape key
document.addEventListener('keydown', function(e) {
    if (e.key === 'Escape') {
        closeVersesModal();
    }
});

// Close modal on outside click
document.getElementById('versesModal').addEventListener('click', function(e) {
    if (e.target === this) {
        closeVersesModal();
    }
});
</script>
@endsection
