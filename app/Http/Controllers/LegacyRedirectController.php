<?php

namespace App\Http\Controllers;

use App\Services\BibleService;
use Illuminate\Http\RedirectResponse;

/**
 * Old short URLs: /{book} and /{book}/{chapter} -> the canonical /books/... routes, permanently.
 *
 * Only for books that exist (matched case-insensitively, redirected to the canonical OSIS id):
 * the closures this replaces redirected ANY first path segment, so /anything, /wp-login.php or
 * /api answered with a redirect to a page that then 404'd.
 */
class LegacyRedirectController extends Controller
{
    public function __construct(private BibleService $bible) {}

    public function book(string $bookOsisId): RedirectResponse
    {
        return redirect()->route('books.show', ['bookOsisId' => $this->canonical($bookOsisId)], 301);
    }

    public function chapter(string $bookOsisId, int $chapterNumber): RedirectResponse
    {
        $book = $this->canonical($bookOsisId);

        abort_unless($this->bible->chapterExists($book, $chapterNumber), 404);

        return redirect()->route('chapters.show', ['bookOsisId' => $book, 'chapterNumber' => $chapterNumber], 301);
    }

    private function canonical(string $bookOsisId): string
    {
        $book = $this->bible->canonicalBookId($bookOsisId);

        abort_if($book === null, 404);

        return $book;
    }
}
