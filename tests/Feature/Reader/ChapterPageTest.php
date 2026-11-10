<?php

/*
 * What a reader sees, rendered from the fixture projection (mini-kjv.osis.xml) with Strong's on.
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
    ['Matt', 26], ['Matt', 28], ['Mark', 2], ['John', 3], ['1John', 4],
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
    $html = $this->get('/books/Gen/chapters/1')->getContent();
    $verse = verseHtml(currentPage($html), 1);

    expect($verse)->toContain('>created</span>')->not->toContain('<em');
    expect($html)->not->toMatch('/\.has-morph\s*\{[^}]*italic/');
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
