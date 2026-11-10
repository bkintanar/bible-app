{{--
    Partial for displaying chapter verses
    Parameters:
    - $verses: Collection of verse paragraphs
    - $chapterNumber: Chapter number (for drop cap)
--}}

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
