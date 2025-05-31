@extends('layouts.bible')

@section('title', $currentBook['name'] . ' ' . $chapterNumber . ':' . $verseNumber . ' - Bible Reader')

@section('content')
<div class="space-y-6">
    <!-- Navigation Breadcrumbs -->
    <nav class="text-sm text-gray-600 dark:text-gray-400">
        <a href="{{ route('bible.index') }}" class="hover:text-bible-blue dark:hover:text-blue-400">📖 Bible</a>
        <span class="mx-2">→</span>
        <a href="{{ route('bible.book', $currentBook['osis_id']) }}" class="hover:text-bible-blue dark:hover:text-blue-400">{{ $currentBook['name'] }}</a>
        <span class="mx-2">→</span>
        <a href="{{ route('bible.chapter', [$currentBook['osis_id'], $chapterNumber]) }}" class="hover:text-bible-blue dark:hover:text-blue-400">Chapter {{ $chapterNumber }}</a>
        <span class="mx-2">→</span>
        <span class="font-medium text-gray-900 dark:text-gray-100">Verse {{ $verseNumber }}</span>
    </nav>

    <!-- Verse Header -->
    <div class="bg-white dark:bg-gray-800 rounded-lg shadow-sm p-6">
        <h1 class="text-2xl font-bold text-gray-900 dark:text-gray-100 mb-4">
            {{ $currentBook['name'] }} {{ $chapterNumber }}:{{ $verseNumber }}
        </h1>

        <!-- Main Verse Text -->
        <div class="text-lg leading-relaxed text-gray-900 dark:text-gray-100 mb-6 p-4 bg-gray-50 dark:bg-gray-700 rounded-lg">
            <span class="font-medium text-bible-blue dark:text-blue-400 mr-2">{{ $verseNumber }}</span>
            {{ $verseDetails['verse']['text'] }}
        </div>

        <!-- Enhanced Features (Database Only) -->
        @if($capabilities['reader_type'] === 'database' && count($capabilities['enhanced']) > 0)
            <div class="space-y-6">
                <!-- Strong's Numbers -->
                @if(isset($verseDetails['strongs_data']) && $verseDetails['strongs_data']->isNotEmpty())
                    <div class="border-t pt-6">
                        <h3 class="text-lg font-semibold text-gray-900 dark:text-gray-100 mb-3">
                            🔤 Strong's Concordance
                        </h3>
                        <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-3">
                            @foreach($verseDetails['strongs_data'] as $word)
                                <div class="bg-blue-50 dark:bg-blue-900/20 p-3 rounded-lg">
                                    <div class="font-medium text-bible-blue dark:text-blue-400">
                                        {{ $word->word_text }}
                                    </div>
                                    @if($word->strongs_number)
                                        <div class="text-sm text-gray-600 dark:text-gray-400">
                                            Strong's: <span class="font-mono">{{ $word->strongs_number }}</span>
                                        </div>
                                    @endif
                                    @if($word->lemma)
                                        <div class="text-sm text-gray-600 dark:text-gray-400">
                                            Lemma: {{ $word->lemma }}
                                        </div>
                                    @endif
                                    @if($word->morphology_code)
                                        <div class="text-sm text-gray-600 dark:text-gray-400">
                                            Morphology: {{ $word->morphology_code }}
                                        </div>
                                    @endif
                                </div>
                            @endforeach
                        </div>
                    </div>
                @endif

                <!-- Translator Changes (Italicized text) -->
                @if(isset($verseDetails['translator_changes']) && $verseDetails['translator_changes']->isNotEmpty())
                    <div class="border-t pt-6">
                        <h3 class="text-lg font-semibold text-gray-900 dark:text-gray-100 mb-3">
                            ✒️ Translator Additions
                        </h3>
                        <div class="space-y-2">
                            @foreach($verseDetails['translator_changes'] as $change)
                                <div class="bg-yellow-50 dark:bg-yellow-900/20 p-3 rounded-lg">
                                    <span class="italic font-medium">{{ $change->text_content }}</span>
                                    <span class="text-sm text-gray-600 dark:text-gray-400 ml-2">
                                        ({{ $change->change_type }})
                                    </span>
                                </div>
                            @endforeach
                        </div>
                    </div>
                @endif

                <!-- Divine Names -->
                @if(isset($verseDetails['divine_names']) && $verseDetails['divine_names']->isNotEmpty())
                    <div class="border-t pt-6">
                        <h3 class="text-lg font-semibold text-gray-900 dark:text-gray-100 mb-3">
                            👑 Divine Names
                        </h3>
                        <div class="space-y-2">
                            @foreach($verseDetails['divine_names'] as $name)
                                <div class="bg-purple-50 dark:bg-purple-900/20 p-3 rounded-lg">
                                    <span class="font-bold">{{ $name->displayed_text }}</span>
                                    <span class="text-sm text-gray-600 dark:text-gray-400 ml-2">
                                        (Original: {{ $name->original_name }})
                                    </span>
                                </div>
                            @endforeach
                        </div>
                    </div>
                @endif

                <!-- Study Notes -->
                @if(isset($verseDetails['study_notes']) && $verseDetails['study_notes']->isNotEmpty())
                    <div class="border-t pt-6">
                        <h3 class="text-lg font-semibold text-gray-900 dark:text-gray-100 mb-3">
                            📚 Study Notes
                        </h3>
                        <div class="space-y-3">
                            @foreach($verseDetails['study_notes'] as $note)
                                <div class="bg-green-50 dark:bg-green-900/20 p-4 rounded-lg">
                                    <div class="text-sm font-medium text-green-800 dark:text-green-200 mb-1">
                                        {{ ucfirst($note->note_type) }}
                                    </div>
                                    <div class="text-gray-700 dark:text-gray-300">
                                        {{ $note->note_text }}
                                    </div>
                                </div>
                            @endforeach
                        </div>
                    </div>
                @endif
            </div>
        @endif

        <!-- Reader Type Information -->
        <div class="border-t pt-6 mt-6">
            <div class="text-sm text-gray-500 dark:text-gray-400">
                <span class="font-medium">Reader:</span>
                {{ $capabilities['reader_type'] === 'database' ? '🗄️ Database' : '📄 XML' }}
                @if($capabilities['reader_type'] === 'database')
                    (Enhanced features available)
                @endif
            </div>
        </div>
    </div>

    <!-- Navigation -->
    <div class="flex justify-between items-center bg-white dark:bg-gray-800 rounded-lg shadow-sm p-4">
        <div>
            @if($verseNumber > 1)
                <a href="{{ route('bible.verse', [$currentBook['osis_id'], $chapterNumber, $verseNumber - 1]) }}"
                   class="bg-bible-blue text-white px-4 py-2 rounded-lg hover:bg-blue-700 transition-colors">
                    ← Previous Verse
                </a>
            @endif
        </div>

        <a href="{{ route('bible.chapter', [$currentBook['osis_id'], $chapterNumber]) }}"
           class="bg-gray-600 text-white px-4 py-2 rounded-lg hover:bg-gray-700 transition-colors">
            Back to Chapter
        </a>

        <div>
            <a href="{{ route('bible.verse', [$currentBook['osis_id'], $chapterNumber, $verseNumber + 1]) }}"
               class="bg-bible-blue text-white px-4 py-2 rounded-lg hover:bg-blue-700 transition-colors">
                Next Verse →
            </a>
        </div>
    </div>
</div>
@endsection
