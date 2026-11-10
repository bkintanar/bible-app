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

it('renders the book selector client-side, with real chapter links', function () {
    $html = $this->get('/books/John/chapters/3')->assertOk()->getContent();

    // The dialog is in the page (x-show), each book carries its chapter count, and chapter
    // URLs come from the named route - no server round-trip to open it or pick a book.
    expect($html)->toContain('role="dialog"')
        ->and($html)->toContain('x-show="selector"')
        ->and($html)->toContain('\/books\/__BOOK__\/chapters\/999999')   // @js() escapes slashes
        ->and($html)->toContain('\u0022id\u0022:\u0022John\u0022,\u0022name\u0022:\u0022John\u0022,\u0022count\u0022:1}');  // fixture: John 3 only
});

it('has no server actions for UI-only toggles', function (string $method) {
    expect(method_exists(BibleChapter::class, $method))->toBeFalse();
})->with(['openBookSelector', 'hideBookSelector', 'selectBookForChapters', 'backToBooks', 'toggleSearch', 'setFontSize', 'goToChapter']);

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
