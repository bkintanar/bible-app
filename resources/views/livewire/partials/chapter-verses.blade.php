{{--
    Partial for displaying chapter verses
    Parameters:
    - $verses: Collection of verse paragraphs
    - $chapterNumber: Chapter number (for drop cap)
    - $frame: the book's title and colophon (BibleChapter::bookFrame, OD-9) - optional
    - $lastChapter: the book's last chapter number (where its colophon belongs) - optional
--}}

@php($chapterNumber = (int) $chapterNumber)

{{-- The book's title, as the KJV prints it above chapter 1 (not scripture). --}}
@if($chapterNumber === 1 && ! empty($frame['title']))
    <h2 class="book-title reader-text text-center font-serif font-semibold text-gray-800 dark:text-gray-200"><span class="book-title-text">{!! strip_tags($frame['title'], '<span><em>') !!}</span></h2>
@endif

@if(isset($verses) && !empty($verses))
    @foreach($verses as $paragraph)
        @include('livewire.partials.verse-paragraph', [
            'paragraph' => $paragraph,
            'chapterNumber' => $chapterNumber,
            'loop' => $loop,
            'emitIds' => $emitIds ?? true,
            'wireKey' => 'p-' . $chapterNumber . '-' . $loop->index,
        ])
    @endforeach
@else
    <!-- No verses available -->
    <div class="text-center py-12">
        <div class="text-gray-500 dark:text-gray-400 font-serif">
            Chapter content loading...
        </div>
    </div>
@endif

{{-- An epistle's colophon, after its last verse: set apart as printed KJVs do (italic, supplied
     words in roman), because it is not scripture. --}}
@if(! empty($frame['colophon']) && $chapterNumber === (int) ($lastChapter ?? 0))
    <p class="colophon reader-text font-serif text-center text-gray-600 dark:text-gray-400 border-t border-gray-200 dark:border-gray-700"><span class="sr-only">Colophon: </span><span class="colophon-text">{!! strip_tags($frame['colophon'], '<span><em>') !!}</span></p>
@endif
