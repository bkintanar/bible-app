<?php

/*
 * What a reader sees, rendered from the fixture projection (mini-kjv.osis.xml). A new visitor sees
 * the plain text (Strong's off, OD-3); tests of the Strong's view turn it on in the session.
 */

beforeEach(function () {
    $this->withoutVite();
    projectFixture();
});

/** The current page only (the hidden previous chapter renders first). */
function currentPage(string $html): string
{
    return substr($html, strpos($html, 'page page-current'), strpos($html, 'page page-next') - strpos($html, 'page page-current'));
}

/** One verse's markup, from its anchor to the next verse's. */
function verseHtml(string $page, int $verse): string
{
    $start = strpos($page, "id=\"verse-{$verse}\"");
    expect($start)->not->toBeFalse("verse {$verse} has an anchor");
    $next = strpos($page, 'id="verse-', $start + 10);

    return substr($page, $start, ($next ?: strlen($page)) - $start);
}

function visibleText(string $html): string
{
    return trim(preg_replace('/\s+/', ' ', html_entity_decode(strip_tags($html), ENT_QUOTES | ENT_HTML5)));
}

it('serves every fixture chapter, numbered books included', function (string $book, int $chapter) {
    $this->get("/books/{$book}/chapters/{$chapter}")->assertOk();
})->with([
    ['Gen', 1], ['Gen', 2], ['Ps', 3], ['Ps', 18], ['Ps', 23], ['Matt', 5], ['Matt', 6], ['Matt', 9],
    ['Matt', 15], ['Matt', 16], ['Matt', 17], ['Matt', 19], ['Matt', 20], ['Matt', 21], ['Matt', 22],
    ['Matt', 26], ['Matt', 28], ['Mark', 2], ['John', 3], ['1John', 4], ['Phlm', 1],
]);

it('shows Matt 26:56 complete, with the narration after Christ\'s words not in red', function () {
    $verse = verseHtml(currentPage($this->get('/books/Matt/chapters/26')->getContent()), 56);

    expect(visibleText($verse))
        ->toContain('But all this was done, that the scriptures of the prophets might be fulfilled.')
        ->toContain('Then all the disciples forsook him, and fled.');

    // The red span closes before the narration.
    $red = substr($verse, strpos($verse, 'text-red-600'));
    $redEnd = strrpos($red, 'fulfilled');
    expect(visibleText(substr($red, 0, strpos($red, 'Then'))))->toContain('fulfilled');
});

it('renders John 3:16 inside the words of Christ', function () {
    $verse = verseHtml(currentPage($this->get('/books/John/chapters/3')->getContent()), 16);

    expect($verse)->toContain('text-red-600');
    expect(visibleText($verse))->toContain('For God so loved the world, that he gave his only begotten Son');
});

it('does not italicise Gen 1:1 "created" (morphology is not a translator addition)', function () {
    $html = $this->withSession(['show_strongs_numbers' => true])->get('/books/Gen/chapters/1')->getContent();
    $verse = verseHtml(currentPage($html), 1);

    expect($verse)->toContain('>created</span>')->not->toContain('<em');
    expect($html)->not->toMatch('/\.has-morph\s*\{[^}]*italic/');

    $plain = verseHtml(currentPage($this->get('/books/Gen/chapters/1')->getContent()), 1);
    expect(visibleText($plain))->toContain('In the beginning God created the heaven and the earth.')
        ->and($plain)->not->toContain('<em');
});

it('shows plain text to a new visitor and Strong\'s numbers once the reader turns them on', function () {
    $plain = currentPage($this->get('/books/John/chapters/3')->getContent());
    $strongs = currentPage($this->withSession(['show_strongs_numbers' => true])->get('/books/John/chapters/3')->getContent());

    expect($plain)->not->toContain('strong-word')
        ->and($strongs)->toContain('class="strong-word')
        ->and(visibleText(verseHtml($plain, 16)))->toBe(visibleText(verseHtml($strongs, 16)));
});

it('shows the book\'s title above chapter 1 only', function () {
    $gen1 = currentPage($this->get('/books/Gen/chapters/1')->getContent());
    $gen2 = currentPage($this->get('/books/Gen/chapters/2')->getContent());

    expect($gen1)->toMatch('/<h2 class="book-title[^"]*"><span class="book-title-text">THE FIRST BOOK OF MOSES CALLED GENESIS<\/span><\/h2>/')
        ->and(strpos($gen1, 'book-title'))->toBeLessThan(strpos($gen1, 'id="verse-1"'))
        ->and($gen2)->not->toContain('book-title');
});

it('shows an epistle\'s colophon after its last verse, outside the verse, marked as not scripture', function () {
    $page = currentPage($this->get('/books/Phlm/chapters/1')->getContent());

    expect($page)->toContain('THE EPISTLE OF PAUL THE APOSTLE TO PHILEMON')
        ->and($page)->toMatch('/<p class="colophon[^"]*"><span class="sr-only">Colophon: <\/span><span class="colophon-text">Written from Rome to Philemon, by Onesimus a servant\.<\/span><\/p>/')
        ->and(strpos($page, 'class="colophon'))->toBeGreaterThan(strpos($page, 'id="verse-25"'));
    // The last verse ends where its own element ends: the colophon is not part of its text.
    $dom = new DOMDocument;
    @$dom->loadHTML('<?xml encoding="UTF-8">'.$page);
    expect(trim($dom->getElementById('verse-25')->textContent))
        ->toStartWith('25The grace of our Lord Jesus Christ')->not->toContain('Written from Rome');
});

it('shows no colophon on a book that has none', function () {
    expect(currentPage($this->get('/books/John/chapters/3')->getContent()))->not->toContain('class="colophon');
});

it('renders Ps 23:1 with LORD in small capitals and the supplied "is" in italics', function () {
    $verse = verseHtml(currentPage($this->get('/books/Ps/chapters/23')->getContent()), 1);

    expect($verse)->toMatch('/font-variant: small-caps[^>]*>Lord</')
        ->and($verse)->toMatch('/<em[^>]*italic[^>]*>is<\/em>/')
        ->and(visibleText($verse))->toContain('The Lord is my shepherd; I shall not want.');
});

it('never leaks raw OSIS markup, emits each verse anchor once, and balances its spans', function (string $book, int $chapter) {
    $html = $this->get("/books/{$book}/chapters/{$chapter}")->getContent();
    $page = currentPage($html);

    // Rendered markup only (a CSS comment in the styles partial quotes <transChange> on purpose).
    $markup = preg_replace('#<(style|script)\b[^>]*>.*?</\1>#s', '', $html);
    expect($markup)->not->toMatch('/<(w|transChange|divineName|inscription|note|milestone)\b[\s>\/]/');
    expect(substr_count($html, 'id="verse-1"'))->toBe(1);
    expect(substr_count($page, '<span'))->toBe(substr_count($page, '</span>'));
})->with([['Gen', 1], ['Ps', 23], ['Matt', 5], ['Matt', 26], ['John', 3], ['1John', 4]]);

it('has one h1, one main landmark, a skip link and real metadata', function () {
    config(['app.url' => 'https://bible.example']);
    $html = $this->get('/books/Gen/chapters/1')->getContent();

    expect(substr_count($html, '<h1'))->toBe(1)
        ->and(substr_count($html, '<main'))->toBe(1)
        ->and($html)->toContain('<main id="main"')
        ->and($html)->toContain('href="#main"')
        ->and($html)->toMatch('/<h1[^>]*>\s*<button[^>]*>\s*Genesis 1\s*<\/button>\s*<\/h1>/');

    expect($html)->toMatch('/<title>Genesis 1 — [^<]+<\/title>/')
        ->and($html)->toContain('<link rel="canonical" href="https://bible.example/books/Gen/chapters/1">')
        ->and($html)->toContain('<meta property="og:url" content="https://bible.example/books/Gen/chapters/1">');

    preg_match('/<meta name="description" content="([^"]*)">/', $html, $m);
    expect(html_entity_decode($m[1]))->toStartWith('Genesis 1: In the beginning God created the heaven and the earth.')
        ->and(mb_strlen(html_entity_decode($m[1])))->toBeLessThanOrEqual(155);
});

it('marks editorial titles as headings and psalm titles as text', function () {
    expect(currentPage($this->get('/books/Gen/chapters/1')->getContent()))->toMatch('/<h2 class="verse-title[^"]*">The Creation<\/h2>/');
    expect(currentPage($this->get('/books/Ps/chapters/23')->getContent()))->toMatch('/<p class="psalm-title[^"]*">A Psalm of David\.?<\/p>/');
});

it('names and describes its controls for assistive technology', function () {
    $html = $this->get('/books/John/chapters/3')->getContent();

    expect($html)->toContain('aria-label="Strong\'s numbers" aria-pressed="false"')
        ->and($html)->toContain('aria-label="Toggle dark mode"')
        ->and($html)->toContain('aria-label="Smaller text"')
        ->and($html)->toContain('aria-label="Search the Bible"')
        ->and($html)->toMatch('/class="page-content [^"]*"\s*tabindex="0" role="region" aria-label="John 3"/')
        ->and($html)->toContain('x-trap.noscroll.inert="selector"')
        ->and($html)->toContain('viewport-fit=cover')
        ->and(substr_count($html, 'wire:key="v-3-'))->toBe(36); // one key per verse of John 3
});

it('groups verses in paragraphs with headings between them, never inside', function (string $book, int $chapter) {
    $page = currentPage($this->get("/books/{$book}/chapters/{$chapter}")->getContent());

    preg_match_all('/<p\b[^>]*verse-content[^>]*>(.*?)<\/p>/s', $page, $paragraphs);
    expect($paragraphs[0])->not->toBeEmpty();

    foreach ($paragraphs[1] as $inner) {
        // A block element here would make the browser close the <p> early.
        expect($inner)->not->toMatch('/<(div|p|h[1-6]|ul|ol|table|section|blockquote)\b/i');
    }

    // Every verse anchor is inside a paragraph.
    preg_match_all('/id="verse-(\d+)"/', implode('', $paragraphs[1]), $inside);
    preg_match_all('/id="verse-(\d+)"/', $page, $all);
    expect(count($inside[1]))->toBe(count($all[1]));
})->with([['Gen', 1], ['Ps', 3], ['Ps', 18], ['Ps', 23], ['Matt', 5], ['John', 3], ['1John', 4]]);
