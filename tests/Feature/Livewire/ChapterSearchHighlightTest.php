<?php

use App\Livewire\BibleChapter;
use Livewire\Livewire;

beforeEach(function () {
    $this->withoutVite();
    projectFixture();
});

function chapterSearchHtml(string $query): string
{
    $html = Livewire::test(BibleChapter::class, ['bookOsisId' => 'John', 'chapterNumber' => 3])
        ->set('searchQuery', $query)
        ->call('search')
        ->html();

    // Just the results list.
    $start = strpos($html, 'Search Results List');

    return substr($html, $start, strpos($html, '@endforeach') ?: 40000);
}

it('highlights matches in the scripture\'s own casing with balanced markup', function () {
    $html = chapterSearchHtml('em');

    expect(substr_count($html, '<mark>'))->toBe(substr_count($html, '</mark>'))
        ->and($html)->not->toContain('<<mark>')
        ->and($html)->not->toContain('</<mark>');

    $loved = chapterSearchHtml('LOVED');
    expect($loved)->toContain('<mark>loved</mark>')->not->toContain('<mark>LOVED</mark>');
});

it('never renders the search text as markup (regression guard)', function () {
    $html = chapterSearchHtml('<img src=x onerror=alert(1)> love');

    expect($html)->not->toContain('<img src=x');
});

it('links each result to its verse', function () {
    expect(chapterSearchHtml('God so loved'))->toContain(route('chapters.show', ['bookOsisId' => 'John', 'chapterNumber' => 3]).'#verse-16');
});
