<?php

namespace App\Services\Osis;

use Illuminate\Support\Facades\Log;

/**
 * Writes the OSIS source of truth: locked, atomic, validated - plus the pure title transforms the
 * editor applies under that lock (pure so they can be tested without touching any file).
 *
 * Moved out of the VerseTitleManager Livewire component unchanged in behaviour, so it can be unit
 * tested against a temporary copy instead of the real 27MB source.
 */
final class OsisSourceWriter
{
    /** Statuses returned by update(). */
    public const STATUSES = ['ok', 'busy', 'lock_failed', 'read_failed', 'transform_failed', 'unchanged', 'invalid_xml', 'write_failed'];

    /**
     * Read-modify-write the OSIS source under an exclusive lock, replacing it atomically.
     *
     * The XML is the source of truth, which raises the bar on this write considerably:
     *  - LOST UPDATES: the transform runs INSIDE the lock, so two concurrent saves serialise.
     *  - TORN FILE: we write a sibling temp file and rename() it over the original; rename is
     *    atomic within a filesystem, so a reader sees the old file or the new one, never half.
     *  - NO VALIDATION: the temp file is parsed (streamed) before it is promoted.
     *
     * The lock lives in a sidecar .lock file rather than on the source itself: rename() replaces
     * the inode, so a writer waiting on the ORIGINAL inode would wake holding a lock on an unlinked
     * file and read stale content. The sidecar is never replaced. (osis:import takes the same lock,
     * shared, while it reads.)
     *
     * @param  callable(string): ?string  $transform  current contents -> new contents, or null to abort
     * @return string one of self::STATUSES
     */
    public function update(string $path, callable $transform, float $waitSeconds = 10.0): string
    {
        $lockPath = $path . '.lock';
        $lock = @fopen($lockPath, 'c');

        if ($lock === false) {
            Log::error('Cannot open OSIS lock file', ['lock' => $lockPath, 'last_error' => error_get_last()['message'] ?? null]);

            return 'lock_failed';
        }

        // Shared between artisan (often root) and PHP-FPM (www-data): whoever creates it first
        // would otherwise lock the other out with its umask.
        @chmod($lockPath, 0664);

        // Bounded wait rather than blocking forever: a stuck writer must not pin a worker.
        $deadline = microtime(true) + $waitSeconds;
        $acquired = false;

        do {
            if (flock($lock, LOCK_EX | LOCK_NB)) {
                $acquired = true;
                break;
            }
            usleep(100_000);
        } while (microtime(true) < $deadline);

        if (! $acquired) {
            fclose($lock);

            return 'busy';
        }

        $tmp = null;

        try {
            $current = @file_get_contents($path);

            if ($current === false) {
                Log::error('Cannot read OSIS source', ['path' => $path, 'last_error' => error_get_last()['message'] ?? null]);

                return 'read_failed';
            }

            $new = $transform($current);

            if ($new === null) {
                Log::error('OSIS transform failed', ['path' => $path, 'preg' => preg_last_error_msg()]);

                return 'transform_failed';
            }

            if ($new === $current) {
                return 'unchanged';
            }

            // Temp file must be a sibling so rename() stays within the filesystem.
            $tmp = $path . '.tmp.' . getmypid();

            // rename() gives the new file the CREATING process's uid/gid and umask; restore the
            // original's, or a write by root resets a root:www-data 664 source to root:root 644.
            $original = @stat($path);

            if (@file_put_contents($tmp, $new) !== strlen($new)) {
                Log::error('Cannot write OSIS temp file', ['tmp' => $tmp, 'last_error' => error_get_last()['message'] ?? null]);

                return 'write_failed';
            }

            if ($original !== false) {
                @chmod($tmp, $original['mode'] & 0777);
                @chgrp($tmp, $original['gid']); // chgrp/chown only succeed as root; no-ops otherwise
                @chown($tmp, $original['uid']);
            }

            if (! self::fileParsesAsXml($tmp)) {
                return 'invalid_xml';
            }

            // Durability before promotion, so a crash cannot leave a renamed-but-empty file.
            if ($fh = @fopen($tmp, 'r+')) {
                fflush($fh);
                @fsync($fh);
                fclose($fh);
            }

            if (! @rename($tmp, $path)) {
                Log::error('Cannot promote OSIS temp file', ['tmp' => $tmp, 'path' => $path, 'last_error' => error_get_last()['message'] ?? null]);

                return 'write_failed';
            }

            $tmp = null;

            return 'ok';
        } finally {
            if ($tmp !== null && file_exists($tmp)) {
                @unlink($tmp);
            }
            flock($lock, LOCK_UN);
            fclose($lock);
        }
    }

    /**
     * Does this file parse as XML? Streamed with XMLReader: DOMDocument->load() of the 27MB source
     * costs ~420MB RSS that memory_limit cannot stop (libxml mallocs).
     */
    public static function fileParsesAsXml(string $path): bool
    {
        $previous = libxml_use_internal_errors(true);
        libxml_clear_errors();

        $reader = new \XMLReader();

        if (! @$reader->open($path)) {
            libxml_clear_errors();
            libxml_use_internal_errors($previous);

            return false;
        }

        while (@$reader->read()) {
            // Streaming to the end is the parse check.
        }

        $errors = libxml_get_errors();
        $reader->close();
        libxml_clear_errors();
        libxml_use_internal_errors($previous);

        foreach ($errors as $error) {
            if ($error->level === LIBXML_ERR_FATAL) {
                Log::error('OSIS source failed validation before promotion', ['line' => $error->line, 'message' => trim($error->message)]);

                return false;
            }
        }

        return true;
    }

    private static function existingTitlePattern(string $verseOsisId): string
    {
        return '/<title type="verse" canonical="(?:true|false)">([^<]*)<\/title>\s*(?=<verse osisID="'
            . preg_quote($verseOsisId, '/') . '"[^>]*sID="[^"]*")/';
    }

    /**
     * Set (insert or replace) the editorial title before a verse. Null on PCRE failure.
     *
     * The title is inserted with a CALLBACK, never as a replacement string: in a replacement string
     * "$1", "$0" and "\1" in the title text are backreferences, so a title like "Costs $10" put a
     * duplicate <verse sID> inside the title - still well-formed XML, so validation passed it.
     * canonical="false": OSIS canonical="true" would assert the heading IS scripture.
     */
    public static function setVerseTitle(string $xml, string $verseOsisId, string $title): ?string
    {
        $xml = preg_replace(self::existingTitlePattern($verseOsisId), '', $xml);
        if ($xml === null) {
            return null;
        }

        $element = '<title type="verse" canonical="false">' . htmlspecialchars($title, ENT_XML1 | ENT_QUOTES, 'UTF-8') . '</title>';

        return preg_replace_callback(
            '/<verse osisID="' . preg_quote($verseOsisId, '/') . '"[^>]*sID="[^"]*"[^>]*>/',
            fn (array $m) => $element . $m[0],
            $xml,
            1
        );
    }

    /** Remove the editorial title before a verse (unchanged string if it has none). */
    public static function removeVerseTitle(string $xml, string $verseOsisId): ?string
    {
        return preg_replace(self::existingTitlePattern($verseOsisId), '', $xml);
    }

    /** The current title text, decoded (an "&amp;" in the file is "&" to the editor). */
    public static function readVerseTitle(string $xml, string $verseOsisId): ?string
    {
        return preg_match(self::existingTitlePattern($verseOsisId), $xml, $m)
            ? html_entity_decode($m[1], ENT_QUOTES | ENT_XML1, 'UTF-8')
            : null;
    }

    /** Does the source contain this verse's start marker at all? */
    public static function hasVerse(string $xml, string $verseOsisId): bool
    {
        return str_contains($xml, ' sID="' . $verseOsisId . '"'); // leading space: "osisID" ends in "sID"
    }
}
