<?php

use App\Livewire\BibleBook;
use App\Livewire\BibleSearch;
use Livewire\Livewire;

beforeEach(function () {
    $this->withoutVite();
    projectFixture();
});

it('shows real verses, linked to the verse, for ?q=', function () {
    $this->get('/searches?q=God%20is%20love')
        ->assertOk()
        ->assertDontSee('mock search result')
        ->assertSee('1 John 4:8')
        ->assertSee(route('chapters.show', ['bookOsisId' => '1John', 'chapterNumber' => 4]) . '#verse-8', false);
});

it('accepts the old ?query= parameter', function () {
    $this->get('/searches?query=God%20is%20love')->assertOk()->assertSee('1 John 4:8')->assertDontSee('mock search result');
});

it('redirects /search permanently and keeps the query', function () {
    $this->get('/search?q=love')->assertStatus(301)->assertRedirect(route('searches.index', ['q' => 'love']));
});

it('survives malformed parameters', function (string $query) {
    $this->get('/searches?' . $query)->assertOk();
})->with(['query=x&limit=abc', 'query[]=x', 'q[]=x', 'q=love&limit=-5', 'limit=99999999999999999999']);

it('clamps the page size and stops loading more at the cap', function () {
    Livewire::withQueryParams(['q' => 'the', 'limit' => '100000'])->test(BibleSearch::class)->assertSet('limit', BibleSearch::MAX_LIMIT);
    Livewire::withQueryParams(['q' => 'the', 'limit' => 'abc'])->test(BibleSearch::class)->assertSet('limit', BibleSearch::PAGE);

    $c = Livewire::withQueryParams(['q' => 'the'])->test(BibleSearch::class);
    foreach (range(1, 10) as $i) {
        $c->call('loadMore');
    }
    $c->assertSet('limit', BibleSearch::MAX_LIMIT)->assertSet('hasMoreResults', false);
});

it('has no POST endpoint', function () {
    $this->post('/searches', ['q' => 'love'])->assertStatus(405);
});

it('sends the book page search to the search page', function () {
    Livewire::test(BibleBook::class, ['bookOsisId' => 'John'])
        ->set('searchQuery', 'born again')
        ->call('search')
        ->assertRedirect(route('searches.index', ['q' => 'born again']));
});
