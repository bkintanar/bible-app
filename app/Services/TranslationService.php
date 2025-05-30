<?php

namespace App\Services;

use App\Services\OsisReader;
use Illuminate\Support\Collection;

class TranslationService
{
    /**
     * Get all available translations
     */
    public function getAvailableTranslations(): Collection
    {
        $translations = config('bible.translations', []);

        return collect($translations)->map(function ($config, $key) {
            return array_merge($config, ['key' => $key]);
        })->values();
    }

    /**
     * Get translation configuration by key
     */
    public function getTranslation(string $translationKey): ?array
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
        return config('bible.default_translation', 'kjv');
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
        $translation = $this->getTranslation($translationKey);

        if (!$translation) {
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

        if (!$filePath) {
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
