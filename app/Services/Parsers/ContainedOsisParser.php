<?php

namespace App\Services\Parsers;

use DOMXPath;
use Illuminate\Support\Collection;
use App\Services\Contracts\OsisParserInterface;

class ContainedOsisParser implements OsisParserInterface
{
    protected DOMXPath $xpath;

    public function __construct(DOMXPath $xpath)
    {
        $this->xpath = $xpath;
    }

    public function getChapters(string $bookOsisId): Collection
    {
        $chapters = collect();

        // Find chapters by their osisID attribute
        $chapterNodes = $this->xpath->query("//osis:chapter[starts-with(@osisID, '$bookOsisId.')]");

        foreach ($chapterNodes as $chapterNode) {
            $osisId = $chapterNode->getAttribute('osisID');
            $parts = explode('.', $osisId);
            $chapterNumber = (int) end($parts);

            // Count contained verses within this chapter
            $verseNodes = $this->xpath->query('.//osis:verse[@osisID]', $chapterNode);
            $verseCount = $verseNodes->length;

            $chapters->push([
                'osis_ref' => $osisId,
                'chapter_number' => $chapterNumber,
                'verse_count' => $verseCount,
            ]);
        }

        return $chapters->sortBy('chapter_number');
    }

    public function getVerses(string $chapterOsisRef): Collection
    {
        $verses = collect();

        // Find the chapter element
        $chapterNode = $this->xpath->query("//osis:chapter[@osisID='$chapterOsisRef']")->item(0);

        if (! $chapterNode) {
            return $verses;
        }

        // Find all verse elements within this chapter
        $verseNodes = $this->xpath->query('.//osis:verse[@osisID]', $chapterNode);

        foreach ($verseNodes as $verseNode) {
            $osisId = $verseNode->getAttribute('osisID');
            $parts = explode('.', $osisId);
            $verseNumber = (int) end($parts);
            $verseText = $this->getVerseTextFromNode($verseNode);

            $verses->push([
                'osis_id' => $osisId,
                'verse_number' => $verseNumber,
                'text' => $verseText,
            ]);
        }

        return $verses->sortBy('verse_number');
    }

    public function getVersesParagraphStyle(string $chapterOsisRef): Collection
    {
        $paragraphs = collect();

        // Find the chapter element by osisID
        $chapterNode = $this->xpath->query("//osis:chapter[@osisID='$chapterOsisRef']")->item(0);

        if (! $chapterNode) {
            return $paragraphs;
        }

        // For contained verses, we need to create a single paragraph with all verses
        // since ASV doesn't have explicit paragraph markup like KJV
        $verses = [];
        $combinedText = '';

        // Get all verse elements within this chapter
        $verseNodes = $this->xpath->query('.//osis:verse[@osisID]', $chapterNode);

        foreach ($verseNodes as $verseNode) {
            $osisId = $verseNode->getAttribute('osisID');
            $parts = explode('.', $osisId);
            $verseNumber = (int) end($parts);
            $verseText = $this->getVerseTextFromNode($verseNode);

            $verses[] = [
                'osis_id' => $osisId,
                'verse_number' => $verseNumber,
                'text' => $verseText,
            ];

            // Add verse text to combined text with space
            $combinedText .= $verseText . ' ';
        }

        // Create a single paragraph containing all verses
        if (! empty($verses)) {
            $paragraphs->push([
                'verses' => $verses,
                'combined_text' => trim($combinedText),
            ]);
        }

        return $paragraphs;
    }

    public function getVerseText(string $verseOsisId): string
    {
        // For contained verses, we need to find the verse node and extract its content
        $verseNode = $this->xpath->query("//osis:verse[@osisID='$verseOsisId']")->item(0);
        if (! $verseNode) {
            return '';
        }
        return $this->getVerseTextFromNode($verseNode);
    }

    public function searchVerses(string $searchTerm, int $limit = 100): Collection
    {
        $results = collect();
        $processedCount = 0;

        // Early exit for empty search
        if (empty(trim($searchTerm))) {
            return $results;
        }

        // Use a more targeted XPath to search in textContent first (much faster)
        $searchTermLower = strtolower($searchTerm);

        // Get all verse elements with contained format
        $verseNodes = $this->xpath->query('//osis:verse[@osisID and not(@sID)]');

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

                // OPTIMIZATION: First do a fast plain text search using textContent
                $plainText = $verseNode->textContent;
                if (stripos($plainText, $searchTerm) === false) {
                    continue; // Skip expensive processing if no match
                }

                // Only do expensive text processing if we have a match
                $verseText = $this->getVerseTextFromNode($verseNode);

                // Double-check with formatted text (in case textContent differs from formatted)
                if (stripos($verseText, $searchTerm) !== false) {
                    $chapterNum = $parts[1];
                    $verseNum = $parts[2];

                    $results->push([
                        'osis_id' => $osisId,
                        'book_id' => $bookId,
                        'chapter' => (int) $chapterNum,
                        'verse' => (int) $verseNum,
                        'text' => $verseText,
                        'context' => $this->highlightSearchTerm($verseText, $searchTerm),
                    ]);

                    $processedCount++;
                }
            }
        }

        return $results;
    }

    /**
     * Get text for contained verses (content within verse tags)
     * @param mixed $verseNode
     */
    private function getVerseTextFromNode($verseNode): string
    {
        return $this->extractTextWithRedLetters($verseNode);
    }

    /**
     * Extract text from a node while preserving Red Letter formatting and other OSIS formatting
     * @param mixed $node
     * @param bool $inJesusQuote Whether we're currently inside a Jesus quote context
     */
    private function extractTextWithRedLetters($node, bool $inJesusQuote = false): string
    {
        $text = '';

        // Handle the case where the node itself is a special element
        if ($node->nodeType === XML_ELEMENT_NODE) {
            if ($node->nodeName === 'transChange') {
                // Handle translator additions - traditionally italicized
                $changeType = $node instanceof \DOMElement ? $node->getAttribute('type') : '';
                if ($changeType === 'added') {
                    if ($inJesusQuote) {
                        // Red italics for translator additions within Jesus's speech
                        return '<em class="text-red-500 dark:text-red-300 font-normal italic opacity-80">' . $node->textContent . '</em>';
                    } else {
                        // Gray italics for translator additions outside Jesus's speech
                        return '<em class="text-gray-600 dark:text-gray-400 font-normal italic">' . $node->textContent . '</em>';
                    }
                }
                return $node->textContent;

            } elseif ($node->nodeName === 'q' && $node instanceof \DOMElement && $node->getAttribute('who') === 'Jesus') {
                // Handle Red Letter text for contained verses
                // Process the content with Jesus quote context enabled
                $content = '';
                foreach ($node->childNodes as $child) {
                    if ($child->nodeType === XML_TEXT_NODE) {
                        $content .= $child->textContent;
                    } elseif ($child->nodeType === XML_ELEMENT_NODE) {
                        $content .= $this->extractTextWithRedLetters($child, true);
                    }
                }
                return '<span class="text-red-600 dark:text-red-400 font-medium">' . $content . '</span>';
            } elseif ($node->nodeName === 'title') {
                // Handle titles (psalm titles, etc.)
                $titleType = $node instanceof \DOMElement ? $node->getAttribute('type') : '';
                if ($titleType === 'psalm') {
                    return '<div class="text-center text-sm font-medium text-gray-700 dark:text-gray-300 italic mb-3 border-b border-gray-200 dark:border-gray-600 pb-2">' . $node->textContent . '</div>';
                } elseif ($titleType === 'main') {
                    return '<h2 class="text-xl font-bold text-gray-900 dark:text-gray-100 mb-4">' . $node->textContent . '</h2>';
                }
                return '<div class="text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">' . $node->textContent . '</div>';

            }
        }

        // Process child nodes for complex elements
        foreach ($node->childNodes as $child) {
            if ($child->nodeType === XML_TEXT_NODE) {
                $text .= $child->textContent;
            } elseif ($child->nodeType === XML_ELEMENT_NODE) {
                if ($child->nodeName === 'q' && $child instanceof \DOMElement && $child->getAttribute('who') === 'Jesus') {
                    // Red Letter Bible - Jesus' words in red
                    if ($child->hasAttribute('sID')) {
                        $text .= '<span class="text-red-600 dark:text-red-400 font-medium">';
                        $inJesusQuote = true;
                    } elseif ($child->hasAttribute('eID')) {
                        $text .= '</span>';
                        $inJesusQuote = false;
                    } else {
                        // For contained verses, process content with Jesus context
                        $content = '';
                        foreach ($child->childNodes as $grandChild) {
                            if ($grandChild->nodeType === XML_TEXT_NODE) {
                                $content .= $grandChild->textContent;
                            } elseif ($grandChild->nodeType === XML_ELEMENT_NODE) {
                                $content .= $this->extractTextWithRedLetters($grandChild, true);
                            }
                        }
                        $text .= '<span class="text-red-600 dark:text-red-400 font-medium">' . $content . '</span>';
                    }
                } elseif ($child->nodeName === 'transChange') {
                    // Handle translator additions - traditionally italicized
                    $changeType = $child instanceof \DOMElement ? $child->getAttribute('type') : '';
                    if ($changeType === 'added') {
                        if ($inJesusQuote) {
                            // Red italics for translator additions within Jesus's speech
                            $text .= '<em class="text-red-500 dark:text-red-300 font-normal italic opacity-80">' . $child->textContent . '</em>';
                        } else {
                            // Gray italics for translator additions outside Jesus's speech
                            $text .= '<em class="text-gray-600 dark:text-gray-400 font-normal italic">' . $child->textContent . '</em>';
                        }
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
                    $text .= $this->extractTextWithRedLetters($child, $inJesusQuote);
                } elseif ($child->nodeName === 'lb') {
                    // Handle line breaks
                    $text .= '<br class="my-2">';
                } else {
                    $text .= $this->extractTextWithRedLetters($child, $inJesusQuote);
                }
            }
        }

        return $text;
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
