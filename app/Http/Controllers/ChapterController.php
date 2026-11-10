<?php

namespace App\Http\Controllers;

use App\Services\BibleService;
use App\Support\ResumePosition;
use Illuminate\Http\RedirectResponse;
use Illuminate\View\View;

class ChapterController extends Controller
{
    public function __construct(private BibleService $bibleService)
    {
    }

    /**
     * GET /books/{bookOsisId}/chapters/{chapterNumber}
     *
     * The chapter and its neighbours are read straight from the projection (indexed reads,
     * ~0.02 ms). The chapter cache and the background job that filled it are gone: they
     * existed to feed the neighbours into the Livewire snapshot, which no longer carries them.
     */
    public function show(string $bookOsisId, int $chapterNumber): View|RedirectResponse
    {
        // One URL per chapter: /books/gen/chapters/1 -> /books/Gen/chapters/1 (permanent).
        $canonical = $this->bibleService->canonicalBookId($bookOsisId);
        abort_if($canonical === null, 404);

        if ($canonical !== $bookOsisId) {
            return redirect()->route('chapters.show', ['bookOsisId' => $canonical, 'chapterNumber' => $chapterNumber], 301);
        }

        abort_unless($this->bibleService->chapterExists($bookOsisId, $chapterNumber), 404);

        ResumePosition::remember($bookOsisId, $chapterNumber);

        return view('livewire-chapter', [
            'bookOsisId' => $bookOsisId,
            'chapterNumber' => $chapterNumber,
            'meta' => $this->meta($bookOsisId, $chapterNumber),
        ]);
    }

    /**
     * <title>, description and canonical/Open Graph data for the page head (SEO-2): every chapter
     * used to be titled "Bible Chapter", with no description and no canonical URL.
     *
     * @return array{title: string, description: string, url: string}
     */
    private function meta(string $bookOsisId, int $chapterNumber): array
    {
        $book = $this->bibleService->getBooks()->firstWhere('osis_id', $bookOsisId);
        $translation = $this->bibleService->getCurrentTranslation()['name'] ?? 'King James Version';
        $heading = ($book['name'] ?? $bookOsisId) . ' ' . $chapterNumber;

        $first = $this->bibleService->getVerses("{$bookOsisId}.{$chapterNumber}")->first();
        $text = trim(preg_replace('/\s+/', ' ', html_entity_decode(strip_tags((string) ($first['text'] ?? '')), ENT_QUOTES | ENT_HTML5)));

        return [
            'title' => "{$heading} — {$translation}",
            'description' => $text === '' ? "{$heading}, {$translation}." : \Illuminate\Support\Str::limit("{$heading}: {$text}", 155, '…'),
            // From APP_URL, like the sitemap: the canonical must not depend on how the request arrived.
            'url' => rtrim((string) config('app.url'), '/') . route('chapters.show', ['bookOsisId' => $bookOsisId, 'chapterNumber' => $chapterNumber], false),
        ];
    }
}
