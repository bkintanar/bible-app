<?php

beforeEach(function () {
    $this->withoutVite();
    projectFixture();
});

function snapshotData(string $html): array
{
    preg_match('/wire:snapshot="([^"]+)"/', $html, $m);

    return json_decode(html_entity_decode($m[1]), true)['data'];
}

it('renders the neighbouring chapters for the page peel but keeps them out of the snapshot', function () {
    $html = $this->get('/books/Gen/chapters/1')->assertOk()->getContent();

    $data = snapshotData($html);
    expect($data)->not->toHaveKeys(['previousChapterVerses', 'nextChapterVerses']);

    // Gen 2 is rendered in the hidden, inert next page - without verse ids.
    $next = substr($html, strpos($html, 'page page-next'), 200000);
    expect($next)->toContain('aria-hidden="true"')->toContain('inert');
    expect(strip_tags($next))->toContain('Thus the heavens and the earth were finished');
    expect($next)->not->toMatch('/id="verse-\d+"/');
});

it('links to the neighbouring chapters directly, with no redirect hop', function () {
    $html = $this->get('/books/Gen/chapters/1')->getContent();

    expect($html)->toContain('href="/books/Gen/chapters/2"')
        ->and($html)->toContain('data-next-url="/books/Gen/chapters/2"');

    $this->get('/books/Gen/chapters/2')->assertOk();
});

it('has no previous chapter on chapter 1 and still links across books', function () {
    $html = $this->get('/books/Gen/chapters/2')->getContent();
    expect($html)->toContain('href="/books/Gen/chapters/1"');

    $first = $this->get('/books/Matt/chapters/5')->getContent();
    expect(substr($first, strpos($first, 'page page-prev'), 2000))->not->toContain('id="verse-');
});
