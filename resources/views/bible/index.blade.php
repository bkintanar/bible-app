@extends('layouts.bible')

@section('title', $bibleInfo['title'] . ' - Bible Reader')

@section('content')
<div class="space-y-8">
    <!-- Bible Info Header -->
    <div class="bg-white dark:bg-gray-800 rounded-lg shadow-sm p-6">
        <h1 class="text-3xl font-bold text-gray-900 dark:text-gray-100 mb-2">{{ $bibleInfo['title'] }}</h1>
        @if($bibleInfo['description'])
            <p class="text-gray-600 dark:text-gray-400 mb-4">{{ $bibleInfo['description'] }}</p>
        @endif
        <div class="flex flex-wrap gap-4 text-sm text-gray-500 dark:text-gray-400">
            @if($bibleInfo['publisher'])
                <span>📚 {{ $bibleInfo['publisher'] }}</span>
            @endif
            @if($bibleInfo['language'])
                <span>🌍 {{ $bibleInfo['language'] }}</span>
            @endif
        </div>
    </div>

    <!-- Old Testament -->
    @php
        $oldTestamentBooks = $books->where('testament', 'Old Testament');
        $newTestamentBooks = $books->where('testament', 'New Testament');
    @endphp

    @if($oldTestamentBooks->isNotEmpty())
    <div class="bg-white dark:bg-gray-800 rounded-lg shadow-sm p-6">
        <h2 class="text-2xl font-bold text-gray-900 dark:text-gray-100 mb-6 flex items-center">
            <span class="text-bible-gold dark:text-yellow-400 mr-2">📜</span>
            Old Testament
            <span class="ml-2 text-sm font-normal text-gray-500 dark:text-gray-400">({{ $oldTestamentBooks->count() }} books)</span>
        </h2>
        <div class="grid grid-cols-2 md:grid-cols-3 lg:grid-cols-4 xl:grid-cols-5 gap-3">
            @foreach($oldTestamentBooks as $book)
                <a href="{{ route('bible.book', $book['osis_id']) }}"
                   class="block p-4 bg-gray-50 dark:bg-gray-700 hover:bg-bible-blue dark:hover:bg-blue-600 hover:text-white rounded-lg transition-all duration-200 group">
                    <div class="font-semibold text-sm text-gray-900 dark:text-gray-100 group-hover:text-white">{{ $book['short_name'] }}</div>
                    <div class="text-xs opacity-75 mt-1 group-hover:opacity-100 text-gray-600 dark:text-gray-300 group-hover:text-white">{{ $book['name'] }}</div>
                </a>
            @endforeach
        </div>
    </div>
    @endif

    <!-- New Testament -->
    @if($newTestamentBooks->isNotEmpty())
    <div class="bg-white dark:bg-gray-800 rounded-lg shadow-sm p-6">
        <h2 class="text-2xl font-bold text-gray-900 dark:text-gray-100 mb-6 flex items-center">
            <span class="text-bible-gold dark:text-yellow-400 mr-2">✝️</span>
            New Testament
            <span class="ml-2 text-sm font-normal text-gray-500 dark:text-gray-400">({{ $newTestamentBooks->count() }} books)</span>
        </h2>
        <div class="grid grid-cols-2 md:grid-cols-3 lg:grid-cols-4 xl:grid-cols-5 gap-3">
            @foreach($newTestamentBooks as $book)
                <a href="{{ route('bible.book', $book['osis_id']) }}"
                   class="block p-4 bg-gray-50 dark:bg-gray-700 hover:bg-bible-blue dark:hover:bg-blue-600 hover:text-white rounded-lg transition-all duration-200 group">
                    <div class="font-semibold text-sm text-gray-900 dark:text-gray-100 group-hover:text-white">{{ $book['short_name'] }}</div>
                    <div class="text-xs opacity-75 mt-1 group-hover:opacity-100 text-gray-600 dark:text-gray-300 group-hover:text-white">{{ $book['name'] }}</div>
                </a>
            @endforeach
        </div>
    </div>
    @endif

    <!-- Quick Stats -->
    <div class="bg-gradient-to-r from-bible-blue to-blue-600 dark:from-blue-700 dark:to-blue-800 text-white rounded-lg p-6">
        <h3 class="text-lg font-semibold mb-4">Quick Stats</h3>
        <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
            <div class="text-center">
                <div class="text-2xl font-bold">{{ $books->count() }}</div>
                <div class="text-sm opacity-90">Total Books</div>
            </div>
            <div class="text-center">
                <div class="text-2xl font-bold">{{ $oldTestamentBooks->count() }}</div>
                <div class="text-sm opacity-90">Old Testament</div>
            </div>
            <div class="text-center">
                <div class="text-2xl font-bold">{{ $newTestamentBooks->count() }}</div>
                <div class="text-sm opacity-90">New Testament</div>
            </div>
        </div>
    </div>

    <div class="bg-blue-50 dark:bg-blue-900/20 border border-blue-200 dark:border-blue-700 rounded-lg p-4 text-sm text-blue-800 dark:text-blue-300">
        <p class="font-medium mb-2">💡 Search Tips:</p>
        <div class="space-y-1">
            <p><strong>For specific verses:</strong> Try "Acts 2:38", "John 3:16", "genesis1:1", "ps23"</p>
            <p><strong>For verse ranges:</strong> Try "deut 6:1-4", "1cor 13:4-8", "matt5:3-12"</p>
            <p><strong>For text search:</strong> Try "love", "faith", "salvation", "shepherd"</p>
        </div>
    </div>
</div>
@endsection
