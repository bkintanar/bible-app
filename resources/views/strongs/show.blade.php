@extends('layouts.bible')

@section('title', $lexicon->strongs_number . ' - ' . $lexicon->short_definition)

@section('content')
<div class="space-y-4">
    <!-- Mobile-First Breadcrumb Navigation -->
    <div class="flex items-center space-x-2 text-xs sm:text-sm text-gray-600 dark:text-gray-400 overflow-x-auto hide-scrollbar pb-2">
        <a href="{{ route('bible.index') }}" class="whitespace-nowrap hover:text-blue-600 dark:hover:text-blue-400 touch-friendly">📖 Bible</a>
        <span class="text-gray-400">›</span>
        <a href="{{ route('strongs.index') }}" class="whitespace-nowrap hover:text-blue-600 dark:hover:text-blue-400 touch-friendly">🔤 Strong's</a>
        <span class="text-gray-400">›</span>
        <span class="text-gray-900 dark:text-gray-100 font-medium">{{ $lexicon->strongs_number }}</span>
    </div>

    <!-- Mobile-First Word Header Card -->
    <div class="ios-card rounded-2xl shadow-lg p-4 sm:p-6">
        <!-- Strong's Number & Language -->
        <div class="flex flex-wrap items-center gap-2 mb-4">
            <h1 class="text-2xl sm:text-3xl font-bold text-blue-600 dark:text-blue-400">{{ $lexicon->strongs_number }}</h1>
            <span class="text-xl sm:text-2xl">{{ $lexicon->language_emoji }}</span>
            <span class="bg-gray-100 dark:bg-gray-700 text-gray-700 dark:text-gray-300 px-2 py-1 rounded-lg text-xs sm:text-sm">
                {{ $lexicon->language }}
            </span>
            @if($lexicon->part_of_speech)
                <span class="bg-blue-100 dark:bg-blue-900 text-blue-800 dark:text-blue-200 px-2 py-1 rounded-lg text-xs sm:text-sm">
                    {{ $lexicon->part_of_speech_emoji }} {{ $lexicon->part_of_speech }}
                </span>
            @endif
        </div>

        <!-- Original Word & Transliteration -->
        @if($lexicon->original_word)
            <div class="mb-4">
                <div class="text-2xl sm:text-4xl font-bold text-gray-900 dark:text-gray-100 mb-2" style="font-family: 'Noto Sans Hebrew', 'Noto Sans', serif;">
                    {{ $lexicon->original_word }}
                </div>
                @if($lexicon->transliteration)
                    <div class="text-lg sm:text-xl text-gray-600 dark:text-gray-400 italic">
                        {{ $lexicon->transliteration }}
                        @if($lexicon->pronunciation)
                            <span class="text-sm sm:text-base ml-2">[{{ $lexicon->pronunciation }}]</span>
                        @endif
                    </div>
                @endif
            </div>
        @endif

        <!-- Quick Actions Grid -->
        <div class="grid grid-cols-2 sm:grid-cols-4 gap-2 sm:gap-3 mb-4">
            <a href="{{ route('bible.search') }}?q={{ $lexicon->strongs_number }}" class="touch-friendly bg-blue-500 hover:bg-blue-600 text-white text-center px-3 py-2 rounded-xl transition-colors text-xs sm:text-sm font-medium">
                🔍 Search Bible
            </a>
            @if($relatedWords->count() > 0)
                <button onclick="scrollToSection('related-words')" class="touch-friendly bg-purple-500 hover:bg-purple-600 text-white text-center px-3 py-2 rounded-xl transition-colors text-xs sm:text-sm font-medium">
                    🌳 Related
                </button>
            @endif
            <button onclick="scrollToSection('sample-verses')" class="touch-friendly bg-green-500 hover:bg-green-600 text-white text-center px-3 py-2 rounded-xl transition-colors text-xs sm:text-sm font-medium">
                📖 Verses
            </button>
            <a href="{{ route('strongs.export', ['numbers' => $lexicon->strongs_number, 'format' => 'json']) }}" class="touch-friendly bg-gray-500 hover:bg-gray-600 text-white text-center px-3 py-2 rounded-xl transition-colors text-xs sm:text-sm font-medium">
                💾 Export
            </a>
        </div>
    </div>

    <!-- Definition Card -->
    <div class="ios-card rounded-2xl shadow-sm p-4 sm:p-6">
        <h2 class="text-lg sm:text-xl font-semibold text-gray-900 dark:text-gray-100 mb-3 flex items-center">
            📚 Definition
        </h2>
        <div class="space-y-3">
            <div class="text-base sm:text-lg font-medium text-gray-900 dark:text-gray-100">
                {{ $lexicon->short_definition }}
            </div>
            @if($lexicon->detailed_definition && $lexicon->detailed_definition !== $lexicon->short_definition)
                <div class="text-sm sm:text-base text-gray-700 dark:text-gray-300 leading-relaxed">
                    {{ $lexicon->detailed_definition }}
                </div>
            @endif
        </div>

        <!-- Etymology -->
        @if($lexicon->etymology)
            <div class="mt-4 pt-4 border-t border-gray-200 dark:border-gray-600">
                <h3 class="text-sm sm:text-base font-semibold text-gray-900 dark:text-gray-100 mb-2">Etymology</h3>
                <div class="text-sm sm:text-base text-gray-700 dark:text-gray-300 italic">
                    {{ $lexicon->etymology }}
                </div>
            </div>
        @endif
    </div>

    <!-- Usage Statistics Card -->
    <div class="ios-card rounded-2xl shadow-sm p-4 sm:p-6">
        <h2 class="text-lg sm:text-xl font-semibold text-gray-900 dark:text-gray-100 mb-4 flex items-center">
            📊 Usage Statistics
        </h2>
        <div class="grid grid-cols-1 sm:grid-cols-3 gap-4">
            @if($lexicon->occurrence_count > 0)
                <div class="text-center p-3 bg-blue-50 dark:bg-blue-900/20 rounded-xl">
                    <div class="text-xl sm:text-2xl font-bold text-blue-600 dark:text-blue-400">{{ $lexicon->occurrence_count }}</div>
                    <div class="text-xs sm:text-sm text-blue-600 dark:text-blue-400">Total Occurrences</div>
                </div>
            @endif
            @if($usageStats && isset($usageStats['by_testament']))
                <div class="text-center p-3 bg-amber-50 dark:bg-amber-900/20 rounded-xl">
                    <div class="text-xl sm:text-2xl font-bold text-amber-600 dark:text-amber-400">{{ $usageStats['by_testament']['OT'] ?? 0 }}</div>
                    <div class="text-xs sm:text-sm text-amber-600 dark:text-amber-400">Old Testament</div>
                </div>
                <div class="text-center p-3 bg-green-50 dark:bg-green-900/20 rounded-xl">
                    <div class="text-xl sm:text-2xl font-bold text-green-600 dark:text-green-400">{{ $usageStats['by_testament']['NT'] ?? 0 }}</div>
                    <div class="text-xs sm:text-sm text-green-600 dark:text-green-400">New Testament</div>
                </div>
            @endif
        </div>
    </div>

    <!-- Usage by Books -->
    @if($usageStats && isset($usageStats['by_book']) && count($usageStats['by_book']) > 0)
        <div class="ios-card rounded-2xl shadow-sm p-4 sm:p-6">
            <h2 class="text-lg sm:text-xl font-semibold text-gray-900 dark:text-gray-100 mb-4 flex items-center">
                📚 Usage by Book
            </h2>
            <div class="grid grid-cols-2 sm:grid-cols-3 lg:grid-cols-4 gap-2">
                @foreach(array_slice($usageStats['by_book'], 0, 12, true) as $book => $count)
                    <div class="flex justify-between items-center bg-gray-50 dark:bg-gray-700 rounded-lg px-3 py-2">
                        <span class="text-xs sm:text-sm text-gray-900 dark:text-gray-100 truncate">{{ $book }}</span>
                        <span class="text-xs sm:text-sm font-semibold text-blue-600 dark:text-blue-400 ml-2">{{ $count }}</span>
                    </div>
                @endforeach
            </div>
            @if(count($usageStats['by_book']) > 12)
                <div class="mt-3 text-center">
                    <button onclick="toggleAllBooks()" id="toggle-books" class="text-blue-600 dark:text-blue-400 hover:underline text-sm">
                        Show {{ count($usageStats['by_book']) - 12 }} more books
                    </button>
                </div>
            @endif
        </div>
    @endif

    <!-- Sample Verses -->
    @if($sampleVerses && $sampleVerses->count() > 0)
        <div id="sample-verses" class="ios-card rounded-2xl shadow-sm p-4 sm:p-6">
            <h2 class="text-lg sm:text-xl font-semibold text-gray-900 dark:text-gray-100 mb-4 flex items-center">
                📖 Sample Verses
            </h2>
            <div class="space-y-4">
                @foreach($sampleVerses->take(5) as $verse)
                    <div class="border-l-4 border-blue-500 pl-4 bg-gray-50 dark:bg-gray-700/50 rounded-r-lg p-3">
                        <div class="flex flex-col sm:flex-row sm:items-start sm:justify-between mb-2">
                            <a href="/{{ str_replace('.', '/', $verse->osis_id) }}" class="text-blue-600 dark:text-blue-400 hover:underline font-medium text-sm sm:text-base">
                                {{ $verse->reference }}
                            </a>
                            @if($verse->word_text)
                                <span class="text-xs bg-blue-100 dark:bg-blue-900 text-blue-800 dark:text-blue-200 px-2 py-1 rounded mt-1 sm:mt-0">
                                    "{{ $verse->word_text }}"
                                </span>
                            @endif
                        </div>
                        <p class="text-sm sm:text-base text-gray-700 dark:text-gray-300 leading-relaxed">{{ $verse->text }}</p>
                        @if($verse->morphology)
                            <div class="mt-2 text-xs text-gray-500 dark:text-gray-400">
                                Morphology: {{ $verse->morphology }}
                            </div>
                        @endif
                    </div>
                @endforeach
            </div>
            @if($lexicon->occurrence_count > $sampleVerses->count())
                <div class="mt-4 text-center">
                    <a href="{{ route('bible.search') }}?q={{ $lexicon->strongs_number }}" class="touch-friendly bg-blue-500 hover:bg-blue-600 text-white px-4 py-3 rounded-xl transition-colors text-sm font-medium">
                        View All {{ $lexicon->occurrence_count }} Verses
                    </a>
                </div>
            @endif
        </div>
    @endif

    <!-- Related Words -->
    @if($relatedWords->count() > 0)
        <div id="related-words" class="ios-card rounded-2xl shadow-sm p-4 sm:p-6">
            <h2 class="text-lg sm:text-xl font-semibold text-gray-900 dark:text-gray-100 mb-4 flex items-center">
                🌳 Related Words
            </h2>
            <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
                @foreach($relatedWords->take(6) as $relation)
                    <div class="border border-gray-200 dark:border-gray-600 rounded-xl p-3 hover:shadow-md transition-shadow">
                        <div class="flex items-center justify-between mb-2">
                            <span class="text-xs font-medium text-gray-600 dark:text-gray-400">
                                {{ $relation->relationship_type_emoji }} {{ ucfirst($relation->relationship_type) }}
                            </span>
                        </div>
                        <a href="{{ route('strongs.show', $relation->related_strongs_number) }}" class="block">
                            <div class="text-sm sm:text-base font-semibold text-blue-600 dark:text-blue-400 hover:underline">
                                {{ $relation->related_strongs_number }}
                            </div>
                            @if($relation->relatedLexicon)
                                <div class="text-xs sm:text-sm text-gray-700 dark:text-gray-300 mt-1">
                                    {{ $relation->relatedLexicon->short_definition }}
                                </div>
                            @endif
                        </a>
                    </div>
                @endforeach
            </div>
        </div>
    @endif

    <!-- Morphological Analysis -->
    @if($morphologyAnalysis && isset($morphologyAnalysis['forms']) && count($morphologyAnalysis['forms']) > 0)
        <div class="ios-card rounded-2xl shadow-sm p-4 sm:p-6">
            <h2 class="text-lg sm:text-xl font-semibold text-gray-900 dark:text-gray-100 mb-4 flex items-center">
                🔤 Morphological Forms
            </h2>
            <div class="grid grid-cols-2 sm:grid-cols-3 gap-3">
                @foreach(array_slice($morphologyAnalysis['forms'], 0, 6) as $form => $count)
                    <div class="bg-gray-50 dark:bg-gray-700 rounded-xl p-3 text-center">
                        <div class="text-sm sm:text-base font-medium text-gray-900 dark:text-gray-100">{{ $form }}</div>
                        <div class="text-xs text-gray-600 dark:text-gray-400">{{ $count }} times</div>
                    </div>
                @endforeach
            </div>
        </div>
    @endif
</div>

<script>
function scrollToSection(sectionId) {
    const section = document.getElementById(sectionId);
    if (section) {
        section.scrollIntoView({ behavior: 'smooth', block: 'start' });
    }
}

function toggleAllBooks() {
    // Implementation for showing/hiding all books
    const button = document.getElementById('toggle-books');
    // This would need additional implementation to show hidden books
}

// Smooth scroll for better mobile experience
document.addEventListener('DOMContentLoaded', function() {
    // Add smooth scrolling to all anchor links
    document.querySelectorAll('a[href^="#"]').forEach(anchor => {
        anchor.addEventListener('click', function (e) {
            e.preventDefault();
            const target = document.querySelector(this.getAttribute('href'));
            if (target) {
                target.scrollIntoView({
                    behavior: 'smooth',
                    block: 'start'
                });
            }
        });
    });
});
</script>
@endsection
