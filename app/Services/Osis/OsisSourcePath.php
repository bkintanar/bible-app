<?php

namespace App\Services\Osis;

use App\Models\BibleVersion;
use InvalidArgumentException;

/**
 * The single place that turns a translation key into the path of its OSIS source file.
 *
 * The OSIS XML is the source of truth and the editor writes it through this path, so:
 *  - the key is validated (it reaches here from client-side Livewire state; "../x" must not
 *    escape the OSIS directory);
 *  - only translations that are actually projected into the database qualify - writing the
 *    source of an unprojected translation (ASV, MAO today) and then "re-projecting" it would
 *    create a second, mostly empty bible_versions row;
 *  - the directory comes from config('bible.osis_directory'), relative to the base path or
 *    absolute, so tests can point it at a temporary copy instead of the real 27MB source.
 */
final class OsisSourcePath
{
    public static function directory(): string
    {
        $dir = (string) config('bible.osis_directory', 'assets');

        return rtrim(str_starts_with($dir, '/') ? $dir : base_path($dir), '/');
    }

    public static function defaultKey(): string
    {
        return strtolower((string) config('bible.default_translation', 'kjv'));
    }

    /**
     * @throws InvalidArgumentException for a malformed key or a translation that is not projected
     */
    public static function for(?string $translationKey = null): string
    {
        $key = strtolower(trim($translationKey ?? self::defaultKey()));

        if (! preg_match('/^[a-z0-9_-]+$/', $key)) {
            throw new InvalidArgumentException("Invalid translation key [{$translationKey}].");
        }

        if (! self::isProjected($key)) {
            throw new InvalidArgumentException("Translation [{$key}] is not projected into the database; its source cannot be edited here.");
        }

        return self::directory() . "/{$key}.osis.xml";
    }

    public static function isProjected(string $key): bool
    {
        return BibleVersion::where('abbreviation', strtoupper($key))->exists();
    }
}
