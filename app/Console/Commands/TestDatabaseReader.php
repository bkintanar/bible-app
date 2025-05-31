<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Services\DatabaseBibleReader;
use App\Services\BibleService;
use App\Services\TranslationService;

class TestDatabaseReader extends Command
{
    protected $signature = 'bible:test-database-reader';
    protected $description = 'Test the database-based Bible reader functionality';

    public function handle()
    {
        $this->info('🗄️ Testing Database Bible Reader');
        $this->info('================================');
        $this->newLine();

        // Test 1: Create DatabaseBibleReader
        $this->info('1. Creating DatabaseBibleReader...');
        $databaseReader = new DatabaseBibleReader('kjv');

        // Test 2: Get books
        $this->info('2. Getting books...');
        $books = $databaseReader->getBooks();
        $this->info("   Found {$books->count()} books");
        if ($books->count() > 0) {
            $firstBook = $books->first();
            $this->info("   First book: {$firstBook['name']} ({$firstBook['osis_id']})");
        }

        // Test 3: Get chapters
        if ($books->count() > 0) {
            $firstBook = $books->first();
            $this->newLine();
            $this->info("3. Getting chapters for {$firstBook['name']}...");
            $chapters = $databaseReader->getChapters($firstBook['osis_id']);
            $this->info("   Found {$chapters->count()} chapters");
        }

        // Test 4: Get verses
        if (isset($chapters) && $chapters->count() > 0) {
            $firstChapter = $chapters->first();
            $this->newLine();
            $this->info("4. Getting verses for {$firstChapter['osis_ref']}...");
            $verses = $databaseReader->getVerses($firstChapter['osis_ref']);
            $this->info("   Found {$verses->count()} verses");
            if ($verses->count() > 0) {
                $firstVerseText = substr($verses->first()['text'], 0, 50) . '...';
                $this->info("   First verse: {$firstVerseText}");
            }
        }

        // Test 5: BibleService with database reader
        $this->newLine();
        $this->info('5. Testing BibleService...');
        $translationService = new TranslationService();
        $bibleService = new BibleService($translationService);

        $capabilities = $bibleService->getCapabilities();
        $this->info("   Reader type: {$capabilities['reader_type']}");
        $this->info("   Enhanced features: " . (count($capabilities['enhanced']) > 0 ? 'Yes' : 'No'));
        $this->info("   Enhanced features available: " . implode(', ', $capabilities['enhanced']));

        // Test 6: Search functionality
        $this->newLine();
        $this->info('6. Testing search...');
        try {
            $searchResults = $bibleService->searchVerses('love', 5);
            $this->info("   Found {$searchResults->count()} results for 'love'");
            if ($searchResults->count() > 0) {
                $first = $searchResults->first();
                $this->info("   First result: {$first['reference']}");
                $this->info("   Text: " . substr($first['text'], 0, 100) . '...');
            }
        } catch (\Exception $e) {
            $this->error("   Search error: {$e->getMessage()}");
        }

        // Test 7: Enhanced features (if available)
        if ($bibleService->hasEnhancedFeatures()) {
            $this->newLine();
            $this->info('7. Testing enhanced features...');

            // Test Strong's search
            try {
                $strongsResults = $bibleService->searchByStrongsNumber('G2316', 3); // Greek word for "God"
                $this->info("   Strong's G2316 results: {$strongsResults->count()}");
            } catch (\Exception $e) {
                $this->error("   Strong's search error: {$e->getMessage()}");
            }

            // Test verse details
            try {
                $verseDetails = $bibleService->getVerseWithDetails('John.3.16');
                $hasVerse = isset($verseDetails['verse']) ? 'Yes' : 'No';
                $this->info("   John 3:16 details loaded: {$hasVerse}");

                if (isset($verseDetails['strongs_data'])) {
                    $strongsCount = $verseDetails['strongs_data']->count();
                    $this->info("   Strong's data entries: {$strongsCount}");
                }

                if (isset($verseDetails['translator_changes'])) {
                    $changesCount = $verseDetails['translator_changes']->count();
                    $this->info("   Translator changes: {$changesCount}");
                }

                if (isset($verseDetails['divine_names'])) {
                    $divineCount = $verseDetails['divine_names']->count();
                    $this->info("   Divine names: {$divineCount}");
                }

                if (isset($verseDetails['study_notes'])) {
                    $notesCount = $verseDetails['study_notes']->count();
                    $this->info("   Study notes: {$notesCount}");
                }
            } catch (\Exception $e) {
                $this->error("   Verse details error: {$e->getMessage()}");
            }
        }

        $this->newLine();
        $this->info('✅ Database Bible Reader test completed!');

        return 0;
    }
}
