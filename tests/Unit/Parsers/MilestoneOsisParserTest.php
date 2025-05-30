<?php

use App\Services\Parsers\MilestoneOsisParser;

describe('MilestoneOsisParser', function () {
    beforeEach(function () {
        $kjv_file_path = config('bible.osis_directory') . '/kjv.osis.xml';

        $dom = new DOMDocument();
        $dom->load($kjv_file_path);

        $xpath = new DOMXPath($dom);
        $xpath->registerNamespace('osis', 'http://www.bibletechnologies.net/2003/OSIS/namespace');

        $this->parser = new MilestoneOsisParser($xpath);
    });

    describe('essential coverage', function () {
        it('verifies parser instantiation and basic methods', function () {
            // Ultra-lightweight test - just verify the parser works and has required methods
            expect($this->parser)->toBeInstanceOf(MilestoneOsisParser::class);

            // Test method existence and basic return types
            expect(method_exists($this->parser, 'getChapters'))->toBeTrue();
            expect(method_exists($this->parser, 'getVerses'))->toBeTrue();
            expect(method_exists($this->parser, 'getVerseText'))->toBeTrue();
            expect(method_exists($this->parser, 'searchVerses'))->toBeTrue();
            expect(method_exists($this->parser, 'getVersesParagraphStyle'))->toBeTrue();

            // Test basic non-existent handling (should be fast)
            expect($this->parser->getChapters('NonExistent'))->toBeInstanceOf(\Illuminate\Support\Collection::class);
            expect($this->parser->getVerses('NonExistent.999'))->toBeInstanceOf(\Illuminate\Support\Collection::class);
            expect($this->parser->getVerseText('invalid'))->toBeString();
        });

        it('performs minimal functionality verification', function () {
            // Absolute minimum functional test - just verify one operation works
            $text = $this->parser->getVerseText('Gen.1.1');
            expect($text)->toBeString();
            expect(strlen($text))->toBeGreaterThan(0);

            // One minimal search test
            $results = $this->parser->searchVerses('beginning', 1);
            expect($results)->toBeInstanceOf(\Illuminate\Support\Collection::class);
        });
    });
});
