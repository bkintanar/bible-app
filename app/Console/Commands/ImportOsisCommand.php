<?php

namespace App\Console\Commands;

use DOMXPath;
use Exception;
use DOMDocument;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class ImportOsisCommand extends Command
{
    protected $signature = 'osis:import {file=assets/kjv.osis.xml} {--chunk=100 : Number of verses to process per chunk}';
    protected $description = 'Import OSIS XML file into the comprehensive Bible database';

    private $versionId;
    private $books = [];
    private $chapters = [];
    private $verseCount = 0;
    private $wordElementCount = 0;
    private $translatorChangeCount = 0;
    private $divineNameCount = 0;
    private $redLetterCount = 0;
    private $titleCount = 0;
    private $poetryLineCount = 0;
    private $paragraphCount = 0;
    private $xpath;
    private $doc;

    // Paragraph tracking
    private $currentParagraphStart = null;
    private $currentParagraphVerses = [];
    private $currentChapterId = null;

    public function handle()
    {
        $file = $this->argument('file');
        $chunkSize = (int) $this->option('chunk');

        if (! file_exists($file)) {
            $this->error("File not found: {$file}");
            return 1;
        }

        $this->info("🚀 Starting OSIS XML import from: {$file}");
        $this->info("📊 Chunk size: {$chunkSize} verses");

        try {
            // Load and validate XML
            $this->loadXML($file);

            // Create Bible version record
            $this->createBibleVersion();

            // Import structure and content
            $this->importBooks();
            $this->importChapters();

            // Process book titles after chapters are available
            $this->processAllBookTitles();

            $this->importVerses($chunkSize);

            // Finalize any remaining paragraphs
            $this->finalizeParagraphs();

            // Update FTS tables
            $this->updateFTSTables();

            // Add comprehensive red letter entries
            $this->addComprehensiveRedLetters();

            $this->displaySummary();

        } catch (Exception $e) {
            $this->error('❌ Import failed: ' . $e->getMessage());
            Log::error('OSIS Import Error', ['error' => $e->getMessage(), 'trace' => $e->getTraceAsString()]);
            return 1;
        }

        return 0;
    }

    private function loadXML($file)
    {
        $this->info('📖 Loading XML file...');

        $this->doc = new DOMDocument();
        $this->doc->preserveWhiteSpace = false;
        $this->doc->formatOutput = true;

        // Load with error handling
        libxml_use_internal_errors(true);
        if (! $this->doc->load($file)) {
            $errors = libxml_get_errors();
            throw new Exception('XML parsing failed: ' . implode(', ', array_map(fn ($e) => $e->message, $errors)));
        }

        $this->xpath = new DOMXPath($this->doc);
        $this->xpath->registerNamespace('osis', 'http://www.bibletechnologies.net/2003/OSIS/namespace');

        $this->info('✅ XML loaded successfully');
    }

    private function createBibleVersion()
    {
        $this->info('📚 Creating Bible version record...');

        // Extract version information from the OSIS file
        $versionInfo = $this->extractVersionInfo();

        if (! $versionInfo) {
            throw new Exception('Could not extract version information from OSIS file');
        }

        // Check if version already exists
        $existingVersion = DB::table('bible_versions')
            ->where('osis_work', $versionInfo['osis_work'])
            ->first();

        if ($existingVersion) {
            $this->warn("⚠️  Bible version '{$versionInfo['osis_work']}' already exists (ID: {$existingVersion->id})");
            $this->warn('    This import will re-process titles and other elements.');
            $this->versionId = $existingVersion->id;
            return;
        }

        $this->versionId = DB::table('bible_versions')->insertGetId([
            'osis_work' => $versionInfo['osis_work'],
            'abbreviation' => $versionInfo['abbreviation'],
            'title' => $versionInfo['title'],
            'language' => $versionInfo['language'],
            'description' => $versionInfo['description'],
            'publisher' => $versionInfo['publisher'],
            'canonical' => true,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $this->info("✅ Bible version created (ID: {$this->versionId})");
    }

    /**
     * Extract version information from the OSIS XML file
     */
    private function extractVersionInfo()
    {
        // Get the osisText element to extract basic info
        $osisText = $this->xpath->query('//osis:osisText')->item(0);
        if (! $osisText) {
            return null;
        }

        $osisIDWork = $osisText->getAttribute('osisIDWork') ?: 'Unknown';
        $language = $osisText->getAttribute('xml:lang') ?: 'en';

        // Get work element for detailed information
        $workElement = $this->xpath->query('//osis:work[@osisWork]')->item(0);

        $title = 'Unknown Bible Version';
        $description = 'Bible version imported from OSIS XML';
        $publisher = 'Unknown';

        if ($workElement) {
            // Try to get title from work element
            $titleElement = $this->xpath->query('.//osis:title', $workElement)->item(0);
            if ($titleElement) {
                $title = trim($titleElement->textContent);
            }

            // Try to get description
            $descElement = $this->xpath->query('.//osis:description', $workElement)->item(0);
            if ($descElement) {
                $description = trim($descElement->textContent);
            }
        }

        // Set default values based on known versions
        $versionMappings = [
            'KJV' => [
                'title' => 'King James Version',
                'publisher' => 'Public Domain',
                'description' => 'King James Version imported from OSIS XML',
            ],
            'ASV' => [
                'title' => 'American Standard Version',
                'publisher' => 'Public Domain',
                'description' => 'American Standard Version imported from OSIS XML',
            ],
            'MAO' => [
                'title' => 'Maori Bible',
                'publisher' => 'Public Domain',
                'description' => 'Maori Bible imported from OSIS XML',
            ],
        ];

        if (isset($versionMappings[$osisIDWork])) {
            $mapping = $versionMappings[$osisIDWork];
            $title = $mapping['title'];
            $publisher = $mapping['publisher'];
            $description = $mapping['description'];
        }

        return [
            'osis_work' => 'Bible.' . strtolower($language) . '.' . strtolower($osisIDWork),
            'abbreviation' => $osisIDWork,
            'title' => $title,
            'language' => $language,
            'description' => $description,
            'publisher' => $publisher,
        ];
    }

    private function importBooks()
    {
        $this->info('📚 Loading existing books...');

        // Load existing books from database instead of creating new ones
        // Books are shared across all Bible versions
        $existingBooks = DB::table('books')->get();

        foreach ($existingBooks as $book) {
            $this->books[$book->osis_id] = $book->id;
        }

        // Verify that the OSIS file contains the expected books
        $bookDivs = $this->xpath->query('//osis:div[@type="book"]');
        $osisBooks = [];

        foreach ($bookDivs as $bookDiv) {
            $osisId = $bookDiv->getAttribute('osisID');
            if ($osisId) {
                $osisBooks[] = $osisId;
            }
        }

        // Check for any missing books in our database
        $missingBooks = [];
        foreach ($osisBooks as $osisBook) {
            if (! isset($this->books[$osisBook])) {
                $missingBooks[] = $osisBook;
            }
        }

        if (! empty($missingBooks)) {
            $this->warn('⚠️  Some books from OSIS are not in database: ' . implode(', ', $missingBooks));
            $this->warn('    You may need to add these books to the database first.');
        }

        $foundBooks = array_intersect($osisBooks, array_keys($this->books));
        $this->info('✅ Books available for import: ' . count($foundBooks) . ' (Total in OSIS: ' . count($osisBooks) . ')');
    }

    private function importChapters()
    {
        $this->info('📖 Importing chapters...');

        // Get all chapters with namespace
        $chapterElements = $this->xpath->query('//osis:chapter[@osisID]');

        foreach ($chapterElements as $chapterElement) {
            $osisId = $chapterElement->getAttribute('osisID');
            if (! $osisId) {
                continue;
            }

            // Parse book and chapter (e.g., "Gen.1" -> "Gen", 1)
            $parts = explode('.', $osisId);
            if (count($parts) < 2) {
                continue;
            }

            $bookOsis = $parts[0];
            $chapterNumber = (int) $parts[1];

            $bookId = $this->books[$bookOsis] ?? null;
            if (! $bookId) {
                continue;
            }

            // Check if this chapter already exists for this version
            $existingChapter = DB::table('chapters')
                ->where('book_id', $bookId)
                ->where('version_id', $this->versionId)
                ->where('chapter_number', $chapterNumber)
                ->first();

            if ($existingChapter) {
                $this->chapters[$osisId] = $existingChapter->id;
                continue; // Skip if chapter already exists for this version
            }

            $chapterTitle = $chapterElement->getAttribute('chapterTitle');

            $chapterId = DB::table('chapters')->insertGetId([
                'book_id' => $bookId,
                'version_id' => $this->versionId,
                'chapter_number' => $chapterNumber,
                'osis_ref' => $osisId,
                'osis_id' => $osisId,
                'chapter_title' => $chapterTitle,
                'created_at' => now(),
                'updated_at' => now(),
            ]);

            $this->chapters[$osisId] = $chapterId;

            // Process any title elements that immediately follow this chapter
            $this->processChapterTitles($chapterElement, $chapterId);
        }

        $this->info('✅ Chapters imported: ' . count($this->chapters));
    }

    /**
     * Process titles that appear at the chapter level (like chapter headings)
     * @param mixed $chapterElement
     * @param mixed $chapterId
     */
    private function processChapterTitles($chapterElement, $chapterId)
    {
        $current = $chapterElement->nextSibling;
        $order = 1;

        while ($current) {
            if ($current->nodeType === XML_ELEMENT_NODE) {
                $nodeName = $current->localName ?: $current->nodeName;

                if ($nodeName === 'title') {
                    $titleType = $current->getAttribute('type') ?: 'main';

                    // Only process titles that should be at chapter level
                    if ($titleType === 'chapter') {
                        $this->importChapterTitle($current, $chapterId, $order);
                        $order++;
                    }
                } elseif ($nodeName === 'verse') {
                    // Stop when we reach the first verse
                    break;
                }
            }
            $current = $current->nextSibling;
        }
    }

    /**
     * Import a title specifically associated with a chapter
     * @param mixed $element
     * @param mixed $chapterId
     * @param mixed $order
     */
    private function importChapterTitle($element, $chapterId, $order)
    {
        // Extract inner content and format to HTML (preserve formatting)
        $innerContent = '';
        foreach ($element->childNodes as $child) {
            $innerContent .= $this->doc->saveXML($child);
        }

        // Format the title content to HTML
        $formattedText = $this->formatToHTML($innerContent);

        $titleType = $element->getAttribute('type') ?: 'chapter';
        $canonical = $element->getAttribute('canonical') === 'true';

        // Store title in database
        $titleData = [
            'verse_id' => null, // Chapter titles are not associated with specific verses
            'chapter_id' => $chapterId,
            'title_type' => $titleType,
            'title_text' => trim($formattedText),
            'canonical' => $canonical,
            'placement' => 'before',
            'title_order' => $order,
            'attributes' => json_encode($this->getElementAttributes($element)),
            'created_at' => now(),
            'updated_at' => now(),
        ];

        DB::table('titles')->insert($titleData);
        $this->titleCount++;
    }

    private function importVerses($chunkSize)
    {
        $this->info('📝 Importing verses and content...');

        // Get all verse start markers with namespace
        $verses = $this->xpath->query('//osis:verse[@osisID][@sID]');
        $totalVerses = $verses->length;

        $this->info("📊 Total verses to import: {$totalVerses}");

        $bar = $this->output->createProgressBar($totalVerses);
        $bar->start();

        $processed = 0;
        foreach ($verses as $verseElement) {
            $this->processVerse($verseElement);
            $processed++;

            if ($processed % 100 === 0) {
                $bar->advance(100);
            }
        }

        $bar->finish();
        $this->newLine();
    }

    private function processVerse($verseElement)
    {
        $osisId = $verseElement->getAttribute('osisID');
        $sId = $verseElement->getAttribute('sID');
        if (! $osisId || ! $sId) {
            return;
        }

        // Parse verse reference (e.g., "Gen.1.1")
        $parts = explode('.', $osisId);
        if (count($parts) < 3) {
            return;
        }

        $bookOsis = $parts[0];
        $chapterNum = (int) $parts[1];
        $verseNum = (int) $parts[2];

        // Find chapter
        $chapterRef = $bookOsis . '.' . $chapterNum;
        $chapterId = $this->chapters[$chapterRef] ?? null;
        if (! $chapterId) {
            return;
        }

        // Handle paragraph tracking when switching chapters
        if ($this->currentChapterId !== $chapterId) {
            // Save previous chapter's final paragraph if exists
            if ($this->currentChapterId && $this->currentParagraphStart !== null && ! empty($this->currentParagraphVerses)) {
                $this->saveParagraph($this->currentChapterId, $this->currentParagraphStart, $this->currentParagraphVerses);
            }

            // Reset for new chapter
            $this->currentChapterId = $chapterId;
            $this->currentParagraphStart = null;
            $this->currentParagraphVerses = [];

            // Always start a paragraph at verse 1 of each chapter
            if ($verseNum === 1) {
                $this->currentParagraphStart = 1;
                $this->currentParagraphVerses = [1];
            }
        }

        // Check if this verse already exists in this chapter
        $existingVerse = DB::table('verses')
            ->where('chapter_id', $chapterId)
            ->where('verse_number', $verseNum)
            ->first();

        if ($existingVerse) {
            // Update the existing verse with ASV content instead of skipping
            $content = $this->extractVerseContent($verseElement);

            DB::table('verses')
                ->where('id', $existingVerse->id)
                ->update([
                    'text' => $content['text'],
                    'formatted_text' => $content['formatted'],
                    'original_xml' => $content['xml'],
                    'updated_at' => now(),
                ]);

            $verseId = $existingVerse->id;
        } else {
            // Extract verse content
            $content = $this->extractVerseContent($verseElement);

            // Insert new verse
            $verseId = DB::table('verses')->insertGetId([
                'chapter_id' => $chapterId,
                'verse_number' => $verseNum,
                'osis_id' => $osisId,
                'se_id' => $sId,
                'text' => $content['text'],
                'formatted_text' => $content['formatted'],
                'original_xml' => $content['xml'],
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }

        $this->verseCount++;

        // Check for paragraph markers in verse content BEFORE adding to current paragraph
        $hasNewParagraphMarker = preg_match('/<milestone[^>]*type="x-p"[^>]*marker="¶"[^>]*>/', $content['xml']);

        if ($hasNewParagraphMarker) {
            $this->processParagraphMarkers($content['xml'], $verseNum);
        } else {
            // Add verse to current paragraph tracking only if it doesn't start a new paragraph
            if ($this->currentParagraphStart !== null && ! in_array($verseNum, $this->currentParagraphVerses)) {
                $this->currentParagraphVerses[] = $verseNum;
            }
        }

        // Check for titles that come before this verse
        $this->extractVerseTitles($verseElement, $verseId);

        // Extract poetry structure that contains this verse
        $this->extractPoetryStructure($verseElement, $verseId);

        // Process verse elements
        $this->processVerseElements($verseElement, $verseId, $content['xml']);
    }

    private function extractPoetryStructure($verseElement, $verseId)
    {
        // Check if this verse is contained within poetry structure (lg, l elements)
        $current = $verseElement->parentNode;
        $order = 1;

        // Get verse reference for book detection
        $verseRef = $verseElement->getAttribute('osisID');
        $parts = explode('.', $verseRef);
        $bookOsis = $parts[0] ?? '';

        // List of poetic books that should have poetry formatting
        $poeticBooks = ['Ps', 'Prov', 'Eccl', 'Song', 'Job', 'Lam'];
        $isPoetryBook = in_array($bookOsis, $poeticBooks);

        while ($current && $current->nodeType === XML_ELEMENT_NODE) {
            $nodeName = $current->localName ?: $current->nodeName;

            if ($nodeName === 'l') {
                // This verse is inside a poetry line
                $level = (int) ($current->getAttribute('level') ?: 1);
                $lineText = trim($current->textContent);

                DB::table('poetry_structure')->insert([
                    'verse_id' => $verseId,
                    'structure_type' => 'l',
                    'level' => $level,
                    'line_text' => $lineText,
                    'line_order' => $order,
                    'attributes' => json_encode($this->getElementAttributes($current)),
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);

                $this->poetryLineCount++;
                $order++;
            } elseif ($nodeName === 'lg') {
                // This verse is inside a line group
                $groupText = trim($current->textContent);

                DB::table('poetry_structure')->insert([
                    'verse_id' => $verseId,
                    'structure_type' => 'lg',
                    'level' => 1,
                    'line_text' => $groupText,
                    'line_order' => $order,
                    'attributes' => json_encode($this->getElementAttributes($current)),
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);

                $this->poetryLineCount++;
                $order++;
            } elseif ($nodeName === 'chapter') {
                // Stop when we reach the chapter level
                break;
            }

            $current = $current->parentNode;
        }

        // If no explicit poetry structure found but this is a poetic book,
        // create basic poetry structure
        if ($order === 1 && $isPoetryBook) {
            $verseText = trim($verseElement->textContent);

            // Create a basic poetry line for this verse
            DB::table('poetry_structure')->insert([
                'verse_id' => $verseId,
                'structure_type' => 'l',
                'level' => 1,
                'line_text' => $verseText,
                'line_order' => 1,
                'attributes' => json_encode(['auto_generated' => true, 'book' => $bookOsis]),
                'created_at' => now(),
                'updated_at' => now(),
            ]);

            $this->poetryLineCount++;
        }
    }

    private function extractVerseTitles($verseElement, $verseId)
    {
        // Look backwards from the verse to find any title elements
        $current = $verseElement->previousSibling;
        $order = 1;
        $foundTitles = [];

        while ($current) {
            if ($current->nodeType === XML_ELEMENT_NODE) {
                $nodeName = $current->localName ?: $current->nodeName;

                if ($nodeName === 'title') {
                    // Collect titles but don't process yet (we want to process in forward order)
                    $foundTitles[] = $current;
                } elseif ($nodeName === 'verse') {
                    // Stop when we reach another verse (titles should be immediately before this verse)
                    break;
                } elseif ($nodeName === 'chapter') {
                    // Stop when we reach the chapter start
                    break;
                }
            }
            $current = $current->previousSibling;
        }

        // Process titles in forward order (reverse the array since we collected backwards)
        $foundTitles = array_reverse($foundTitles);
        foreach ($foundTitles as $titleElement) {
            $this->importTitle($titleElement, $verseId, $order);
            $order++;
        }
    }

    private function extractVerseContent($verseElement)
    {
        $osisId = $verseElement->getAttribute('osisID');

        // Use the original DOM traversal method which is much more efficient
        $content = '';
        $current = $verseElement->nextSibling;

        while ($current) {
            // Check if this element contains the verse end marker (recursively)
            if ($current->nodeType === XML_ELEMENT_NODE) {
                $nodeName = $current->localName ?: $current->nodeName;

                // Check if this is the verse end marker
                if ($nodeName === 'verse' && $current->getAttribute('eID') === $osisId) {
                    break;
                }

                // Check if the verse end marker is nested inside this element
                if ($this->containsVerseEndMarker($current, $osisId)) {
                    // Extract content up to the verse end marker
                    $content .= $this->extractContentUpToEndMarker($current, $osisId);
                    break;
                }

                // If no end marker found in this element, add the whole element
                $content .= $this->doc->saveXML($current);
            } elseif ($current->nodeType === XML_TEXT_NODE) {
                $content .= $current->textContent;
            }

            $current = $current->nextSibling;
        }

        // Special handling for verses that have incomplete content due to complex XML structure
        // Only apply this fix for specific problematic verses to avoid performance impact
        $problematicVerses = ['Matt.9.6']; // Add other problematic verses here if needed

        if (in_array($osisId, $problematicVerses)) {
            $plainText = $this->extractPlainTextWithSpacing($content);

            // If the extracted text is suspiciously short, try the line-based extraction as fallback
            if (strlen(trim($plainText)) < 100) {
                $fixedContent = $this->extractVerseContentFromLine($osisId);
                if (!empty($fixedContent)) {
                    $content = $fixedContent;
                }
            }
        }

        // Fix spacing issues when extracting plain text
        $plainText = $this->extractPlainTextWithSpacing($content);
        $formattedText = $this->formatToHTML($content);

        return [
            'text' => trim($plainText),
            'formatted' => trim($formattedText),
            'xml' => trim($content),
        ];
    }

    private function extractVerseContentFromLine($osisId)
    {
        // This method is only called for specific problematic verses
        static $xmlLines = null;

        // Cache the XML lines on first call
        if ($xmlLines === null) {
            $xmlString = $this->doc->saveXML();
            $xmlLines = explode("\n", $xmlString);
        }

        foreach ($xmlLines as $line) {
            if (strpos($line, 'osisID="' . $osisId . '"') !== false &&
                strpos($line, 'sID="' . $osisId . '"') !== false) {

                // Extract content between sID and eID markers
                $startPattern = '/<verse osisID="' . preg_quote($osisId, '/') . '" sID="' . preg_quote($osisId, '/') . '"\/>/';
                $endPattern = '/<verse eID="' . preg_quote($osisId, '/') . '"\/>/';

                if (preg_match($startPattern, $line, $startMatch, PREG_OFFSET_CAPTURE) &&
                    preg_match($endPattern, $line, $endMatch, PREG_OFFSET_CAPTURE)) {

                    $startPos = $startMatch[0][1] + strlen($startMatch[0][0]);
                    $endPos = $endMatch[0][1];
                    return substr($line, $startPos, $endPos - $startPos);
                }
                break;
            }
        }

        return '';
    }

    private function containsVerseEndMarker($element, $osisId)
    {
        // Check if this element or any of its descendants contains the verse end marker
        $xpath = new DOMXPath($this->doc);
        $xpath->registerNamespace('osis', 'http://www.bibletechnologies.net/2003/OSIS/namespace');

        // Create a temporary document with just this element to search within it
        $tempDoc = new DOMDocument();
        $tempElement = $tempDoc->importNode($element, true);
        $tempDoc->appendChild($tempElement);

        $tempXpath = new DOMXPath($tempDoc);
        $tempXpath->registerNamespace('osis', 'http://www.bibletechnologies.net/2003/OSIS/namespace');

        $endMarkers = $tempXpath->query('.//verse[@eID="' . $osisId . '"] | .//osis:verse[@eID="' . $osisId . '"]');

        return $endMarkers->length > 0;
    }

    private function extractContentUpToEndMarker($element, $osisId)
    {
        // Clone the element to avoid modifying the original
        $clonedElement = $element->cloneNode(true);

        // Find and remove the verse end marker and everything after it
        $xpath = new DOMXPath($this->doc);
        $xpath->registerNamespace('osis', 'http://www.bibletechnologies.net/2003/OSIS/namespace');

        // Create a temporary document for processing
        $tempDoc = new DOMDocument();
        $tempElement = $tempDoc->importNode($clonedElement, true);
        $tempDoc->appendChild($tempElement);

        $tempXpath = new DOMXPath($tempDoc);
        $tempXpath->registerNamespace('osis', 'http://www.bibletechnologies.net/2003/OSIS/namespace');

        // Find the verse end marker
        $endMarkers = $tempXpath->query('.//verse[@eID="' . $osisId . '"] | .//osis:verse[@eID="' . $osisId . '"]');

        if ($endMarkers->length > 0) {
            $endMarker = $endMarkers->item(0);

            // Remove the end marker and everything after it in the same parent
            $parent = $endMarker->parentNode;
            $toRemove = [];
            $found = false;

            foreach ($parent->childNodes as $child) {
                if ($found) {
                    $toRemove[] = $child;
                } elseif ($child === $endMarker) {
                    $toRemove[] = $child;
                    $found = true;
                }
            }

            foreach ($toRemove as $node) {
                $parent->removeChild($node);
            }
        }

        return $tempDoc->saveXML($tempElement);
    }

    private function extractPlainTextWithSpacing($content)
    {
        // Replace word element boundaries with spaces to preserve word separation
        $text = $content;

        // Add space before each opening <w> tag (except the first one)
        $text = preg_replace('/(?<!^)(<w[^>]*>)/', ' $1', $text);

        // Add space after each closing </w> tag, but NOT before punctuation
        $text = preg_replace('/(<\/w>)(?![.,:;!?\'")\]\s\-]|$)/', '$1 ', $text);

        // Now strip all tags
        $text = strip_tags($text);

        // Clean up multiple spaces
        $text = preg_replace('/\s+/', ' ', $text);

        return $text;
    }

    private function processVerseElements($verseElement, $verseId, $xmlContent)
    {
        // Create a document fragment for processing
        $fragment = $this->doc->createDocumentFragment();
        if (! empty($xmlContent)) {
            @$fragment->appendXML('<root>' . $xmlContent . '</root>');

            if ($fragment->firstChild) {
                $this->processElementsRecursively($fragment->firstChild, $verseId);
            }
        }
    }

    private function processElementsRecursively($element, $verseId, $order = 1)
    {
        foreach ($element->childNodes as $child) {
            if ($child->nodeType === XML_ELEMENT_NODE) {
                $this->processSpecificElement($child, $verseId, $order);
                $this->processElementsRecursively($child, $verseId, $order + 1);
            }
        }
    }

    private function processSpecificElement($element, $verseId, $order)
    {
        $tagName = $element->nodeName;
        $text = $element->textContent;

        switch ($tagName) {
            case 'w':
                $this->importWordElement($element, $verseId, $order);
                break;

            case 'transChange':
                if ($element->getAttribute('type') === 'added') {
                    $this->importTranslatorChange($element, $verseId, $order);
                }
                break;

            case 'q':
                if ($element->getAttribute('who') === 'Jesus') {
                    $this->importRedLetterText($element, $verseId, $order);
                }
                break;

            case 'divineName':
                $this->importDivineName($element, $verseId);
                break;

            case 'title':
                $this->importTitle($element, $verseId, $order);
                break;

            case 'lg':
                $this->importLineGroup($element, $verseId, $order);
                break;

            case 'l':
                $this->importPoetryLine($element, $verseId, $order);
                break;

            case 'note':
                $this->importStudyNote($element, $verseId);
                break;
        }
    }

    private function importWordElement($element, $verseId, $order)
    {
        $lemma = $element->getAttribute('lemma');
        $morph = $element->getAttribute('morph');
        $text = $element->textContent;

        // Extract Strong's number
        $strongsNumber = null;
        if ($lemma && str_contains($lemma, 'strong:')) {
            preg_match('/strong:([HG]\d+)/', $lemma, $matches);
            $strongsNumber = $matches[1] ?? null;
        }

        DB::table('word_elements')->insert([
            'verse_id' => $verseId,
            'word_text' => $text,
            'strongs_number' => $strongsNumber,
            'morphology_code' => $morph,
            'lemma' => $lemma,
            'word_order' => $order,
            'attributes' => json_encode($this->getElementAttributes($element)),
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $this->wordElementCount++;
    }

    private function importTranslatorChange($element, $verseId, $order)
    {
        $text = $element->textContent;

        DB::table('translator_changes')->insert([
            'verse_id' => $verseId,
            'change_type' => 'added',
            'text_content' => $text,
            'text_order' => $order,
            'attributes' => json_encode($this->getElementAttributes($element)),
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $this->translatorChangeCount++;
    }

    private function importRedLetterText($element, $verseId, $order)
    {
        $text = $element->textContent;
        $speaker = $element->getAttribute('who') ?: 'Jesus';

        DB::table('red_letter_text')->insert([
            'verse_id' => $verseId,
            'speaker' => $speaker,
            'text_content' => $text,
            'text_order' => $order,
            'attributes' => json_encode($this->getElementAttributes($element)),
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $this->redLetterCount++;
    }

    private function importDivineName($element, $verseId)
    {
        $text = $element->textContent;

        DB::table('divine_names')->insert([
            'verse_id' => $verseId,
            'displayed_text' => $text,
            'original_name' => 'YHWH',
            'attributes' => json_encode($this->getElementAttributes($element)),
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $this->divineNameCount++;
    }

    private function importTitle($element, $verseId, $order)
    {
        // Extract inner content and format to HTML (preserve formatting)
        $innerContent = '';
        foreach ($element->childNodes as $child) {
            $innerContent .= $this->doc->saveXML($child);
        }

        // Format the title content to HTML (like we do for verses)
        $formattedText = $this->formatToHTML($innerContent);

        $titleType = $element->getAttribute('type') ?: 'main';
        $canonical = $element->getAttribute('canonical') === 'true';

        // Validate title type and map to expected values
        $validTitleTypes = ['main', 'chapter', 'psalm', 'acrostic', 'sub', 'verse'];
        if (! in_array($titleType, $validTitleTypes)) {
            // Log unknown title type and default to 'main'
            $this->warn("Unknown title type '{$titleType}' found, defaulting to 'main'");
            $titleType = 'main';
        }

        // Determine chapter_id and verse_id based on title type
        $chapterId = null;
        $targetVerseId = null;

        if ($titleType === 'chapter') {
            // Chapter titles should be associated with the chapter, not the verse
            // Find the chapter this verse belongs to
            $verse = DB::table('verses')->where('id', $verseId)->first();
            if ($verse) {
                $chapterId = $verse->chapter_id;
                // For chapter titles, don't associate with a specific verse
                $targetVerseId = null;
            }
        } else {
            // All other titles (main, psalm, acrostic, sub) are associated with verses
            $targetVerseId = $verseId;
        }

        // Store title in database
        $titleData = [
            'verse_id' => $targetVerseId,
            'chapter_id' => $chapterId,
            'title_type' => $titleType,
            'title_text' => trim($formattedText),
            'canonical' => $canonical,
            'placement' => 'before',
            'title_order' => $order,
            'attributes' => json_encode($this->getElementAttributes($element)),
            'created_at' => now(),
            'updated_at' => now(),
        ];

        DB::table('titles')->insert($titleData);

        $this->titleCount++;

        // Log title type for debugging
        if ($this->titleCount % 100 === 0) {
            $this->line("   📝 Processed {$this->titleCount} titles...");
        }
    }

    private function importLineGroup($element, $verseId, $order)
    {
        // For line groups, we'll process their child elements individually
        // but also store the group info in poetry_structure
        $text = $element->textContent;

        DB::table('poetry_structure')->insert([
            'verse_id' => $verseId,
            'structure_type' => 'lg',
            'level' => 1,
            'line_text' => $text,
            'line_order' => $order,
            'attributes' => json_encode($this->getElementAttributes($element)),
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $this->poetryLineCount++;
    }

    private function importPoetryLine($element, $verseId, $order)
    {
        $text = $element->textContent;
        $level = (int) ($element->getAttribute('level') ?: 1);

        DB::table('poetry_structure')->insert([
            'verse_id' => $verseId,
            'structure_type' => 'l',
            'level' => $level,
            'line_text' => $text,
            'line_order' => $order,
            'attributes' => json_encode($this->getElementAttributes($element)),
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $this->poetryLineCount++;
    }

    private function importStudyNote($element, $verseId)
    {
        $type = $element->getAttribute('type') ?: 'study';
        $text = $element->textContent;

        DB::table('study_notes')->insert([
            'verse_id' => $verseId,
            'note_type' => $type,
            'note_text' => $text,
            'attributes' => json_encode($this->getElementAttributes($element)),
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    private function formatToHTML($content)
    {
        $html = $content;

        $html = $this->formatTitles($html);
        $html = $this->formatPoetry($html);
        $html = $this->formatCaesura($html);
        $html = $this->formatRedLetterText($html);
        $html = $this->formatTranslatorChanges($html);
        $html = $this->formatDivineNames($html);
        $html = $this->removeWordMarkup($html);
        $html = $this->addSpacing($html);
        $html = $this->removeNotes($html);
        $html = $this->cleanupFormatting($html);

        return $html;
    }

    /**
     * Format title elements with appropriate styling
     */
    private function formatTitles($html)
    {
        $html = preg_replace('/<title\s+type="psalm"[^>]*>/', '<div class="psalm-title text-center text-sm font-medium text-gray-700 dark:text-gray-300 italic mb-3 border-b border-gray-200 dark:border-gray-600 pb-2">', $html);
        $html = preg_replace('/<title\s+type="main"[^>]*>/', '<h2 class="main-title text-xl font-bold text-gray-900 dark:text-gray-100 mb-4">', $html);
        $html = preg_replace('/<title\s+type="chapter"[^>]*>/', '<h3 class="chapter-title text-lg font-semibold text-gray-800 dark:text-gray-200 mb-3 text-center">', $html);
        $html = preg_replace('/<title\s+type="acrostic"[^>]*>/', '<div class="acrostic-title text-center text-lg font-semibold text-blue-700 dark:text-blue-400 mb-2">', $html);
        $html = preg_replace('/<title\s+type="sub"[^>]*>/', '<h4 class="sub-title text-md font-medium text-gray-700 dark:text-gray-300 mb-2">', $html);
        $html = preg_replace('/<title[^>]*>/', '<div class="title text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">', $html);
        $html = str_replace('</title>', '</div>', $html);

        return $html;
    }

    /**
     * Format poetry elements with proper indentation
     */
    private function formatPoetry($html)
    {
        // Line groups
        $html = preg_replace('/<lg[^>]*>/', '<div class="line-group mb-2">', $html);
        $html = str_replace('</lg>', '</div>', $html);

        // Poetry lines with level-based indentation
        $html = preg_replace('/<l\s+level="1"[^>]*>/', '<div class="poetry-line indent-0 leading-relaxed">', $html);
        $html = preg_replace('/<l\s+level="2"[^>]*>/', '<div class="poetry-line indent-4 leading-relaxed ml-4">', $html);
        $html = preg_replace('/<l\s+level="3"[^>]*>/', '<div class="poetry-line indent-8 leading-relaxed ml-8">', $html);
        $html = preg_replace('/<l\s+level="4"[^>]*>/', '<div class="poetry-line indent-12 leading-relaxed ml-12">', $html);
        $html = preg_replace('/<l[^>]*>/', '<div class="poetry-line indent-0 leading-relaxed">', $html);
        $html = str_replace('</l>', '</div>', $html);

        return $html;
    }

    /**
     * Format caesura (poetry pause) elements
     */
    private function formatCaesura($html)
    {
        $html = str_replace('<caesura/>', '<span class="caesura text-gray-400 mx-2">‖</span>', $html);
        $html = str_replace('<caesura>', '<span class="caesura text-gray-400 mx-2">‖</span>', $html);

        return $html;
    }

    /**
     * Format red letter text (Jesus's words) and handle nested translator changes
     */
    private function formatRedLetterText($html)
    {
        // First, handle the specific malformed case in Matt.9.6 where there's a stray </q> tag
        // This happens because the XML is missing the opening <q> tag for the first Jesus speech
        $html = preg_replace_callback('/^(.*?),<\/q>\s*(\([^)]+\))\s*(<q\s+who="Jesus"[^>]*>.*?<\/q>)/s', function($matches) {
            $firstSpeech = $matches[1];
            $narrative = $matches[2];
            $secondSpeech = $matches[3];

            // Wrap the first speech in red letter formatting
            $firstSpeech = '<span class="text-red-600 dark:text-red-400 font-medium">' . $firstSpeech . ',</span>';

            // Process the second speech normally
            $secondSpeech = preg_replace_callback('/<q\s+who="Jesus"[^>]*>(.*?)<\/q>/s', function($speechMatches) {
                return $this->processJesusQuoteContent($speechMatches[1]);
            }, $secondSpeech);

            return $firstSpeech . ' ' . $narrative . ' ' . $secondSpeech;
        }, $html);

        // Handle normal Jesus quotes (this will process any remaining <q who="Jesus"> tags)
        $html = preg_replace_callback('/<q\s+who="Jesus"[^>]*>(.*?)<\/q>/s', function($matches) {
            return $this->processJesusQuoteContent($matches[1]);
        }, $html);

        return $html;
    }

    /**
     * Process content within Jesus quotes, converting translator changes to red italics
     */
    private function processJesusQuoteContent($jesusContent)
    {
        // Replace transChange within Jesus quotes with red italics
        $jesusContent = str_replace('<transChange type="added">', '<em class="text-red-500 dark:text-red-300 font-normal italic opacity-80">', $jesusContent);
        $jesusContent = str_replace('</transChange>', '</em>', $jesusContent);

        return '<span class="text-red-600 dark:text-red-400 font-medium">' . $jesusContent . '</span>';
    }

    /**
     * Format remaining translator changes outside Jesus quotes (gray italics)
     */
    private function formatTranslatorChanges($html)
    {
        $html = str_replace('<transChange type="added">', '<em class="text-gray-600 dark:text-gray-400 font-normal italic">', $html);
        $html = str_replace('</transChange>', '</em>', $html);

        return $html;
    }

    /**
     * Format divine names with small caps styling
     */
    private function formatDivineNames($html)
    {
        $html = str_replace('<divineName>', '<span style="font-variant: small-caps; font-weight: 600; letter-spacing: 0.05em; text-transform: uppercase; font-size: 0.95em;">', $html);
        $html = str_replace('</divineName>', '</span>', $html);

        return $html;
    }

    /**
     * Remove word markup while preserving spacing
     */
    private function removeWordMarkup($html)
    {
        $html = preg_replace('/<w[^>]*>/', '', $html);
        // Add space after </w> only if followed by actual text content (not punctuation or whitespace)
        $html = preg_replace('/<\/w>(?=[^\s.,:;!?\'")\]\-])/', '</w> ', $html);
        $html = str_replace('</w>', '', $html); // Clean up remaining </w> tags

        return $html;
    }

    /**
     * Add proper spacing around elements
     */
    private function addSpacing($html)
    {
        // Add space after </em> only if followed by actual text content
        $html = preg_replace('/<\/em>(?=[^\s.,:;!?\'")\]\-])/', '</em> ', $html);

        // Add space after red letter text </span> only if followed by actual text content
        $html = preg_replace('/<\/span>(?=[^\s.,:;!?\'")\]\-])/', '</span> ', $html);

        return $html;
    }

    /**
     * Remove study notes and other non-display elements
     */
    private function removeNotes($html)
    {
        // Remove notes for basic display
        $html = preg_replace('/<note[^>]*>.*?<\/note>/s', '', $html);

        return $html;
    }

    /**
     * Final cleanup of formatting
     */
    private function cleanupFormatting($html)
    {
        // Remove pilcrow symbols - they should not be displayed to users
        $html = str_replace('¶', '', $html);

        // Clean up multiple spaces
        $html = preg_replace('/\s+/', ' ', $html);

        return $html;
    }

    private function getElementAttributes($element)
    {
        $attributes = [];
        if ($element->attributes) {
            foreach ($element->attributes as $attr) {
                $attributes[$attr->name] = $attr->value;
            }
        }
        return $attributes;
    }

    private function updateFTSTables()
    {
        $this->info('🔍 Updating FTS search tables...');

        try {
            DB::statement("INSERT INTO verses_fts(verses_fts) VALUES('rebuild')");
        } catch (Exception $e) {
            $this->warn('FTS rebuild failed: ' . $e->getMessage());
        }

        $this->info('✅ FTS tables updated');
    }

    private function getBookGroupId($osisId)
    {
        $otBooks = ['Gen', 'Exod', 'Lev', 'Num', 'Deut', 'Josh', 'Judg', 'Ruth', '1Sam', '2Sam', '1Kgs', '2Kgs', '1Chr', '2Chr', 'Ezra', 'Neh', 'Esth', 'Job', 'Ps', 'Prov', 'Eccl', 'Song', 'Isa', 'Jer', 'Lam', 'Ezek', 'Dan', 'Hos', 'Joel', 'Amos', 'Obad', 'Jonah', 'Mic', 'Nah', 'Hab', 'Zeph', 'Hag', 'Zech', 'Mal'];

        if (in_array($osisId, $otBooks)) {
            return DB::table('book_groups')->where('name', 'Old Testament')->value('id');
        }
        return DB::table('book_groups')->where('name', 'New Testament')->value('id');

    }

    private function getBookNumber($osisId)
    {
        $bookNumbers = [
            'Gen' => 1, 'Exod' => 2, 'Lev' => 3, 'Num' => 4, 'Deut' => 5,
            'Josh' => 6, 'Judg' => 7, 'Ruth' => 8, '1Sam' => 9, '2Sam' => 10,
            '1Kgs' => 11, '2Kgs' => 12, '1Chr' => 13, '2Chr' => 14, 'Ezra' => 15,
            'Neh' => 16, 'Esth' => 17, 'Job' => 18, 'Ps' => 19, 'Prov' => 20,
            'Eccl' => 21, 'Song' => 22, 'Isa' => 23, 'Jer' => 24, 'Lam' => 25,
            'Ezek' => 26, 'Dan' => 27, 'Hos' => 28, 'Joel' => 29, 'Amos' => 30,
            'Obad' => 31, 'Jonah' => 32, 'Mic' => 33, 'Nah' => 34, 'Hab' => 35,
            'Zeph' => 36, 'Hag' => 37, 'Zech' => 38, 'Mal' => 39, 'Matt' => 40,
            'Mark' => 41, 'Luke' => 42, 'John' => 43, 'Acts' => 44, 'Rom' => 45,
            '1Cor' => 46, '2Cor' => 47, 'Gal' => 48, 'Eph' => 49, 'Phil' => 50,
            'Col' => 51, '1Thess' => 52, '2Thess' => 53, '1Tim' => 54, '2Tim' => 55,
            'Titus' => 56, 'Phlm' => 57, 'Heb' => 58, 'Jas' => 59, '1Pet' => 60,
            '2Pet' => 61, '1John' => 62, '2John' => 63, '3John' => 64, 'Jude' => 65,
            'Rev' => 66,
        ];

        return $bookNumbers[$osisId] ?? 999;
    }

    private function getBookName($osisId)
    {
        $bookNames = [
            'Gen' => 'Genesis', 'Exod' => 'Exodus', 'Lev' => 'Leviticus', 'Num' => 'Numbers', 'Deut' => 'Deuteronomy',
            'Josh' => 'Joshua', 'Judg' => 'Judges', 'Ruth' => 'Ruth', '1Sam' => '1 Samuel', '2Sam' => '2 Samuel',
            '1Kgs' => '1 Kings', '2Kgs' => '2 Kings', '1Chr' => '1 Chronicles', '2Chr' => '2 Chronicles', 'Ezra' => 'Ezra',
            'Neh' => 'Nehemiah', 'Esth' => 'Esther', 'Job' => 'Job', 'Ps' => 'Psalms', 'Prov' => 'Proverbs',
            'Eccl' => 'Ecclesiastes', 'Song' => 'Song of Solomon', 'Isa' => 'Isaiah', 'Jer' => 'Jeremiah', 'Lam' => 'Lamentations',
            'Ezek' => 'Ezekiel', 'Dan' => 'Daniel', 'Hos' => 'Hosea', 'Joel' => 'Joel', 'Amos' => 'Amos',
            'Obad' => 'Obadiah', 'Jonah' => 'Jonah', 'Mic' => 'Micah', 'Nah' => 'Nahum', 'Hab' => 'Habakkuk',
            'Zeph' => 'Zephaniah', 'Hag' => 'Haggai', 'Zech' => 'Zechariah', 'Mal' => 'Malachi', 'Matt' => 'Matthew',
            'Mark' => 'Mark', 'Luke' => 'Luke', 'John' => 'John', 'Acts' => 'Acts', 'Rom' => 'Romans',
            '1Cor' => '1 Corinthians', '2Cor' => '2 Corinthians', 'Gal' => 'Galatians', 'Eph' => 'Ephesians', 'Phil' => 'Philippians',
            'Col' => 'Colossians', '1Thess' => '1 Thessalonians', '2Thess' => '2 Thessalonians', '1Tim' => '1 Timothy', '2Tim' => '2 Timothy',
            'Titus' => 'Titus', 'Phlm' => 'Philemon', 'Heb' => 'Hebrews', 'Jas' => 'James', '1Pet' => '1 Peter',
            '2Pet' => '2 Peter', '1John' => '1 John', '2John' => '2 John', '3John' => '3 John', 'Jude' => 'Jude',
            'Rev' => 'Revelation',
        ];

        return $bookNames[$osisId] ?? $osisId;
    }

    private function displaySummary()
    {
        $this->newLine();
        $this->info('🎉 Import completed successfully!');
        $this->newLine();

        $this->line('📊 <info>Import Summary:</info>');
        $this->line('   📚 Books: ' . count($this->books));
        $this->line('   📖 Chapters: ' . count($this->chapters));
        $this->line('   📝 Verses: ' . $this->verseCount);
        $this->line('   📄 Paragraphs: ' . $this->paragraphCount);
        $this->line('   🔤 Word Elements: ' . $this->wordElementCount);
        $this->line('   ✒️  Translator Changes: ' . $this->translatorChangeCount);
        $this->line('   👑 Divine Names: ' . $this->divineNameCount);
        $this->line('   🔴 Red Letter Text: ' . $this->redLetterCount);
        $this->line('   📚 Titles: ' . $this->titleCount);

        // Show title type breakdown
        $titleBreakdown = DB::table('titles')
            ->select('title_type', DB::raw('count(*) as count'))
            ->groupBy('title_type')
            ->orderBy('count', 'desc')
            ->get();

        if ($titleBreakdown->count() > 0) {
            $this->line('       Title breakdown:');
            foreach ($titleBreakdown as $titleType) {
                $this->line("       - {$titleType->title_type}: {$titleType->count}");
            }
        }

        $this->line('   📝 Poetry Lines: ' . $this->poetryLineCount);

        $this->newLine();
        $this->line('✅ Database is ready for biblical scholarship!');
    }

    /**
     * Process all book titles after chapters have been imported
     */
    private function processAllBookTitles()
    {
        $this->info('📑 Processing book titles...');

        // Get all book divisions with namespace
        $bookDivs = $this->xpath->query('//osis:div[@type="book"]');

        foreach ($bookDivs as $bookDiv) {
            $osisId = $bookDiv->getAttribute('osisID');
            if (! $osisId) {
                continue;
            }

            $bookId = $this->books[$osisId] ?? null;
            if (! $bookId) {
                continue;
            }

            // Process any main titles that appear in this book div
            $this->processBookTitles($bookDiv, $bookId);
        }
    }

    /**
     * Process main book titles that appear at the book level
     * @param mixed $bookDiv
     * @param mixed $bookId
     */
    private function processBookTitles($bookDiv, $bookId)
    {
        // Look for title elements that are direct children of the book div
        foreach ($bookDiv->childNodes as $child) {
            if ($child->nodeType === XML_ELEMENT_NODE) {
                $nodeName = $child->localName ?: $child->nodeName;

                if ($nodeName === 'title') {
                    $titleType = $child->getAttribute('type') ?: 'main';

                    // Only process main titles at book level
                    if ($titleType === 'main') {
                        $this->importBookTitle($child, $bookId);
                    }
                }
            }
        }
    }

    /**
     * Import a title specifically associated with a book
     * @param mixed $element
     * @param mixed $bookId
     */
    private function importBookTitle($element, $bookId)
    {
        // Extract inner content and format to HTML (preserve formatting)
        $innerContent = '';
        foreach ($element->childNodes as $child) {
            $innerContent .= $this->doc->saveXML($child);
        }

        // Format the title content to HTML
        $formattedText = $this->formatToHTML($innerContent);

        $titleType = $element->getAttribute('type') ?: 'main';
        $canonical = $element->getAttribute('canonical') === 'true';

        // For book titles, we'll associate them with the first chapter of the book
        $firstChapter = DB::table('chapters')
            ->where('book_id', $bookId)
            ->orderBy('chapter_number')
            ->first();

        $chapterId = $firstChapter ? $firstChapter->id : null;

        // Store title in database
        $titleData = [
            'verse_id' => null, // Book titles are not associated with specific verses
            'chapter_id' => $chapterId, // Associate with first chapter if available
            'title_type' => $titleType,
            'title_text' => trim($formattedText),
            'canonical' => $canonical,
            'placement' => 'before',
            'title_order' => 1, // Book titles come first
            'attributes' => json_encode($this->getElementAttributes($element)),
            'created_at' => now(),
            'updated_at' => now(),
        ];

        DB::table('titles')->insert($titleData);
        $this->titleCount++;
    }

    /**
     * Process paragraph markers found in verse content
     * @param mixed $xmlContent
     * @param mixed $verseNum
     */
    private function processParagraphMarkers($xmlContent, $verseNum)
    {
        // Check for OSIS paragraph markers
        if (preg_match('/<milestone[^>]*type="x-p"[^>]*marker="¶"[^>]*>/', $xmlContent)) {
            // Found a paragraph marker - save current paragraph and start new one
            if ($this->currentParagraphStart !== null && ! empty($this->currentParagraphVerses)) {
                // End the current paragraph at the verse BEFORE this new paragraph marker
                $previousParagraphVerses = array_filter($this->currentParagraphVerses, function ($v) use ($verseNum) {
                    return $v < $verseNum;
                });

                if (! empty($previousParagraphVerses)) {
                    $this->saveParagraph($this->currentChapterId, $this->currentParagraphStart, $previousParagraphVerses);
                }
            }

            // Start new paragraph at this verse
            $this->currentParagraphStart = $verseNum;
            $this->currentParagraphVerses = [$verseNum];
        }
    }

    /**
     * Save a paragraph to the database
     * @param mixed $chapterId
     * @param mixed $startVerse
     * @param mixed $verses
     */
    private function saveParagraph($chapterId, $startVerse, $verses)
    {
        $endVerse = max($verses);

        // Get verse IDs
        $startVerseId = DB::table('verses')
            ->where('chapter_id', $chapterId)
            ->where('verse_number', $startVerse)
            ->value('id');

        $endVerseId = null;
        if ($endVerse !== $startVerse) {
            $endVerseId = DB::table('verses')
                ->where('chapter_id', $chapterId)
                ->where('verse_number', $endVerse)
                ->value('id');
        }

        if ($startVerseId) {
            // Get combined text content
            $textContent = DB::table('verses')
                ->where('chapter_id', $chapterId)
                ->whereIn('verse_number', $verses)
                ->orderBy('verse_number')
                ->pluck('formatted_text')
                ->implode(' ');

            // Check if this paragraph already exists
            $existingParagraph = DB::table('paragraphs')
                ->where('chapter_id', $chapterId)
                ->where('start_verse_id', $startVerseId)
                ->where('end_verse_id', $endVerseId)
                ->first();

            if (! $existingParagraph) {
                DB::table('paragraphs')->insert([
                    'chapter_id' => $chapterId,
                    'start_verse_id' => $startVerseId,
                    'end_verse_id' => $endVerseId,
                    'paragraph_type' => 'normal',
                    'text_content' => $textContent,
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);

                $this->paragraphCount++;
            }
        }
    }

    /**
     * Finalize any remaining paragraphs at the end of import
     */
    private function finalizeParagraphs()
    {
        // Save any remaining paragraphs
        if ($this->currentParagraphStart !== null && ! empty($this->currentParagraphVerses)) {
            $this->saveParagraph($this->currentChapterId, $this->currentParagraphStart, $this->currentParagraphVerses);
        }
    }

    /**
     * Add comprehensive red letter entries for verses where Jesus speaks
     * This supplements the XML-based red letter import with missing entries
     */
    private function addComprehensiveRedLetters()
    {
        $this->info('🔴 Adding comprehensive red letter entries...');

        // Get all verses where Jesus speaks but don't have red letter entries
        $jesusVerses = $this->getJesusVerses();

        $addedCount = 0;

        foreach ($jesusVerses as $verseRef) {
            // Find the verse in the database
            $verse = DB::table('verses')->where('osis_id', 'LIKE', "%{$verseRef}%")->first();

            if ($verse) {
                // Check if red letter entry already exists
                $existingEntry = DB::table('red_letter_text')->where('verse_id', $verse->id)->first();

                if (!$existingEntry) {
                    // Extract the text content from the verse
                    $textContent = strip_tags($verse->formatted_text ?? $verse->text);

                    // Add red letter entry
                    DB::table('red_letter_text')->insert([
                        'verse_id' => $verse->id,
                        'speaker' => 'Jesus',
                        'text_content' => $textContent,
                        'text_order' => 1,
                        'attributes' => json_encode(['source' => 'comprehensive_import']),
                        'created_at' => now(),
                        'updated_at' => now(),
                    ]);

                    $addedCount++;
                    $this->redLetterCount++; // Update the counter for the summary
                }
            }
        }

        if ($addedCount > 0) {
            $this->info("✅ Added {$addedCount} comprehensive red letter entries");
        }
    }

    /**
     * Get a comprehensive list of verses where Jesus speaks
     * This supplements the OSIS XML markup with missing red letter verses
     */
    private function getJesusVerses(): array
    {
        return [
            // Matthew - Jesus's words
            'Matt.3.15', 'Matt.4.4', 'Matt.4.7', 'Matt.4.10', 'Matt.4.17', 'Matt.4.19',
            'Matt.5.3', 'Matt.5.4', 'Matt.5.5', 'Matt.5.6', 'Matt.5.7', 'Matt.5.8', 'Matt.5.9', 'Matt.5.10', 'Matt.5.11', 'Matt.5.12',
            'Matt.5.13', 'Matt.5.14', 'Matt.5.15', 'Matt.5.16', 'Matt.5.17', 'Matt.5.18', 'Matt.5.19', 'Matt.5.20',
            'Matt.5.21', 'Matt.5.22', 'Matt.5.23', 'Matt.5.24', 'Matt.5.25', 'Matt.5.26', 'Matt.5.27', 'Matt.5.28',
            'Matt.5.29', 'Matt.5.30', 'Matt.5.31', 'Matt.5.32', 'Matt.5.33', 'Matt.5.34', 'Matt.5.35', 'Matt.5.36', 'Matt.5.37',
            'Matt.5.38', 'Matt.5.39', 'Matt.5.40', 'Matt.5.41', 'Matt.5.42', 'Matt.5.43', 'Matt.5.44', 'Matt.5.45', 'Matt.5.46', 'Matt.5.47', 'Matt.5.48',
            'Matt.6.1', 'Matt.6.2', 'Matt.6.3', 'Matt.6.4', 'Matt.6.5', 'Matt.6.6', 'Matt.6.7', 'Matt.6.8', 'Matt.6.9', 'Matt.6.10', 'Matt.6.11', 'Matt.6.12', 'Matt.6.13',
            'Matt.6.14', 'Matt.6.15', 'Matt.6.16', 'Matt.6.17', 'Matt.6.18', 'Matt.6.19', 'Matt.6.20', 'Matt.6.21', 'Matt.6.22', 'Matt.6.23', 'Matt.6.24', 'Matt.6.25', 'Matt.6.26',
            'Matt.6.27', 'Matt.6.28', 'Matt.6.29', 'Matt.6.30', 'Matt.6.31', 'Matt.6.32', 'Matt.6.33', 'Matt.6.34',
            'Matt.7.1', 'Matt.7.2', 'Matt.7.3', 'Matt.7.4', 'Matt.7.5', 'Matt.7.6', 'Matt.7.7', 'Matt.7.8', 'Matt.7.9', 'Matt.7.10', 'Matt.7.11', 'Matt.7.12',
            'Matt.7.13', 'Matt.7.14', 'Matt.7.15', 'Matt.7.16', 'Matt.7.17', 'Matt.7.18', 'Matt.7.19', 'Matt.7.20', 'Matt.7.21', 'Matt.7.22', 'Matt.7.23', 'Matt.7.24', 'Matt.7.25', 'Matt.7.26', 'Matt.7.27',
            'Matt.8.3', 'Matt.8.4', 'Matt.8.7', 'Matt.8.10', 'Matt.8.11', 'Matt.8.12', 'Matt.8.13', 'Matt.8.20', 'Matt.8.22', 'Matt.8.26', 'Matt.8.32',
            'Matt.9.2', 'Matt.9.4', 'Matt.9.5', 'Matt.9.6', 'Matt.9.9', 'Matt.9.12', 'Matt.9.13', 'Matt.9.15', 'Matt.9.16', 'Matt.9.17', 'Matt.9.22', 'Matt.9.24', 'Matt.9.28', 'Matt.9.29', 'Matt.9.30', 'Matt.9.37', 'Matt.9.38',

            // Mark - Jesus's words
            'Mark.1.15', 'Mark.1.17', 'Mark.1.25', 'Mark.1.38', 'Mark.1.41', 'Mark.1.44',
            'Mark.2.5', 'Mark.2.8', 'Mark.2.9', 'Mark.2.10', 'Mark.2.11', 'Mark.2.14', 'Mark.2.17', 'Mark.2.19', 'Mark.2.20', 'Mark.2.21', 'Mark.2.22', 'Mark.2.25', 'Mark.2.26', 'Mark.2.27', 'Mark.2.28',
            'Mark.3.3', 'Mark.3.4', 'Mark.3.5', 'Mark.3.23', 'Mark.3.24', 'Mark.3.25', 'Mark.3.26', 'Mark.3.27', 'Mark.3.28', 'Mark.3.29', 'Mark.3.33', 'Mark.3.34', 'Mark.3.35',
            'Mark.4.3', 'Mark.4.4', 'Mark.4.5', 'Mark.4.6', 'Mark.4.7', 'Mark.4.8', 'Mark.4.9', 'Mark.4.11', 'Mark.4.12', 'Mark.4.13', 'Mark.4.14', 'Mark.4.15', 'Mark.4.16', 'Mark.4.17', 'Mark.4.18', 'Mark.4.19', 'Mark.4.20',
            'Mark.4.21', 'Mark.4.22', 'Mark.4.23', 'Mark.4.24', 'Mark.4.25', 'Mark.4.26', 'Mark.4.27', 'Mark.4.28', 'Mark.4.29', 'Mark.4.30', 'Mark.4.31', 'Mark.4.32', 'Mark.4.35', 'Mark.4.39', 'Mark.4.40',

            // Luke - Jesus's words
            'Luke.4.4', 'Luke.4.8', 'Luke.4.12', 'Luke.4.18', 'Luke.4.19', 'Luke.4.21', 'Luke.4.23', 'Luke.4.24', 'Luke.4.25', 'Luke.4.26', 'Luke.4.27', 'Luke.4.35', 'Luke.4.43',
            'Luke.5.4', 'Luke.5.8', 'Luke.5.10', 'Luke.5.13', 'Luke.5.14', 'Luke.5.20', 'Luke.5.22', 'Luke.5.23', 'Luke.5.24', 'Luke.5.27', 'Luke.5.31', 'Luke.5.32', 'Luke.5.34', 'Luke.5.35', 'Luke.5.36', 'Luke.5.37', 'Luke.5.38', 'Luke.5.39',
            'Luke.6.3', 'Luke.6.4', 'Luke.6.5', 'Luke.6.9', 'Luke.6.10', 'Luke.6.20', 'Luke.6.21', 'Luke.6.22', 'Luke.6.23', 'Luke.6.24', 'Luke.6.25', 'Luke.6.26', 'Luke.6.27', 'Luke.6.28', 'Luke.6.29', 'Luke.6.30',
            'Luke.6.31', 'Luke.6.32', 'Luke.6.33', 'Luke.6.34', 'Luke.6.35', 'Luke.6.36', 'Luke.6.37', 'Luke.6.38', 'Luke.6.39', 'Luke.6.40', 'Luke.6.41', 'Luke.6.42', 'Luke.6.43', 'Luke.6.44', 'Luke.6.45', 'Luke.6.46', 'Luke.6.47', 'Luke.6.48', 'Luke.6.49',

            // John - Jesus's words (extensive since John records many long discourses)
            'John.1.38', 'John.1.39', 'John.1.42', 'John.1.43', 'John.1.47', 'John.1.48', 'John.1.50', 'John.1.51',
            'John.2.4', 'John.2.7', 'John.2.8', 'John.2.16', 'John.2.19',
            'John.3.3', 'John.3.5', 'John.3.7', 'John.3.8', 'John.3.10', 'John.3.11', 'John.3.12', 'John.3.13', 'John.3.14', 'John.3.15', 'John.3.16', 'John.3.17', 'John.3.18', 'John.3.19', 'John.3.20', 'John.3.21',
            'John.4.7', 'John.4.9', 'John.4.10', 'John.4.13', 'John.4.14', 'John.4.16', 'John.4.17', 'John.4.18', 'John.4.21', 'John.4.22', 'John.4.23', 'John.4.24', 'John.4.26', 'John.4.32', 'John.4.34', 'John.4.35', 'John.4.36', 'John.4.37', 'John.4.38', 'John.4.48', 'John.4.50', 'John.4.53',
            'John.5.6', 'John.5.8', 'John.5.14', 'John.5.17', 'John.5.19', 'John.5.20', 'John.5.21', 'John.5.22', 'John.5.23', 'John.5.24', 'John.5.25', 'John.5.26', 'John.5.27', 'John.5.28', 'John.5.29', 'John.5.30',
            'John.5.31', 'John.5.32', 'John.5.33', 'John.5.34', 'John.5.35', 'John.5.36', 'John.5.37', 'John.5.38', 'John.5.39', 'John.5.40', 'John.5.41', 'John.5.42', 'John.5.43', 'John.5.44', 'John.5.45', 'John.5.46', 'John.5.47',
            'John.6.5', 'John.6.10', 'John.6.12', 'John.6.20', 'John.6.26', 'John.6.27', 'John.6.28', 'John.6.29', 'John.6.32', 'John.6.33', 'John.6.34', 'John.6.35', 'John.6.36', 'John.6.37', 'John.6.38', 'John.6.39', 'John.6.40',
            'John.6.41', 'John.6.42', 'John.6.43', 'John.6.44', 'John.6.45', 'John.6.46', 'John.6.47', 'John.6.48', 'John.6.49', 'John.6.50', 'John.6.51', 'John.6.52', 'John.6.53', 'John.6.54', 'John.6.55', 'John.6.56', 'John.6.57', 'John.6.58', 'John.6.61', 'John.6.62', 'John.6.63', 'John.6.64', 'John.6.65', 'John.6.67', 'John.6.70',

            // John 14 - the chapter we specifically fixed
            'John.14.1', 'John.14.2', 'John.14.3', 'John.14.4', 'John.14.6', 'John.14.7', 'John.14.9', 'John.14.10', 'John.14.11', 'John.14.12', 'John.14.13', 'John.14.14', 'John.14.15', 'John.14.16', 'John.14.17', 'John.14.18', 'John.14.19', 'John.14.20', 'John.14.21', 'John.14.23', 'John.14.24', 'John.14.25', 'John.14.26', 'John.14.27', 'John.14.28', 'John.14.29', 'John.14.30', 'John.14.31',

            // John 15-17 - More extensive Jesus discourses
            'John.15.1', 'John.15.2', 'John.15.3', 'John.15.4', 'John.15.5', 'John.15.6', 'John.15.7', 'John.15.8', 'John.15.9', 'John.15.10', 'John.15.11', 'John.15.12', 'John.15.13', 'John.15.14', 'John.15.15', 'John.15.16', 'John.15.17', 'John.15.18', 'John.15.19', 'John.15.20', 'John.15.21', 'John.15.22', 'John.15.23', 'John.15.24', 'John.15.25', 'John.15.26', 'John.15.27',
            'John.16.1', 'John.16.2', 'John.16.3', 'John.16.4', 'John.16.5', 'John.16.6', 'John.16.7', 'John.16.8', 'John.16.9', 'John.16.10', 'John.16.11', 'John.16.12', 'John.16.13', 'John.16.14', 'John.16.15', 'John.16.16', 'John.16.19', 'John.16.20', 'John.16.21', 'John.16.22', 'John.16.23', 'John.16.24', 'John.16.25', 'John.16.26', 'John.16.27', 'John.16.28', 'John.16.31', 'John.16.32', 'John.16.33',

            // John 17 - The High Priestly Prayer (entirely Jesus speaking)
            'John.17.1', 'John.17.2', 'John.17.3', 'John.17.4', 'John.17.5', 'John.17.6', 'John.17.7', 'John.17.8', 'John.17.9', 'John.17.10', 'John.17.11', 'John.17.12', 'John.17.13', 'John.17.14', 'John.17.15', 'John.17.16', 'John.17.17', 'John.17.18', 'John.17.19', 'John.17.20', 'John.17.21', 'John.17.22', 'John.17.23', 'John.17.24', 'John.17.25', 'John.17.26',

            // Post-resurrection appearances
            'John.20.15', 'John.20.16', 'John.20.17', 'John.20.19', 'John.20.21', 'John.20.22', 'John.20.23', 'John.20.26', 'John.20.27', 'John.20.29',
            'John.21.5', 'John.21.6', 'John.21.10', 'John.21.12', 'John.21.15', 'John.21.16', 'John.21.17', 'John.21.18', 'John.21.19', 'John.21.22', 'John.21.23',

            // Acts - Post-resurrection appearances
            'Acts.1.4', 'Acts.1.5', 'Acts.1.7', 'Acts.1.8',
            'Acts.9.4', 'Acts.9.5', 'Acts.9.6',
            'Acts.22.7', 'Acts.22.8', 'Acts.22.10',
            'Acts.26.14', 'Acts.26.15', 'Acts.26.16', 'Acts.26.17', 'Acts.26.18',

            // Revelation - Jesus speaking to John
            'Rev.1.8', 'Rev.1.11', 'Rev.1.17', 'Rev.1.18', 'Rev.1.19', 'Rev.1.20',
            'Rev.2.1', 'Rev.2.2', 'Rev.2.3', 'Rev.2.4', 'Rev.2.5', 'Rev.2.6', 'Rev.2.7',
            'Rev.2.8', 'Rev.2.9', 'Rev.2.10', 'Rev.2.11',
            'Rev.2.12', 'Rev.2.13', 'Rev.2.14', 'Rev.2.15', 'Rev.2.16', 'Rev.2.17',
            'Rev.2.18', 'Rev.2.19', 'Rev.2.20', 'Rev.2.21', 'Rev.2.22', 'Rev.2.23', 'Rev.2.24', 'Rev.2.25', 'Rev.2.26', 'Rev.2.27', 'Rev.2.28', 'Rev.2.29',
            'Rev.3.1', 'Rev.3.2', 'Rev.3.3', 'Rev.3.4', 'Rev.3.5', 'Rev.3.6',
            'Rev.3.7', 'Rev.3.8', 'Rev.3.9', 'Rev.3.10', 'Rev.3.11', 'Rev.3.12', 'Rev.3.13',
            'Rev.3.14', 'Rev.3.15', 'Rev.3.16', 'Rev.3.17', 'Rev.3.18', 'Rev.3.19', 'Rev.3.20', 'Rev.3.21', 'Rev.3.22',
            'Rev.16.15',
            'Rev.22.7', 'Rev.22.12', 'Rev.22.13', 'Rev.22.16', 'Rev.22.20',
        ];
    }
}
