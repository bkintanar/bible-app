<?php

namespace App\Services\Parsers;

use App\Services\Contracts\OsisParserInterface;
use DOMXPath;
use Illuminate\Support\Collection;

class MilestoneOsisParser implements OsisParserInterface
{
    protected DOMXPath $xpath;

    public function __construct(DOMXPath $xpath)
    {
        $this->xpath = $xpath;
    }

    public function getChapters(string $bookOsisId): Collection
    {
        $chapters = collect();

        // Find chapter start markers for this book
        $chapterNodes = $this->xpath->query("//osis:chapter[@sID and starts-with(@osisRef, '$bookOsisId.')]");

        foreach ($chapterNodes as $chapterNode) {
            $osisRef = $chapterNode->getAttribute('osisRef');
            $chapterNumber = $chapterNode->getAttribute('n');
            $sId = $chapterNode->getAttribute('sID');

            // Create eID pattern from sID
            $eId = str_replace('.sID.', '.eID.', $sId);

            // Count verses between sID and eID markers
            $verseCount = 0;
            $currentNode = $chapterNode->nextSibling;

            while ($currentNode) {
                // Check if we've reached the chapter end marker
                if ($currentNode->nodeType === XML_ELEMENT_NODE &&
                    $currentNode->nodeName === 'chapter' &&
                    $currentNode->getAttribute('eID') === $eId) {
                    break;
                }

                // Count verse start markers
                if ($currentNode->nodeType === XML_ELEMENT_NODE &&
                    $currentNode->nodeName === 'verse' &&
                    $currentNode->hasAttribute('sID')) {
                    $verseCount++;
                }

                // Also check children for verse elements
                if ($currentNode->nodeType === XML_ELEMENT_NODE) {
                    $verseElements = $this->xpath->query('.//osis:verse[@sID]', $currentNode);
                    $verseCount += $verseElements->length;
                }

                $currentNode = $currentNode->nextSibling;
            }

            $chapters->push([
                'osis_ref' => $osisRef,
                'chapter_number' => (int) $chapterNumber,
                'verse_count' => $verseCount
            ]);
        }

        return $chapters->sortBy('chapter_number');
    }

    public function getVerses(string $chapterOsisRef): Collection
    {
        $verses = collect();

        // Find the chapter start marker
        $chapterNode = $this->xpath->query("//osis:chapter[@osisRef='$chapterOsisRef' and @sID]")->item(0);

        if (!$chapterNode) {
            return $verses;
        }

        $sId = $chapterNode->getAttribute('sID');
        $eId = str_replace('.sID.', '.eID.', $sId);

        // Collect all verse start markers between chapter start and end
        $verseNodes = [];
        $currentNode = $chapterNode->nextSibling;

        while ($currentNode) {
            // Check if we've reached the chapter end marker
            if ($currentNode->nodeType === XML_ELEMENT_NODE &&
                $currentNode->nodeName === 'chapter' &&
                $currentNode->getAttribute('eID') === $eId) {
                break;
            }

            // Collect verse start markers
            if ($currentNode->nodeType === XML_ELEMENT_NODE) {
                // Direct verse elements
                if ($currentNode->nodeName === 'verse' && $currentNode->hasAttribute('sID')) {
                    $verseNodes[] = $currentNode;
                }

                // Verse elements within other elements (like <p>)
                $innerVerses = $this->xpath->query('.//osis:verse[@sID]', $currentNode);
                foreach ($innerVerses as $verseNode) {
                    $verseNodes[] = $verseNode;
                }
            }

            $currentNode = $currentNode->nextSibling;
        }

        // Process each verse
        foreach ($verseNodes as $verseNode) {
            $osisId = $verseNode->getAttribute('osisID');
            $verseNumber = $verseNode->getAttribute('n');
            $verseText = $this->getVerseText($osisId);

            $verses->push([
                'osis_id' => $osisId,
                'verse_number' => (int) $verseNumber,
                'text' => $verseText
            ]);
        }

        return $verses->sortBy('verse_number');
    }

    public function getVersesParagraphStyle(string $chapterOsisRef): Collection
    {
        $paragraphs = collect();

        // Find the chapter start marker
        $chapterNode = $this->xpath->query("//osis:chapter[@osisRef='$chapterOsisRef' and @sID]")->item(0);

        if (!$chapterNode) {
            return $paragraphs;
        }

        $sId = $chapterNode->getAttribute('sID');
        $eId = str_replace('.sID.', '.eID.', $sId);

        // Find all paragraph and poetic elements within this chapter
        $currentNode = $chapterNode->nextSibling;

        while ($currentNode) {
            // Check if we've reached the chapter end marker
            if ($currentNode->nodeType === XML_ELEMENT_NODE &&
                $currentNode->nodeName === 'chapter' &&
                $currentNode->getAttribute('eID') === $eId) {
                break;
            }

            // Process paragraph elements
            if ($currentNode->nodeType === XML_ELEMENT_NODE && $currentNode->nodeName === 'p') {
                $paragraphData = $this->extractParagraphContent($currentNode);
                if (!empty($paragraphData['verses'])) {
                    $paragraphs->push($paragraphData);
                }
            }
            // Process poetic line groups (for Psalms, poetry)
            elseif ($currentNode->nodeType === XML_ELEMENT_NODE && $currentNode->nodeName === 'lg') {
                $lineGroupData = $this->extractLineGroupContent($currentNode);
                if (!empty($lineGroupData['verses'])) {
                    $paragraphs->push($lineGroupData);
                }
            }
            // Process line breaks (lb elements)
            elseif ($currentNode->nodeType === XML_ELEMENT_NODE && $currentNode->nodeName === 'lb') {
                // Add a line break element as a special paragraph type
                $paragraphs->push([
                    'verses' => [],
                    'combined_text' => '<br class="my-4">',
                    'type' => 'line_break'
                ]);
            }

            $currentNode = $currentNode->nextSibling;
        }

        return $paragraphs;
    }

    public function getVerseText(string $verseOsisId): string
    {
        // Find the verse start element
        $verseStart = $this->xpath->query("//osis:verse[@osisID='$verseOsisId' and @sID]")->item(0);
        if (!$verseStart) {
            return '';
        }

        $sId = $verseStart->getAttribute('sID');
        $eId = str_replace('.sID.', '.eID.', $sId);

        // Find the verse end element
        $verseEnd = $this->xpath->query("//osis:verse[@eID='$eId']")->item(0);
        if (!$verseEnd) {
            return '';
        }

        // Extract text between start and end markers with Red Letter formatting
        $text = '';
        $currentNode = $verseStart->nextSibling;

        while ($currentNode && $currentNode !== $verseEnd) {
            if ($currentNode->nodeType === XML_TEXT_NODE) {
                $text .= $currentNode->textContent;
            } elseif ($currentNode->nodeType === XML_ELEMENT_NODE) {
                $text .= $this->extractTextWithRedLetters($currentNode);
            }
            $currentNode = $currentNode->nextSibling;
        }

        return trim($text);
    }

    public function searchVerses(string $searchTerm, int $limit = 100): Collection
    {
        $results = collect();
        $processedCount = 0;

        // Early exit for empty search
        if (empty(trim($searchTerm))) {
            return $results;
        }

        // MAJOR OPTIMIZATION: Pre-build a map of eID to verse nodes to avoid repeated XPath queries
        $verseEndMap = [];
        $verseEndNodes = $this->xpath->query('//osis:verse[@eID]');
        foreach ($verseEndNodes as $endNode) {
            $eId = $endNode->getAttribute('eID');
            $verseEndMap[$eId] = $endNode;
        }

        // Get all verse elements with milestone format
        $verseNodes = $this->xpath->query('//osis:verse[@sID]');

        foreach ($verseNodes as $verseNode) {
            if ($processedCount >= $limit) {
                break;
            }

            $osisId = $verseNode->getAttribute('osisID');
            $parts = explode('.', $osisId);

            if (count($parts) >= 3) {
                $bookId = $parts[0];

                // Skip apocrypha books
                if ($this->isApocrypha($bookId)) {
                    continue;
                }

                // OPTIMIZATION: Fast pre-screening using the cached end markers
                if (!$this->fastTextContainsOptimized($verseNode, $verseEndMap, $searchTerm)) {
                    continue; // Skip expensive processing if no match
                }

                // Only do expensive text processing if we have a potential match
                $verseText = $this->getVerseText($osisId);

                // Double-check with full formatted text
                if (stripos($verseText, $searchTerm) !== false) {
                    $chapterNum = $parts[1];
                    $verseNum = $parts[2];

                    $results->push([
                        'osis_id' => $osisId,
                        'book_id' => $bookId,
                        'chapter' => (int) $chapterNum,
                        'verse' => (int) $verseNum,
                        'text' => $verseText,
                        'context' => $this->highlightSearchTerm($verseText, $searchTerm)
                    ]);

                    $processedCount++;
                }
            }
        }

        return $results;
    }

    /**
     * Optimized fast text search for milestone verses using pre-cached end markers
     */
    private function fastTextContainsOptimized($verseStartNode, array $verseEndMap, string $searchTerm): bool
    {
        $sId = $verseStartNode->getAttribute('sID');
        $eId = str_replace('.sID.', '.eID.', $sId);

        // Use cached verse end node instead of XPath query
        $verseEnd = $verseEndMap[$eId] ?? null;
        if (!$verseEnd) {
            return false;
        }

        // Extract plain text between start and end markers with early exit
        $plainText = '';
        $currentNode = $verseStartNode->nextSibling;
        $maxNodes = 200; // Safety limit to prevent runaway processing
        $nodeCount = 0;

        while ($currentNode && $currentNode !== $verseEnd && $nodeCount < $maxNodes) {
            if ($currentNode->nodeType === XML_TEXT_NODE) {
                $plainText .= $currentNode->textContent;
            } elseif ($currentNode->nodeType === XML_ELEMENT_NODE) {
                // Just get textContent for speed - no formatting
                $plainText .= $currentNode->textContent;
            }

            // Early exit optimization: check for match as we build the text
            if (strlen($plainText) > strlen($searchTerm) && stripos($plainText, $searchTerm) !== false) {
                return true;
            }

            $currentNode = $currentNode->nextSibling;
            $nodeCount++;
        }

        // Final check in case the search term spans the end of the text
        return stripos($plainText, $searchTerm) !== false;
    }

    /**
     * Extract content from a paragraph element, including all verses within it
     */
    private function extractParagraphContent($paragraphNode): array
    {
        $verses = [];
        $paragraphText = '';
        $currentVerse = null;
        $verseText = '';

        foreach ($paragraphNode->childNodes as $child) {
            if ($child->nodeType === XML_ELEMENT_NODE && $child->nodeName === 'verse') {
                if ($child->hasAttribute('sID')) {
                    // Save previous verse if exists
                    if ($currentVerse) {
                        $verses[] = [
                            'osis_id' => $currentVerse,
                            'verse_number' => $this->extractVerseNumber($currentVerse),
                            'text' => trim($verseText)
                        ];
                    }

                    // Start new verse
                    $currentVerse = $child->getAttribute('osisID');
                    $verseText = '';
                } elseif ($child->hasAttribute('eID')) {
                    // End current verse
                    if ($currentVerse) {
                        $verses[] = [
                            'osis_id' => $currentVerse,
                            'verse_number' => $this->extractVerseNumber($currentVerse),
                            'text' => trim($verseText)
                        ];
                        $currentVerse = null;
                        $verseText = '';
                    }
                }
            } elseif ($child->nodeType === XML_TEXT_NODE) {
                $verseText .= $child->textContent;
                $paragraphText .= $child->textContent;
            } elseif ($child->nodeType === XML_ELEMENT_NODE) {
                $elementText = $this->extractTextWithRedLetters($child);
                $verseText .= $elementText;
                $paragraphText .= $elementText;
            }
        }

        // Handle case where verse doesn't have eID in this paragraph
        if ($currentVerse && !empty(trim($verseText))) {
            $verses[] = [
                'osis_id' => $currentVerse,
                'verse_number' => $this->extractVerseNumber($currentVerse),
                'text' => trim($verseText)
            ];
        }

        return [
            'verses' => $verses,
            'combined_text' => trim($paragraphText)
        ];
    }

    /**
     * Extract content from a line group element (for poetry like Psalms)
     */
    private function extractLineGroupContent($lineGroupNode): array
    {
        $verses = [];
        $combinedText = '';

        // Process all line elements within the line group
        foreach ($lineGroupNode->childNodes as $child) {
            if ($child->nodeType === XML_ELEMENT_NODE && $child->nodeName === 'l') {
                $lineData = $this->extractParagraphContent($child);
                if (!empty($lineData['verses'])) {
                    $verses = array_merge($verses, $lineData['verses']);
                    $combinedText .= $lineData['combined_text'] . ' ';
                }
            }
        }

        return [
            'verses' => $verses,
            'combined_text' => trim($combinedText),
            'type' => 'poetry'
        ];
    }

    /**
     * Extract text from a node while preserving Red Letter formatting and other OSIS formatting
     */
    private function extractTextWithRedLetters($node): string
    {
        $text = '';

        // Handle the case where the node itself is a special element
        if ($node->nodeType === XML_ELEMENT_NODE) {
            if ($node->nodeName === 'transChange') {
                // Handle translator additions - traditionally italicized
                $changeType = $node->getAttribute('type');
                if ($changeType === 'added') {
                    return '<em class="text-gray-600 dark:text-gray-400 font-normal italic">' . $node->textContent . '</em>';
                } else {
                    return $node->textContent;
                }
            } elseif ($node->nodeName === 'q' && $node->getAttribute('who') === 'Jesus') {
                // Handle Red Letter text for contained verses
                return '<span class="text-red-600 font-medium">' . $node->textContent . '</span>';
            } elseif ($node->nodeName === 'title') {
                // Handle titles (psalm titles, etc.)
                $titleType = $node->getAttribute('type');
                if ($titleType === 'psalm') {
                    return '<div class="text-center text-sm font-medium text-gray-700 dark:text-gray-300 italic mb-3 border-b border-gray-200 dark:border-gray-600 pb-2">' . $node->textContent . '</div>';
                } elseif ($titleType === 'main') {
                    return '<h2 class="text-xl font-bold text-gray-900 dark:text-gray-100 mb-4">' . $node->textContent . '</h2>';
                } else {
                    return '<div class="text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">' . $node->textContent . '</div>';
                }
            }
        }

        // Process child nodes for complex elements
        foreach ($node->childNodes as $child) {
            if ($child->nodeType === XML_TEXT_NODE) {
                $text .= $child->textContent;
            } elseif ($child->nodeType === XML_ELEMENT_NODE) {
                if ($child->nodeName === 'q' && $child->getAttribute('who') === 'Jesus') {
                    // Red Letter Bible - Jesus' words in red
                    if ($child->hasAttribute('sID')) {
                        $text .= '<span class="text-red-600 font-medium">';
                    } elseif ($child->hasAttribute('eID')) {
                        $text .= '</span>';
                    }
                } elseif ($child->nodeName === 'transChange') {
                    // Handle translator additions - traditionally italicized
                    $changeType = $child->getAttribute('type');
                    if ($changeType === 'added') {
                        $text .= '<em class="text-gray-600 dark:text-gray-400 font-normal italic">' . $child->textContent . '</em>';
                    } else {
                        $text .= $child->textContent;
                    }
                } elseif ($child->nodeName === 'title') {
                    // Handle titles (psalm titles, etc.)
                    $titleType = $child->getAttribute('type');
                    if ($titleType === 'psalm') {
                        $text .= '<div class="text-center text-sm font-medium text-gray-700 dark:text-gray-300 italic mb-3 border-b border-gray-200 dark:border-gray-600 pb-2">' . $child->textContent . '</div>';
                    } elseif ($titleType === 'main') {
                        $text .= '<h2 class="text-xl font-bold text-gray-900 dark:text-gray-100 mb-4">' . $child->textContent . '</h2>';
                    } else {
                        $text .= '<div class="text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">' . $child->textContent . '</div>';
                    }
                } elseif ($child->nodeName === 'lg' || $child->nodeName === 'l') {
                    // Handle line groups and lines for poetry - preserve structure but extract text
                    $text .= $this->extractTextWithRedLetters($child);
                } elseif ($child->nodeName === 'lb') {
                    // Handle line breaks
                    $text .= '<br class="my-2">';
                } else {
                    $text .= $this->extractTextWithRedLetters($child);
                }
            }
        }

        return $text;
    }

    /**
     * Extract verse number from OSIS ID (e.g., "Gen.1.1" -> 1)
     */
    private function extractVerseNumber(string $osisId): int
    {
        $parts = explode('.', $osisId);
        return (int) end($parts);
    }

    /**
     * Highlight search terms in text while preserving Red Letter formatting
     */
    private function highlightSearchTerm(string $text, string $searchTerm): string
    {
        // Strip HTML for search matching, but preserve it in display
        $plainText = strip_tags($text);
        $searchTermLower = strtolower($searchTerm);
        $plainTextLower = strtolower($plainText);

        $pos = strpos($plainTextLower, $searchTermLower);
        if ($pos === false) {
            return $text; // No match, return original
        }

        // Use simple replacement if no HTML tags are present
        if ($text === $plainText) {
            return str_ireplace($searchTerm, "<mark>$searchTerm</mark>", $text);
        }

        // For HTML content, use a more careful approach
        return preg_replace(
            '/(?![^<]*>)(' . preg_quote($searchTerm, '/') . ')/i',
            '<mark>$1</mark>',
            $text
        );
    }

    /**
     * Check if a book is part of the Apocrypha
     */
    private function isApocrypha(string $osisId): bool
    {
        $apocryphaBooks = [
            'Tob', 'Tobit', 'Jdt', 'Judith', 'AddEsth', 'EsthGr', 'Wis', 'Wisd',
            'Sir', 'Sirach', 'Bar', 'Baruch', 'EpJer', 'EpisJer', 'PrAzar', 'SgThree',
            'Sus', 'Susanna', 'Bel', 'BelDragon', '1Macc', 'IMacc', '2Macc', 'IIMacc',
            '1Esd', 'IEsd', '2Esd', 'IIEsd', 'PrMan', 'ManPr', '3Macc', 'IIIMacc',
            '4Macc', 'IVMacc', 'Ps151', 'AddPs',
        ];

        return in_array($osisId, $apocryphaBooks);
    }
}
