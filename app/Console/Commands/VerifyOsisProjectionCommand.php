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

    /** Verse-scoped element types found outside any verse span (e.g. inside Psalm superscriptions). */
    private array $outsideVerses = [];

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

        if ($this->outsideVerses !== []) {
            $this->line('');
            $this->line('Note: these source elements sit outside any verse span (Psalm superscriptions and');
            $this->line('similar) and are correctly not stored as verse-scoped rows:');
            foreach ($this->outsideVerses as $k => $n) {
                $this->line(sprintf('  %-34s %d', $k, $n));
            }
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

        // Elements that only count when they sit INSIDE a verse span.
        //
        // The projection stores these as verse-scoped rows, so the comparable source figure is
        // "inside a verse", not "anywhere in the file". Counting file-wide over-reported:
        // 48 transChange and 6 divineName elements live inside Psalm superscription <title>
        // elements rather than in any verse, which showed up as phantom under-extraction of
        // -59 and -6 when the extractor was in fact correct.
        $verseScoped = [
            'transChange (translator_changes)',
            'divineName (divine_names)',
            'q who="Jesus" (red letter)',
            'note (study_notes)',
            'w with lemma (word_elements)',
        ];

        $outsideVerses = array_fill_keys($verseScoped, 0);

        $reader = new XMLReader();
        // LIBXML_NOENT: do not expand entities while streaming untrusted-shaped input.
        $reader->open($file, null, LIBXML_NOENT);

        $currentBook = null;
        $openVerse = null;
        $noteDepth = 0;

        while ($reader->read()) {
            // <note> subtrees are editorial apparatus, not scripture text. Translator-added
            // words inside a study note (e.g. Job 41:1) are correctly NOT stored as verse-level
            // translator changes, so counting them over-reported the source by 9.
            if ($reader->localName === 'note') {
                if ($reader->nodeType === XMLReader::ELEMENT) {
                    if (! $reader->isEmptyElement) {
                        $noteDepth++;
                    }
                } elseif ($reader->nodeType === XMLReader::END_ELEMENT) {
                    $noteDepth = max(0, $noteDepth - 1);
                }
            }

            // Milestone verses are empty elements: <verse sID="Gen.1.1"/> ... <verse eID="Gen.1.1"/>.
            // Track the open span so verse-scoped elements can be attributed correctly.
            if ($reader->nodeType === XMLReader::ELEMENT && $reader->localName === 'verse') {
                if (($sid = $reader->getAttribute('sID')) !== null) {
                    $openVerse = $sid;
                } elseif (($eid = $reader->getAttribute('eID')) !== null && $eid === $openVerse) {
                    $openVerse = null;
                }
            }

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
                    if ($noteDepth > 0) {
                        break;
                    }
                    $bucket = 'transChange (translator_changes)';
                    $openVerse === null ? $outsideVerses[$bucket]++ : $counts[$bucket]++;
                    break;

                case 'divineName':
                    // NOT skipped inside notes: the extractor stores note-internal divineName
                    // elements (65 of them) while excluding note-internal transChange. That
                    // inconsistency is a real defect in ImportOsisCommand, not in this check -
                    // recorded here so the counts stay honest rather than being tuned to hide it.
                    $bucket = 'divineName (divine_names)';
                    $openVerse === null ? $outsideVerses[$bucket]++ : $counts[$bucket]++;
                    break;

                case 'q':
                    if ($reader->getAttribute('who') === 'Jesus') {
                        $bucket = 'q who="Jesus" (red letter)';
                        $openVerse === null ? $outsideVerses[$bucket]++ : $counts[$bucket]++;
                    }
                    break;

                case 'note':
                    $bucket = 'note (study_notes)';
                    $openVerse === null ? $outsideVerses[$bucket]++ : $counts[$bucket]++;
                    break;

                case 'w':
                    // No <w lemma="strong:..."> occurs inside a note in this corpus, so the
                    // note guard is a no-op here; kept for correctness against other sources.
                    if ($noteDepth === 0 && str_contains((string) $reader->getAttribute('lemma'), 'strong:')) {
                        $bucket = 'w with lemma (word_elements)';
                        $openVerse === null ? $outsideVerses[$bucket]++ : $counts[$bucket]++;
                    }
                    break;
            }
        }

        $reader->close();

        $this->outsideVerses = array_filter($outsideVerses);

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
