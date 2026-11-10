<?php

use App\Livewire\BibleBook;
use App\Livewire\BibleChapter;
use App\Livewire\BibleSearch;
use Livewire\Livewire;

beforeEach(function () {
    $this->withoutVite();
    projectFixture();
});

it('files books under the right testament in every component', function (string $component, array $params) {
    $c = Livewire::test($component, $params);
    $groups = $component === BibleChapter::class ? $c->instance()->testamentBooks : $c->get('testamentBooks');

    expect(collect($groups['oldTestament'])->pluck('osis_id'))->toContain('Gen')->not->toContain('Matt')
        ->and(collect($groups['newTestament'])->pluck('osis_id'))->toContain('Matt')->not->toContain('Gen');
})->with([
    'chapter' => [BibleChapter::class, ['bookOsisId' => 'John', 'chapterNumber' => 3]],
    'book' => [BibleBook::class, ['bookOsisId' => 'John']],
    'search' => [BibleSearch::class, []],
]);

it('switches translation in every component and ignores a non-string key', function (string $component, array $params) {
    Livewire::test($component, $params)
        ->call('switchTranslation', 'kjv')
        ->assertSet('currentTranslation.key', 'kjv')
        ->call('switchTranslation', ['not' => 'a key'])
        ->assertSet('currentTranslation.key', 'kjv')
        ->assertOk();
})->with([
    'chapter' => [BibleChapter::class, ['bookOsisId' => 'John', 'chapterNumber' => 3]],
    'book' => [BibleBook::class, ['bookOsisId' => 'John']],
    'search' => [BibleSearch::class, ['q' => 'love']],
]);
