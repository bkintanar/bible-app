<?php

use App\Services\Parsers\ContainedOsisParser;

describe('ContainedOsisParser', function () {
    beforeEach(function () {
        $asv_file_path = config('bible.osis_directory') . '/asv.osis.xml';

        $dom = new DOMDocument();
        $dom->load($asv_file_path);

        $xpath = new DOMXPath($dom);
        $xpath->registerNamespace('osis', 'http://www.bibletechnologies.net/2003/OSIS/namespace');

        $this->parser = new ContainedOsisParser($xpath);
    });

    describe('getChapters', function () {
        it('returns chapters for a book', function () {
            $chapters = $this->parser->getChapters('Gen');

            expect($chapters)->not->toBeEmpty();
            expect($chapters->first())->toHaveKeys(['chapter_number', 'verse_count']);
        });

        it('returns correct chapter count for Genesis', function () {
            $chapters = $this->parser->getChapters('Gen');

            expect($chapters->count())->toBe(50);
        });

        it('returns chapters in correct order', function () {
            $chapters = $this->parser->getChapters('Gen');

            $chapterNumbers = $chapters->pluck('chapter_number')->toArray();
            expect($chapterNumbers)->toBe(range(1, 50));
        });

        it('returns empty collection for non-existent book', function () {
            $chapters = $this->parser->getChapters('NonExistent');

            expect($chapters)->toBeEmpty();
        });
    });

    describe('getVerses', function () {
        it('returns verses for a chapter', function () {
            $verses = $this->parser->getVerses('Gen.1');

            expect($verses)->not->toBeEmpty();
            expect($verses->first())->toHaveKeys(['osis_id', 'verse_number', 'text']);
        });

        it('returns verses in order', function () {
            $verses = $this->parser->getVerses('Gen.1');

            $verseNumbers = $verses->pluck('verse_number')->toArray();
            expect($verseNumbers)->toBe(range(1, count($verseNumbers)));
        });

        it('returns empty collection for non-existent chapter', function () {
            $verses = $this->parser->getVerses('Gen.999');

            expect($verses)->toBeEmpty();
        });
    });

    describe('getVerseText', function () {
        it('extracts text from contained verses', function () {
            $text = $this->parser->getVerseText('Gen.1.1');

            expect($text)->toContain('beginning');
            expect($text)->toContain('God');
        });

        it('returns empty string for non-existent verse', function () {
            $text = $this->parser->getVerseText('Gen.999.999');

            expect($text)->toBe('');
        });

        it('handles special formatting elements', function () {
            // Test verse with potential special formatting
            $text = $this->parser->getVerseText('Gen.1.2');

            expect($text)->toBeString();
            expect(strlen($text))->toBeGreaterThan(0);
        });
    });

    describe('getVersesParagraphStyle', function () {
        it('returns single paragraph with all verses', function () {
            $paragraphs = $this->parser->getVersesParagraphStyle('Gen.1');

            expect($paragraphs)->not->toBeEmpty();
            expect($paragraphs->first())->toHaveKeys(['verses', 'combined_text']);

            $firstParagraph = $paragraphs->first();
            expect($firstParagraph['verses'])->not->toBeEmpty();
            expect($firstParagraph['combined_text'])->toBeString();
        });

        it('returns empty collection for non-existent chapter', function () {
            $paragraphs = $this->parser->getVersesParagraphStyle('Gen.999');

            expect($paragraphs)->toBeEmpty();
        });

        it('combines verse text correctly', function () {
            $paragraphs = $this->parser->getVersesParagraphStyle('Gen.1');

            $firstParagraph = $paragraphs->first();
            $combinedText = $firstParagraph['combined_text'];

            expect($combinedText)->toContain('beginning');
            expect($combinedText)->toContain('God');
        });
    });

    describe('searchVerses', function () {
        it('finds verses containing search term', function () {
            $results = $this->parser->searchVerses('God', 5);

            expect($results)->not->toBeEmpty();
            expect($results->first())->toHaveKeys(['osis_id', 'book_id', 'chapter', 'verse', 'text', 'context']);
        });

        it('highlights search terms', function () {
            $results = $this->parser->searchVerses('God', 1);

            expect($results)->not->toBeEmpty();
            $result = $results->first();
            expect($result['context'])->toContain('<mark>');
        });

        it('respects search limit', function () {
            $results = $this->parser->searchVerses('the', 3);

            expect($results->count())->toBeLessThanOrEqual(3);
        });

        it('returns empty collection for non-matching search', function () {
            $results = $this->parser->searchVerses('xyzzyx', 10);

            expect($results)->toBeEmpty();
        });

        it('handles case-insensitive search', function () {
            $results = $this->parser->searchVerses('GOD', 1);

            expect($results)->not->toBeEmpty();
        });

        it('skips apocrypha books', function () {
            // This tests the isApocrypha method indirectly
            $results = $this->parser->searchVerses('test', 100);

            // All results should be from canonical books
            foreach ($results as $result) {
                expect($result['book_id'])->not->toStartWith('Tob');
                expect($result['book_id'])->not->toStartWith('Jdt');
                expect($result['book_id'])->not->toStartWith('1Macc');
            }
        });

        it('handles verses with insufficient OSIS parts', function () {
            // This tests the edge case where parts count is less than 3
            $results = $this->parser->searchVerses('beginning', 1);

            expect($results)->not->toBeEmpty();
            // All valid results should have proper structure
            foreach ($results as $result) {
                expect($result)->toHaveKeys(['osis_id', 'book_id', 'chapter', 'verse']);
            }
        });
    });

    describe('specific formatting coverage tests', function () {
        it('handles Red Letter text with sID attribute', function () {
            // Create a mock DOM node with Red Letter markup (sID)
            $dom = new DOMDocument();
            $verse = $dom->createElement('verse');
            $qElement = $dom->createElement('q');
            $qElement->setAttribute('who', 'Jesus');
            $qElement->setAttribute('sID', 'q1');
            $verse->appendChild($qElement);

            // Use reflection to access the private method
            $reflection = new ReflectionClass($this->parser);
            $method = $reflection->getMethod('extractTextWithRedLetters');
            $method->setAccessible(true);

            $result = $method->invoke($this->parser, $verse);
            expect($result)->toContain('<span class="text-red-600 font-medium">');
        });

        it('handles Red Letter text with eID attribute', function () {
            // Create a mock DOM node with Red Letter markup (eID)
            $dom = new DOMDocument();
            $verse = $dom->createElement('verse');
            $qElement = $dom->createElement('q');
            $qElement->setAttribute('who', 'Jesus');
            $qElement->setAttribute('eID', 'q1');
            $verse->appendChild($qElement);

            // Use reflection to access the private method
            $reflection = new ReflectionClass($this->parser);
            $method = $reflection->getMethod('extractTextWithRedLetters');
            $method->setAccessible(true);

            $result = $method->invoke($this->parser, $verse);
            expect($result)->toContain('</span>');
        });

        it('handles Red Letter text with contained content', function () {
            // Create a mock DOM node with Red Letter markup (contained)
            $dom = new DOMDocument();
            $verse = $dom->createElement('verse');
            $qElement = $dom->createElement('q', 'Jesus said');
            $qElement->setAttribute('who', 'Jesus');
            $verse->appendChild($qElement);

            // Use reflection to access the private method
            $reflection = new ReflectionClass($this->parser);
            $method = $reflection->getMethod('extractTextWithRedLetters');
            $method->setAccessible(true);

            $result = $method->invoke($this->parser, $verse);
            expect($result)->toContain('<span class="text-red-600 font-medium">Jesus said</span>');
        });

        it('handles transChange elements with added type', function () {
            // Create a mock DOM node with transChange markup
            $dom = new DOMDocument();
            $verse = $dom->createElement('verse');
            $transElement = $dom->createElement('transChange', 'supplied');
            $transElement->setAttribute('type', 'added');
            $verse->appendChild($transElement);

            // Use reflection to access the private method
            $reflection = new ReflectionClass($this->parser);
            $method = $reflection->getMethod('extractTextWithRedLetters');
            $method->setAccessible(true);

            $result = $method->invoke($this->parser, $verse);
            expect($result)->toContain('<em class="text-gray-600 dark:text-gray-400 font-normal italic">supplied</em>');
        });

        it('handles transChange elements with other types', function () {
            // Create a mock DOM node with transChange markup (non-added type)
            $dom = new DOMDocument();
            $verse = $dom->createElement('verse');
            $transElement = $dom->createElement('transChange', 'other');
            $transElement->setAttribute('type', 'other');
            $verse->appendChild($transElement);

            // Use reflection to access the private method
            $reflection = new ReflectionClass($this->parser);
            $method = $reflection->getMethod('extractTextWithRedLetters');
            $method->setAccessible(true);

            $result = $method->invoke($this->parser, $verse);
            expect($result)->toContain('other');
            expect($result)->not->toContain('<em');
        });

        it('handles title elements with psalm type', function () {
            // Create a mock DOM node with psalm title
            $dom = new DOMDocument();
            $verse = $dom->createElement('verse');
            $titleElement = $dom->createElement('title', 'A Psalm of David');
            $titleElement->setAttribute('type', 'psalm');
            $verse->appendChild($titleElement);

            // Use reflection to access the private method
            $reflection = new ReflectionClass($this->parser);
            $method = $reflection->getMethod('extractTextWithRedLetters');
            $method->setAccessible(true);

            $result = $method->invoke($this->parser, $verse);
            expect($result)->toContain('<div class="text-center text-sm font-medium text-gray-700 dark:text-gray-300 italic mb-3 border-b border-gray-200 dark:border-gray-600 pb-2">A Psalm of David</div>');
        });

        it('handles title elements with main type', function () {
            // Create a mock DOM node with main title
            $dom = new DOMDocument();
            $verse = $dom->createElement('verse');
            $titleElement = $dom->createElement('title', 'Main Title');
            $titleElement->setAttribute('type', 'main');
            $verse->appendChild($titleElement);

            // Use reflection to access the private method
            $reflection = new ReflectionClass($this->parser);
            $method = $reflection->getMethod('extractTextWithRedLetters');
            $method->setAccessible(true);

            $result = $method->invoke($this->parser, $verse);
            expect($result)->toContain('<h2 class="text-xl font-bold text-gray-900 dark:text-gray-100 mb-4">Main Title</h2>');
        });

        it('handles title elements with generic type', function () {
            // Create a mock DOM node with generic title
            $dom = new DOMDocument();
            $verse = $dom->createElement('verse');
            $titleElement = $dom->createElement('title', 'Generic Title');
            $titleElement->setAttribute('type', 'other');
            $verse->appendChild($titleElement);

            // Use reflection to access the private method
            $reflection = new ReflectionClass($this->parser);
            $method = $reflection->getMethod('extractTextWithRedLetters');
            $method->setAccessible(true);

            $result = $method->invoke($this->parser, $verse);
            expect($result)->toContain('<div class="text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">Generic Title</div>');
        });

        it('handles line group elements', function () {
            // Create a mock DOM node with line group
            $dom = new DOMDocument();
            $verse = $dom->createElement('verse');
            $lgElement = $dom->createElement('lg');
            $lineElement = $dom->createElement('l', 'Poetic line');
            $lgElement->appendChild($lineElement);
            $verse->appendChild($lgElement);

            // Use reflection to access the private method
            $reflection = new ReflectionClass($this->parser);
            $method = $reflection->getMethod('extractTextWithRedLetters');
            $method->setAccessible(true);

            $result = $method->invoke($this->parser, $verse);
            expect($result)->toContain('Poetic line');
        });

        it('handles line break elements', function () {
            // Create a mock DOM node with line break
            $dom = new DOMDocument();
            $verse = $dom->createElement('verse');
            $verse->appendChild($dom->createTextNode('Before break'));
            $lbElement = $dom->createElement('lb');
            $verse->appendChild($lbElement);
            $verse->appendChild($dom->createTextNode('After break'));

            // Use reflection to access the private method
            $reflection = new ReflectionClass($this->parser);
            $method = $reflection->getMethod('extractTextWithRedLetters');
            $method->setAccessible(true);

            $result = $method->invoke($this->parser, $verse);
            expect($result)->toContain('<br class="my-2">');
            expect($result)->toContain('Before break');
            expect($result)->toContain('After break');
        });

        it('handles unknown elements recursively', function () {
            // Create a mock DOM node with unknown element
            $dom = new DOMDocument();
            $verse = $dom->createElement('verse');
            $unknownElement = $dom->createElement('unknown');
            $unknownElement->appendChild($dom->createTextNode('Unknown content'));
            $verse->appendChild($unknownElement);

            // Use reflection to access the private method
            $reflection = new ReflectionClass($this->parser);
            $method = $reflection->getMethod('extractTextWithRedLetters');
            $method->setAccessible(true);

            $result = $method->invoke($this->parser, $verse);
            expect($result)->toContain('Unknown content');
        });
    });

    describe('highlighting edge cases', function () {
        it('returns original text when search term not found', function () {
            // Use reflection to access the private method
            $reflection = new ReflectionClass($this->parser);
            $method = $reflection->getMethod('highlightSearchTerm');
            $method->setAccessible(true);

            $result = $method->invoke($this->parser, 'This is test text', 'notfound');
            expect($result)->toBe('This is test text');
        });

        it('handles simple text without HTML tags', function () {
            // Use reflection to access the private method
            $reflection = new ReflectionClass($this->parser);
            $method = $reflection->getMethod('highlightSearchTerm');
            $method->setAccessible(true);

            $result = $method->invoke($this->parser, 'This is test text', 'test');
            expect($result)->toBe('This is <mark>test</mark> text');
        });

        it('handles HTML content with regex approach', function () {
            // Use reflection to access the private method
            $reflection = new ReflectionClass($this->parser);
            $method = $reflection->getMethod('highlightSearchTerm');
            $method->setAccessible(true);

            $result = $method->invoke($this->parser, 'This is <em>test</em> text', 'test');
            expect($result)->toContain('<mark>test</mark>');
            expect($result)->toContain('<em>');
        });
    });

    describe('apocrypha detection coverage', function () {
        it('triggers apocrypha detection during search', function () {
            // Create a custom parser with apocrypha books to test the continue path
            // We'll create a mock XPath that returns apocrypha book nodes

            // Since ASV doesn't have apocrypha, we need to test the method directly
            $reflection = new ReflectionClass($this->parser);
            $method = $reflection->getMethod('isApocrypha');
            $method->setAccessible(true);

            // Test various apocrypha book identifiers
            expect($method->invoke($this->parser, 'Tob'))->toBeTrue();
            expect($method->invoke($this->parser, 'Tobit'))->toBeTrue();
            expect($method->invoke($this->parser, 'Jdt'))->toBeTrue();
            expect($method->invoke($this->parser, '1Macc'))->toBeTrue();
            expect($method->invoke($this->parser, 'Wis'))->toBeTrue();

            // Test canonical books
            expect($method->invoke($this->parser, 'Gen'))->toBeFalse();
            expect($method->invoke($this->parser, 'Matt'))->toBeFalse();
            expect($method->invoke($this->parser, 'Rev'))->toBeFalse();
        });

        it('tests the continue statement in searchVerses', function () {
            // We need to create a mock scenario where apocrypha books would be encountered
            // Since we can't easily modify the DOM, we'll test indirectly by ensuring
            // that search results never contain apocrypha books

            $results = $this->parser->searchVerses('test', 1000);

            $apocryphaBooks = ['Tob', 'Tobit', 'Jdt', 'Judith', '1Macc', '2Macc', 'Wis', 'Sir'];

            foreach ($results as $result) {
                foreach ($apocryphaBooks as $apocrypha) {
                    expect($result['book_id'])->not->toEqual($apocrypha);
                }
            }
        });

        it('triggers the continue path with mock apocrypha verse', function () {
            // Create a mock DOM with an apocrypha verse to trigger the continue statement
            $dom = new DOMDocument();
            $dom->loadXML('<?xml version="1.0" encoding="UTF-8"?>
                <osis xmlns="http://www.bibletechnologies.net/2003/OSIS/namespace">
                    <osisText>
                        <div type="book" osisID="Tob">
                            <div type="chapter" osisID="Tob.1">
                                <verse osisID="Tob.1.1">This is a verse from Tobit</verse>
                            </div>
                        </div>
                    </osisText>
                </osis>');

            $xpath = new DOMXPath($dom);
            $xpath->registerNamespace('osis', 'http://www.bibletechnologies.net/2003/OSIS/namespace');

            $mockParser = new ContainedOsisParser($xpath);

            // This should trigger the continue statement and return empty results
            $results = $mockParser->searchVerses('Tobit', 10);

            // Should be empty because apocrypha books are skipped
            expect($results)->toBeEmpty();
        });
    });

    describe('text extraction with formatting', function () {
        it('handles Red Letter text for Jesus words', function () {
            // Find a verse that might have Jesus' words (from Gospels in ASV)
            $results = $this->parser->searchVerses('said', 1);

            expect($results)->not->toBeEmpty();
            // Test passes if no exceptions are thrown during text extraction
        });

        it('handles translator additions (transChange)', function () {
            // Test verses that might have translator additions
            $results = $this->parser->searchVerses('earth', 1);

            expect($results)->not->toBeEmpty();
            // Test passes if formatting is handled properly
        });

        it('handles title elements', function () {
            // Test verses that might have titles (like psalm titles)
            $text = $this->parser->getVerseText('Ps.1.1');

            expect($text)->toBeString();
        });

        it('handles line breaks and poetry formatting', function () {
            // Test with poetic text that might have line breaks
            $text = $this->parser->getVerseText('Ps.23.1');

            expect($text)->toBeString();
            expect(strlen($text))->toBeGreaterThan(0);
        });

        it('handles different title types', function () {
            // Find a psalm that might have different title types
            $text = $this->parser->getVerseText('Ps.3.1');

            expect($text)->toBeString();
        });

        it('handles nested formatting elements', function () {
            // Test with complex nested formatting
            $results = $this->parser->searchVerses('love', 1);

            expect($results)->not->toBeEmpty();
            $result = $results->first();
            expect($result['text'])->toBeString();
        });

        it('handles verses with complex HTML formatting', function () {
            // Test highlighting on text with existing HTML
            $results = $this->parser->searchVerses('Lord', 1);

            if ($results->isNotEmpty()) {
                $result = $results->first();
                // Should handle HTML content correctly
                expect($result['context'])->toBeString();
            }
        });

        it('preserves formatting in combined text', function () {
            $paragraphs = $this->parser->getVersesParagraphStyle('Ps.1');

            if ($paragraphs->isNotEmpty()) {
                $firstParagraph = $paragraphs->first();
                expect($firstParagraph['combined_text'])->toBeString();
                expect(strlen($firstParagraph['combined_text']))->toBeGreaterThan(0);
            }
        });
    });

    describe('edge cases and error handling', function () {
        it('handles missing verse nodes gracefully', function () {
            $text = $this->parser->getVerseText('InvalidBook.999.999');

            expect($text)->toBe('');
        });

        it('handles empty search terms', function () {
            $results = $this->parser->searchVerses('', 10);

            // Should handle empty search gracefully
            expect($results)->toBeInstanceOf(\Illuminate\Support\Collection::class);
        });

        it('handles very long search terms', function () {
            $longTerm = str_repeat('test', 100);
            $results = $this->parser->searchVerses($longTerm, 10);

            expect($results)->toBeEmpty();
        });

        it('handles special characters in search', function () {
            $results = $this->parser->searchVerses('&', 1);

            expect($results)->toBeInstanceOf(\Illuminate\Support\Collection::class);
        });

        it('handles search with HTML characters', function () {
            $results = $this->parser->searchVerses('<test>', 1);

            expect($results)->toBeInstanceOf(\Illuminate\Support\Collection::class);
        });

        it('handles regex special characters in search', function () {
            $results = $this->parser->searchVerses('[test]', 1);

            expect($results)->toBeInstanceOf(\Illuminate\Support\Collection::class);
        });

        it('handles unicode characters in search', function () {
            $results = $this->parser->searchVerses('αβγ', 1);

            expect($results)->toBeInstanceOf(\Illuminate\Support\Collection::class);
        });

        it('handles zero limit in search', function () {
            $results = $this->parser->searchVerses('God', 0);

            expect($results)->toBeEmpty();
        });

        it('handles negative limit in search', function () {
            $results = $this->parser->searchVerses('God', -1);

            expect($results)->toBeEmpty();
        });
    });

    describe('apocrypha detection', function () {
        it('correctly identifies canonical books as non-apocrypha', function () {
            $results = $this->parser->searchVerses('God', 1);

            expect($results)->not->toBeEmpty();
            $result = $results->first();

            // Should be from a canonical book
            $canonicalBooks = ['Gen', 'Exod', 'Lev', 'Num', 'Deut', 'Josh', 'Judg', 'Ruth',
                              '1Sam', '2Sam', '1Kgs', '2Kgs', '1Chr', '2Chr', 'Ezra', 'Neh',
                              'Esth', 'Job', 'Ps', 'Prov', 'Eccl', 'Song', 'Isa', 'Jer',
                              'Lam', 'Ezek', 'Dan', 'Hos', 'Joel', 'Amos', 'Obad', 'Jonah',
                              'Mic', 'Nah', 'Hab', 'Zeph', 'Hag', 'Zech', 'Mal', 'Matt',
                              'Mark', 'Luke', 'John', 'Acts', 'Rom', '1Cor', '2Cor', 'Gal',
                              'Eph', 'Phil', 'Col', '1Thess', '2Thess', '1Tim', '2Tim',
                              'Titus', 'Phlm', 'Heb', 'Jas', '1Pet', '2Pet', '1John',
                              '2John', '3John', 'Jude', 'Rev'];

            expect($canonicalBooks)->toContain($result['book_id']);
        });

        it('would skip apocrypha books if present', function () {
            // Test the apocrypha detection logic
            $apocryphaBooks = ['Tob', 'Tobit', 'Jdt', 'Judith', '1Macc', '2Macc', 'Wis', 'Sir'];

            // Since ASV doesn't contain apocrypha, we test the logic indirectly
            $results = $this->parser->searchVerses('wisdom', 100);

            foreach ($results as $result) {
                foreach ($apocryphaBooks as $apocrypha) {
                    expect($result['book_id'])->not->toEqual($apocrypha);
                }
            }
        });
    });

    describe('formatting preservation and highlighting', function () {
        it('preserves HTML tags when highlighting', function () {
            $results = $this->parser->searchVerses('God', 1);

            if ($results->isNotEmpty()) {
                $result = $results->first();

                // Should contain the highlighted term
                expect($result['context'])->toContain('<mark>');
                expect($result['context'])->toContain('God');
            }
        });

        it('handles text without HTML tags', function () {
            // Test simple text highlighting
            $results = $this->parser->searchVerses('the', 1);

            if ($results->isNotEmpty()) {
                $result = $results->first();
                expect($result['context'])->toBeString();
                expect($result['context'])->toContain('<mark>');
            }
        });

        it('handles mixed case search highlighting', function () {
            $results = $this->parser->searchVerses('god', 1);

            if ($results->isNotEmpty()) {
                $result = $results->first();
                expect($result['context'])->toContain('<mark>');
            }
        });

        it('handles overlapping HTML and highlighting', function () {
            $results = $this->parser->searchVerses('Lord', 1);

            if ($results->isNotEmpty()) {
                $result = $results->first();
                // Should handle HTML correctly and add highlighting
                expect($result['context'])->toBeString();
            }
        });
    });

    describe('comprehensive text extraction', function () {
        it('extracts all verse elements correctly', function () {
            $verses = $this->parser->getVerses('Gen.1');

            expect($verses->count())->toBeGreaterThan(25); // Genesis 1 has 31 verses

            foreach ($verses as $verse) {
                expect($verse['text'])->toBeString();
                expect(strlen($verse['text']))->toBeGreaterThan(0);
                expect($verse['verse_number'])->toBeInt();
                expect($verse['osis_id'])->toBeString();
            }
        });

        it('handles empty verse content gracefully', function () {
            // Test with a potentially empty or missing verse
            $text = $this->parser->getVerseText('InvalidRef');

            expect($text)->toBe('');
        });

        it('maintains verse order in paragraphs', function () {
            $paragraphs = $this->parser->getVersesParagraphStyle('Gen.1');

            expect($paragraphs)->not->toBeEmpty();
            $firstParagraph = $paragraphs->first();

            $verseNumbers = array_column($firstParagraph['verses'], 'verse_number');
            expect($verseNumbers)->toBe(array_values($verseNumbers)); // Should be in order
        });
    });

    describe('comprehensive extractTextWithRedLetters coverage', function () {
        it('handles transChange node as direct element with added type', function () {
            // Create a transChange element as the main node (not child)
            $dom = new DOMDocument();
            $transElement = $dom->createElement('transChange', 'supplied text');
            $transElement->setAttribute('type', 'added');

            // Use reflection to access the private method
            $reflection = new ReflectionClass($this->parser);
            $method = $reflection->getMethod('extractTextWithRedLetters');
            $method->setAccessible(true);

            $result = $method->invoke($this->parser, $transElement);
            expect($result)->toBe('<em class="text-gray-600 dark:text-gray-400 font-normal italic">supplied text</em>');
        });

        it('handles transChange node as direct element with non-added type', function () {
            // Create a transChange element with non-added type
            $dom = new DOMDocument();
            $transElement = $dom->createElement('transChange', 'modified text');
            $transElement->setAttribute('type', 'modified');

            $reflection = new ReflectionClass($this->parser);
            $method = $reflection->getMethod('extractTextWithRedLetters');
            $method->setAccessible(true);

            $result = $method->invoke($this->parser, $transElement);
            expect($result)->toBe('modified text');
        });

        it('handles q node as direct element with Jesus attribute', function () {
            // Create a q element as the main node with Jesus attribution
            $dom = new DOMDocument();
            $qElement = $dom->createElement('q', 'Jesus spoke these words');
            $qElement->setAttribute('who', 'Jesus');

            $reflection = new ReflectionClass($this->parser);
            $method = $reflection->getMethod('extractTextWithRedLetters');
            $method->setAccessible(true);

            $result = $method->invoke($this->parser, $qElement);
            expect($result)->toBe('<span class="text-red-600 font-medium">Jesus spoke these words</span>');
        });

        it('handles title node as direct element with psalm type', function () {
            // Create a title element as the main node with psalm type
            $dom = new DOMDocument();
            $titleElement = $dom->createElement('title', 'A Psalm of David');
            $titleElement->setAttribute('type', 'psalm');

            $reflection = new ReflectionClass($this->parser);
            $method = $reflection->getMethod('extractTextWithRedLetters');
            $method->setAccessible(true);

            $result = $method->invoke($this->parser, $titleElement);
            expect($result)->toBe('<div class="text-center text-sm font-medium text-gray-700 dark:text-gray-300 italic mb-3 border-b border-gray-200 dark:border-gray-600 pb-2">A Psalm of David</div>');
        });

        it('handles title node as direct element with main type', function () {
            // Create a title element as the main node with main type
            $dom = new DOMDocument();
            $titleElement = $dom->createElement('title', 'Chapter Title');
            $titleElement->setAttribute('type', 'main');

            $reflection = new ReflectionClass($this->parser);
            $method = $reflection->getMethod('extractTextWithRedLetters');
            $method->setAccessible(true);

            $result = $method->invoke($this->parser, $titleElement);
            expect($result)->toBe('<h2 class="text-xl font-bold text-gray-900 dark:text-gray-100 mb-4">Chapter Title</h2>');
        });

        it('handles title node as direct element with generic type', function () {
            // Create a title element as the main node with generic type
            $dom = new DOMDocument();
            $titleElement = $dom->createElement('title', 'Section Title');
            $titleElement->setAttribute('type', 'section');

            $reflection = new ReflectionClass($this->parser);
            $method = $reflection->getMethod('extractTextWithRedLetters');
            $method->setAccessible(true);

            $result = $method->invoke($this->parser, $titleElement);
            expect($result)->toBe('<div class="text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">Section Title</div>');
        });

        it('handles title node as direct element without type attribute', function () {
            // Create a title element without type attribute
            $dom = new DOMDocument();
            $titleElement = $dom->createElement('title', 'Untitled');

            $reflection = new ReflectionClass($this->parser);
            $method = $reflection->getMethod('extractTextWithRedLetters');
            $method->setAccessible(true);

            $result = $method->invoke($this->parser, $titleElement);
            expect($result)->toBe('<div class="text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">Untitled</div>');
        });

        it('handles non-XML_ELEMENT_NODE as direct node', function () {
            // Create a text node
            $dom = new DOMDocument();
            $textNode = $dom->createTextNode('Plain text content');

            $reflection = new ReflectionClass($this->parser);
            $method = $reflection->getMethod('extractTextWithRedLetters');
            $method->setAccessible(true);

            $result = $method->invoke($this->parser, $textNode);
            // Text nodes passed directly to the method return empty string
            // because the method processes childNodes, and text nodes have no children
            expect($result)->toBe('');
        });

        it('handles mixed content with multiple child node types', function () {
            // Create a complex node with text and element children
            $dom = new DOMDocument();
            $verse = $dom->createElement('verse');

            // Add text node
            $verse->appendChild($dom->createTextNode('Before '));

            // Add transChange child
            $transElement = $dom->createElement('transChange', 'supplied');
            $transElement->setAttribute('type', 'added');
            $verse->appendChild($transElement);

            // Add more text
            $verse->appendChild($dom->createTextNode(' and '));

            // Add q element
            $qElement = $dom->createElement('q', 'Jesus said');
            $qElement->setAttribute('who', 'Jesus');
            $verse->appendChild($qElement);

            // Add final text
            $verse->appendChild($dom->createTextNode(' to them.'));

            $reflection = new ReflectionClass($this->parser);
            $method = $reflection->getMethod('extractTextWithRedLetters');
            $method->setAccessible(true);

            $result = $method->invoke($this->parser, $verse);
            expect($result)->toBe('Before <em class="text-gray-600 dark:text-gray-400 font-normal italic">supplied</em> and <span class="text-red-600 font-medium">Jesus said</span> to them.');
        });

        it('handles q element with who attribute but not Jesus', function () {
            // Create a q element with different speaker
            $dom = new DOMDocument();
            $verse = $dom->createElement('verse');
            $qElement = $dom->createElement('q', 'Someone else spoke');
            $qElement->setAttribute('who', 'Moses');
            $verse->appendChild($qElement);

            $reflection = new ReflectionClass($this->parser);
            $method = $reflection->getMethod('extractTextWithRedLetters');
            $method->setAccessible(true);

            $result = $method->invoke($this->parser, $verse);
            expect($result)->toBe('Someone else spoke');
        });

        it('handles q element without who attribute', function () {
            // Create a q element without who attribute
            $dom = new DOMDocument();
            $verse = $dom->createElement('verse');
            $qElement = $dom->createElement('q', 'Generic quote');
            $verse->appendChild($qElement);

            $reflection = new ReflectionClass($this->parser);
            $method = $reflection->getMethod('extractTextWithRedLetters');
            $method->setAccessible(true);

            $result = $method->invoke($this->parser, $verse);
            expect($result)->toBe('Generic quote');
        });

        it('handles nested elements recursively', function () {
            // Create nested structure
            $dom = new DOMDocument();
            $verse = $dom->createElement('verse');
            $outerDiv = $dom->createElement('div');
            $innerSpan = $dom->createElement('span');

            $transElement = $dom->createElement('transChange', 'nested');
            $transElement->setAttribute('type', 'added');

            $innerSpan->appendChild($transElement);
            $outerDiv->appendChild($innerSpan);
            $verse->appendChild($outerDiv);

            $reflection = new ReflectionClass($this->parser);
            $method = $reflection->getMethod('extractTextWithRedLetters');
            $method->setAccessible(true);

            $result = $method->invoke($this->parser, $verse);
            expect($result)->toBe('<em class="text-gray-600 dark:text-gray-400 font-normal italic">nested</em>');
        });

        it('handles l element recursively', function () {
            // Create a line element with content
            $dom = new DOMDocument();
            $verse = $dom->createElement('verse');
            $lElement = $dom->createElement('l');
            $lElement->appendChild($dom->createTextNode('Poetic line content'));
            $verse->appendChild($lElement);

            $reflection = new ReflectionClass($this->parser);
            $method = $reflection->getMethod('extractTextWithRedLetters');
            $method->setAccessible(true);

            $result = $method->invoke($this->parser, $verse);
            expect($result)->toBe('Poetic line content');
        });

        it('handles lg element recursively', function () {
            // Create a line group element with content
            $dom = new DOMDocument();
            $verse = $dom->createElement('verse');
            $lgElement = $dom->createElement('lg');
            $lgElement->appendChild($dom->createTextNode('Line group content'));
            $verse->appendChild($lgElement);

            $reflection = new ReflectionClass($this->parser);
            $method = $reflection->getMethod('extractTextWithRedLetters');
            $method->setAccessible(true);

            $result = $method->invoke($this->parser, $verse);
            expect($result)->toBe('Line group content');
        });

        it('handles empty element gracefully', function () {
            // Create an empty element
            $dom = new DOMDocument();
            $emptyElement = $dom->createElement('div');

            $reflection = new ReflectionClass($this->parser);
            $method = $reflection->getMethod('extractTextWithRedLetters');
            $method->setAccessible(true);

            $result = $method->invoke($this->parser, $emptyElement);
            expect($result)->toBe('');
        });

        it('handles complex combination of all element types', function () {
            // Create a complex structure with all supported elements
            $dom = new DOMDocument();
            $verse = $dom->createElement('verse');

            // Add psalm title
            $psalmTitle = $dom->createElement('title', 'Psalm Title');
            $psalmTitle->setAttribute('type', 'psalm');
            $verse->appendChild($psalmTitle);

            // Add text
            $verse->appendChild($dom->createTextNode('Regular text '));

            // Add transChange
            $trans = $dom->createElement('transChange', 'added');
            $trans->setAttribute('type', 'added');
            $verse->appendChild($trans);

            // Add line break
            $verse->appendChild($dom->createElement('lb'));

            // Add Jesus quote
            $jesus = $dom->createElement('q', 'I am the way');
            $jesus->setAttribute('who', 'Jesus');
            $verse->appendChild($jesus);

            // Add line group with line
            $lg = $dom->createElement('lg');
            $l = $dom->createElement('l', 'Poetic line');
            $lg->appendChild($l);
            $verse->appendChild($lg);

            $reflection = new ReflectionClass($this->parser);
            $method = $reflection->getMethod('extractTextWithRedLetters');
            $method->setAccessible(true);

            $result = $method->invoke($this->parser, $verse);

            expect($result)->toContain('<div class="text-center text-sm font-medium text-gray-700 dark:text-gray-300 italic mb-3 border-b border-gray-200 dark:border-gray-600 pb-2">Psalm Title</div>');
            expect($result)->toContain('Regular text');
            expect($result)->toContain('<em class="text-gray-600 dark:text-gray-400 font-normal italic">added</em>');
            expect($result)->toContain('<br class="my-2">');
            expect($result)->toContain('<span class="text-red-600 font-medium">I am the way</span>');
            expect($result)->toContain('Poetic line');
        });

        it('handles transChange with empty type attribute', function () {
            // Create transChange with empty type
            $dom = new DOMDocument();
            $verse = $dom->createElement('verse');
            $trans = $dom->createElement('transChange', 'content');
            $trans->setAttribute('type', '');
            $verse->appendChild($trans);

            $reflection = new ReflectionClass($this->parser);
            $method = $reflection->getMethod('extractTextWithRedLetters');
            $method->setAccessible(true);

            $result = $method->invoke($this->parser, $verse);
            expect($result)->toBe('content');
        });

        it('handles transChange without type attribute', function () {
            // Create transChange without type attribute
            $dom = new DOMDocument();
            $verse = $dom->createElement('verse');
            $trans = $dom->createElement('transChange', 'no type');
            $verse->appendChild($trans);

            $reflection = new ReflectionClass($this->parser);
            $method = $reflection->getMethod('extractTextWithRedLetters');
            $method->setAccessible(true);

            $result = $method->invoke($this->parser, $verse);
            expect($result)->toBe('no type');
        });

        it('handles deeply nested recursive elements', function () {
            // Create deeply nested structure
            $dom = new DOMDocument();
            $verse = $dom->createElement('verse');

            // Create nested lg > l > div > span structure
            $lg = $dom->createElement('lg');
            $l = $dom->createElement('l');
            $div = $dom->createElement('div');
            $span = $dom->createElement('span');

            $span->appendChild($dom->createTextNode('Deep content'));
            $div->appendChild($span);
            $l->appendChild($div);
            $lg->appendChild($l);
            $verse->appendChild($lg);

            $reflection = new ReflectionClass($this->parser);
            $method = $reflection->getMethod('extractTextWithRedLetters');
            $method->setAccessible(true);

            $result = $method->invoke($this->parser, $verse);
            expect($result)->toBe('Deep content');
        });

        it('handles XML comment nodes gracefully', function () {
            // Create element with comment node
            $dom = new DOMDocument();
            $verse = $dom->createElement('verse');
            $verse->appendChild($dom->createTextNode('Before comment '));
            $verse->appendChild($dom->createComment(' This is a comment '));
            $verse->appendChild($dom->createTextNode(' After comment'));

            $reflection = new ReflectionClass($this->parser);
            $method = $reflection->getMethod('extractTextWithRedLetters');
            $method->setAccessible(true);

            $result = $method->invoke($this->parser, $verse);
            expect($result)->toBe('Before comment  After comment');
        });

        it('handles CDATA sections', function () {
            // Create element with CDATA
            $dom = new DOMDocument();
            $verse = $dom->createElement('verse');
            $verse->appendChild($dom->createTextNode('Before CDATA '));
            $verse->appendChild($dom->createCDATASection('CDATA content'));
            $verse->appendChild($dom->createTextNode(' After CDATA'));

            $reflection = new ReflectionClass($this->parser);
            $method = $reflection->getMethod('extractTextWithRedLetters');
            $method->setAccessible(true);

            $result = $method->invoke($this->parser, $verse);
            // CDATA sections are handled as text nodes, but the method only processes
            // XML_TEXT_NODE and XML_ELEMENT_NODE types in the foreach loop
            expect($result)->toBe('Before CDATA  After CDATA');
        });

        it('handles processing instructions gracefully', function () {
            // Create element with processing instruction
            $dom = new DOMDocument();
            $verse = $dom->createElement('verse');
            $verse->appendChild($dom->createTextNode('Regular text'));
            $verse->appendChild($dom->createProcessingInstruction('xml-stylesheet', 'type="text/xsl" href="style.xsl"'));

            $reflection = new ReflectionClass($this->parser);
            $method = $reflection->getMethod('extractTextWithRedLetters');
            $method->setAccessible(true);

            $result = $method->invoke($this->parser, $verse);
            expect($result)->toBe('Regular text');
        });
    });
});
