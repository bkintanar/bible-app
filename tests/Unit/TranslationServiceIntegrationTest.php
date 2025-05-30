<?php

use App\Services\TranslationService;
use App\Services\OsisReader;

describe('TranslationService Integration', function () {
    beforeEach(function () {
        $this->service = app(TranslationService::class);
    });

    describe('real translation management', function () {
        it('manages real translation configurations', function () {
            $translations = $this->service->getAvailableTranslations();

            expect($translations)->toBeInstanceOf(\Illuminate\Support\Collection::class);
            expect($translations->count())->toBeGreaterThan(0);

            $kjv = $translations->firstWhere('key', 'kjv');
            expect($kjv)->not->toBeNull();
            expect($kjv['name'])->toBe('King James Version');
        });

        it('creates real OSIS readers', function () {
            $reader = $this->service->createReader('kjv');

            expect($reader)->toBeInstanceOf(OsisReader::class);

            // Test actual functionality
            $books = $reader->getBooks();
            expect($books->count())->toBe(66);
            expect($books->first()['osis_id'])->toBe('Gen');
        });

        it('handles session state correctly', function () {
            // Test setting and getting current translation
            $this->service->setCurrentTranslation('asv');
            $current = $this->service->getCurrentTranslationKey();
            expect($current)->toBe('asv');

            // Test reader creation for current translation
            $reader = $this->service->getCurrentReader();
            expect($reader)->toBeInstanceOf(OsisReader::class);

            // Verify it's actually ASV - check actual content instead
            $verse = $reader->getVerseText('Gen.1.1');
            expect($verse)->toContain('heavens and the earth'); // ASV specific
        });

        it('validates translation existence correctly', function () {
            expect($this->service->translationExists('kjv'))->toBeTrue();
            expect($this->service->translationExists('asv'))->toBeTrue();
            expect($this->service->translationExists('mao'))->toBeTrue();
            expect($this->service->translationExists('invalid'))->toBeFalse();
        });

        it('provides correct file paths', function () {
            $kjvPath = $this->service->getOsisFilePath('kjv');
            expect($kjvPath)->not->toBeNull();
            expect(file_exists($kjvPath))->toBeTrue();
            expect($kjvPath)->toEndWith('kjv.osis.xml');
        });
    });

    describe('cross-translation compatibility', function () {
        it('reads Genesis 1:1 from all translations', function () {
            // Test KJV
            $kjvReader = $this->service->createReader('kjv');
            $kjvVerse = $kjvReader->getVerseText('Gen.1.1');
            expect($kjvVerse)->not->toBeEmpty();
            expect($kjvVerse)->toContain('God');
            expect($kjvVerse)->toContain('created');
            expect($kjvVerse)->toContain('beginning');

            // Test ASV
            $asvReader = $this->service->createReader('asv');
            $asvVerse = $asvReader->getVerseText('Gen.1.1');
            expect($asvVerse)->not->toBeEmpty();
            expect($asvVerse)->toContain('God');
            expect($asvVerse)->toContain('created');
            expect($asvVerse)->toContain('beginning');

            // Test Māori (different language)
            $maoReader = $this->service->createReader('mao');
            $maoVerse = $maoReader->getVerseText('Gen.1.1');
            expect($maoVerse)->not->toBeEmpty();
            expect($maoVerse)->toContain('atua'); // God in Māori
        });

        it('maintains consistent book structure across translations', function () {
            $kjvReader = $this->service->createReader('kjv');
            $asvReader = $this->service->createReader('asv');

            $kjvBooks = $kjvReader->getBooks();
            $asvBooks = $asvReader->getBooks();

            expect($kjvBooks->count())->toBe($asvBooks->count());
            expect($kjvBooks->pluck('osis_id'))->toEqual($asvBooks->pluck('osis_id'));
        });
    });
});
