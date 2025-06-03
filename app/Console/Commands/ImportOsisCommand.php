<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use DOMDocument;
use DOMXPath;
use Exception;

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

        if (!file_exists($file)) {
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

            $this->displaySummary();

        } catch (Exception $e) {
            $this->error("❌ Import failed: " . $e->getMessage());
            Log::error("OSIS Import Error", ['error' => $e->getMessage(), 'trace' => $e->getTraceAsString()]);
            return 1;
        }

        return 0;
    }

    private function loadXML($file)
    {
        $this->info("📖 Loading XML file...");

        $this->doc = new DOMDocument();
        $this->doc->preserveWhiteSpace = false;
        $this->doc->formatOutput = true;

        // Load with error handling
        libxml_use_internal_errors(true);
        if (!$this->doc->load($file)) {
            $errors = libxml_get_errors();
            throw new Exception("XML parsing failed: " . implode(', ', array_map(fn($e) => $e->message, $errors)));
        }

        $this->xpath = new DOMXPath($this->doc);
        $this->xpath->registerNamespace('osis', 'http://www.bibletechnologies.net/2003/OSIS/namespace');

        $this->info("✅ XML loaded successfully");
    }

    private function createBibleVersion()
    {
        $this->info("📚 Creating Bible version record...");

        // Extract version information from the OSIS file
        $versionInfo = $this->extractVersionInfo();

        if (!$versionInfo) {
            throw new Exception("Could not extract version information from OSIS file");
        }

        // Check if version already exists
        $existingVersion = DB::table('bible_versions')
            ->where('osis_work', $versionInfo['osis_work'])
            ->first();

        if ($existingVersion) {
            $this->warn("⚠️  Bible version '{$versionInfo['osis_work']}' already exists (ID: {$existingVersion->id})");
            $this->warn("    This import will re-process titles and other elements.");
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
        if (!$osisText) {
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
                'description' => 'King James Version imported from OSIS XML'
            ],
            'ASV' => [
                'title' => 'American Standard Version',
                'publisher' => 'Public Domain',
                'description' => 'American Standard Version imported from OSIS XML'
            ],
            'MAO' => [
                'title' => 'Maori Bible',
                'publisher' => 'Public Domain',
                'description' => 'Maori Bible imported from OSIS XML'
            ]
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
        $this->info("📚 Loading existing books...");

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
            if (!isset($this->books[$osisBook])) {
                $missingBooks[] = $osisBook;
            }
        }

        if (!empty($missingBooks)) {
            $this->warn("⚠️  Some books from OSIS are not in database: " . implode(', ', $missingBooks));
            $this->warn("    You may need to add these books to the database first.");
        }

        $foundBooks = array_intersect($osisBooks, array_keys($this->books));
        $this->info("✅ Books available for import: " . count($foundBooks) . " (Total in OSIS: " . count($osisBooks) . ")");
    }

    private function importChapters()
    {
        $this->info("📖 Importing chapters...");

        // Get all chapters with namespace
        $chapterElements = $this->xpath->query('//osis:chapter[@osisID]');

        foreach ($chapterElements as $chapterElement) {
            $osisId = $chapterElement->getAttribute('osisID');
            if (!$osisId) continue;

            // Parse book and chapter (e.g., "Gen.1" -> "Gen", 1)
            $parts = explode('.', $osisId);
            if (count($parts) < 2) continue;

            $bookOsis = $parts[0];
            $chapterNumber = (int) $parts[1];

            $bookId = $this->books[$bookOsis] ?? null;
            if (!$bookId) continue;

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

        $this->info("✅ Chapters imported: " . count($this->chapters));
    }

    /**
     * Process titles that appear at the chapter level (like chapter headings)
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
        $this->info("📝 Importing verses and content...");

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
        if (!$osisId || !$sId) return;

        // Parse verse reference (e.g., "Gen.1.1")
        $parts = explode('.', $osisId);
        if (count($parts) < 3) return;

        $bookOsis = $parts[0];
        $chapterNum = (int) $parts[1];
        $verseNum = (int) $parts[2];

        // Find chapter
        $chapterRef = $bookOsis . '.' . $chapterNum;
        $chapterId = $this->chapters[$chapterRef] ?? null;
        if (!$chapterId) return;

        // Handle paragraph tracking when switching chapters
        if ($this->currentChapterId !== $chapterId) {
            // Save previous chapter's final paragraph if exists
            if ($this->currentChapterId && $this->currentParagraphStart !== null && !empty($this->currentParagraphVerses)) {
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
            if ($this->currentParagraphStart !== null && !in_array($verseNum, $this->currentParagraphVerses)) {
                $this->currentParagraphVerses[] = $verseNum;
            }
        }

        // Check for titles that come before this verse (especially for verse 1)
        if ($verseNum === 1) {
            $this->extractChapterTitles($verseElement, $verseId);
        }

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

    private function extractChapterTitles($verseElement, $verseId)
    {
        // Look backwards from the verse to find any title elements in the same chapter
        $current = $verseElement->previousSibling;
        $order = 1;

        while ($current) {
            if ($current->nodeType === XML_ELEMENT_NODE) {
                $nodeName = $current->localName ?: $current->nodeName;

                if ($nodeName === 'title') {
                    $this->importTitle($current, $verseId, $order);
                    $order++;
                } elseif ($nodeName === 'chapter') {
                    // Stop when we reach the chapter start
                    break;
                }
            }
            $current = $current->previousSibling;
        }
    }

    private function extractVerseContent($verseElement)
    {
        $osisId = $verseElement->getAttribute('osisID');

        // Get all content until the verse end marker
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

        // Fix spacing issues when extracting plain text
        $plainText = $this->extractPlainTextWithSpacing($content);
        $formattedText = $this->formatToHTML($content);

        return [
            'text' => trim($plainText),
            'formatted' => trim($formattedText),
            'xml' => trim($content)
        ];
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
        if (!empty($xmlContent)) {
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
        $validTitleTypes = ['main', 'chapter', 'psalm', 'acrostic', 'sub'];
        if (!in_array($titleType, $validTitleTypes)) {
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

        // Title formatting
        $html = preg_replace('/<title\s+type="psalm"[^>]*>/', '<div class="psalm-title text-center text-sm font-medium text-gray-700 dark:text-gray-300 italic mb-3 border-b border-gray-200 dark:border-gray-600 pb-2">', $html);
        $html = preg_replace('/<title\s+type="main"[^>]*>/', '<h2 class="main-title text-xl font-bold text-gray-900 dark:text-gray-100 mb-4">', $html);
        $html = preg_replace('/<title\s+type="chapter"[^>]*>/', '<h3 class="chapter-title text-lg font-semibold text-gray-800 dark:text-gray-200 mb-3 text-center">', $html);
        $html = preg_replace('/<title\s+type="acrostic"[^>]*>/', '<div class="acrostic-title text-center text-lg font-semibold text-blue-700 dark:text-blue-400 mb-2">', $html);
        $html = preg_replace('/<title\s+type="sub"[^>]*>/', '<h4 class="sub-title text-md font-medium text-gray-700 dark:text-gray-300 mb-2">', $html);
        $html = preg_replace('/<title[^>]*>/', '<div class="title text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">', $html);
        $html = str_replace('</title>', '</div>', $html);

        // Poetry formatting with 4-level indentation
        $html = preg_replace('/<lg[^>]*>/', '<div class="line-group mb-2">', $html);
        $html = str_replace('</lg>', '</div>', $html);

        // Poetry lines with level-based indentation
        $html = preg_replace('/<l\s+level="1"[^>]*>/', '<div class="poetry-line indent-0 leading-relaxed">', $html);
        $html = preg_replace('/<l\s+level="2"[^>]*>/', '<div class="poetry-line indent-4 leading-relaxed ml-4">', $html);
        $html = preg_replace('/<l\s+level="3"[^>]*>/', '<div class="poetry-line indent-8 leading-relaxed ml-8">', $html);
        $html = preg_replace('/<l\s+level="4"[^>]*>/', '<div class="poetry-line indent-12 leading-relaxed ml-12">', $html);
        $html = preg_replace('/<l[^>]*>/', '<div class="poetry-line indent-0 leading-relaxed">', $html);
        $html = str_replace('</l>', '</div>', $html);

        // Caesura (poetry pause) formatting
        $html = str_replace('<caesura/>', '<span class="caesura text-gray-400 mx-2">‖</span>', $html);
        $html = str_replace('<caesura>', '<span class="caesura text-gray-400 mx-2">‖</span>', $html);

        // Red Letter text (Jesus' words) - convert OSIS markup to styled HTML
        $html = preg_replace('/<q\s+who="Jesus"[^>]*>/', '<span class="text-red-600 dark:text-red-400 font-medium">', $html);
        $html = str_replace('</q>', '</span>', $html);

        // Basic transformations
        $html = str_replace('<transChange type="added">', '<em class="text-gray-600 dark:text-gray-400 font-normal italic">', $html);
        $html = str_replace('</transChange>', '</em>', $html);
        $html = str_replace('<divineName>', '<span style="font-variant: small-caps; font-weight: 600; letter-spacing: 0.05em; text-transform: uppercase; font-size: 0.95em;">', $html);
        $html = str_replace('</divineName>', '</span>', $html);

        // Remove word markup for display while preserving spacing
        $html = preg_replace('/<w[^>]*>/', '', $html);
        // Add space after </w> only if followed by actual text content (not punctuation or whitespace)
        $html = preg_replace('/<\/w>(?=[^\s.,:;!?\'")\]\-])/', '</w> ', $html);
        $html = str_replace('</w>', '', $html); // Clean up remaining </w> tags

        // Add space after </em> only if followed by actual text content
        $html = preg_replace('/<\/em>(?=[^\s.,:;!?\'")\]\-])/', '</em> ', $html);

        // Add space after red letter text </span> only if followed by actual text content
        $html = preg_replace('/<\/span>(?=[^\s.,:;!?\'")\]\-])/', '</span> ', $html);

        // Remove notes for basic display
        $html = preg_replace('/<note[^>]*>.*?<\/note>/s', '', $html);

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
        $this->info("🔍 Updating FTS search tables...");

        try {
            DB::statement("INSERT INTO verses_fts(verses_fts) VALUES('rebuild')");
        } catch (Exception $e) {
            $this->warn("FTS rebuild failed: " . $e->getMessage());
        }

        $this->info("✅ FTS tables updated");
    }

    private function getBookGroupId($osisId)
    {
        $otBooks = ['Gen', 'Exod', 'Lev', 'Num', 'Deut', 'Josh', 'Judg', 'Ruth', '1Sam', '2Sam', '1Kgs', '2Kgs', '1Chr', '2Chr', 'Ezra', 'Neh', 'Esth', 'Job', 'Ps', 'Prov', 'Eccl', 'Song', 'Isa', 'Jer', 'Lam', 'Ezek', 'Dan', 'Hos', 'Joel', 'Amos', 'Obad', 'Jonah', 'Mic', 'Nah', 'Hab', 'Zeph', 'Hag', 'Zech', 'Mal'];

        if (in_array($osisId, $otBooks)) {
            return DB::table('book_groups')->where('name', 'Old Testament')->value('id');
        } else {
            return DB::table('book_groups')->where('name', 'New Testament')->value('id');
        }
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
            'Rev' => 66
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
            'Rev' => 'Revelation'
        ];

        return $bookNames[$osisId] ?? $osisId;
    }

    private function displaySummary()
    {
        $this->newLine();
        $this->info("🎉 Import completed successfully!");
        $this->newLine();

        $this->line("📊 <info>Import Summary:</info>");
        $this->line("   📚 Books: " . count($this->books));
        $this->line("   📖 Chapters: " . count($this->chapters));
        $this->line("   📝 Verses: " . $this->verseCount);
        $this->line("   📄 Paragraphs: " . $this->paragraphCount);
        $this->line("   🔤 Word Elements: " . $this->wordElementCount);
        $this->line("   ✒️  Translator Changes: " . $this->translatorChangeCount);
        $this->line("   👑 Divine Names: " . $this->divineNameCount);
        $this->line("   🔴 Red Letter Text: " . $this->redLetterCount);
        $this->line("   📚 Titles: " . $this->titleCount);

        // Show title type breakdown
        $titleBreakdown = DB::table('titles')
            ->select('title_type', DB::raw('count(*) as count'))
            ->groupBy('title_type')
            ->orderBy('count', 'desc')
            ->get();

        if ($titleBreakdown->count() > 0) {
            $this->line("       Title breakdown:");
            foreach ($titleBreakdown as $titleType) {
                $this->line("       - {$titleType->title_type}: {$titleType->count}");
            }
        }

        $this->line("   📝 Poetry Lines: " . $this->poetryLineCount);

        $this->newLine();
        $this->line("✅ Database is ready for biblical scholarship!");
    }

    /**
     * Process all book titles after chapters have been imported
     */
    private function processAllBookTitles()
    {
        $this->info("📑 Processing book titles...");

        // Get all book divisions with namespace
        $bookDivs = $this->xpath->query('//osis:div[@type="book"]');

        foreach ($bookDivs as $bookDiv) {
            $osisId = $bookDiv->getAttribute('osisID');
            if (!$osisId) continue;

            $bookId = $this->books[$osisId] ?? null;
            if (!$bookId) continue;

            // Process any main titles that appear in this book div
            $this->processBookTitles($bookDiv, $bookId);
        }
    }

    /**
     * Process main book titles that appear at the book level
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
     */
    private function processParagraphMarkers($xmlContent, $verseNum)
    {
        // Check for OSIS paragraph markers
        if (preg_match('/<milestone[^>]*type="x-p"[^>]*marker="¶"[^>]*>/', $xmlContent)) {
            // Found a paragraph marker - save current paragraph and start new one
            if ($this->currentParagraphStart !== null && !empty($this->currentParagraphVerses)) {
                // End the current paragraph at the verse BEFORE this new paragraph marker
                $previousParagraphVerses = array_filter($this->currentParagraphVerses, function($v) use ($verseNum) {
                    return $v < $verseNum;
                });

                if (!empty($previousParagraphVerses)) {
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

            if (!$existingParagraph) {
                DB::table('paragraphs')->insert([
                    'chapter_id' => $chapterId,
                    'start_verse_id' => $startVerseId,
                    'end_verse_id' => $endVerseId,
                    'paragraph_type' => 'normal',
                    'text_content' => $textContent,
                    'created_at' => now(),
                    'updated_at' => now()
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
        if ($this->currentParagraphStart !== null && !empty($this->currentParagraphVerses)) {
            $this->saveParagraph($this->currentChapterId, $this->currentParagraphStart, $this->currentParagraphVerses);
        }
    }
}
