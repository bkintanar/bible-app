<?php

beforeEach(function () {
    $this->withoutVite();
    projectFixture();
});

it('lists every projected chapter in the sitemap, by canonical URL', function () {
    config(['app.url' => 'https://bible.example']);

    $xml = $this->get('/sitemap.xml')->assertOk()->assertHeader('Content-Type', 'application/xml; charset=UTF-8')->getContent();

    expect(substr_count($xml, '<loc>'))->toBe(20) // the fixture's 20 chapters
        ->and($xml)->toContain('<loc>https://bible.example/books/1John/chapters/4</loc>')   // APP_URL, whatever the request
        ->and($xml)->toContain('/books/Ps/chapters/23</loc>')
        ->and($xml)->not->toContain('/books/Ps/chapters/1</loc>');
});

it('redirects case variants of a book to its canonical URL', function (string $from, string $to) {
    $this->get($from)->assertStatus(301)->assertRedirect($to);
})->with([
    ['/books/gen/chapters/1', '/books/Gen/chapters/1'],
    ['/books/1JOHN/chapters/4', '/books/1John/chapters/4'],
    ['/books/john', '/books/John'],
]);

it('keeps login and the editor out of search indexes', function () {
    $this->get('/login')->assertHeader('X-Robots-Tag', 'noindex, nofollow');

    $user = \App\Models\User::create(['name' => 'A', 'email' => 'a@example.com', 'password' => 'correct-horse-battery']);
    config(['bible.editor_enabled' => true]);
    $this->actingAs($user)->get('/admin/verse-titles')->assertHeader('X-Robots-Tag', 'noindex, nofollow');

    $this->get('/books/Gen/chapters/1')->assertHeaderMissing('X-Robots-Tag');
});

it('points crawlers at the sitemap and away from search', function () {
    $robots = file_get_contents(public_path('robots.txt'));

    expect($robots)->toContain('Sitemap: https://bible.b8.co.nz/sitemap.xml')->toContain('Disallow: /searches');
});
