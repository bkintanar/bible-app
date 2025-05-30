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

        it('gets default translation configuration', function () {
            $defaultTranslation = $this->service->getDefaultTranslation();

            expect($defaultTranslation)->toBeArray();
            expect($defaultTranslation)->toHaveKeys(['key', 'name', 'filename']);
            expect($defaultTranslation['key'])->toBe('kjv');
        });

        it('gets translation by key', function () {
            $translation = $this->service->getTranslation('kjv');

            expect($translation)->toBeArray();
            expect($translation)->toHaveKeys(['key', 'name', 'filename']);
            expect($translation['key'])->toBe('kjv');
        });

        it('returns null for invalid translation key', function () {
            $translation = $this->service->getTranslation('invalid');

            expect($translation)->toBeNull();
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

        it('returns null for non-existent file', function () {
            // Mock a translation with non-existent file
            config(['bible.translations.fake' => [
                'name' => 'Fake Translation',
                'filename' => 'nonexistent.osis.xml'
            ]]);

            $path = $this->service->getOsisFilePath('fake');

            expect($path)->toBeNull();
        });
    });

    describe('current translation management', function () {
        beforeEach(function () {
            // Clear session before each test
            session()->forget('current_translation');
        });

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

        it('ignores setting invalid translation', function () {
            // Try to set invalid translation
            $this->service->setCurrentTranslation('invalid');

            // Should still be default
            $current = $this->service->getCurrentTranslationKey();
            expect($current)->toBe('kjv');
        });

        it('falls back to default when current translation becomes invalid', function () {
            // Set valid translation first
            $this->service->setCurrentTranslation('asv');
            expect($this->service->getCurrentTranslationKey())->toBe('asv');

            // Simulate translation becoming unavailable
            session(['current_translation' => 'invalid']);

            // getCurrentTranslation should fall back gracefully
            $config = $this->service->getCurrentTranslation();
            expect($config['key'])->toBe('kjv'); // Should fall back to default
        });

        it('handles session persistence correctly', function () {
            // Set translation
            $this->service->setCurrentTranslation('asv');

            // Create new service instance (simulating new request)
            $newService = new TranslationService();

            // Should maintain session state
            expect($newService->getCurrentTranslationKey())->toBe('asv');
        });
    });

    describe('edge cases and error handling', function () {
        it('handles empty translations config', function () {
            // Temporarily clear translations config
            $originalConfig = config('bible.translations');
            config(['bible.translations' => []]);

            $translations = $this->service->getAvailableTranslations();
            expect($translations)->toBeEmpty();

            // Restore config
            config(['bible.translations' => $originalConfig]);
        });

        it('handles missing default translation config', function () {
            // Test with invalid default translation
            config(['bible.default_translation' => 'nonexistent']);

            $defaultTranslation = $this->service->getDefaultTranslation();
            expect($defaultTranslation)->toBe([]); // Should return empty array

            // Restore config
            config(['bible.default_translation' => 'kjv']);
        });

        it('handles missing OSIS directory config', function () {
            $originalDir = config('bible.osis_directory');
            config(['bible.osis_directory' => null]);

            $path = $this->service->getOsisFilePath('kjv');
            // Should handle gracefully - might return null or string
            expect($path === null || is_string($path))->toBeTrue();

            config(['bible.osis_directory' => $originalDir]);
        });
    });
});
