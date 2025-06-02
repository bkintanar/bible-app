@extends('layouts.bible')

@section('title', $currentBook['short_name'] . ' - Bible Reader')

@section('content')
<div class="space-y-4">
    <!-- Mobile-Optimized Chapters Grid -->
    <div class="ios-card rounded-2xl shadow-sm p-4">
        <h2 class="text-base font-semibold text-gray-900 dark:text-gray-100 mb-3 flex items-center">
            📚 Chapters
        </h2>

        <!-- Mobile: 4 columns, Desktop: 6+ -->
        <div class="grid grid-cols-4 sm:grid-cols-6 md:grid-cols-8 gap-2">
            @foreach($chapters as $chapter)
                <a href="{{ route('bible.chapter', [$currentBook['osis_id'], $chapter['chapter_number']]) }}"
                   class="touch-friendly flex items-center justify-center aspect-square bg-white dark:bg-gray-800 hover:bg-blue-500 dark:hover:bg-blue-600 hover:text-white text-gray-900 dark:text-gray-100 transition-all duration-200 font-semibold text-sm border border-gray-200 dark:border-gray-700 hover:border-blue-300 dark:hover:border-blue-400 shadow-sm hover:shadow-md active:scale-95">
                    {{ $chapter['chapter_number'] }}
                </a>
            @endforeach
        </div>
    </div>

    <!-- Popular Chapters (Simplified) -->
    @if(in_array($currentBook['osis_id'], ['Ps', 'Prov', 'John', 'Rom', '1Cor']) && !empty($popularChapters))
        <div class="ios-card rounded-2xl shadow-sm p-4">
            <h3 class="text-base font-semibold text-gray-900 dark:text-gray-100 mb-3 flex items-center">
                🌟 Popular
            </h3>
            <div class="grid grid-cols-2 sm:grid-cols-4 gap-2">
                @foreach($popularChapters as $chapter)
                    @if($chapter <= $chapters->count())
                        <a href="{{ route('bible.chapter', [$currentBook['osis_id'], $chapter]) }}"
                           class="touch-friendly flex items-center justify-center p-2 bg-gradient-to-r from-yellow-100 to-orange-100 dark:from-yellow-900/20 dark:to-orange-900/20 hover:from-yellow-200 hover:to-orange-200 dark:hover:from-yellow-800/30 dark:hover:to-orange-800/30 text-yellow-800 dark:text-yellow-300 rounded-lg font-medium text-xs transition-colors">
                            Ch {{ $chapter }}
                        </a>
                    @endif
                @endforeach
            </div>
        </div>
    @endif
</div>
@endsection
