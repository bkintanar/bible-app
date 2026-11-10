<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use XMLReader;

/**
 * Verifies that the database agrees with the OSIS source.
 *
 * The OSIS XML is the source of truth and the database is a derived read model, so the useful
 * question is not "does the database look plausible" but "does it still match the source". This
 * streams the XML with XMLReader (the DOM route costs ~400MB RSS on a 27MB file, and libxml
 * allocates through C malloc so memory_limit does not stop it).
 *
 * Default mode compares element counts. --content compares every verse: its scripture text (study
 * notes and titles excluded, normalised exactly as the importer stores it), whether it holds words
 * of Christ (red letters), and the paragraph starts - and names the verses that differ. Counts
 * alone passed a projection with 14 truncated verses, 5,844 note-polluted texts and half of
 * Christ's words not red.
 *
 * Intended as the gate after every import, and as the regression test for the projection pipeline.
 */
class VerifyOsisProjectionCommand extends Command
{
    protected $signature = 'osis:verify
        {file=assets/kjv.osis.xml}
        {--book= : Restrict verification to one book, by OSIS id}
        {--content : Compare every verse (text, red letters, paragraph starts), not just counts}';

    protected $description = 'Verify the database projection still matches the OSIS source file';

    /** Verse-scoped element types found outside any verse span (e.g. inside Psalm superscriptions). */
    private array $outsideVerses = [];

    /** @var array<string, array{text: string, christ: bool, para: bool}> per-verse source facts (--content) */
    private array $sourceVerses = [];

    /** @var array<string, string> book osisID => colophon text, normalised (--content) */
    private array $sourceColophons = [];

    private ?string $work = null;

    public function handle(): int
    {
        $file = $this->argument('file');

        if (! file_exists($file)) {
            $this->error("File not found: {$file}");

            return self::FAILURE;
        }

        $book = $this->option('book');
        $content = (bool) $this->option('content');

        $this->info("🔍 Verifying projection against {$file}".($book ? " (book: {$book})" : '').($content ? ' [content]' : ''));

        $source = $this->readSource($file, $book, $content);
        if ($source === null) {
            return self::FAILURE;
        }

        $versionId = DB::table('bible_versions')->where('abbreviation', strtoupper((string) $this->work))->value('id');
        if (! $versionId) {
            $this->error("No projected bible_versions row for this source (osisIDWork={$this->work}).");

            return self::FAILURE;
        }

        $failures = $this->compareCounts($source, $this->countProjectedRows($book, (int) $versionId));

        if ($content) {
            $failures += $this->compareContent($book, (int) $versionId);
        }

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
     * Stream the OSIS file once: element counts, and (with --content) per-verse text facts.
     *
     * @return array<string,int>|null counts, or null if the file cannot be read
     */
    private function readSource(string $file, ?string $book, bool $content): ?array
    {
        $counts = [
            'verses' => 0,
            'chapters' => 0,
            'titles' => 0,
            'colophons' => 0,
            'transChange (translator_changes)' => 0,
            'divineName (divine_names)' => 0,
            'verses with words of Christ (red letter)' => 0,
            'note (study_notes)' => 0,
            'w with lemma (word_elements)' => 0,
        ];

        // Elements that only count when they sit INSIDE a verse span: the projection stores them
        // as verse-scoped rows (48 transChange and 6 divineName live in Psalm superscriptions).
        $verseScoped = ['transChange (translator_changes)', 'divineName (divine_names)', 'note (study_notes)', 'w with lemma (word_elements)'];
        $outsideVerses = array_fill_keys($verseScoped, 0);

        libxml_use_internal_errors(true);
        libxml_clear_errors();
        $reader = new XMLReader;
        // LIBXML_NOENT: do not expand entities while streaming untrusted-shaped input.
        if (! @$reader->open($file, null, LIBXML_NOENT)) {
            $this->error("Cannot open {$file} as XML.");

            return null;
        }

        $currentBook = null;
        $openVerse = null;
        $noteDepth = 0;
        $headerDepth = 0;
        $titleDepth = 0;
        $quoteStack = []; // one bool per open <q>: is it Christ's?
        $christDepth = 0;
        $text = '';
        $christ = false;
        $para = false;
        $colophonBook = null; // the book whose <div type="colophon"> is open (it holds no nested div)
        $colophonText = '';

        while (@$reader->read()) {
            $type = $reader->nodeType;
            $name = $reader->localName;

            if ($type === XMLReader::ELEMENT || $type === XMLReader::END_ELEMENT) {
                $isEnd = $type === XMLReader::END_ELEMENT;
                $empty = ! $isEnd && $reader->isEmptyElement;

                if ($name === 'header' && ! $empty) {
                    $headerDepth += $isEnd ? -1 : 1;
                } elseif ($name === 'note' && ! $empty) {
                    // <note> is apparatus, not scripture: excluded from text and from transChange counts.
                    $noteDepth = max(0, $noteDepth + ($isEnd ? -1 : 1));
                } elseif ($name === 'title' && ! $empty) {
                    $titleDepth = max(0, $titleDepth + ($isEnd ? -1 : 1));
                } elseif ($name === 'q' && ! $empty) {
                    if ($isEnd) {
                        $wasChrist = array_pop($quoteStack);
                        $christDepth -= $wasChrist ? 1 : 0;
                    } else {
                        $isChrist = $reader->getAttribute('who') === 'Jesus';
                        $quoteStack[] = $isChrist;
                        $christDepth += $isChrist ? 1 : 0;
                    }
                }
            }

            if ($type === XMLReader::ELEMENT && $name === 'osisText') {
                $this->work = $reader->getAttribute('osisIDWork');
            }

            if ($type === XMLReader::ELEMENT && $name === 'div' && $reader->getAttribute('type') === 'book') {
                $currentBook = $reader->getAttribute('osisID');
            }
            $inScope = $book === null || $currentBook === $book;

            // An epistle's colophon (OD-9): stored as a non-canonical 'colophon' title.
            if ($type === XMLReader::ELEMENT && $name === 'div' && $reader->getAttribute('type') === 'colophon' && ! $reader->isEmptyElement) {
                $colophonBook = $currentBook;
                $colophonText = '';
            } elseif ($type === XMLReader::END_ELEMENT && $name === 'div' && $colophonBook !== null) {
                if ($content && $inScope) {
                    $this->sourceColophons[$colophonBook] = self::normalise($colophonText);
                }
                $colophonBook = null;
            } elseif ($colophonBook !== null && $noteDepth === 0) {
                if (in_array($type, [XMLReader::TEXT, XMLReader::WHITESPACE, XMLReader::SIGNIFICANT_WHITESPACE, XMLReader::CDATA], true)) {
                    $colophonText .= $reader->value;
                } elseif ($type === XMLReader::ELEMENT && $name === 'w' && $reader->isEmptyElement) {
                    $colophonText .= ' ';
                }
            }

            // Milestone verses: <verse sID="Gen.1.1"/> ... <verse eID="Gen.1.1"/>.
            if ($type === XMLReader::ELEMENT && $name === 'verse') {
                if (($sid = $reader->getAttribute('sID')) !== null) {
                    $openVerse = $sid;
                    $text = '';
                    $christ = false;
                    $para = false;
                } elseif (($eid = $reader->getAttribute('eID')) !== null && $eid === $openVerse) {
                    if ($content && $inScope) {
                        $this->sourceVerses[$eid] = ['text' => self::normalise($text), 'christ' => $christ, 'para' => $para];
                    }
                    if ($inScope && $christ) {
                        $counts['verses with words of Christ (red letter)']++;
                    }
                    $openVerse = null;
                }
            }

            // Scripture text of the open verse.
            if ($openVerse !== null && $noteDepth === 0 && $titleDepth === 0) {
                if (in_array($type, [XMLReader::TEXT, XMLReader::WHITESPACE, XMLReader::SIGNIFICANT_WHITESPACE, XMLReader::CDATA], true)) {
                    $text .= $reader->value;
                    if ($christDepth > 0 && trim($reader->value) !== '') {
                        $christ = true;
                    }
                } elseif ($type === XMLReader::ELEMENT && $name === 'w' && $reader->isEmptyElement) {
                    $text .= ' '; // a self-closing <w/> can be the only separator between words
                } elseif ($type === XMLReader::ELEMENT && $name === 'milestone' && $reader->getAttribute('type') === 'x-p') {
                    $para = true;
                }
            }

            if ($type !== XMLReader::ELEMENT || ! $inScope) {
                continue;
            }

            switch ($name) {
                case 'verse':
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
                    if ($headerDepth === 0) { // the <work><title> in the header is metadata, not a title row
                        $counts['titles']++;
                    }
                    break;

                case 'div':
                    if ($reader->getAttribute('type') === 'colophon') {
                        $counts['colophons']++;
                    }
                    break;

                case 'transChange':
                    if ($noteDepth === 0) {
                        $bucket = 'transChange (translator_changes)';
                        $openVerse === null ? $outsideVerses[$bucket]++ : $counts[$bucket]++;
                    }
                    break;

                case 'divineName':
                    if ($noteDepth === 0) { // inside a note it is apparatus, not a verse-level row
                        $bucket = 'divineName (divine_names)';
                        $openVerse === null ? $outsideVerses[$bucket]++ : $counts[$bucket]++;
                    }
                    break;

                case 'note':
                    $bucket = 'note (study_notes)';
                    $openVerse === null ? $outsideVerses[$bucket]++ : $counts[$bucket]++;
                    break;

                case 'w':
                    if ($noteDepth === 0 && str_contains((string) $reader->getAttribute('lemma'), 'strong:')) {
                        $bucket = 'w with lemma (word_elements)';
                        $openVerse === null ? $outsideVerses[$bucket]++ : $counts[$bucket]++;
                    }
                    break;
            }
        }

        $reader->close();

        $fatal = array_filter(libxml_get_errors(), fn ($e) => $e->level === LIBXML_ERR_FATAL);
        if ($fatal !== []) {
            $this->error('The source is not well-formed XML: '.trim(reset($fatal)->message).' (line '.reset($fatal)->line.')');

            return null;
        }

        $this->outsideVerses = array_filter($outsideVerses);

        return $counts;
    }

    /** The importer's plain-text normalisation (ImportOsisCommand::extractPlainTextWithSpacing). */
    private static function normalise(string $text): string
    {
        $text = preg_replace('/\s+/u', ' ', $text);
        $text = preg_replace('/\s+([,.;:!?)\]])/u', '$1', $text);

        return trim(preg_replace('/([(\[])\s+/u', '$1', $text));
    }

    /**
     * Count the corresponding projected rows, for this version only.
     *
     * @return array<string,int>
     */
    private function countProjectedRows(?string $book, int $versionId): array
    {
        $chapterIds = DB::table('chapters')
            ->join('books', 'books.id', '=', 'chapters.book_id')
            ->where('chapters.version_id', $versionId)
            ->when($book !== null, fn ($q) => $q->where('books.osis_id', $book))
            ->pluck('chapters.id');

        $verseIds = DB::table('verses')->whereIn('chapter_id', $chapterIds)->pluck('id');

        $count = fn (string $table) => $verseIds->chunk(30000)->sum(fn ($ids) => DB::table($table)->whereIn('verse_id', $ids)->count());
        $distinct = fn (string $table) => $verseIds->chunk(30000)->sum(fn ($ids) => DB::table($table)->whereIn('verse_id', $ids)->distinct()->count('verse_id'));

        return [
            'verses' => $verseIds->count(),
            'chapters' => $chapterIds->count(),
            'titles' => $count('titles') + DB::table('titles')->whereNull('verse_id')->whereIn('chapter_id', $chapterIds)->where('title_type', '<>', 'colophon')->count(),
            'colophons' => DB::table('titles')->whereNull('verse_id')->whereIn('chapter_id', $chapterIds)->where('title_type', 'colophon')->count(),
            'transChange (translator_changes)' => $count('translator_changes'),
            'divineName (divine_names)' => $count('divine_names'),
            'verses with words of Christ (red letter)' => $distinct('red_letter_text'),
            'note (study_notes)' => $count('study_notes'),
            'w with lemma (word_elements)' => $count('word_elements'),
        ];
    }

    private function compareCounts(array $source, array $projected): int
    {
        $rows = [];
        $failures = 0;

        foreach ($source as $key => $expected) {
            $actual = $projected[$key] ?? 0;
            $delta = $actual - $expected;
            $failures += $delta === 0 ? 0 : 1;
            $rows[] = [$key, number_format($expected), number_format($actual), $delta === 0 ? '0' : sprintf('%+d', $delta), $delta === 0 ? 'ok' : 'MISMATCH'];
        }

        $this->table(['check', 'source', 'database', 'delta', 'verdict'], $rows);

        return $failures;
    }

    /** Per-verse comparison; prints the first 20 differing verses of each kind. */
    private function compareContent(?string $book, int $versionId): int
    {
        $db = DB::table('verses')
            ->join('chapters', 'chapters.id', '=', 'verses.chapter_id')
            ->join('books', 'books.id', '=', 'chapters.book_id')
            ->leftJoin(DB::raw('(SELECT DISTINCT verse_id FROM red_letter_text) r'), 'r.verse_id', '=', 'verses.id')
            ->leftJoin(DB::raw('(SELECT DISTINCT start_verse_id FROM paragraphs) p'), 'p.start_verse_id', '=', 'verses.id')
            ->where('chapters.version_id', $versionId)
            ->when($book !== null, fn ($q) => $q->where('books.osis_id', $book))
            ->get(['verses.osis_id', 'verses.text', 'verses.verse_number', DB::raw('r.verse_id IS NOT NULL AS red'), DB::raw('p.start_verse_id IS NOT NULL AS para')])
            ->keyBy('osis_id');

        $problems = ['missing from database' => [], 'not in source' => [], 'text differs' => [], 'red letters differ' => [], 'paragraph start differs' => []];

        foreach ($this->sourceVerses as $osisId => $src) {
            $row = $db->get($osisId);
            if ($row === null) {
                $problems['missing from database'][] = $osisId;

                continue;
            }
            if (self::normalise((string) $row->text) !== $src['text']) {
                $problems['text differs'][] = $osisId;
            }
            if ((bool) $row->red !== $src['christ']) {
                $problems['red letters differ'][] = $osisId.($src['christ'] ? ' (should be red)' : ' (should not be red)');
            }
            $expectedPara = $src['para'] || (int) $row->verse_number === 1;
            if ((bool) $row->para !== $expectedPara) {
                $problems['paragraph start differs'][] = $osisId;
            }
        }
        foreach ($db->keys() as $osisId) {
            if (! isset($this->sourceVerses[$osisId])) {
                $problems['not in source'][] = $osisId;
            }
        }

        $failures = 0;
        $rows = [];
        foreach ($problems as $kind => $ids) {
            $rows[] = [$kind, count($ids), implode(', ', array_slice($ids, 0, 20))];
            $failures += $ids === [] ? 0 : 1;
        }
        $this->table(['per-verse check ('.count($this->sourceVerses).' verses)', 'differing', 'first 20'], $rows);

        return $failures + $this->compareColophons($book, $versionId);
    }

    /** Each book's colophon text, as stored (HTML with supplied words in <em>), against the source. */
    private function compareColophons(?string $book, int $versionId): int
    {
        $db = DB::table('titles')
            ->join('chapters', 'chapters.id', '=', 'titles.chapter_id')
            ->join('books', 'books.id', '=', 'chapters.book_id')
            ->where('titles.title_type', 'colophon')
            ->whereNull('titles.verse_id')
            ->where('chapters.version_id', $versionId)
            ->when($book !== null, fn ($q) => $q->where('books.osis_id', $book))
            ->pluck('titles.title_text', 'books.osis_id')
            ->map(fn ($html) => self::normalise(html_entity_decode(strip_tags((string) $html), ENT_QUOTES | ENT_HTML5, 'UTF-8')));

        $differing = [];
        foreach ($this->sourceColophons + $db->all() as $osisId => $_) {
            if (($this->sourceColophons[$osisId] ?? null) !== $db->get($osisId)) {
                $differing[] = $osisId;
            }
        }

        $this->table(['colophon check ('.count($this->sourceColophons).' books)', 'differing', 'books'], [['text differs or missing', count($differing), implode(', ', $differing)]]);

        return $differing === [] ? 0 : 1;
    }
}
