<?php

namespace App\Console\Commands;

use App\Models\StrongsLexicon;
use App\Models\WordRelationship;
use App\Models\WordElement;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class SeedStrongsLexicon extends Command
{
    protected $signature = 'strongs:seed
                           {--count=100 : Number of entries to seed}
                           {--with-relationships : Include word relationships}';

    protected $description = 'Seed the Strong\'s lexicon with sample data';

    public function handle()
    {
        $count = (int) $this->option('count');
        $withRelationships = $this->option('with-relationships');

        $this->info("Seeding {$count} Strong's lexicon entries...");

        // Get existing Strong's numbers from word_elements
        $existingStrongs = WordElement::whereNotNull('strongs_number')
            ->select('strongs_number')
            ->groupBy('strongs_number')
            ->orderBy('strongs_number')
            ->limit($count)
            ->pluck('strongs_number')
            ->toArray();

        if (empty($existingStrongs)) {
            $this->error('No Strong\'s numbers found in word_elements table. Import OSIS data first.');
            return 1;
        }

        $this->info('Found ' . count($existingStrongs) . ' existing Strong\'s numbers');

        $progressBar = $this->output->createProgressBar(count($existingStrongs));
        $progressBar->start();

        $seeded = 0;
        foreach ($existingStrongs as $strongsNumber) {
            if ($this->seedLexiconEntry($strongsNumber)) {
                $seeded++;
            }
            $progressBar->advance();
        }

        $progressBar->finish();
        $this->newLine();

        $this->info("Seeded {$seeded} lexicon entries");

        // Update occurrence counts
        $this->info('Updating occurrence counts...');
        $this->updateOccurrenceCounts();

        // Seed relationships if requested
        if ($withRelationships) {
            $this->info('Seeding word relationships...');
            $this->seedRelationships();
        }

        $this->info('✅ Strong\'s lexicon seeding completed!');
        return 0;
    }

    private function seedLexiconEntry(string $strongsNumber): bool
    {
        // Skip if already exists
        if (StrongsLexicon::where('strongs_number', $strongsNumber)->exists()) {
            return false;
        }

        $isHebrew = str_starts_with($strongsNumber, 'H');
        $isGreek = str_starts_with($strongsNumber, 'G');

        // Get sample data from predefined entries
        $sampleData = $this->getSampleData($strongsNumber, $isHebrew, $isGreek);

        if (!$sampleData) {
            // Generate basic entry for unknown numbers
            $sampleData = $this->generateBasicEntry($strongsNumber, $isHebrew, $isGreek);
        }

        StrongsLexicon::create($sampleData);
        return true;
    }

    private function getSampleData(string $strongsNumber, bool $isHebrew, bool $isGreek): ?array
    {
        // Popular Hebrew words
        $hebrewSamples = [
            'H0430' => [
                'original_word' => 'אֱלֹהִים',
                'transliteration' => 'elohim',
                'pronunciation' => 'el-o-HEEM',
                'short_definition' => 'God, gods',
                'detailed_definition' => 'rulers, judges, divine ones, angels, gods, god, goddess, godlike one, works or special possessions of God',
                'outline_definition' => 'Supreme God, mighty ones in authority, supernatural beings',
                'part_of_speech' => 'noun',
                'etymology' => 'plural of H433; gods in the ordinary sense; but specifically used (in the plural thus, especially with the article) of the supreme God'
            ],
            'H03068' => [
                'original_word' => 'יְהוָה',
                'transliteration' => 'Yehovah',
                'pronunciation' => 'yeh-ho-VAW',
                'short_definition' => 'LORD, Jehovah',
                'detailed_definition' => 'the proper name of the one true God, YHWH, Yahweh, Jehovah',
                'outline_definition' => 'The sacred name of God, the covenant name revealed to Moses',
                'part_of_speech' => 'proper noun',
                'etymology' => 'from H1961; (the) self-Existent or Eternal; Jehovah, Jewish national name of God'
            ],
            'H0157' => [
                'original_word' => 'אָהַב',
                'transliteration' => 'ahab',
                'pronunciation' => 'aw-HAB',
                'short_definition' => 'to love',
                'detailed_definition' => 'to have affection for (sexually or otherwise), like, to be loved, lovely, beloved',
                'outline_definition' => 'To love deeply, show affection, have romantic feelings',
                'part_of_speech' => 'verb',
                'etymology' => 'a primitive root; to have affection for (sexually or otherwise)'
            ]
        ];

        // Popular Greek words
        $greekSamples = [
            'G2316' => [
                'original_word' => 'θεός',
                'transliteration' => 'theos',
                'pronunciation' => 'theh-OSS',
                'short_definition' => 'God, deity',
                'detailed_definition' => 'a general name of deities or divinities, spoken of the only and true God',
                'outline_definition' => 'God, the Godhead, trinity',
                'part_of_speech' => 'noun',
                'etymology' => 'of uncertain affinity; a deity, especially (with G3588) the supreme Divinity'
            ],
            'G25' => [
                'original_word' => 'ἀγαπάω',
                'transliteration' => 'agapao',
                'pronunciation' => 'ag-ap-AH-o',
                'short_definition' => 'to love',
                'detailed_definition' => 'to love, to be loved, beloved, to love dearly',
                'outline_definition' => 'Divine love, unconditional love, love as God loves',
                'part_of_speech' => 'verb',
                'etymology' => 'perhaps from agan (much) (or compare H5689); to love (in a social or moral sense)'
            ],
            'G2424' => [
                'original_word' => 'Ἰησοῦς',
                'transliteration' => 'Iesous',
                'pronunciation' => 'ee-ay-SOOCE',
                'short_definition' => 'Jesus',
                'detailed_definition' => 'Jesus = "Jehovah is salvation", the name of our Lord and Saviour',
                'outline_definition' => 'Jesus Christ, the Messiah, Son of God',
                'part_of_speech' => 'proper noun',
                'etymology' => 'of Hebrew origin (H3091); Jesus (i.e. Jehoshua), the name of our Lord'
            ]
        ];

        if ($isHebrew && isset($hebrewSamples[$strongsNumber])) {
            return array_merge($hebrewSamples[$strongsNumber], ['language' => 'Hebrew', 'strongs_number' => $strongsNumber]);
        }

        if ($isGreek && isset($greekSamples[$strongsNumber])) {
            return array_merge($greekSamples[$strongsNumber], ['language' => 'Greek', 'strongs_number' => $strongsNumber]);
        }

        return null;
    }

    private function generateBasicEntry(string $strongsNumber, bool $isHebrew, bool $isGreek): array
    {
        $language = $isHebrew ? 'Hebrew' : ($isGreek ? 'Greek' : 'Unknown');
        $numberPart = preg_replace('/[HG]/', '', $strongsNumber);

        return [
            'strongs_number' => $strongsNumber,
            'language' => $language,
            'original_word' => $isHebrew ? 'עברית' : ($isGreek ? 'ελληνικά' : 'word'),
            'transliteration' => "word_{$numberPart}",
            'pronunciation' => null,
            'short_definition' => "Definition for {$strongsNumber}",
            'detailed_definition' => "Detailed definition for Strong's number {$strongsNumber}",
            'outline_definition' => null,
            'part_of_speech' => null,
            'etymology' => null,
        ];
    }

    private function updateOccurrenceCounts(): void
    {
        $counts = WordElement::whereNotNull('strongs_number')
            ->select('strongs_number', DB::raw('COUNT(*) as count'))
            ->groupBy('strongs_number')
            ->get();

        foreach ($counts as $count) {
            StrongsLexicon::where('strongs_number', $count->strongs_number)
                ->update(['occurrence_count' => $count->count]);
        }

        $this->info('Updated occurrence counts for ' . $counts->count() . ' entries');
    }

    private function seedRelationships(): void
    {
        $relationships = [
            // Love relationships
            ['H0157', 'G25', 'synonym', 'Both mean "to love"', 9],
            ['G25', 'G5368', 'synonym', 'Both express love, agape vs phileo', 7],

            // God relationships
            ['H0430', 'G2316', 'synonym', 'Both refer to God/deity', 10],
            ['H03068', 'H0430', 'variant', 'YHWH is a specific name for Elohim', 8],

            // Word family examples
            ['G26', 'G25', 'derivative', 'agape (noun) from agapao (verb)', 9],
            ['G27', 'G25', 'derivative', 'agapetos (beloved) from agapao', 8],
        ];

        foreach ($relationships as [$source, $target, $type, $desc, $strength]) {
            WordRelationship::updateOrCreate(
                [
                    'source_strongs' => $source,
                    'target_strongs' => $target,
                    'relationship_type' => $type
                ],
                [
                    'description' => $desc,
                    'strength' => $strength
                ]
            );
        }

        $this->info('Seeded ' . count($relationships) . ' word relationships');
    }
}
