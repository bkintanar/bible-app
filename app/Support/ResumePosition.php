<?php

namespace App\Support;

use Illuminate\Support\Facades\Cookie;

/**
 * Where a reader left off, so "/" can send them back there.
 *
 * Kept in the session (this visit) and a long-lived `resume` cookie (the next visit, after the
 * session has expired). It used to be a cache row per session id - one database write per page
 * view, and one never-reused row per bot request (1,004 such rows, 643 expired, on 2026-11-10).
 */
final class ResumePosition
{
    public const COOKIE = 'resume';

    private const SESSION_KEY = 'last_visited';

    private const COOKIE_MINUTES = 60 * 24 * 365;

    /** Book OSIS id, optionally "/chapter": "Gen", "1John/4". */
    private const FORMAT = '/^([1-3]?[A-Za-z]{2,10})(?:\/([0-9]{1,3}))?$/';

    public static function remember(string $bookOsisId, ?int $chapterNumber = null): void
    {
        $value = $chapterNumber === null ? $bookOsisId : "{$bookOsisId}/{$chapterNumber}";

        if (! preg_match(self::FORMAT, $value)) {
            return;
        }

        session([self::SESSION_KEY => $value]);
        Cookie::queue(self::COOKIE, $value, self::COOKIE_MINUTES, null, null, null, true, false, 'lax');
    }

    /**
     * The remembered position, or null. Callers must still check that it exists: a cookie is
     * client-controlled.
     *
     * @return array{book: string, chapter: ?int}|null
     */
    public static function get(): ?array
    {
        foreach ([session(self::SESSION_KEY), request()->cookie(self::COOKIE)] as $value) {
            if (is_string($value) && preg_match(self::FORMAT, $value, $m)) {
                return ['book' => $m[1], 'chapter' => isset($m[2]) ? (int) $m[2] : null];
            }
        }

        return null;
    }

    public static function forget(): void
    {
        session()->forget(self::SESSION_KEY);
        Cookie::queue(Cookie::forget(self::COOKIE));
    }
}
