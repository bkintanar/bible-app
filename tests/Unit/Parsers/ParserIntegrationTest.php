<?php

use App\Services\Parsers\MilestoneOsisParser;
use App\Services\Parsers\ContainedOsisParser;

describe('Parser Integration Tests', function () {
    beforeEach(function () {
        // Load real OSIS files for integration testing
        $kjvDom = new DOMDocument();
        $kjvDom->load(base_path('assets/kjv.osis.xml'));
        $kjvXpath = new DOMXPath($kjvDom);
        $kjvXpath->registerNamespace('osis', 'http://www.bibletechnologies.net/2003/OSIS/namespace');
        $this->milestoneParser = new MilestoneOsisParser($kjvXpath);

        $asvDom = new DOMDocument();
        $asvDom->load(base_path('assets/asv.osis.xml'));
        $asvXpath = new DOMXPath($asvDom);
        $asvXpath->registerNamespace('osis', 'http://www.bibletechnologies.net/2003/OSIS/namespace');
        $this->containedParser = new ContainedOsisParser($asvXpath);
    });

    describe('essential parser functionality', function () {
        it('loads and parses different OSIS formats', function () {
            // Test both parsers with minimal operations
            $milestoneChapters = $this->milestoneParser->getChapters('Gen');
            $containedChapters = $this->containedParser->getChapters('Gen');

            expect($milestoneChapters->count())->toBe(50);
            expect($containedChapters->count())->toBe(50);
        });

        it('extracts verses correctly from both formats', function () {
            // Test verse extraction for both parsers
            $milestoneVerses = $this->milestoneParser->getVerses('Gen.1');
            $containedVerses = $this->containedParser->getVerses('Gen.1');

            expect($milestoneVerses->count())->toBe(31);
            expect($containedVerses->count())->toBe(31);

            // Verify content
            expect($milestoneVerses->first()['text'])->toContain('God created');
            expect($containedVerses->first()['text'])->toContain('God created');
        });

        it('performs fast text search on both formats', function () {
            // Use "God" for immediate match in Genesis 1:1
            $milestoneResults = $this->milestoneParser->searchVerses('God', 2);
            $containedResults = $this->containedParser->searchVerses('God', 2);

            expect($milestoneResults->count())->toBe(2);
            expect($containedResults->count())->toBe(2);

            // Verify search highlighting
            expect($milestoneResults->first()['context'])->toContain('<mark>God</mark>');
            expect($containedResults->first()['context'])->toContain('<mark>God</mark>');
        });

        it('handles error cases gracefully', function () {
            // Test error handling for both parsers
            $milestoneEmpty = $this->milestoneParser->getVerses('InvalidBook.1');
            $containedEmpty = $this->containedParser->getVerses('InvalidBook.1');

            expect($milestoneEmpty)->toBeEmpty();
            expect($containedEmpty)->toBeEmpty();
        });
    });
});
