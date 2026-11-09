<?php

namespace App\Support;

use Illuminate\Support\Facades\Cache;

/**
 * Cache keys for anything derived from the database projection of the OSIS source (books,
 * chapters, chapter ids, verse payloads).
 *
 * Every key carries the projection version. osis:import bumps it after it writes, so nothing
 * cached from the previous projection can be served afterwards - previously a CLI re-projection
 * left readers on stale chapters for up to 24h, and only the editor knew which keys to forget.
 * Bumping is used instead of Cache::flush(), which would also wipe rate-limiter counters and job
 * locks that share the store. Old keys simply expire by their TTL.
 */
final class ProjectionCache
{
    private const VERSION_KEY = 'projection_version';

    /** Re-read the version at most this often per process (queue workers live for an hour). */
    private const MEMO_SECONDS = 5.0;

    private static ?int $version = null;

    private static float $readAt = 0.0;

    public static function key(string $base): string
    {
        return 'p' . self::version() . '_' . $base;
    }

    public static function version(): int
    {
        if (self::$version === null || microtime(true) - self::$readAt > self::MEMO_SECONDS) {
            self::$version = (int) (Cache::get(self::VERSION_KEY) ?? 1);
            self::$readAt = microtime(true);
        }

        return self::$version;
    }

    /** Invalidate every projection-derived cache entry at once. */
    public static function bump(): void
    {
        Cache::forever(self::VERSION_KEY, self::version() + 1);
        self::$version = null;
    }
}
