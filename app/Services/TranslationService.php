<?php

namespace App\Services;

use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * Which translations readers may choose: exactly those projected into bible_versions.
 *
 * Database only. The XML reader and its config-backed translation list are gone (plan 9.1): they
 * offered translations (ASV, Maori) that were never projected, so choosing one broke the reader.
 * One query per request - the list is memoised and every lookup is answered from it.
 */
class TranslationService
{
    private ?Collection $available = null;

    public function getAvailableTranslations(): Collection
    {
        return $this->available ??= $this->loadAvailableTranslations();
    }

    private function loadAvailableTranslations(): Collection
    {
        try {
            return DB::table('bible_versions')
                ->where('canonical', true)
                ->orderBy('abbreviation')
                ->get()
                ->map(fn ($version) => [
                    'key' => strtolower($version->abbreviation),
                    'name' => $version->title,
                    'short_name' => $version->abbreviation,
                    'language' => $this->getLanguageName($version->language),
                    'description' => $version->description,
                    'publisher' => $version->publisher,
                    'is_default' => strtolower($version->abbreviation) === 'kjv',
                ]);
        } catch (\Exception $e) {
            // Offer nothing rather than translations that may not be projected; the page still
            // renders in the default translation.
            Log::warning('Translations unavailable', ['exception' => $e]);

            return collect();
        }
    }

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

    public function getTranslation(string $translationKey): ?array
    {
        return $this->getAvailableTranslations()->firstWhere('key', strtolower($translationKey));
    }

    /** KJV if projected, else the first projected translation, else the configured default. */
    public function getDefaultTranslationKey(): string
    {
        $available = $this->getAvailableTranslations();

        if ($available->isEmpty()) {
            return config('bible.default_translation', 'kjv');
        }

        return $available->firstWhere('key', 'kjv') ? 'kjv' : $available->first()['key'];
    }

    public function getDefaultTranslation(): array
    {
        return $this->getTranslation($this->getDefaultTranslationKey())
            ?? ['key' => $this->getDefaultTranslationKey(), 'name' => 'King James Version', 'short_name' => 'KJV'];
    }

    public function translationExists(string $translationKey): bool
    {
        return $this->getTranslation($translationKey) !== null;
    }

    public function getCurrentTranslationKey(): string
    {
        return session('current_translation') ?? $this->getDefaultTranslationKey();
    }

    public function setCurrentTranslation(string $translationKey): void
    {
        if ($this->translationExists($translationKey)) {
            session(['current_translation' => strtolower($translationKey)]);
        }
    }

    public function getCurrentTranslation(): array
    {
        return $this->getTranslation($this->getCurrentTranslationKey()) ?? $this->getDefaultTranslation();
    }
}
