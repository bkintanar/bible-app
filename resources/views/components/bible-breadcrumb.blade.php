<!-- Mobile-First Breadcrumb -->
<div class="ios-card rounded-xl shadow-sm p-2 sm:p-3">
    <div class="flex items-center space-x-1 sm:space-x-2 text-xs sm:text-sm overflow-x-auto hide-scrollbar">
        <button onclick="showBookSelector()" class="{{ isset($chapterNumber) ? 'flex items-center gap-1 px-1.5 py-1 sm:px-2 sm:py-1 rounded-md hover:bg-blue-50 dark:hover:bg-blue-900/20 text-blue-600 dark:text-blue-400 transition-colors whitespace-nowrap touch-friendly font-medium -ml-1' : 'touch-friendly p-1.5 sm:p-2 bg-blue-500 text-gray-900 dark:text-white shadow-md text-xs font-semibold transition-all duration-200 text-center h-6 sm:h-8 flex items-center justify-center gap-1 whitespace-nowrap -ml-1' }}">
            <span>{{ $currentBook['short_name'] }}</span>
        </button>

        @if(isset($chapterNumber))
            <svg class="w-3 h-3 sm:w-4 sm:h-4 text-gray-300 dark:text-gray-600 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"></path>
            </svg>

            <button onclick="showChapterSelector()" class="touch-friendly p-1.5 sm:p-2 bg-blue-500 text-gray-900 dark:text-white shadow-md text-xs font-semibold transition-all duration-200 text-center h-6 sm:h-8 flex items-center justify-center gap-1 whitespace-nowrap -ml-1">
                <span>Chapter {{ $chapterNumber }}</span>
            </button>
        @endif
    </div>
</div>

<!-- Book Selector Modal -->
<div id="bookSelector" class="fixed inset-0 bg-gray-900/20 dark:bg-black/30 hidden z-50">
    <div class="ios-card w-full rounded-none" style="height: 100vh; display: flex; flex-direction: column;">
        <!-- Fixed Header -->
        <div class="flex justify-between items-center p-4 border-b border-gray-200 dark:border-gray-600" style="flex-shrink: 0;">
            <h3 class="text-sm font-semibold text-gray-900 dark:text-gray-100">Select Book</h3>
            <button onclick="hideBookSelector()" class="touch-friendly p-1 text-gray-400 dark:text-gray-500 hover:text-gray-600 dark:hover:text-gray-300">
                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path>
                </svg>
            </button>
        </div>

        <!-- Scrollable Content -->
        <div class="p-3" style="flex: 1; overflow-y: auto; -webkit-overflow-scrolling: touch; height: calc(100vh - 140px);">
            <!-- Old Testament -->
            @php
                $oldTestament = $books->where('testament', 'Old Testament');
                $newTestament = $books->where('testament', 'New Testament');
            @endphp

            <div class="space-y-4">
                <div>
                    <h4 class="text-xs font-semibold text-gray-600 dark:text-gray-400 mb-2 flex items-center">
                        📜 OLD TESTAMENT <span class="ml-2 text-xs text-gray-400 dark:text-gray-500">({{ $oldTestament->count() }})</span>
                    </h4>
                    <div class="grid gap-2 mb-3" style="grid-template-columns: repeat(3, 1fr);">
                        @foreach($oldTestament as $book)
                            <a href="{{ route('bible.book', $book['osis_id']) }}"
                               class="touch-friendly p-2 {{ $book['osis_id'] === $currentBook['osis_id'] ? 'bg-blue-500 text-gray-900 dark:text-white shadow-md' : 'bg-white dark:bg-gray-800 hover:bg-blue-50 dark:hover:bg-blue-900/20 text-gray-800 dark:text-gray-200 shadow-sm hover:shadow-md' }} rounded-xl text-xs font-semibold transition-all duration-200 text-center h-8 flex items-center justify-center border {{ $book['osis_id'] === $currentBook['osis_id'] ? 'border-blue-300 dark:border-blue-400' : 'border-gray-200 dark:border-gray-700 hover:border-blue-200 dark:hover:border-blue-800' }}">
                                {{ $book['name'] }}
                            </a>
                        @endforeach
                    </div>
                </div>

                <div>
                    <h4 class="text-xs font-semibold text-gray-600 dark:text-gray-400 mb-2 flex items-center">
                        ✝️ NEW TESTAMENT <span class="ml-2 text-xs text-gray-400 dark:text-gray-500">({{ $newTestament->count() }})</span>
                    </h4>
                    <div class="grid gap-2" style="grid-template-columns: repeat(3, 1fr);">
                        @foreach($newTestament as $book)
                            <a href="{{ route('bible.book', $book['osis_id']) }}"
                               class="touch-friendly p-2 {{ $book['osis_id'] === $currentBook['osis_id'] ? 'bg-blue-500 text-gray-900 dark:text-white shadow-md' : 'bg-white dark:bg-gray-800 hover:bg-blue-50 dark:hover:bg-blue-900/20 text-gray-800 dark:text-gray-200 shadow-sm hover:shadow-md' }} rounded-xl text-xs font-semibold transition-all duration-200 text-center h-8 flex items-center justify-center border {{ $book['osis_id'] === $currentBook['osis_id'] ? 'border-blue-300 dark:border-blue-400' : 'border-gray-200 dark:border-gray-700 hover:border-blue-200 dark:hover:border-blue-800' }}">
                                {{ $book['name'] }}
                            </a>
                        @endforeach
                    </div>
                </div>
            </div>
        </div>

        <!-- Fixed Footer -->
        <div class="p-4 border-t border-gray-200 dark:border-gray-600" style="flex-shrink: 0;">
            <button onclick="hideBookSelector()"
                    class="touch-friendly px-4 py-2 bg-gray-500 hover:bg-gray-600 text-white rounded-lg font-medium transition-colors w-full text-sm">
                Close
            </button>
        </div>
    </div>
</div>

<!-- Chapter Selector Modal (only show if chapters are provided) -->
@if(isset($chapters) && isset($chapterNumber))
    <div id="chapterSelector" class="fixed inset-0 bg-gray-900/20 dark:bg-black/30 hidden z-50">
        <div class="ios-card w-full rounded-none" style="height: 100vh; display: flex; flex-direction: column;">
            <!-- Fixed Header -->
            <div class="flex justify-between items-center p-4 border-b border-gray-200 dark:border-gray-600" style="flex-shrink: 0;">
                <h3 class="text-sm font-semibold text-gray-900 dark:text-gray-100">{{ $currentBook['short_name'] }} Chapters</h3>
                <button onclick="hideChapterSelector()" class="touch-friendly p-1 text-gray-400 dark:text-gray-500 hover:text-gray-600 dark:hover:text-gray-300">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path>
                    </svg>
                </button>
            </div>

            <!-- Scrollable Content -->
            <div class="p-4" style="flex: 1; overflow-y: auto; -webkit-overflow-scrolling: touch; height: calc(100vh - 140px);">
                <!-- Chapters Grid -->
                <div class="grid gap-3 mb-4" style="grid-template-columns: repeat(4, 1fr);">
                    @foreach($chapters as $chapter)
                        <a href="{{ route('bible.chapter', [$currentBook['osis_id'], $chapter['chapter_number']]) }}"
                           class="touch-friendly p-2 {{ $chapter['chapter_number'] == $chapterNumber ? 'bg-blue-500 text-gray-900 dark:text-white shadow-md' : 'bg-white dark:bg-gray-800 hover:bg-blue-50 dark:hover:bg-blue-900/20 text-gray-800 dark:text-gray-200 shadow-sm hover:shadow-md' }} rounded-xl text-sm font-semibold transition-all duration-200 text-center h-10 flex items-center justify-center border {{ $chapter['chapter_number'] == $chapterNumber ? 'border-blue-300 dark:border-blue-400' : 'border-gray-200 dark:border-gray-700 hover:border-blue-200 dark:hover:border-blue-800' }} active:scale-95">
                            {{ $chapter['chapter_number'] }}
                        </a>
                    @endforeach
                </div>
            </div>

            <!-- Fixed Footer -->
            <div class="p-4 border-t border-gray-200 dark:border-gray-600" style="flex-shrink: 0;">
                <button onclick="hideChapterSelector()"
                        class="touch-friendly px-4 py-2 bg-gray-500 hover:bg-gray-600 text-white rounded-lg font-medium transition-colors w-full text-sm">
                    Close
                </button>
            </div>
        </div>
    </div>
@endif

@push('scripts')
<script>
function disableBodyScroll() {
    document.body.style.overflow = 'hidden';
}

function enableBodyScroll() {
    document.body.style.overflow = '';
}

function hideFloatingNavigation() {
    const prevBtn = document.getElementById('prevChapterBtn');
    const nextBtn = document.getElementById('nextChapterBtn');
    if (prevBtn) prevBtn.style.display = 'none';
    if (nextBtn) nextBtn.style.display = 'none';
}

function showFloatingNavigation() {
    const prevBtn = document.getElementById('prevChapterBtn');
    const nextBtn = document.getElementById('nextChapterBtn');
    if (prevBtn) prevBtn.style.display = 'flex';
    if (nextBtn) nextBtn.style.display = 'flex';
}

function showBookSelector() {
    document.getElementById('bookSelector').classList.remove('hidden');
    hideFloatingNavigation();
    disableBodyScroll();
}

function hideBookSelector() {
    document.getElementById('bookSelector').classList.add('hidden');
    showFloatingNavigation();
    enableBodyScroll();
}

@if(isset($chapters) && isset($chapterNumber))
function showChapterSelector() {
    document.getElementById('chapterSelector').classList.remove('hidden');
    hideFloatingNavigation();
    disableBodyScroll();
}

function hideChapterSelector() {
    document.getElementById('chapterSelector').classList.add('hidden');
    showFloatingNavigation();
    enableBodyScroll();
}
@endif
</script>
@endpush
