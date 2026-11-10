<?php

use App\Livewire\BibleBook;
use App\Livewire\BibleChapter;
use Illuminate\Support\Facades\Route;
use Livewire\Features\SupportLockedProperties\CannotUpdateLockedPropertyException;
use Livewire\Livewire;

beforeEach(function () {
    $this->withoutVite();
    projectFixture();
});

it('does not let the browser change server-owned reader state', function (string $component, array $params, string $property, mixed $value) {
    expect(fn () => Livewire::test($component, $params)->set($property, $value))
        ->toThrow(CannotUpdateLockedPropertyException::class);
})->with([
    'chapter book' => [BibleChapter::class, ['bookOsisId' => 'John', 'chapterNumber' => 3], 'bookOsisId', 'x'],
    'chapter number' => [BibleChapter::class, ['bookOsisId' => 'John', 'chapterNumber' => 3], 'chapterNumber', 99],
    'chapter title' => [BibleChapter::class, ['bookOsisId' => 'John', 'chapterNumber' => 3], 'chapterTitle', ['title_text' => '<script>']],
    'chapter translation' => [BibleChapter::class, ['bookOsisId' => 'John', 'chapterNumber' => 3], 'currentTranslation', ['key' => 'x']],
    'book id' => [BibleBook::class, ['bookOsisId' => 'John'], 'bookOsisId', '../x'],
]);

it('keeps the verses out of client state entirely (computed, not public)', function () {
    expect(fn () => Livewire::test(BibleChapter::class, ['bookOsisId' => 'John', 'chapterNumber' => 3])->set('verses', ['<script>']))
        ->toThrow(\Livewire\Exceptions\PublicPropertyNotFoundException::class);
});

it('still lets the reader type a search', function () {
    Livewire::test(BibleChapter::class, ['bookOsisId' => 'John', 'chapterNumber' => 3])
        ->set('searchQuery', 'love')
        ->assertSet('searchQuery', 'love');
});

it('only navigates to a chapter that exists', function () {
    $c = Livewire::test(BibleChapter::class, ['bookOsisId' => 'John', 'chapterNumber' => 3]);

    $c->call('goToChapter', '/evil.example', 1)->assertNoRedirect();
    $c->call('goToChapter', 'John', 999)->assertNoRedirect();
    $c->call('goToChapter', 'Matt', 5)->assertRedirect(route('chapters.show', ['bookOsisId' => 'Matt', 'chapterNumber' => 5]));
});

it('throttles the in-chapter search like the search API', function () {
    $c = Livewire::test(BibleChapter::class, ['bookOsisId' => 'John', 'chapterNumber' => 3])->set('searchQuery', 'love');

    foreach (range(1, 30) as $i) {
        $c->call('search');
    }
    expect($c->get('searchStats.throttled'))->toBeNull();

    $c->call('search')->assertSet('searchStats.throttled', true)->assertSee('Too many searches');
});

it('throttles the Livewire update endpoint per address', function () {
    $route = collect(Route::getRoutes())->first(fn ($r) => $r->uri() === 'livewire/update');

    expect($route)->not->toBeNull()
        ->and($route->gatherMiddleware())->toContain('throttle:livewire');

    $statuses = collect(range(1, 121))->map(fn () => $this->withServerVariables(['REMOTE_ADDR' => '198.51.100.20'])
        ->postJson('/livewire/update', ['components' => []])->status());

    expect($statuses->take(120)->contains(429))->toBeFalse()
        ->and($statuses->last())->toBe(429);
});
