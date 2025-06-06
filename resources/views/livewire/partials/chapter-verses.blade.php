{{--
    Partial for displaying chapter verses
    Parameters:
    - $verses: Collection of verse paragraphs
    - $chapterNumber: Chapter number (for drop cap)
    - $getFontSizeClass: Method to get font size class
--}}

@if(isset($verses) && !empty($verses))
    @foreach($verses as $paragraph)
        @include('livewire.partials.verse-paragraph', [
            'paragraph' => $paragraph,
            'chapterNumber' => $chapterNumber,
            'getFontSizeClass' => $getFontSizeClass,
            'loop' => $loop
        ])
    @endforeach
@else
    <!-- No verses available -->
    <div class="text-center py-12">
        <div class="text-gray-500 dark:text-gray-500 font-serif">
            Chapter content loading...
        </div>
    </div>
@endif
