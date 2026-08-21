<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use XMLReader;

/**
 * Verifies that the database agrees with the OSIS source.
 *
 * The OSIS XML is the source of truth and the database is a derived read model, so the
 * useful question is not "does the database look plausible" but "does it still match the
 * source". This streams the XML with XMLReader (the DOM route costs ~400MB RSS on a 27MB
 * file, and libxml allocates through C malloc so memory_limit does not stop it) and compares
 * element counts against the projected tables.
 *
 * Intended as the gate after every import, and as the regression test for changes to the
 * projection pipeline.
 */
class VerifyOsisProjectionCommand extends Command
{
    protected $signature = 'osis:verify
        {file=assets/kjv.osis.xml}
        {--book= : Restrict verification to one book, by OSIS id}';

    protected $description = 'Verify the database projection still matches the OSIS source file';

    /** Tolerated absolute difference per check. Kept at 0 - a projection either matches or it does not. */
    private const TOLERANCE = 0;

    public function handle(): int
    {
        $file = $this->argument('file');

        if (! file_exists($file)) {
            $this->error("File not found: {$file}");

            return self::FAILURE;
        }

        $book = $this->option('book');

        $this->info("🔍 Verifying projection against {$file}".($book ? " (book: {$book})" : ''));

        $source = $this->countSourceElements($file, $book);
        $projected = $this->countProjectedRows($book);

        $rows = [];
        $failures = 0;

        foreach ($source as $key => $expected) {
            $actual = $projected[$key] ?? 0;
            $delta = $actual - $expected;
            $ok = abs($delta) <= self::TOLERANCE;

            if (! $ok) {
                $failures++;
            }

            $rows[] = [
                $key,
                number_format($expected),
                number_format($actual),
                $delta === 0 ? '0' : sprintf('%+d', $delta),
                $ok ? 'ok' : 'MISMATCH',
            ];
        }

        $this->table(['check', 'source', 'database', 'delta', 'verdict'], $rows);

        if ($failures > 0) {
            $this->error("❌ {$failures} check(s) do not match the source. The projection is stale or wrong.");
            $this->line('   Re-project with: php artisan osis:import'.($book ? " --book={$book}" : ''));

            return self::FAILURE;
        }

        $this->info('✅ Projection matches the source.');

        return self::SUCCESS;
    }

    /**
     * Stream the OSIS file and count the elements the projection is derived from.
     *
     * @return array<string,int>
     */
    private function countSourceElements(string $file, ?string $book): array
    {
        $counts = [
            'verses' => 0,
            'chapters' => 0,
            'titles' => 0,
            'transChange (translator_changes)' => 0,
            'divineName (divine_names)' => 0,
            'q who="Jesus" (red letter)' => 0,
            'note (study_notes)' => 0,
            'w with lemma (word_elements)' => 0,
        ];

        $reader = new XMLReader();
        // LIBXML_NOENT: do not expand entities while streaming untrusted-shaped input.
        $reader->open($file, null, LIBXML_NOENT);

        $currentBook = null;

        while ($reader->read()) {
            if ($reader->nodeType !== XMLReader::ELEMENT) {
                continue;
            }

            $name = $reader->localName;

            // Track which book we are inside so --book can filter.
            if ($name === 'div' && $reader->getAttribute('type') === 'book') {
                $currentBook = $reader->getAttribute('osisID');
            }

            if ($book !== null && $currentBook !== $book) {
                continue;
            }

            switch ($name) {
                case 'verse':
                    // Milestone verses appear twice (sID then eID); count only the opening one.
                    if ($reader->getAttribute('sID') !== null && $reader->getAttribute('osisID') !== null) {
                        $counts['verses']++;
                    }
                    break;

                case 'chapter':
                    if ($reader->getAttribute('osisID') !== null) {
                        $counts['chapters']++;
                    }
                    break;

                case 'title':
                    $counts['titles']++;
                    break;

                case 'transChange':
                    $counts['transChange (translator_changes)']++;
                    break;

                case 'divineName':
                    $counts['divineName (divine_names)']++;
                    break;

                case 'q':
                    if ($reader->getAttribute('who') === 'Jesus') {
                        $counts['q who="Jesus" (red letter)']++;
                    }
                    break;

                case 'note':
                    $counts['note (study_notes)']++;
                    break;

                case 'w':
                    if (str_contains((string) $reader->getAttribute('lemma'), 'strong:')) {
                        $counts['w with lemma (word_elements)']++;
                    }
                    break;
            }
        }

        $reader->close();

        return $counts;
    }

    /**
     * Count the corresponding projected rows.
     *
     * @return array<string,int>
     */
    private function countProjectedRows(?string $book): array
    {
        $verseIds = null;
        $chapterIds = null;

        if ($book !== null) {
            $chapterIds = DB::table('chapters')
                ->join('books', 'books.id', '=', 'chapters.book_id')
                ->where('books.osis_id', $book)
                ->pluck('chapters.id');

            $verseIds = DB::table('verses')->whereIn('chapter_id', $chapterIds)->pluck('id');
        }

        $scoped = function (string $table, string $column = 'verse_id') use ($verseIds) {
            $q = DB::table($table);

            return $verseIds === null
                ? $q->count()
                : $q->whereIn($column, $verseIds)->count();
        };

        return [
            'verses' => $verseIds === null
                ? DB::table('verses')->count()
                : $verseIds->count(),
            'chapters' => $chapterIds === null
                ? DB::table('chapters')->count()
                : $chapterIds->count(),
            'titles' => $verseIds === null
                ? DB::table('titles')->count()
                : DB::table('titles')->whereIn('verse_id', $verseIds)
                    ->orWhereIn('chapter_id', $chapterIds)->count(),
            'transChange (translator_changes)' => $scoped('translator_changes'),
            'divineName (divine_names)' => $scoped('divine_names'),
            'q who="Jesus" (red letter)' => $scoped('red_letter_text'),
            'note (study_notes)' => $scoped('study_notes'),
            'w with lemma (word_elements)' => $scoped('word_elements'),
        ];
    }
}
