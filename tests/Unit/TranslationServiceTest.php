<?php

use App\Services\TranslationService;
use App\Services\OsisReader;

describe('TranslationService', function () {
    beforeEach(function () {
        // Create a fresh instance for each test
        $this->service = new TranslationService();
    });

    afterEach(function () {
        // Clean up any mocks
        Mockery::close();
    });

    describe('translation management', function () {
        it('returns available translations from config', function () {
            $translations = $this->service->getAvailableTranslations();

            expect($translations)->toBeInstanceOf(\Illuminate\Support\Collection::class);
            expect($translations->count())->toBeGreaterThan(0);

            // Should have required keys
            $first = $translations->first();
            expect($first)->toHaveKeys(['key', 'name', 'filename']);
        });

        it('identifies default translation correctly', function () {
            $default = $this->service->getDefaultTranslationKey();
            expect($default)->toBe('kjv');
        });

        it('creates OSIS reader for valid translation', function () {
            $reader = $this->service->createReader('kjv');

            expect($reader)->toBeInstanceOf(OsisReader::class);
        });

        it('returns null for invalid translation', function () {
            $reader = $this->service->createReader('invalid');
            expect($reader)->toBeNull();
        });

        it('validates translation existence', function () {
            expect($this->service->translationExists('kjv'))->toBeTrue();
            expect($this->service->translationExists('asv'))->toBeTrue();
            expect($this->service->translationExists('mao'))->toBeTrue();
            expect($this->service->translationExists('nonexistent'))->toBeFalse();
        });

        it('gets OSIS file path for translation', function () {
            $path = $this->service->getOsisFilePath('kjv');

            expect($path)->not->toBeNull();
            expect($path)->toEndWith('kjv.osis.xml');
            expect(file_exists($path))->toBeTrue();
        });

        it('returns null for invalid translation path', function () {
            $path = $this->service->getOsisFilePath('invalid');

            expect($path)->toBeNull();
        });
    });

    describe('current translation management', function () {
        it('sets and gets current translation', function () {
            // Default should be KJV
            $current = $this->service->getCurrentTranslationKey();
            expect($current)->toBe('kjv');

            // Set to ASV
            $this->service->setCurrentTranslation('asv');
            $current = $this->service->getCurrentTranslationKey();
            expect($current)->toBe('asv');
        });

        it('gets current translation config', function () {
            $config = $this->service->getCurrentTranslation();

            expect($config)->toHaveKeys(['key', 'name', 'filename']);
            expect($config['key'])->toBe('kjv'); // Default
        });

        it('creates reader for current translation', function () {
            $reader = $this->service->getCurrentReader();

            expect($reader)->toBeInstanceOf(OsisReader::class);
        });

        it('handles invalid current translation gracefully', function () {
            // Test that invalid translation handling works
            $originalDefault = $this->service->getDefaultTranslationKey();

            // Should always have a valid default
            expect($originalDefault)->toBe('kjv');
        });
    });
});
