@extends('layouts.bible')

@section('title', $currentBook['name'] . ' - Bible Reader')

@section('content')
<div class="space-y-6">
    <!-- Breadcrumb -->
    <nav class="flex" aria-label="Breadcrumb">
        <ol class="flex items-center space-x-4">
            <li>
                <a href="{{ route('bible.index') }}" class="text-bible-blue dark:text-blue-400 hover:text-blue-800 dark:hover:text-blue-300">
                    📖 Bible
                </a>
            </li>
            <li>
                <span class="text-gray-400 dark:text-gray-500">/</span>
            </li>
            <li>
                <span class="text-gray-600 dark:text-gray-400">{{ $currentBook['name'] }}</span>
            </li>
        </ol>
    </nav>

    <!-- Book Header -->
    <div class="bg-white dark:bg-gray-800 rounded-lg shadow-sm p-6">
        <div class="flex items-center justify-between">
            <div>
                <h1 class="text-3xl font-bold text-gray-900 dark:text-gray-100">{{ $currentBook['name'] }}</h1>
                <p class="text-gray-600 dark:text-gray-400 mt-1">{{ $currentBook['testament'] }}</p>
            </div>
            <div class="text-right">
                <div class="text-2xl font-bold text-bible-blue dark:text-blue-400">{{ $chapters->count() }}</div>
                <div class="text-sm text-gray-500 dark:text-gray-400">Chapters</div>
            </div>
        </div>
    </div>

    <!-- Chapters Grid -->
    <div class="bg-white dark:bg-gray-800 rounded-lg shadow-sm p-6">
        <h2 class="text-xl font-semibold text-gray-900 dark:text-gray-100 mb-4">Chapters</h2>
        <div class="grid grid-cols-4 sm:grid-cols-6 md:grid-cols-8 lg:grid-cols-10 xl:grid-cols-12 gap-3">
            @foreach($chapters as $chapter)
                <a href="{{ route('bible.chapter', [$currentBook['osis_id'], $chapter['chapter_number']]) }}"
                   class="flex items-center justify-center w-12 h-12 bg-gray-50 dark:bg-gray-700 hover:bg-bible-blue dark:hover:bg-blue-600 hover:text-white text-gray-900 dark:text-gray-100 rounded-lg transition-all duration-200 font-semibold text-sm border-2 border-transparent hover:border-blue-300 dark:hover:border-blue-400">
                    {{ $chapter['chapter_number'] }}
                </a>
            @endforeach
        </div>
    </div>

    <!-- Quick Navigation -->
    @php
        $otherBooks = $books->where('testament', $currentBook['testament'])->reject(function($book) use ($currentBook) {
            return $book['osis_id'] === $currentBook['osis_id'];
        });
    @endphp

    @if($otherBooks->isNotEmpty())
    <div class="bg-white dark:bg-gray-800 rounded-lg shadow-sm p-6">
        <h3 class="text-lg font-semibold text-gray-900 dark:text-gray-100 mb-4">Other {{ $currentBook['testament'] }} Books</h3>
        <div class="flex flex-wrap gap-2">
            @foreach($otherBooks as $book)
                <a href="{{ route('bible.book', $book['osis_id']) }}"
                   class="px-3 py-2 bg-gray-100 dark:bg-gray-700 hover:bg-bible-blue dark:hover:bg-blue-600 hover:text-white text-gray-900 dark:text-gray-100 rounded-md text-sm transition-colors">
                    {{ $book['short_name'] }}
                </a>
            @endforeach
        </div>
    </div>
    @endif
</div>
@endsection
