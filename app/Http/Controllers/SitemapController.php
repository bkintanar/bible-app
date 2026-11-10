<?php

namespace App\Http\Controllers;

use App\Services\BibleService;
use App\Support\ProjectionCache;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Cache;

class SitemapController extends Controller
{
    /**
     * GET /sitemap.xml - every chapter of the projected Bible (1,189 for the KJV), canonical URLs.
     * Built from the projected chapters (not counts: chapter numbers are what exists) and cached
     * with the projection, so a re-projection that changes chapters changes the sitemap.
     */
    public function __invoke(BibleService $bible): Response
    {
        $xml = Cache::remember(ProjectionCache::key('sitemap_xml'), 86400, function () use ($bible) {
            $lines = ['<?xml version="1.0" encoding="UTF-8"?>', '<urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9">'];

            foreach ($bible->getBooks() as $book) {
                foreach ($bible->getChapters($book['osis_id']) as $chapter) {
                    $lines[] = '  <url><loc>' . e(route('chapters.show', ['bookOsisId' => $book['osis_id'], 'chapterNumber' => $chapter['chapter_number']])) . '</loc></url>';
                }
            }

            $lines[] = '</urlset>';

            return implode("\n", $lines) . "\n";
        });

        return response($xml, 200, ['Content-Type' => 'application/xml; charset=UTF-8']);
    }
}
