<?php

describe('Bible Configuration', function () {
    beforeEach(function () {
        $this->config = config('bible');
    });

    describe('basic structure', function () {
        it('has required configuration keys', function () {
            expect($this->config)->toHaveKeys(['translations', 'default_translation', 'osis_directory']);
        });

        it('has translations array', function () {
            expect($this->config['translations'])->toBeArray();
            expect($this->config['translations'])->not->toBeEmpty();
        });

        it('has valid default translation', function () {
            $default = $this->config['default_translation'];

            expect($default)->toBeString();
            expect($this->config['translations'])->toHaveKey($default);
        });

        it('has valid osis directory', function () {
            $directory = $this->config['osis_directory'];

            expect($directory)->toBeString();
            expect($directory)->toBe('assets');
        });
    });

    describe('translation entries', function () {
        it('has all required translations', function () {
            $translations = $this->config['translations'];

            expect($translations)->toHaveKeys(['kjv', 'asv', 'mao']);
        });

        it('has complete metadata for each translation', function () {
            $translations = $this->config['translations'];

            foreach ($translations as $key => $translation) {
                expect($translation)->toHaveKeys([
                    'name', 'short_name', 'language', 'year',
                    'filename', 'description', 'is_default'
                ], "Translation $key missing required keys");

                expect($translation['name'])->toBeString();
                expect($translation['short_name'])->toBeString();
                expect($translation['language'])->toBeString();
                expect($translation['year'])->toBeString();
                expect($translation['filename'])->toBeString();
                expect($translation['description'])->toBeString();
                expect($translation['is_default'])->toBeBool();
            }
        });

        it('has exactly one default translation', function () {
            $translations = $this->config['translations'];
            $defaults = array_filter($translations, fn($t) => $t['is_default']);

            expect($defaults)->toHaveCount(1);
            expect(array_keys($defaults)[0])->toBe($this->config['default_translation']);
        });

        it('has valid file extensions', function () {
            $translations = $this->config['translations'];

            foreach ($translations as $key => $translation) {
                expect($translation['filename'])->toEndWith('.osis.xml',
                    "Translation $key filename should end with .osis.xml");
            }
        });
    });

    describe('file existence', function () {
        it('has accessible OSIS files for all translations', function () {
            $translations = $this->config['translations'];
            $directory = $this->config['osis_directory'];

            foreach ($translations as $key => $translation) {
                $filePath = base_path($directory . '/' . $translation['filename']);

                expect(file_exists($filePath))->toBeTrue(
                    "OSIS file not found for translation $key: {$translation['filename']}"
                );

                expect(is_readable($filePath))->toBeTrue(
                    "OSIS file not readable for translation $key: {$translation['filename']}"
                );
            }
        });

        it('has valid XML files', function () {
            $translations = $this->config['translations'];
            $directory = $this->config['osis_directory'];

            foreach ($translations as $key => $translation) {
                $filePath = base_path($directory . '/' . $translation['filename']);

                $dom = new DOMDocument();
                $loadResult = @$dom->load($filePath);

                expect($loadResult)->toBeTrue(
                    "Invalid XML file for translation $key: {$translation['filename']}"
                );

                // Verify it's an OSIS file
                expect($dom->documentElement->tagName)->toBe('osis',
                    "File is not a valid OSIS document for translation $key");
            }
        });
    });

    describe('translation metadata validation', function () {
        it('has proper KJV configuration', function () {
            $kjv = $this->config['translations']['kjv'];

            expect($kjv['name'])->toBe('King James Version');
            expect($kjv['short_name'])->toBe('KJV');
            expect($kjv['language'])->toBe('English');
            expect($kjv['year'])->toBe('1769');
            expect($kjv['is_default'])->toBeTrue();
        });

        it('has proper ASV configuration', function () {
            $asv = $this->config['translations']['asv'];

            expect($asv['name'])->toBe('American Standard Version');
            expect($asv['short_name'])->toBe('ASV');
            expect($asv['language'])->toBe('English');
            expect($asv['year'])->toBe('1901');
            expect($asv['is_default'])->toBeFalse();
        });

        it('has proper Maori configuration', function () {
            $mao = $this->config['translations']['mao'];

            expect($mao['name'])->toBe('Maori Version');
            expect($mao['short_name'])->toBe('MAO');
            expect($mao['language'])->toBe('Māori');
            expect($mao['year'])->toBe('2009');
            expect($mao['is_default'])->toBeFalse();
        });
    });
});
