<?php

use App\Services\Parsers\ContainedOsisParser;

describe('ContainedOsisParser', function () {
    beforeEach(function () {
        $dom = new DOMDocument();
        $dom->load(base_path('assets/asv.osis.xml'));

        $xpath = new DOMXPath($dom);
        $xpath->registerNamespace('osis', 'http://www.bibletechnologies.net/2003/OSIS/namespace');

        $this->parser = new ContainedOsisParser($xpath);
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
        it('returns verses for a chapter', function () {
            $verses = $this->parser->getVerses('Gen.1');

            expect($verses)->toHaveCount(31);
            expect($verses->first())->toHaveKeys(['osis_id', 'verse_number', 'text']);
        });

        it('returns verses in order', function () {
            $verses = $this->parser->getVerses('Gen.1');

            expect($verses->first()['verse_number'])->toBe(1);
            expect($verses->last()['verse_number'])->toBe(31);
        });
    });

    describe('getVerseText', function () {
        it('extracts text from contained verses', function () {
            $text = $this->parser->getVerseText('Gen.1.1');

            expect($text)->toContain('In the beginning God created');
        });

        it('returns empty string for non-existent verse', function () {
            $text = $this->parser->getVerseText('Gen.999.999');

            expect($text)->toBe('');
        });
    });

    describe('getVersesParagraphStyle', function () {
        it('returns single paragraph with all verses', function () {
            $paragraphs = $this->parser->getVersesParagraphStyle('Gen.1');

            expect($paragraphs)->toHaveCount(1);
            expect($paragraphs->first()['verses'])->toHaveCount(31);
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

describe('ContainedOsisParser with Maori', function () {
    beforeEach(function () {
        $dom = new DOMDocument();
        $dom->load(base_path('assets/mao.osis.xml'));

        $xpath = new DOMXPath($dom);
        $xpath->registerNamespace('osis', 'http://www.bibletechnologies.net/2003/OSIS/namespace');

        $this->parser = new ContainedOsisParser($xpath);
    });

    describe('searchVerses with Maori text', function () {
        it('finds Maori verses containing search term', function () {
            $results = $this->parser->searchVerses('atua', 3);

            expect($results)->toHaveCount(3);
            expect($results->first()['text'])->toContain('atua');
        });
    });

    describe('getVerseText with Maori', function () {
        it('extracts Maori text correctly', function () {
            $text = $this->parser->getVerseText('Gen.1.1');

            expect($text)->toContain('He mea hanga na te atua');
        });
    });
});
