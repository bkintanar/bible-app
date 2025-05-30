<?php

use App\Services\Parsers\MilestoneOsisParser;

describe('MilestoneOsisParser', function () {
    beforeEach(function () {
        $dom = new DOMDocument();
        $dom->load(base_path('assets/kjv.osis.xml'));

        $xpath = new DOMXPath($dom);
        $xpath->registerNamespace('osis', 'http://www.bibletechnologies.net/2003/OSIS/namespace');

        $this->parser = new MilestoneOsisParser($xpath);
    });

    describe('getChapters', function () {
        it('returns chapters for a book', function () {
            $chapters = $this->parser->getChapters('Gen');

            expect($chapters)->not->toBeEmpty();
            expect($chapters->first())->toHaveKeys(['osis_ref', 'chapter_number', 'verse_count']);
        });

        it('returns correct chapter count for Genesis', function () {
            $chapters = $this->parser->getChapters('Gen');

            expect($chapters)->toHaveCount(50);
        });

        it('returns chapters in correct order', function () {
            $chapters = $this->parser->getChapters('Gen');

            expect($chapters->first()['chapter_number'])->toBe(1);
            expect($chapters->last()['chapter_number'])->toBe(50);
        });
    });

    describe('getVerses', function () {
        it('returns verses with correct structure and order', function () {
            // Use Ruth 1 instead of Genesis 1 for faster testing
            $verses = $this->parser->getVerses('Ruth.1');

            expect($verses->count())->toBeGreaterThan(5); // Ruth 1 has 22 verses
            expect($verses->first())->toHaveKeys(['osis_id', 'verse_number', 'text']);

            // Test ordering in same test
            expect($verses->first()['verse_number'])->toBe(1);
            expect($verses->last()['verse_number'])->toBeGreaterThan(5);
        });
    });

    describe('getVerseText', function () {
        it('extracts text from milestone verses', function () {
            $text = $this->parser->getVerseText('Gen.1.1');

            expect($text)->toContain('In the beginning God created');
        });

        it('returns empty string for non-existent verse', function () {
            $text = $this->parser->getVerseText('Gen.999.999');

            expect($text)->toBe('');
        });
    });

    describe('getVersesParagraphStyle', function () {
        it('returns paragraph structure', function () {
            $paragraphs = $this->parser->getVersesParagraphStyle('Gen.1');

            expect($paragraphs)->not->toBeEmpty();
            expect($paragraphs->first())->toHaveKeys(['verses', 'combined_text']);
        });
    });

    describe('searchVerses', function () {
        it('finds verses containing search term', function () {
            $results = $this->parser->searchVerses('God', 5);

            expect($results)->toHaveCount(5);
            expect($results->first())->toHaveKeys(['osis_id', 'book_id', 'chapter', 'verse', 'text', 'context']);
        });

        it('highlights search terms', function () {
            $results = $this->parser->searchVerses('God', 1);

            expect($results->first()['context'])->toContain('<mark>');
        });
    });
});
