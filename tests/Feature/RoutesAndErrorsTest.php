<?php

use Illuminate\Support\Facades\Route;

beforeEach(function () {
    $this->withoutVite();
    projectFixture();
});

it('has removed the dead translation and user-session endpoints', function (string $method, string $uri) {
    // Gone either way: 404, or 405 where the path still matches the GET-only short-URL route.
    expect($this->call($method, $uri)->status())->toBeIn($method === 'GET' ? [404] : [404, 405]);
})->with([['GET', '/translation'], ['PUT', '/translation'], ['GET', '/user-session'], ['DELETE', '/user-session']]);

it('answers unknown API paths with the JSON error envelope', function (string $uri) {
    $this->get($uri)->assertNotFound()->assertJson(['success' => false])->assertJsonStructure(['success', 'message', 'timestamp']);
})->with(['/api/nope', '/api', '/api/a/b', '/api/books/Nope/chapters/1/verses/1/x']);

it('throttles the API with the JSON error envelope', function () {
    foreach (range(1, 30) as $i) {
        $this->getJson('/api/search?q=love')->assertOk();
    }

    $this->get('/api/search?q=love')->assertStatus(429)->assertJson(['success' => false])->assertHeader('Retry-After');
});

it('does not serve local storage', function () {
    $this->get('/storage/x')->assertNotFound();
    expect(collect(Route::getRoutes())->contains(fn ($r) => str_contains($r->uri(), 'storage/{path}')))->toBeFalse();
});

it('redirects real short URLs permanently, to the canonical book id', function (string $from, string $to) {
    $this->get($from)->assertStatus(301)->assertRedirect($to);
})->with([
    ['/Gen/1', '/books/Gen/chapters/1'],
    ['/gen/2', '/books/Gen/chapters/2'],
    ['/1john/4', '/books/1John/chapters/4'],
    ['/John', '/books/John'],
]);

it('404s short URLs that point nowhere, with the reader error page', function (string $uri) {
    $this->get($uri)->assertNotFound()->assertSee('Go to Genesis 1')->assertSee('Page not found');
})->with(['/Nope/1', '/Gen/999', '/wp-login', '/admin', '/books/Gen/chapters/99999999999999999999', '/books/Gen/chapters/0x1']);

it('shows a static maintenance page', function () {
    $this->app->maintenanceMode()->activate([]);

    try {
        $this->get('/books/Gen/chapters/1')->assertStatus(503)->assertSee('Back shortly')->assertDontSee('Search the Bible');
    } finally {
        $this->app->maintenanceMode()->deactivate();
    }
});

it('renders the 500 page without the database', function () {
    expect(view('errors.500')->render())->toContain('Something went wrong')->toContain('Go to Genesis 1');
});
