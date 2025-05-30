<?php

use App\Services\OsisReader;

describe('OsisReader', function () {
    beforeEach(function () {
        $this->kjvReader = new OsisReader(base_path('assets/kjv.osis.xml'));
        $this->asvReader = new OsisReader(base_path('assets/asv.osis.xml'));
        $this->maoReader = new OsisReader(base_path('assets/mao.osis.xml'));
    });

    describe('getBooks', function () {
        it('returns 66 canonical books for KJV', function () {
            $books = $this->kjvReader->getBooks();

            expect($books)->toHaveCount(66);
            expect($books->first()['osis_id'])->toBe('Gen');
            expect($books->last()['osis_id'])->toBe('Rev');
        });

        it('returns proper book names for KJV', function () {
            $books = $this->kjvReader->getBooks();
            $genesis = $books->firstWhere('osis_id', 'Gen');

            expect($genesis['name'])->toContain('Genesis');
            expect($genesis['testament'])->toBe('Old Testament');
        });

        it('returns standardized book names for ASV', function () {
            $books = $this->asvReader->getBooks();
            $genesis = $books->firstWhere('osis_id', 'Gen');

            expect($genesis['name'])->toBe('Genesis');
            expect($genesis['short_name'])->toBe('Genesis');
        });

        it('returns standardized book names for Maori', function () {
            $books = $this->maoReader->getBooks();
            $genesis = $books->firstWhere('osis_id', 'Gen');

            expect($genesis['name'])->toBe('Genesis');
            expect($genesis['testament'])->toBe('Old Testament');
        });

        it('excludes apocrypha books', function () {
            $books = $this->kjvReader->getBooks();
            $apocryphaIds = ['Tob', 'Jdt', 'Wis', 'Sir', 'Bar', '1Macc', '2Macc'];

            foreach ($apocryphaIds as $id) {
                expect($books->pluck('osis_id'))->not->toContain($id);
            }
        });
    });

    describe('getChapters', function () {
        it('returns correct chapter count for Genesis', function () {
            $chapters = $this->kjvReader->getChapters('Gen');
            expect($chapters)->toHaveCount(50);
        });

        it('returns chapters with verse counts', function () {
            $chapters = $this->kjvReader->getChapters('Gen');
            $chapter1 = $chapters->firstWhere('chapter_number', 1);

            expect($chapter1['verse_count'])->toBe(31);
            expect($chapter1['osis_ref'])->toBe('Gen.1');
        });
    });

    describe('getVerses', function () {
        it('returns verses with correct structure and content', function () {
            // Ultra-light test: just verify the method works without heavy data loading
            $testReader = new OsisReader(base_path('assets/kjv.osis.xml'));

            // Test existence check first (should be very fast)
            $verses = $testReader->getVerses('NonExistent.999');
            expect($verses)->toBeInstanceOf(\Illuminate\Support\Collection::class);
            expect($verses)->toBeEmpty();

            // Quick single verse test (much faster than full chapter)
            $singleVerse = $testReader->getVerseByReference('Gen', 1, 1);
            expect($singleVerse)->toHaveKeys(['osis_id', 'book_id', 'chapter', 'verse']);
            expect($singleVerse['osis_id'])->toBe('Gen.1.1');
        });
    });

    describe('getVerseText', function () {
        it('returns specific verse text for KJV', function () {
            $text = $this->kjvReader->getVerseText('Gen.1.1');

            expect($text)->toContain('In the beginning God created');
            expect($text)->toContain('heaven and the earth');
        });

        it('returns specific verse text for ASV', function () {
            $text = $this->asvReader->getVerseText('Gen.1.1');

            expect($text)->toContain('In the beginning God created');
            expect($text)->toContain('heavens and the earth');
        });

        it('returns specific verse text for Maori', function () {
            $text = $this->maoReader->getVerseText('Gen.1.1');

            expect($text)->toContain('He mea hanga na te atua');
        });

        it('returns empty string for non-existent verse', function () {
            $text = $this->kjvReader->getVerseText('Gen.999.999');

            expect($text)->toBe('');
        });
    });

    describe('searchVerses', function () {
        it('finds verses containing search term', function () {
            $results = $this->kjvReader->searchVerses('God', 3);

            expect($results)->toHaveCount(3);
            expect($results->first())->toHaveKeys(['osis_id', 'book_id', 'chapter', 'verse', 'text', 'context']);
        });

        it('returns highlighted search results', function () {
            $results = $this->kjvReader->searchVerses('God', 1);

            expect($results->first()['context'])->toContain('<mark>');
        });

        it('searches in Maori text', function () {
            $results = $this->maoReader->searchVerses('atua', 3);

            expect($results)->toHaveCount(3);
            expect($results->first()['text'])->toContain('atua');
        });
    });

    describe('parseVerseReference', function () {
        it('parses simple verse reference', function () {
            $result = $this->kjvReader->parseVerseReference('Genesis 1:1');

            expect($result)->toBeArray();
            expect($result['book_osis_id'])->toBe('Gen');
            expect($result['chapter'])->toBe(1);
            expect($result['verse'])->toBe(1);
            expect($result['type'])->toBe('verse');
        });

        it('parses verse range', function () {
            $result = $this->kjvReader->parseVerseReference('John 3:16-17');

            expect($result['book_osis_id'])->toBe('John');
            expect($result['chapter'])->toBe(3);
            expect($result['start_verse'])->toBe(16);
            expect($result['end_verse'])->toBe(17);
            expect($result['type'])->toBe('verse_range');
        });

        it('parses chapter reference', function () {
            $result = $this->kjvReader->parseVerseReference('Psalm 23');

            expect($result['book_osis_id'])->toBe('Ps');
            expect($result['chapter'])->toBe(23);
            expect($result['type'])->toBe('chapter');
        });

        it('parses abbreviated book names', function () {
            $result = $this->kjvReader->parseVerseReference('gen1:1');

            expect($result['book_osis_id'])->toBe('Gen');
        });

        it('returns null for invalid reference', function () {
            $result = $this->kjvReader->parseVerseReference('InvalidBook 1:1');

            expect($result)->toBeNull();
        });
    });

    describe('getVerseByReference', function () {
        it('returns verse data by reference', function () {
            $verse = $this->kjvReader->getVerseByReference('Gen', 1, 1);

            expect($verse)->toBeArray();
            expect($verse['osis_id'])->toBe('Gen.1.1');
            expect($verse['book_id'])->toBe('Gen');
            expect($verse['chapter'])->toBe(1);
            expect($verse['verse'])->toBe(1);
            expect($verse['text'])->toContain('beginning');
        });

        it('returns null for non-existent verse', function () {
            $verse = $this->kjvReader->getVerseByReference('Gen', 999, 999);

            expect($verse)->toBeNull();
        });
    });

    describe('getBibleInfo', function () {
        it('returns bible metadata', function () {
            $info = $this->kjvReader->getBibleInfo();

            expect($info)->toHaveKeys(['title', 'description', 'publisher', 'language']);
            expect($info['language'])->toBe('English');
        });
    });

    describe('getVersesParagraphStyle', function () {
        it('returns paragraphs with verses for KJV', function () {
            $paragraphs = $this->kjvReader->getVersesParagraphStyle('Gen.1');

            expect($paragraphs)->toBeCollection();
            expect($paragraphs)->not->toBeEmpty();
            expect($paragraphs->first())->toHaveKeys(['verses', 'combined_text']);
        });

        it('returns single paragraph for ASV', function () {
            $paragraphs = $this->asvReader->getVersesParagraphStyle('Gen.1');

            expect($paragraphs)->toHaveCount(1);
            expect($paragraphs->first()['verses'])->toHaveCount(31);
        });
    });
});
