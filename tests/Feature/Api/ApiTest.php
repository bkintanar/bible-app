<?php

beforeEach(function () {
    projectFixture();
});

it('wraps successful responses in the envelope', function () {
    $this->getJson('/api/books')
        ->assertOk()
        ->assertJson(['success' => true])
        ->assertJsonStructure(['success', 'message', 'data', 'timestamp']);
});

it('returns every verse of a numbered book\'s chapter', function () {
    $this->getJson('/api/books/1John/chapters/4/verses')
        ->assertOk()
        ->assertJsonPath('data.total_verses', 21)
        ->assertJsonCount(21, 'data.verses');
});

it('returns one verse with its text', function () {
    $this->getJson('/api/books/John/chapters/3/verses/16')
        ->assertOk()
        ->assertJson(['success' => true]);

    expect(json_encode($this->getJson('/api/books/John/chapters/3/verses/16')->json('data')))->toContain('For God so loved the world');
});

it('answers unknown things with a JSON 404', function (string $uri) {
    $this->get($uri)->assertNotFound()->assertJson(['success' => false]);
})->with(['/api/books/Nope', '/api/books/John/chapters/99/verses', '/api/nope']);

it('rejects a NUL in a search with 422', function () {
    $this->get('/api/search?q=' . rawurlencode("lo\0ve"))->assertStatus(422)->assertJson(['success' => false]);
});

it('finds verses and reports their verse numbers', function () {
    $results = $this->getJson('/api/search?q=' . rawurlencode('God is love'))->assertOk()->json('data.results');

    expect($results)->not->toBeEmpty()
        ->and(collect($results)->firstWhere('reference', '1 John 4:8')['verse'] ?? null)->toBe(8);
});
