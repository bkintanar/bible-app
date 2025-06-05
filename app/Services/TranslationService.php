<?php

namespace App\Services;

use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class TranslationService
{
    /**
     * Get all available translations
     */
    public function getAvailableTranslations(): Collection
    {
        $readerType = config('bible.reader_type', 'database');

        if ($readerType === 'database') {
            // Get translations from database
            return $this->getAvailableTranslationsFromDatabase();
        }
        // Get translations from config file (for XML reader)
        return $this->getAvailableTranslationsFromConfig();

    }

    /**
     * Get available translations from database
     */
    private function getAvailableTranslationsFromDatabase(): Collection
    {
        try {
            return DB::table('bible_versions')
                ->where('canonical', true)
                ->orderBy('abbreviation')
                ->get()
                ->map(function ($version) {
                    return [
                        'key' => strtolower($version->abbreviation),
                        'name' => $version->title,
                        'short_name' => $version->abbreviation,
                        'language' => $this->getLanguageName($version->language),
                        'description' => $version->description,
                        'publisher' => $version->publisher,
                        'is_default' => strtolower($version->abbreviation) === 'kjv',
                    ];
                });
        } catch (\Exception $e) {
            // If database is not available, fall back to config
            return $this->getAvailableTranslationsFromConfig();
        }
    }

    /**
     * Get available translations from config file
     */
    private function getAvailableTranslationsFromConfig(): Collection
    {
        $translations = config('bible.translations', []);

        return collect($translations)->map(function ($config, $key) {
            return array_merge($config, ['key' => $key]);
        })->values();
    }

    /**
     * Convert language code to full language name
     */
    private function getLanguageName(string $languageCode): string
    {
        $languageMap = [
            'en' => 'English',
            'mi' => 'Māori',
            'es' => 'Spanish',
            'fr' => 'French',
            'de' => 'German',
            'pt' => 'Portuguese',
            'it' => 'Italian',
            'ru' => 'Russian',
            'zh' => 'Chinese',
            'ja' => 'Japanese',
            'ko' => 'Korean',
            'ar' => 'Arabic',
            'he' => 'Hebrew',
            'el' => 'Greek',
            'la' => 'Latin',
        ];

        return $languageMap[$languageCode] ?? ucfirst($languageCode);
    }

    /**
     * Get translation configuration by key
     */
    public function getTranslation(string $translationKey): ?array
    {
        $readerType = config('bible.reader_type', 'database');

        if ($readerType === 'database') {
            // Get translation from database
            return $this->getTranslationFromDatabase($translationKey);
        }
        // Get translation from config file
        return $this->getTranslationFromConfig($translationKey);

    }

    /**
     * Get translation from database
     */
    private function getTranslationFromDatabase(string $translationKey): ?array
    {
        try {
            $version = DB::table('bible_versions')
                ->whereRaw('LOWER(abbreviation) = ?', [strtolower($translationKey)])
                ->where('canonical', true)
                ->first();

            if ($version) {
                return [
                    'key' => strtolower($version->abbreviation),
                    'name' => $version->title,
                    'short_name' => $version->abbreviation,
                    'language' => $this->getLanguageName($version->language),
                    'description' => $version->description,
                    'publisher' => $version->publisher,
                    'is_default' => strtolower($version->abbreviation) === 'kjv',
                ];
            }

            return null;
        } catch (\Exception $e) {
            // If database is not available, fall back to config
            return $this->getTranslationFromConfig($translationKey);
        }
    }

    /**
     * Get translation from config file
     */
    private function getTranslationFromConfig(string $translationKey): ?array
    {
        $translation = config("bible.translations.{$translationKey}");

        if ($translation) {
            $translation['key'] = $translationKey;
            return $translation;
        }

        return null;
    }

    /**
     * Get default translation key
     */
    public function getDefaultTranslationKey(): string
    {
        $readerType = config('bible.reader_type', 'database');

        if ($readerType === 'database') {
            // Get default from database (first translation or KJV if available)
            try {
                $defaultVersion = DB::table('bible_versions')
                    ->whereRaw('LOWER(abbreviation) = ?', ['kjv'])
                    ->where('canonical', true)
                    ->first();

                if ($defaultVersion) {
                    return strtolower($defaultVersion->abbreviation);
                }

                // If no KJV, get the first available translation
                $firstVersion = DB::table('bible_versions')
                    ->where('canonical', true)
                    ->orderBy('abbreviation')
                    ->first();

                return $firstVersion ? strtolower($firstVersion->abbreviation) : 'kjv';
            } catch (\Exception $e) {
                // Fall back to config default
                return config('bible.default_translation', 'kjv');
            }
        } else {
            return config('bible.default_translation', 'kjv');
        }
    }

    /**
     * Get default translation configuration
     */
    public function getDefaultTranslation(): array
    {
        $defaultKey = $this->getDefaultTranslationKey();
        return $this->getTranslation($defaultKey) ?? [];
    }

    /**
     * Check if a translation exists
     */
    public function translationExists(string $translationKey): bool
    {
        return $this->getTranslation($translationKey) !== null;
    }

    /**
     * Get the OSIS file path for a translation
     */
    public function getOsisFilePath(string $translationKey): ?string
    {
        $translation = $this->getTranslationFromConfig($translationKey);

        if (! $translation) {
            return null;
        }

        $osisDirectory = config('bible.osis_directory', 'assets');
        $filename = $translation['filename'];

        $filePath = base_path("{$osisDirectory}/{$filename}");

        return file_exists($filePath) ? $filePath : null;
    }

    /**
     * Create an OsisReader instance for a specific translation
     */
    public function createReader(string $translationKey): ?OsisReader
    {
        $filePath = $this->getOsisFilePath($translationKey);

        if (! $filePath) {
            return null;
        }

        return new OsisReader($filePath);
    }

    /**
     * Get current translation from session or default
     */
    public function getCurrentTranslationKey(): string
    {
        return session('current_translation', $this->getDefaultTranslationKey());
    }

    /**
     * Set current translation in session
     */
    public function setCurrentTranslation(string $translationKey): void
    {
        if ($this->translationExists($translationKey)) {
            session(['current_translation' => $translationKey]);
        }
    }

    /**
     * Get current translation configuration
     */
    public function getCurrentTranslation(): array
    {
        $currentKey = $this->getCurrentTranslationKey();
        return $this->getTranslation($currentKey) ?? $this->getDefaultTranslation();
    }

    /**
     * Create an OsisReader for the current translation
     */
    public function getCurrentReader(): ?OsisReader
    {
        $currentKey = $this->getCurrentTranslationKey();
        return $this->createReader($currentKey);
    }
}
