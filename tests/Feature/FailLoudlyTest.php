<?php

use App\Livewire\BibleChapter;
use App\Livewire\BibleSearch;
use App\Services\BibleService;
use App\Services\TranslationService;
use Livewire\Livewire;

beforeEach(function () {
    $this->withoutVite();
    projectFixture();
});

it('reports a database failure while loading a chapter as a 500, not a missing chapter', function () {
    $service = Mockery::mock(BibleService::class, [app(TranslationService::class)])->makePartial();
    $service->shouldReceive('getVersesParagraphStyle')->andThrow(new RuntimeException('database is locked'));
    $this->app->instance(BibleService::class, $service);

    $this->get('/books/John/chapters/3')->assertStatus(500)->assertSee('Something went wrong');
});

it('still 404s a chapter that does not exist', function () {
    $this->get('/books/John/chapters/99')->assertNotFound();
});

it('rejects control characters in an API search with 422', function () {
    $this->get('/api/search?q=' . rawurlencode("x\0y"))->assertStatus(422)->assertJson(['success' => false]);
});

it('shows a message instead of failing when the search page gets control characters', function () {
    Livewire::withQueryParams(['q' => "lo\0ve"])->test(BibleSearch::class)
        ->assertHasErrors('q')
        ->assertSet('results', []);

    $this->get('/searches?q=' . rawurlencode("x\0y"))->assertOk()->assertSee('control characters');
});

it('shows a message for control characters in the in-chapter search', function () {
    Livewire::test(BibleChapter::class, ['bookOsisId' => 'John', 'chapterNumber' => 3])
        ->set('searchQuery', "lo\x01ve")
        ->call('search')
        ->assertSet('searchStats.invalid', 'Please remove control characters from your search.')
        ->assertSee('Please remove control characters');
});

it('has removed the stub search history and suggestions endpoints', function (string $uri) {
    $this->get($uri)->assertNotFound()->assertJson(['success' => false]);
})->with(['/api/search/history', '/api/search/suggestions?q=lo']);
