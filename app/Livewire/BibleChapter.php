<?php

namespace App\Livewire;

use App\Support\ResumePosition;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Locked;
use Livewire\Component;
use App\Services\BibleService;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\RateLimiter;

class BibleChapter extends Component
{
    // Every property the browser never edits is #[Locked]: Livewire accepts a client-side update
    // to any unlocked public property, and these feed cache keys, redirects and rendered markup.
    // Only searchQuery is bound (wire:model). Actions may still change locked state server-side.
    #[Locked]
    public $bookOsisId;
    #[Locked]
    public $chapterNumber;
    #[Locked]
    public $currentBook;
    #[Locked]
    public $verses = [];
    #[Locked]
    public $chapters = [];
    #[Locked]
    public $books = [];
    #[Locked]
    public $testamentBooks = [];
    #[Locked]
    public $currentTranslation = null;
    #[Locked]
    public $availableTranslations = [];
    #[Locked]
    public $capabilities = [];
    #[Locked]
    public $chapterTitle = null;
    #[Locked]
    public $paragraphs = [];


    // UI state
    #[Locked]
    public $fontSize = 'base'; // sm, base, lg, xl, 2xl
    public $searchQuery = '';
    #[Locked]
    public $showSearch = false;
    #[Locked]
    public $showBookSelector = false;
    #[Locked]
    public $selectedBookForChapters = null;
    #[Locked]
    public $selectorMode = 'books'; // 'books' or 'chapters'
    #[Locked]
    public $searchResults = [];
    #[Locked]
    public $isSearching = false;
    #[Locked]
    public $searchStats = [];
    #[Locked]
    public $showSearchResults = false;
    #[Locked]
    public $returnToChapter = []; // Store chapter info to return to
    #[Locked]
    public $showStrongsNumbers = false; // Enable/disable Strong's numbers display

    protected $bibleService;

    public function boot(BibleService $bibleService)
    {
        $this->bibleService = $bibleService;
    }

    public function mount($bookOsisId, $chapterNumber)
    {
        $this->bookOsisId = $bookOsisId;
        $this->chapterNumber = (int) $chapterNumber;

        // Get Bible data using the service
        $this->books = $this->bibleService->getBooks();
        $this->currentBook = $this->books->first(function ($book) use ($bookOsisId) {
            return strtolower($book['osis_id']) === strtolower($bookOsisId);
        });

        if (! $this->currentBook) {
            abort(404, "Book not found: {$bookOsisId}");
        }

        // Get chapters and verses - check cache first
        $this->chapters = $this->bibleService->getChapters($bookOsisId);

        $this->verses = $this->chapterParagraphs($bookOsisId, (int) $chapterNumber);


        // Create chapter OSIS reference for error messages
        $chapterOsisRef = $this->bookOsisId . '.' . $this->chapterNumber;

        if ($this->verses->isEmpty()) {
            abort(404, "Chapter not found: {$chapterOsisRef}");
        }

        $this->testamentBooks = $this->getTestamentBooks($this->books);
        $this->capabilities = $this->bibleService->getCapabilities();

        // Get current translation from session
        $this->currentTranslation = $this->bibleService->getCurrentTranslation();
        $this->availableTranslations = $this->bibleService->getAvailableTranslations();

        // Load font size from session or default
        $this->fontSize = session('font_size', 'base');

        // Load Strong's numbers setting from session
        $this->showStrongsNumbers = session('show_strongs_numbers', true);

        // Extract chapter title from first paragraph's first verse if available
        $firstParagraph = $this->verses->first();
        if ($firstParagraph && isset($firstParagraph['verses']) && ! empty($firstParagraph['verses'])) {
            $firstVerse = $firstParagraph['verses'][0];
            if (isset($firstVerse['chapter_titles']) && ! empty($firstVerse['chapter_titles'])) {
                // Extract the title text from the HTML
                if (preg_match('/<div[^>]*>(.*?)<\/div>/', $firstVerse['chapter_titles'], $matches)) {
                    $this->chapterTitle = ['title_text' => $matches[1]];
                }
            }
        }

        // Load paragraphs for the current chapter
        $this->paragraphs = $this->bibleService->getChapterParagraphs($chapterOsisRef);

    }

    /**
     * The neighbouring chapters, rendered (hidden, inert) for the page-peel effect (OD-2: kept).
     *
     * Computed, not public state: they are rendered into the DOM but never serialised into
     * wire:snapshot - as public properties they were 136 KB of Matt 5's 430 KB snapshot, and
     * needed a cache plus a background job to be cheap. These are indexed reads (~0.02 ms).
     */
    #[Computed]
    public function previousChapterVerses()
    {
        return $this->chapterNumber > 1
            ? $this->chapterParagraphs($this->bookOsisId, $this->chapterNumber - 1)
            : collect();
    }

    #[Computed]
    public function nextChapterVerses()
    {
        $last = collect($this->chapters)->max('chapter_number') ?? 0;

        return $this->chapterNumber < $last
            ? $this->chapterParagraphs($this->bookOsisId, $this->chapterNumber + 1)
            : collect();
    }

    /**
     * A chapter's verses grouped for display (paragraphs; Psalm 119 verse by verse so its
     * acrostic titles show).
     *
     * No catch: a database failure here used to be logged and turned into an EMPTY chapter, which
     * the caller then reported as 404 "Chapter not found" - an outage disguised as a missing page.
     */
    private function chapterParagraphs(string $bookOsisId, int $chapterNumber)
    {
        $chapterOsisRef = $bookOsisId . '.' . $chapterNumber;

        if (strtolower($bookOsisId) === 'ps' && $chapterNumber === 119) {
            return $this->bibleService->getVerses($chapterOsisRef)->map(fn ($verse) => [
                'verses' => [$verse],
                'type' => 'individual_verse',
            ]);
        }

        return $this->bibleService->getVersesParagraphStyle($chapterOsisRef);
    }

    public function switchTranslation($translationKey)
    {
        // Switch translation using the service
        $this->bibleService->setCurrentTranslation($translationKey);
        $this->currentTranslation = $this->bibleService->getCurrentTranslation();

        // Refresh books and chapters data
        $this->books = $this->bibleService->getBooks();
        $this->chapters = $this->bibleService->getChapters($this->bookOsisId);
        $this->availableTranslations = $this->bibleService->getAvailableTranslations();

        // Reload verses with new translation
        $chapterOsisRef = $this->bookOsisId . '.' . $this->chapterNumber;

        // For Psalm 119, use individual verse format to properly display acrostic titles
        // For other chapters, use paragraph format for better reading
        if (strtolower($this->bookOsisId) === 'ps' && $this->chapterNumber == 119) {
            // Get individual verses for Psalm 119 to show acrostic titles
            $individualVerses = $this->bibleService->getVerses($chapterOsisRef);
            $this->verses = $individualVerses->map(function ($verse) {
                return [
                    'verses' => [$verse],
                    'type' => 'individual_verse',
                ];
            });
        } else {
            // Get verses grouped by paragraphs for better reading experience
            $this->verses = $this->bibleService->getVersesParagraphStyle($chapterOsisRef);
        }

        // Extract chapter title from first paragraph's first verse if available
        $firstParagraph = $this->verses->first();
        if ($firstParagraph && isset($firstParagraph['verses']) && ! empty($firstParagraph['verses'])) {
            $firstVerse = $firstParagraph['verses'][0];
            if (isset($firstVerse['chapter_titles']) && ! empty($firstVerse['chapter_titles'])) {
                // Extract the title text from the HTML
                if (preg_match('/<div[^>]*>(.*?)<\/div>/', $firstVerse['chapter_titles'], $matches)) {
                    $this->chapterTitle = ['title_text' => $matches[1]];
                }
            }
        } else {
            $this->chapterTitle = null;
        }

        // Emit an event to notify other components of the translation change
        $this->dispatch('translation-changed', $translationKey);

    }

    public function setFontSize($size)
    {
        $validSizes = ['sm', 'base', 'lg', 'xl', '2xl'];
        if (in_array($size, $validSizes)) {
            $this->fontSize = $size;
            session(['font_size' => $size]);
        }
    }

    public function toggleStrongsNumbers()
    {
        $this->showStrongsNumbers = !$this->showStrongsNumbers;
        session(['show_strongs_numbers' => $this->showStrongsNumbers]);
    }

    public function toggleSearch()
    {
        $this->showSearch = ! $this->showSearch;
        if (! $this->showSearch) {
            $this->searchQuery = '';
            $this->searchResults = [];
            $this->searchStats = [];
            $this->isSearching = false;
            $this->showSearchResults = false;
        }
    }

    public function search()
    {
        if (! empty($this->searchQuery)) {
            // The same budget as the search API (AppServiceProvider 'search' limiter), in its own
            // bucket: a Livewire round-trip runs the same FTS query.
            $limit = RateLimiter::limiter('search')(request());
            $key = 'search-livewire:' . $limit->key;

            if (RateLimiter::tooManyAttempts($key, $limit->maxAttempts)) {
                $this->searchResults = [];
                $this->searchStats = ['total_found' => 0, 'search_time_ms' => 0, 'query' => $this->searchQuery, 'throttled' => true];
                $this->showSearchResults = true;
                $this->showSearch = false;

                return;
            }

            RateLimiter::hit($key, $limit->decaySeconds);

            $this->isSearching = true;

            // Store current chapter info for returning
            $this->returnToChapter = [
                'book_osis_id' => $this->bookOsisId,
                'chapter_number' => $this->chapterNumber,
                'book_name' => $this->currentBook['name'] ?? $this->bookOsisId,
            ];

            $invalid = null;

            if (! is_string($this->searchQuery) || preg_match('/[\x00-\x1F\x7F]/u', $this->searchQuery)) {
                $invalid = 'Please remove control characters from your search.';
            } else {
                try {
                    $searchData = $this->bibleService->search($this->searchQuery, 50);
                } catch (\InvalidArgumentException $e) {
                    $invalid = $e->getMessage();
                }
            }

            if ($invalid !== null) {
                $this->isSearching = false;
                $this->searchResults = [];
                $this->searchStats = ['total_found' => 0, 'search_time_ms' => 0, 'query' => (string) $this->searchQuery, 'invalid' => $invalid];
                $this->showSearchResults = true;
                $this->showSearch = false;

                return;
            }

            $this->searchResults = $searchData['results'] ?? [];
            $this->searchStats = [
                'total_found' => $searchData['total_found'] ?? 0,
                'search_time_ms' => $searchData['search_time_ms'] ?? 0,
                'query' => $this->searchQuery,
            ];

            $this->isSearching = false;

            // Switch to search results view
            $this->showSearchResults = true;
            $this->showSearch = false;
        }
    }

    public function backToChapter()
    {
        $this->showSearchResults = false;
        $this->searchResults = [];
        $this->searchStats = [];
        $this->searchQuery = '';
    }

    public function goToPreviousChapter()
    {
        if ($this->chapterNumber > 1) {
            return redirect("/{$this->bookOsisId}/" . ($this->chapterNumber - 1));
        }
    }

    public function goToNextChapter()
    {
        if ($this->chapterNumber < count($this->chapters)) {
            return redirect("/{$this->bookOsisId}/" . ($this->chapterNumber + 1));
        }
    }

    public function clearLastVisited()
    {
        ResumePosition::forget();
    }

    public function openBookSelector()
    {
        $this->showBookSelector = true;
        $this->selectorMode = 'books';
        $this->selectedBookForChapters = null;
    }

    public function hideBookSelector()
    {
        $this->showBookSelector = false;
        $this->selectorMode = 'books';
        $this->selectedBookForChapters = null;
    }

    public function selectBookForChapters($bookOsisId)
    {
        $this->selectedBookForChapters = $this->books->first(function ($book) use ($bookOsisId) {
            return strtolower($book['osis_id']) === strtolower($bookOsisId);
        });
        $this->selectorMode = 'chapters';

        // Load chapters for the selected book
        if ($this->selectedBookForChapters) {
            $this->selectedBookForChapters['chapters'] = $this->bibleService->getChapters($bookOsisId);
        }
    }

    public function goToChapter($bookOsisId, $chapterNumber)
    {
        $this->hideBookSelector();

        // Both arguments come from the browser: only a real book and chapter (a raw
        // "/{$bookOsisId}/..." with bookOsisId "/evil.example" was a protocol-relative redirect).
        $book = is_string($bookOsisId)
            ? $this->books->first(fn ($b) => strtolower($b['osis_id']) === strtolower($bookOsisId))
            : null;

        if (! $book || ! is_numeric($chapterNumber) || ! $this->bibleService->chapterExists($book['osis_id'], (int) $chapterNumber)) {
            return null;
        }

        return redirect()->route('chapters.show', ['bookOsisId' => $book['osis_id'], 'chapterNumber' => (int) $chapterNumber]);
    }

    public function backToBooks()
    {
        $this->selectorMode = 'books';
        $this->selectedBookForChapters = null;
    }

    private function getTestamentBooks($books): array
    {
        $oldTestament = [];
        $newTestament = [];

        foreach ($books as $book) {
            if ($book['testament'] === 'Old Testament') {
                $oldTestament[] = $book;
            } else {
                $newTestament[] = $book;
            }
        }

        return [
            'oldTestament' => $oldTestament,
            'newTestament' => $newTestament,
        ];
    }

    /**
     * Previous/next chapter targets, crossing book boundaries (Mal 4 -> Matt 1; Gen 1 has no
     * previous, Rev 22 no next). URLs are the canonical /books/{book}/chapters/{n} route, so a
     * page turn is one request - the old /{book}/{n} short form answered with a redirect.
     *
     * @return array{prev: ?array{url: string, label: string}, next: ?array{url: string, label: string}}
     */
    public function getNavigationTargets(): array
    {
        $books = collect($this->books)->values();
        $current = (array) $this->currentBook;
        $index = $books->search(fn ($book) => $book['osis_id'] === ($current['osis_id'] ?? null));
        $lastChapter = (int) (collect($this->chapters)->max('chapter_number') ?? 0);

        $target = fn (array $book, int $n) => [
            'url' => route('chapters.show', ['bookOsisId' => $book['osis_id'], 'chapterNumber' => $n], false),
            'label' => ($book['name'] ?? $book['osis_id']) . ' ' . $n,
        ];

        $prev = null;
        if ($this->chapterNumber > 1) {
            $prev = $target($current, $this->chapterNumber - 1);
        } elseif ($index !== false && $index > 0) {
            $book = $books[$index - 1];
            $count = (int) ($this->bibleService->getChapters($book['osis_id'])->max('chapter_number') ?? 0);
            $prev = $count > 0 ? $target($book, $count) : null;
        }

        $next = null;
        if ($this->chapterNumber < $lastChapter) {
            $next = $target($current, $this->chapterNumber + 1);
        } elseif ($index !== false && $index < $books->count() - 1) {
            $next = $target($books[$index + 1], 1);
        }

        return ['prev' => $prev, 'next' => $next];
    }

    public function getFontSizeClass(): string
    {
        $sizeMap = [
            'sm' => 'text-lg',
            'base' => 'text-xl',
            'lg' => 'text-2xl',
            'xl' => 'text-3xl',
            '2xl' => 'text-4xl',
        ];

        return $sizeMap[$this->fontSize] ?? 'text-xl';
    }

    /**
     * Parse the original XML markup and convert it to enhanced HTML with Strong's styling
     */
        public function parseEnhancedVerseText($verse): string
    {
        $osisId = $verse['osis_id'] ?? 'unknown';
        $verseNum = $verse['verse_number'] ?? 'unknown';

                                // Let the XML processing handle red letters - don't override with manual mappings

        // If Strong's numbers are disabled, use regular text but still handle red letters
        if (!$this->showStrongsNumbers) {
            $basicText = strip_tags($verse['text'], '<em><strong><sup><sub><span>');
            // Still need to handle red letters even when Strong's are disabled
            return $this->applyRedLetterFormatting($basicText, $verse);
        }

        // Fallback to regular text if no original_xml
        if (!isset($verse['original_xml']) || empty($verse['original_xml'])) {
            $basicText = strip_tags($verse['text'], '<em><strong><sup><sub><span>');
            return $this->applyRedLetterFormatting($basicText, $verse);
        }

        $xml = $verse['original_xml'];

        // First, handle red letter text (Jesus's words) before processing other elements
        $xml = $this->processRedLetterText($xml);

        // Remove note tags and other non-essential elements
        $xml = preg_replace('/<note[^>]*>.*?<\/note>/i', '', $xml); // Remove study notes
        $xml = preg_replace('/<milestone[^>]*\/?>/i', '', $xml); // Remove milestone markers

        // A self-closing <w .../> carries a Strong's number for a word left untranslated (e.g. the
        // Greek article) and has no text of its own. The old pattern paired it with the NEXT
        // word's </w>, so that word showed the wrong number (John 3:16 "For" -> G3588) and the two
        // words were glued together (Matt 6:10 "as it isin heaven"). Drop them before pairing -
        // as a SPACE, because the source sometimes uses one as the only separator between two
        // words (Matt 12:31 "<w>but</w><w .../>the"); stray spaces before punctuation are
        // removed by the clean-up below.
        $xml = preg_replace('/<w\b[^>]*\/>/', ' ', $xml) ?? $xml;
        // <inscription> (Exod 28:36, Dan 5:25-28) is set in small capitals in the KJV.
        $xml = preg_replace('/<inscription\b[^>]*>(.*?)<\/inscription>/is', '<span style="font-variant: small-caps;">$1</span>', $xml) ?? $xml;
        // <foreign> (e.g. the letter names heading Ps 119's sections): keep the text, drop the tag.
        $xml = preg_replace('/<\/?foreign\b[^>]*>/i', '', $xml) ?? $xml;

        // Parse the XML and convert <w> tags to enhanced spans. /s: some stored fragments are
        // pretty-printed across lines, and without it those <w> elements leaked through raw.
        $enhanced = preg_replace_callback(
            '/<w\b([^>]*)>(.*?)<\/w>/s',
            function ($matches) {
                $attributes = $matches[1];
                $text = $matches[2];

                $classes = ['strong-word'];
                $title = '';
                $dataAttrs = '';

                // Extract Strong's numbers
                if (preg_match('/lemma="([^"]*)"/', $attributes, $lemmaMatch)) {
                    $lemmas = $lemmaMatch[1];
                    if (strpos($lemmas, 'strong:') !== false) {
                        preg_match_all('/strong:([HG]\d+)/', $lemmas, $strongMatches);
                        if (!empty($strongMatches[1])) {
                            $strongNumbers = $strongMatches[1];
                            $classes[] = 'has-strongs';
                            $title = 'Strong\'s: ' . implode(', ', $strongNumbers);
                            $dataAttrs = 'data-strongs="' . implode(',', $strongNumbers) . '"';

                            // Add different styling for Hebrew vs Greek
                            if (strpos($strongNumbers[0], 'H') === 0) {
                                $classes[] = 'hebrew-word';
                            } else {
                                $classes[] = 'greek-word';
                            }
                        }
                    }
                }

                // Extract morphology
                if (preg_match('/morph="([^"]*)"/', $attributes, $morphMatch)) {
                    $morph = $morphMatch[1];
                    if (strpos($morph, 'strongMorph:') !== false) {
                        $classes[] = 'has-morph';
                        $morphCode = str_replace('strongMorph:', '', $morph);
                        $dataAttrs .= ' data-morph="' . $morphCode . '"';
                        $title .= $title ? ' | Morph: ' . $morphCode : 'Morph: ' . $morphCode;
                    }
                }

                $classStr = implode(' ', $classes);
                $titleAttr = $title ? 'title="' . htmlspecialchars($title) . '"' : '';

                return "<span class=\"{$classStr}\" {$titleAttr} {$dataAttrs}>{$text}</span>";
            },
            $xml
        );

        if ($enhanced === null) {
            // PCRE failure (e.g. backtrack limit). Show the words rather than raw OSIS.
            \Log::warning('Strong\'s render fallback', ['osis_id' => $osisId, 'preg' => preg_last_error_msg()]);
            $enhanced = strip_tags($xml, '<em><span><strong>');
        }

        // Convert KJV translator-supplied words (rendered in italics by convention) and
        // divine names (small caps LORD, distinguishing YHWH from Adonai).
        //
        // parseEnhancedVerseText is the DEFAULT render path - showStrongsNumbers resolves to
        // true via session('show_strongs_numbers', true) - but it previously handled only
        // <note>, <milestone>, <w> and <q>. <transChange> (14,233 verses) and <divineName>
        // (5,828 verses) fell through untouched and reached the browser as raw OSIS tags.
        // Browsers render unknown inline elements as their text content, so Ps 23:1 read
        // "The Lordis my shepherd" - no small caps, no italics, and no space.
        //
        // Classes match ImportOsisCommand::formatTranslatorChanges/formatDivineNames so the
        // Strong's-on and Strong's-off paths render identically.
        $enhanced = preg_replace(
            '/<transChange[^>]*type="added"[^>]*>(.*?)<\/transChange>/is',
            '<em class="text-gray-600 dark:text-gray-400 font-normal italic">$1</em>',
            $enhanced
        );
        $enhanced = preg_replace(
            '/<divineName[^>]*>(.*?)<\/divineName>/is',
            '<span style="font-variant: small-caps; font-weight: 600; letter-spacing: 0.05em; text-transform: uppercase; font-size: 0.95em;">$1</span>',
            $enhanced
        );

        // Fix spacing issues: add spaces between words that don't have punctuation between them
        $enhanced = preg_replace('/><span class="strong-word/', '> <span class="strong-word', $enhanced);
        // Same for translator-added words, which sit between <w> elements with no whitespace
        // in the source (<w>These</w><transChange>are</transChange> -> "Theseare").
        $enhanced = preg_replace('/><em class="text-gray-600/', '> <em class="text-gray-600', $enhanced);

        // Clean up extra spaces and fix spacing around punctuation
        $enhanced = preg_replace('/\s+/', ' ', $enhanced); // Multiple spaces to single space
        $enhanced = preg_replace('/\s+([,.;:!?])/', '$1', $enhanced); // Remove space before punctuation
        // ...including when closing tags sit between them: pretty-printed fragments leave
        // "<span>Lord</span> </span>;" which rendered "Lord ;" (Gen 15:2, Ps 12:1, ...).
        $enhanced = preg_replace('/\s+((?:<\/(?:span|em)>)+)(?=[,.;:!?])/', '$1', $enhanced);
        $enhanced = preg_replace('/([(\[])\s+/', '$1', $enhanced); // "( they" -> "(they"
        $enhanced = trim($enhanced);

        return $enhanced;
    }

    /**
     * Process red letter text (Jesus's words) in the XML markup
     * Handles both contained and milestone-style markup with proper nesting
     */
    private function processRedLetterText($xml): string
    {

        // First, handle simple contained markup: <q who="Jesus">content</q>
        $xml = preg_replace('/<q[^>]*who="Jesus"[^>]*>(.*?)<\/q>/is', '<span class="text-red-600 dark:text-red-400 font-medium">$1</span>', $xml);

        // Handle milestone-style red letter markup more carefully
        // Look for Jesus speech markers and track their state
        $redLetterOpen = false;
        $result = '';
        $pos = 0;

        while ($pos < strlen($xml)) {
            // Look for <q who="Jesus" markers
            if (preg_match('/<q[^>]*who="Jesus"[^>]*>/i', $xml, $matches, PREG_OFFSET_CAPTURE, $pos)) {
                $match = $matches[0];
                $matchStart = $match[1];

                // Add content before the match
                $result .= substr($xml, $pos, $matchStart - $pos);

                // Check if this is opening or closing a red letter section
                if (strpos($match[0], 'marker=""') !== false || strpos($match[0], 'sID=') !== false) {
                    // Opening marker
                    if (!$redLetterOpen) {
                        $result .= '<span class="text-red-600 dark:text-red-400 font-medium">';
                        $redLetterOpen = true;
                    }
                } else if (strpos($match[0], 'eID=') !== false) {
                    // Closing marker
                    if ($redLetterOpen) {
                        $result .= '</span>';
                        $redLetterOpen = false;
                    }
                }

                $pos = $matchStart + strlen($match[0]);
            } else {
                // No more Jesus markers, add remaining content
                $result .= substr($xml, $pos);
                break;
            }
        }

        // Clean up any remaining </q> tags that weren't properly handled
        $result = preg_replace('/<\/q>/i', $redLetterOpen ? '</span>' : '', $result);

        // If we ended with an open red letter span, close it
        if ($redLetterOpen) {
            $result .= '</span>';
        }

        return $result;
    }

        /**
     * Apply red letter formatting using the red_letter_text table data
     */
    private function applyRedLetterFormatting($text, $verse): string
    {
        $osisId = $verse['osis_id'] ?? 'unknown';

        // This method now relies primarily on the database red_letter_text entries
        // Run: php artisan bible:fix-red-letters to populate missing red letter entries

        // Check if we have original XML with red letter markup first
        if (isset($verse['original_xml']) && !empty($verse['original_xml'])) {
            $xml = $verse['original_xml'];

            // Check if XML contains Jesus speech markers
            if (preg_match('/<q[^>]*who="Jesus"[^>]*>/i', $xml)) {
                // Let the main XML processing handle this
                return $text;
            }
        }

        // If we have red letter data for this verse in the database, apply it
        $verseId = $verse['id'] ?? null;
        if ($verseId) {
            $redLetterEntries = \DB::table('red_letter_text')
                ->where('verse_id', $verseId)
                ->orderBy('text_order')
                ->get();


            if ($redLetterEntries->count() > 0) {
                // For verses with red letter entries, wrap the entire verse in red letter styling
                // This handles cases where the verse text has HTML markup that doesn't match exactly
                $text = '<span class="text-red-600 dark:text-red-400 font-medium">' . $text . '</span>';

                // Convert any gray translator changes to red italics for consistency
                $text = $this->applyRedLetterTranslatorChanges($text);
            }
        } else {
        }

        return $text;
    }



    /**
     * Convert gray translator changes to red italics for red letter verses
     */
    private function applyRedLetterTranslatorChanges($text): string
    {
        // Replace gray translator change styling with red italics for Jesus's words
        $text = str_replace(
            'class="text-gray-600 dark:text-gray-400 font-normal italic"',
            'class="text-red-500 dark:text-red-300 font-normal italic opacity-80"',
            $text
        );

        return $text;
    }

    public function render()
    {
        return view('livewire.bible-chapter');
    }
}
