<?php

namespace App\Services;

use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class TranslationService
{
    /** @var array<string, ?array> */
    private array $translations = [];

    private ?string $defaultKey = null;

    private ?Collection $available = null;

    /**
     * Get all available translations
     */
    public function getAvailableTranslations(): Collection
    {
        $readerType = config('bible.reader_type', 'database');

        if ($readerType === 'database') {
            // One query per request: single-translation lookups and the default are answered from
            // this list too (they asked bible_versions the same question three times).
            return $this->available ??= $this->getAvailableTranslationsFromDatabase();
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
            // Offer nothing rather than config entries that may not be projected (the reader
            // would then fail on them). The page still renders in the default translation.
            Log::warning('Translations unavailable', ['exception' => $e]);

            return collect();
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
        // Memoised: a chapter view asked for the same translation ~8 times, a query each.
        if (array_key_exists($translationKey, $this->translations)) {
            return $this->translations[$translationKey];
        }

        return $this->translations[$translationKey] = $this->lookUpTranslation($translationKey);
    }

    private function lookUpTranslation(string $translationKey): ?array
    {
        $readerType = config('bible.reader_type', 'database');

        if ($readerType === 'database') {
            // Same filter (canonical) and shape as the available list.
            return $this->getAvailableTranslations()->firstWhere('key', strtolower($translationKey));
        }
        // Get translation from config file
        return $this->getTranslationFromConfig($translationKey);

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
            // KJV if projected, else the first available translation.
            $available = $this->getAvailableTranslations();

            if ($available->isEmpty()) {
                return config('bible.default_translation', 'kjv');
            }

            return $available->firstWhere('key', 'kjv') ? 'kjv' : $available->first()['key'];
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
        // session(key, default) evaluated the default (a database query) on every call, even when
        // the session already had a translation.
        return session('current_translation') ?? ($this->defaultKey ??= $this->getDefaultTranslationKey());
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
