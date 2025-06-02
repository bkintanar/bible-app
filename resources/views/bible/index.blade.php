@extends('layouts.bible')

@section('title', $bibleInfo['title'] . ' - Bible Reader')

@section('content')
<div class="space-y-4">
    <!-- Mobile-First Bible Info Header -->
    <div class="ios-card rounded-2xl shadow-sm p-4 sm:p-6">
        <h1 class="text-2xl sm:text-3xl font-bold text-gray-900 dark:text-gray-100 mb-2">{{ $bibleInfo['title'] }}</h1>
        @if($bibleInfo['description'])
            <p class="text-gray-600 dark:text-gray-400 text-sm sm:text-base mb-3">{{ $bibleInfo['description'] }}</p>
        @endif
        <div class="flex flex-wrap gap-2 text-xs sm:text-sm text-gray-500 dark:text-gray-400">
            @if($bibleInfo['publisher'])
                <span class="bg-gray-100 dark:bg-gray-700 px-2 py-1 rounded-full">📚 {{ $bibleInfo['publisher'] }}</span>
            @endif
            @if($bibleInfo['language'])
                <span class="bg-gray-100 dark:bg-gray-700 px-2 py-1 rounded-full">🌍 {{ $bibleInfo['language'] }}</span>
            @endif
        </div>
    </div>

    <!-- Strong's Concordance Feature -->
    <div class="bg-gradient-to-br from-indigo-500 to-purple-600 rounded-2xl shadow-lg p-4 sm:p-6 text-white">
        <div class="flex flex-col space-y-4">
            <div>
                <h2 class="text-xl sm:text-2xl font-bold mb-2">🔤 Strong's Concordance</h2>
                <p class="text-indigo-100 text-sm sm:text-base mb-3">Explore Hebrew and Greek words with detailed definitions, etymology, and Biblical usage</p>
                <div class="flex flex-wrap gap-2 text-xs">
                    <span class="bg-white/20 px-2 py-1 rounded-full">📚 Comprehensive Lexicon</span>
                    <span class="bg-white/20 px-2 py-1 rounded-full">🔍 Word Studies</span>
                    <span class="bg-white/20 px-2 py-1 rounded-full">🌳 Word Relationships</span>
                </div>
            </div>
            <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
                <a href="{{ route('strongs.index') }}" class="touch-friendly bg-white/20 hover:bg-white/30 px-4 py-3 rounded-xl transition-colors text-center font-medium text-sm">
                    🔤 Browse Lexicon
                </a>
                <a href="{{ route('strongs.index', ['search' => 'G2316']) }}" class="touch-friendly bg-white text-indigo-600 hover:bg-gray-100 px-4 py-3 rounded-xl transition-colors text-center font-medium text-sm">
                    📖 Try Example
                </a>
            </div>
        </div>
    </div>

    <!-- Testament Sections -->

    <!-- Old Testament -->
    @if($testamentBooks['oldTestament']->isNotEmpty())
    <div class="ios-card rounded-2xl shadow-sm p-4 sm:p-6">
        <div class="flex items-center justify-between mb-4">
            <h2 class="text-xl sm:text-2xl font-bold text-gray-900 dark:text-gray-100 flex items-center">
                <span class="text-yellow-500 mr-2">📜</span>
                Old Testament
            </h2>
            <span class="text-xs sm:text-sm font-medium text-gray-500 dark:text-gray-400 bg-gray-100 dark:bg-gray-700 px-2 py-1 rounded-full">
                {{ $testamentBooks['oldTestament']->count() }} books
            </span>
        </div>

        <!-- Mobile Grid: 2 columns, Tablet: 3, Desktop: 4+ -->
        <div class="grid grid-cols-2 sm:grid-cols-3 lg:grid-cols-4 xl:grid-cols-5 gap-2 sm:gap-3">
            @foreach($testamentBooks['oldTestament'] as $book)
                <a href="{{ route('bible.book', $book['osis_id']) }}"
                class="touch-friendly block p-3 sm:p-4 bg-gray-50 dark:bg-gray-700 hover:bg-blue-500 dark:hover:bg-blue-600 hover:text-white rounded-xl transition-all duration-200 group">
                    <div class="font-semibold text-xs sm:text-sm text-gray-900 dark:text-gray-100 group-hover:text-white mb-1">
                        {{ $book['short_name'] }}
                    </div>
                    <div class="text-xs opacity-75 group-hover:opacity-100 text-gray-600 dark:text-gray-300 group-hover:text-white leading-tight">
                        {{ $book['name'] }}
                    </div>
                </a>
            @endforeach
        </div>
    </div>
    @endif

    <!-- New Testament (Mobile First) -->
    @if($testamentBooks['newTestament']->isNotEmpty())
    <div class="ios-card rounded-2xl shadow-sm p-4 sm:p-6">
        <div class="flex items-center justify-between mb-4">
            <h2 class="text-xl sm:text-2xl font-bold text-gray-900 dark:text-gray-100 flex items-center">
                <span class="text-yellow-500 mr-2">✝️</span>
                New Testament
            </h2>
            <span class="text-xs sm:text-sm font-medium text-gray-500 dark:text-gray-400 bg-gray-100 dark:bg-gray-700 px-2 py-1 rounded-full">
                {{ $testamentBooks['newTestament']->count() }} books
            </span>
        </div>

        <!-- Mobile Grid: 2 columns, Tablet: 3, Desktop: 4+ -->
        <div class="grid grid-cols-2 sm:grid-cols-3 lg:grid-cols-4 xl:grid-cols-5 gap-2 sm:gap-3">
            @foreach($testamentBooks['newTestament'] as $book)
                <a href="{{ route('bible.book', $book['osis_id']) }}"
                   class="touch-friendly block p-3 sm:p-4 bg-gray-50 dark:bg-gray-700 hover:bg-blue-500 dark:hover:bg-blue-600 hover:text-white rounded-xl transition-all duration-200 group">
                    <div class="font-semibold text-xs sm:text-sm text-gray-900 dark:text-gray-100 group-hover:text-white mb-1">
                        {{ $book['short_name'] }}
                    </div>
                    <div class="text-xs opacity-75 group-hover:opacity-100 text-gray-600 dark:text-gray-300 group-hover:text-white leading-tight">
                        {{ $book['name'] }}
                    </div>
                </a>
            @endforeach
        </div>
    </div>
    @endif
</div>
@endsection
