<?php

namespace App\Services;

use App\Services\Contracts\OsisParserInterface;
use App\Services\Parsers\MilestoneOsisParser;
use App\Services\Parsers\ContainedOsisParser;
use DOMDocument;
use DOMXPath;
use Illuminate\Support\Collection;

class OsisReader
{
    private DOMDocument $dom;
    private DOMXPath $xpath;
    private string $osisNamespace = 'http://www.bibletechnologies.net/2003/OSIS/namespace';
    private OsisParserInterface $parser;

    public function __construct(string $osisFilePath)
    {
        $this->dom = new DOMDocument();
        $this->dom->load($osisFilePath);

        $this->xpath = new DOMXPath($this->dom);
        $this->xpath->registerNamespace('osis', $this->osisNamespace);

        // Detect verse format and instantiate appropriate parser
        $this->parser = $this->createParser();
    }

    /**
     * Detect verse format and create appropriate parser
     */
    private function createParser(): OsisParserInterface
    {
        // Look for milestone verses first (sID/eID format)
        $milestoneVerses = $this->xpath->query('//osis:verse[@sID]');
        if ($milestoneVerses->length > 0) {
            return new MilestoneOsisParser($this->xpath);
        }

        // Use contained verse parser for other formats
        return new ContainedOsisParser($this->xpath);
    }

    /**
     * Get all books in the Bible
     */
    public function getBooks(): Collection
    {
        $books = collect();
        $bookNodes = $this->xpath->query('//osis:div[@type="book"]');

        foreach ($bookNodes as $index => $bookNode) {
            $osisId = $bookNode->getAttribute('osisID');

            // Skip Apocrypha books
            if ($this->isApocrypha($osisId)) {
                continue;
            }

            $titleNode = $this->xpath->query('.//osis:title[@type="main"]', $bookNode)->item(0);
            $shortTitle = $titleNode ? $titleNode->getAttribute('short') : '';
            $fullTitle = $titleNode ? $titleNode->textContent : '';

            // Use standardized book names if OSIS title is missing or inadequate
            $standardizedNames = $this->getStandardizedBookNames($osisId);
            if (empty(trim($fullTitle)) || strlen(trim($fullTitle)) < 4) {
                $fullTitle = $standardizedNames['full_name'];
            }
            if (empty($shortTitle)) {
                $shortTitle = $standardizedNames['short_name'];
            }

            // Determine testament based on book order or osisId
            $testament = $this->determineTestament($osisId);

            $books->push([
                'osis_id' => $osisId,
                'name' => $fullTitle,
                'short_name' => $shortTitle,
                'testament' => $testament,
                'book_order' => $books->count() + 1 // Reindex after filtering
            ]);
        }

        return $books;
    }

    /**
     * Get chapters for a specific book
     */
    public function getChapters(string $bookOsisId): Collection
    {
        return $this->parser->getChapters($bookOsisId);
    }

    /**
     * Get verses for a specific chapter
     */
    public function getVerses(string $chapterOsisRef): Collection
    {
        return $this->parser->getVerses($chapterOsisRef);
    }

    /**
     * Get verses for a specific chapter grouped by paragraphs (traditional Bible formatting)
     */
    public function getVersesParagraphStyle(string $chapterOsisRef): Collection
    {
        return $this->parser->getVersesParagraphStyle($chapterOsisRef);
    }

    /**
     * Get the text content of a specific verse
     */
    public function getVerseText(string $verseOsisId): string
    {
        return $this->parser->getVerseText($verseOsisId);
    }

    /**
     * Search for verses containing specific text (simple and fast version)
     */
    public function searchVerses(string $searchTerm, int $limit = 100): Collection
    {
        return $this->parser->searchVerses($searchTerm, $limit);
    }

    /**
     * Get Bible metadata from the header
     */
    public function getBibleInfo(): array
    {
        $titleNode = $this->xpath->query('//osis:work/osis:title[@type="x-vernacular"]')->item(0);
        $descriptionNode = $this->xpath->query('//osis:work/osis:description[@type="x-english"]')->item(0);
        $publisherNode = $this->xpath->query('//osis:work/osis:publisher[@type="x-electronic"]')->item(0);
        $languageNode = $this->xpath->query('//osis:work/osis:language[@type="x-vernacular"]')->item(0);

        return [
            'title' => $titleNode ? $titleNode->textContent : 'Unknown',
            'description' => $descriptionNode ? $descriptionNode->textContent : '',
            'publisher' => $publisherNode ? $publisherNode->textContent : '',
            'language' => $languageNode ? $languageNode->textContent : 'English'
        ];
    }

    /**
     * Determine testament based on book osisID
     */
    private function determineTestament(string $osisId): string
    {
        $oldTestamentBooks = [
            'Gen', 'Exod', 'Lev', 'Num', 'Deut', 'Josh', 'Judg', 'Ruth', '1Sam', '2Sam',
            '1Kgs', '2Kgs', '1Chr', '2Chr', 'Ezra', 'Neh', 'Esth', 'Job', 'Ps', 'Prov',
            'Eccl', 'Song', 'Isa', 'Jer', 'Lam', 'Ezek', 'Dan', 'Hos', 'Joel', 'Amos',
            'Obad', 'Jonah', 'Mic', 'Nah', 'Hab', 'Zeph', 'Hag', 'Zech', 'Mal'
        ];

        return in_array($osisId, $oldTestamentBooks) ? 'Old Testament' : 'New Testament';
    }

    /**
     * Check if a book is part of the Apocrypha
     */
    private function isApocrypha(string $osisId): bool
    {
        $apocryphaBooks = [
            'Tob', 'Tobit',           // Tobit
            'Jdt', 'Judith',          // Judith
            'AddEsth', 'EsthGr',      // Additions to Esther / Greek Esther
            'Wis', 'Wisd',           // Wisdom of Solomon
            'Sir', 'Sirach',         // Sirach/Ecclesiasticus
            'Bar', 'Baruch',         // Baruch
            'EpJer', 'EpisJer',      // Letter of Jeremiah
            'PrAzar', 'SgThree',     // Prayer of Azariah and Song of Three Young Men
            'Sus', 'Susanna',        // Susanna
            'Bel', 'BelDragon',      // Bel and the Dragon
            '1Macc', 'IMacc',        // 1 Maccabees
            '2Macc', 'IIMacc',       // 2 Maccabees
            '1Esd', 'IEsd',          // 1 Esdras
            '2Esd', 'IIEsd',         // 2 Esdras
            'PrMan', 'ManPr',        // Prayer of Manasseh
            '3Macc', 'IIIMacc',      // 3 Maccabees
            '4Macc', 'IVMacc',       // 4 Maccabees
            'Ps151',                 // Psalm 151
            'AddPs',                 // Additional Psalms
        ];

        return in_array($osisId, $apocryphaBooks);
    }

    /**
     * Get standardized book names for consistent display across translations
     */
    private function getStandardizedBookNames(string $osisId): array
    {
        $bookNames = [
            // Old Testament
            'Gen' => ['full_name' => 'Genesis', 'short_name' => 'Genesis'],
            'Exod' => ['full_name' => 'Exodus', 'short_name' => 'Exodus'],
            'Lev' => ['full_name' => 'Leviticus', 'short_name' => 'Leviticus'],
            'Num' => ['full_name' => 'Numbers', 'short_name' => 'Numbers'],
            'Deut' => ['full_name' => 'Deuteronomy', 'short_name' => 'Deuteronomy'],
            'Josh' => ['full_name' => 'Joshua', 'short_name' => 'Joshua'],
            'Judg' => ['full_name' => 'Judges', 'short_name' => 'Judges'],
            'Ruth' => ['full_name' => 'Ruth', 'short_name' => 'Ruth'],
            '1Sam' => ['full_name' => '1 Samuel', 'short_name' => '1 Samuel'],
            '2Sam' => ['full_name' => '2 Samuel', 'short_name' => '2 Samuel'],
            '1Kgs' => ['full_name' => '1 Kings', 'short_name' => '1 Kings'],
            '2Kgs' => ['full_name' => '2 Kings', 'short_name' => '2 Kings'],
            '1Chr' => ['full_name' => '1 Chronicles', 'short_name' => '1 Chronicles'],
            '2Chr' => ['full_name' => '2 Chronicles', 'short_name' => '2 Chronicles'],
            'Ezra' => ['full_name' => 'Ezra', 'short_name' => 'Ezra'],
            'Neh' => ['full_name' => 'Nehemiah', 'short_name' => 'Nehemiah'],
            'Esth' => ['full_name' => 'Esther', 'short_name' => 'Esther'],
            'Job' => ['full_name' => 'Job', 'short_name' => 'Job'],
            'Ps' => ['full_name' => 'Psalms', 'short_name' => 'Psalms'],
            'Prov' => ['full_name' => 'Proverbs', 'short_name' => 'Proverbs'],
            'Eccl' => ['full_name' => 'Ecclesiastes', 'short_name' => 'Ecclesiastes'],
            'Song' => ['full_name' => 'Song of Solomon', 'short_name' => 'Song of Solomon'],
            'Isa' => ['full_name' => 'Isaiah', 'short_name' => 'Isaiah'],
            'Jer' => ['full_name' => 'Jeremiah', 'short_name' => 'Jeremiah'],
            'Lam' => ['full_name' => 'Lamentations', 'short_name' => 'Lamentations'],
            'Ezek' => ['full_name' => 'Ezekiel', 'short_name' => 'Ezekiel'],
            'Dan' => ['full_name' => 'Daniel', 'short_name' => 'Daniel'],
            'Hos' => ['full_name' => 'Hosea', 'short_name' => 'Hosea'],
            'Joel' => ['full_name' => 'Joel', 'short_name' => 'Joel'],
            'Amos' => ['full_name' => 'Amos', 'short_name' => 'Amos'],
            'Obad' => ['full_name' => 'Obadiah', 'short_name' => 'Obadiah'],
            'Jonah' => ['full_name' => 'Jonah', 'short_name' => 'Jonah'],
            'Mic' => ['full_name' => 'Micah', 'short_name' => 'Micah'],
            'Nah' => ['full_name' => 'Nahum', 'short_name' => 'Nahum'],
            'Hab' => ['full_name' => 'Habakkuk', 'short_name' => 'Habakkuk'],
            'Zeph' => ['full_name' => 'Zephaniah', 'short_name' => 'Zephaniah'],
            'Hag' => ['full_name' => 'Haggai', 'short_name' => 'Haggai'],
            'Zech' => ['full_name' => 'Zechariah', 'short_name' => 'Zechariah'],
            'Mal' => ['full_name' => 'Malachi', 'short_name' => 'Malachi'],

            // New Testament
            'Matt' => ['full_name' => 'Matthew', 'short_name' => 'Matthew'],
            'Mark' => ['full_name' => 'Mark', 'short_name' => 'Mark'],
            'Luke' => ['full_name' => 'Luke', 'short_name' => 'Luke'],
            'John' => ['full_name' => 'John', 'short_name' => 'John'],
            'Acts' => ['full_name' => 'Acts', 'short_name' => 'Acts'],
            'Rom' => ['full_name' => 'Romans', 'short_name' => 'Romans'],
            '1Cor' => ['full_name' => '1 Corinthians', 'short_name' => '1 Corinthians'],
            '2Cor' => ['full_name' => '2 Corinthians', 'short_name' => '2 Corinthians'],
            'Gal' => ['full_name' => 'Galatians', 'short_name' => 'Galatians'],
            'Eph' => ['full_name' => 'Ephesians', 'short_name' => 'Ephesians'],
            'Phil' => ['full_name' => 'Philippians', 'short_name' => 'Philippians'],
            'Col' => ['full_name' => 'Colossians', 'short_name' => 'Colossians'],
            '1Thess' => ['full_name' => '1 Thessalonians', 'short_name' => '1 Thessalonians'],
            '2Thess' => ['full_name' => '2 Thessalonians', 'short_name' => '2 Thessalonians'],
            '1Tim' => ['full_name' => '1 Timothy', 'short_name' => '1 Timothy'],
            '2Tim' => ['full_name' => '2 Timothy', 'short_name' => '2 Timothy'],
            'Titus' => ['full_name' => 'Titus', 'short_name' => 'Titus'],
            'Phlm' => ['full_name' => 'Philemon', 'short_name' => 'Philemon'],
            'Heb' => ['full_name' => 'Hebrews', 'short_name' => 'Hebrews'],
            'Jas' => ['full_name' => 'James', 'short_name' => 'James'],
            '1Pet' => ['full_name' => '1 Peter', 'short_name' => '1 Peter'],
            '2Pet' => ['full_name' => '2 Peter', 'short_name' => '2 Peter'],
            '1John' => ['full_name' => '1 John', 'short_name' => '1 John'],
            '2John' => ['full_name' => '2 John', 'short_name' => '2 John'],
            '3John' => ['full_name' => '3 John', 'short_name' => '3 John'],
            'Jude' => ['full_name' => 'Jude', 'short_name' => 'Jude'],
            'Rev' => ['full_name' => 'Revelation', 'short_name' => 'Revelation'],
        ];

        return $bookNames[$osisId] ?? ['full_name' => $osisId, 'short_name' => $osisId];
    }

    /**
     * Parse a verse reference and return verse details if valid
     * Supports formats: "Acts 2:38", "acts2:38", "Genesis 1:1", "gen1:1", "deut 6:1-4", etc.
     */
    public function parseVerseReference(string $input): ?array
    {
        // Clean and normalize the input
        $input = trim($input);

        // Try different patterns for verse references
        $patterns = [
            // "Acts 2:38-47", "Genesis 1:1-3" (verse ranges)
            '/^([a-zA-Z\s]+)\s*(\d+):(\d+)-(\d+)$/i',
            // "acts2:38-47", "gen1:1-3" (verse ranges, no spaces)
            '/^([a-zA-Z]+)(\d+):(\d+)-(\d+)$/i',
            // "Acts 2:38", "Genesis 1:1" (single verses)
            '/^([a-zA-Z\s]+)\s*(\d+):(\d+)$/i',
            // "acts2:38", "gen1:1" (single verses, no spaces)
            '/^([a-zA-Z]+)(\d+):(\d+)$/i',
            // "Acts 2" (chapter only)
            '/^([a-zA-Z\s]+)\s*(\d+)$/i',
            // "acts2" (chapter only)
            '/^([a-zA-Z]+)(\d+)$/i'
        ];

        foreach ($patterns as $pattern) {
            if (preg_match($pattern, $input, $matches)) {
                $bookName = trim($matches[1]);
                $chapter = (int) $matches[2];
                $startVerse = isset($matches[3]) ? (int) $matches[3] : null;
                $endVerse = isset($matches[4]) ? (int) $matches[4] : null;

                // Find the OSIS book ID
                $osisId = $this->findBookOsisId($bookName);
                if ($osisId) {
                    if ($startVerse && $endVerse) {
                        // Verse range
                        return [
                            'book_osis_id' => $osisId,
                            'chapter' => $chapter,
                            'start_verse' => $startVerse,
                            'end_verse' => $endVerse,
                            'type' => 'verse_range'
                        ];
                    } elseif ($startVerse) {
                        // Single verse
                        return [
                            'book_osis_id' => $osisId,
                            'chapter' => $chapter,
                            'verse' => $startVerse,
                            'type' => 'verse'
                        ];
                    } else {
                        // Chapter only
                        return [
                            'book_osis_id' => $osisId,
                            'chapter' => $chapter,
                            'type' => 'chapter'
                        ];
                    }
                }
            }
        }

        return null;
    }

    /**
     * Find OSIS book ID from various book name formats
     */
    private function findBookOsisId(string $bookName): ?string
    {
        $bookName = strtolower(trim($bookName));

        // Common book name mappings (full names and abbreviations)
        $bookMappings = [
            // Old Testament
            'genesis' => 'Gen', 'gen' => 'Gen', 'ge' => 'Gen',
            'exodus' => 'Exod', 'exod' => 'Exod', 'ex' => 'Exod', 'exo' => 'Exod',
            'leviticus' => 'Lev', 'lev' => 'Lev', 'le' => 'Lev',
            'numbers' => 'Num', 'num' => 'Num', 'nu' => 'Num',
            'deuteronomy' => 'Deut', 'deut' => 'Deut', 'de' => 'Deut', 'dt' => 'Deut',
            'joshua' => 'Josh', 'josh' => 'Josh', 'jos' => 'Josh',
            'judges' => 'Judg', 'judg' => 'Judg', 'jdg' => 'Judg',
            'ruth' => 'Ruth', 'ru' => 'Ruth',
            '1 samuel' => '1Sam', '1samuel' => '1Sam', '1sam' => '1Sam', '1sa' => '1Sam',
            '2 samuel' => '2Sam', '2samuel' => '2Sam', '2sam' => '2Sam', '2sa' => '2Sam',
            '1 kings' => '1Kgs', '1kings' => '1Kgs', '1kgs' => '1Kgs', '1ki' => '1Kgs',
            '2 kings' => '2Kgs', '2kings' => '2Kgs', '2kgs' => '2Kgs', '2ki' => '2Kgs',
            '1 chronicles' => '1Chr', '1chronicles' => '1Chr', '1chr' => '1Chr', '1ch' => '1Chr',
            '2 chronicles' => '2Chr', '2chronicles' => '2Chr', '2chr' => '2Chr', '2ch' => '2Chr',
            'ezra' => 'Ezra', 'ezr' => 'Ezra',
            'nehemiah' => 'Neh', 'neh' => 'Neh', 'ne' => 'Neh',
            'esther' => 'Esth', 'esth' => 'Esth', 'est' => 'Esth',
            'job' => 'Job', 'jb' => 'Job',
            'psalms' => 'Ps', 'psalm' => 'Ps', 'ps' => 'Ps', 'psa' => 'Ps',
            'proverbs' => 'Prov', 'prov' => 'Prov', 'pr' => 'Prov', 'pro' => 'Prov',
            'ecclesiastes' => 'Eccl', 'eccl' => 'Eccl', 'ecc' => 'Eccl', 'ec' => 'Eccl',
            'song of solomon' => 'Song', 'song' => 'Song', 'sos' => 'Song', 'so' => 'Song',
            'isaiah' => 'Isa', 'isa' => 'Isa', 'is' => 'Isa',
            'jeremiah' => 'Jer', 'jer' => 'Jer', 'je' => 'Jer',
            'lamentations' => 'Lam', 'lam' => 'Lam', 'la' => 'Lam',
            'ezekiel' => 'Ezek', 'ezek' => 'Ezek', 'eze' => 'Ezek', 'ez' => 'Ezek',
            'daniel' => 'Dan', 'dan' => 'Dan', 'da' => 'Dan',
            'hosea' => 'Hos', 'hos' => 'Hos', 'ho' => 'Hos',
            'joel' => 'Joel', 'joe' => 'Joel', 'jl' => 'Joel',
            'amos' => 'Amos', 'am' => 'Amos',
            'obadiah' => 'Obad', 'obad' => 'Obad', 'ob' => 'Obad',
            'jonah' => 'Jonah', 'jon' => 'Jonah', 'jnh' => 'Jonah',
            'micah' => 'Mic', 'mic' => 'Mic', 'mi' => 'Mic',
            'nahum' => 'Nah', 'nah' => 'Nah', 'na' => 'Nah',
            'habakkuk' => 'Hab', 'hab' => 'Hab', 'hb' => 'Hab',
            'zephaniah' => 'Zeph', 'zeph' => 'Zeph', 'zep' => 'Zeph',
            'haggai' => 'Hag', 'hag' => 'Hag', 'hg' => 'Hag',
            'zechariah' => 'Zech', 'zech' => 'Zech', 'zec' => 'Zech',
            'malachi' => 'Mal', 'mal' => 'Mal', 'ml' => 'Mal',

            // New Testament
            'matthew' => 'Matt', 'matt' => 'Matt', 'mt' => 'Matt',
            'mark' => 'Mark', 'mk' => 'Mark', 'mr' => 'Mark',
            'luke' => 'Luke', 'lk' => 'Luke', 'lu' => 'Luke',
            'john' => 'John', 'jn' => 'John', 'joh' => 'John',
            'acts' => 'Acts', 'act' => 'Acts', 'ac' => 'Acts',
            'romans' => 'Rom', 'rom' => 'Rom', 'ro' => 'Rom',
            '1 corinthians' => '1Cor', '1corinthians' => '1Cor', '1cor' => '1Cor', '1co' => '1Cor',
            '2 corinthians' => '2Cor', '2corinthians' => '2Cor', '2cor' => '2Cor', '2co' => '2Cor',
            'galatians' => 'Gal', 'gal' => 'Gal', 'ga' => 'Gal',
            'ephesians' => 'Eph', 'eph' => 'Eph', 'ep' => 'Eph',
            'philippians' => 'Phil', 'phil' => 'Phil', 'php' => 'Phil', 'pp' => 'Phil',
            'colossians' => 'Col', 'col' => 'Col', 'co' => 'Col',
            '1 thessalonians' => '1Thess', '1thessalonians' => '1Thess', '1thess' => '1Thess', '1th' => '1Thess',
            '2 thessalonians' => '2Thess', '2thessalonians' => '2Thess', '2thess' => '2Thess', '2th' => '2Thess',
            '1 timothy' => '1Tim', '1timothy' => '1Tim', '1tim' => '1Tim', '1ti' => '1Tim',
            '2 timothy' => '2Tim', '2timothy' => '2Tim', '2tim' => '2Tim', '2ti' => '2Tim',
            'titus' => 'Titus', 'tit' => 'Titus', 'ti' => 'Titus',
            'philemon' => 'Phlm', 'phlm' => 'Phlm', 'phm' => 'Phlm',
            'hebrews' => 'Heb', 'heb' => 'Heb', 'he' => 'Heb',
            'james' => 'Jas', 'jas' => 'Jas', 'ja' => 'Jas', 'jm' => 'Jas',
            '1 peter' => '1Pet', '1peter' => '1Pet', '1pet' => '1Pet', '1pe' => '1Pet',
            '2 peter' => '2Pet', '2peter' => '2Pet', '2pet' => '2Pet', '2pe' => '2Pet',
            '1 john' => '1John', '1john' => '1John', '1jn' => '1John', '1jo' => '1John',
            '2 john' => '2John', '2john' => '2John', '2jn' => '2John', '2jo' => '2John',
            '3 john' => '3John', '3john' => '3John', '3jn' => '3John', '3jo' => '3John',
            'jude' => 'Jude', 'jud' => 'Jude', 'ju' => 'Jude',
            'revelation' => 'Rev', 'rev' => 'Rev', 're' => 'Rev', 'revelations' => 'Rev',
        ];

        return $bookMappings[$bookName] ?? null;
    }

    /**
     * Get a specific verse by reference
     */
    public function getVerseByReference(string $bookOsisId, int $chapter, int $verse): ?array
    {
        $verseOsisId = $bookOsisId . '.' . $chapter . '.' . $verse;
        $verseText = $this->getVerseText($verseOsisId);

        if (empty($verseText)) {
            return null;
        }

        return [
            'osis_id' => $verseOsisId,
            'book_id' => $bookOsisId,
            'chapter' => $chapter,
            'verse' => $verse,
            'text' => $verseText
        ];
    }
}
