<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class FixVerseSpacing extends Command
{
    protected $signature = 'bible:fix-spacing {--chunk=100 : Number of verses to process at once}';
    protected $description = 'Fix spacing in verse text and formatted_text fields by re-processing original_xml';

    public function handle()
    {
        $this->info('🔧 Fixing verse spacing from original XML...');
        $this->newLine();

        $chunkSize = (int) $this->option('chunk');
        $totalVerses = DB::table('verses')->count();

        $this->info("📊 Found {$totalVerses} verses to process");

        $bar = $this->output->createProgressBar($totalVerses);
        $bar->start();

        $processedCount = 0;
        $errorCount = 0;

        DB::table('verses')
            ->select('id', 'original_xml')
            ->whereNotNull('original_xml')
            ->where('original_xml', '!=', '')
            ->orderBy('id')
            ->chunk($chunkSize, function ($verses) use ($bar, &$processedCount, &$errorCount) {
                foreach ($verses as $verse) {
                    try {
                        $fixedTexts = $this->fixVerseText($verse->original_xml);

                        DB::table('verses')
                            ->where('id', $verse->id)
                            ->update([
                                'text' => $fixedTexts['text'],
                                'formatted_text' => $fixedTexts['formatted'],
                                'updated_at' => now(),
                            ]);

                        $processedCount++;
                    } catch (\Exception $e) {
                        $errorCount++;
                        $this->warn("Error processing verse ID {$verse->id}: " . $e->getMessage());
                    }

                    $bar->advance();
                }
            });

        $bar->finish();
        $this->newLine(2);

        // Update FTS tables
        $this->info('🔍 Updating FTS search tables...');
        try {
            DB::statement("INSERT INTO verses_fts(verses_fts) VALUES('rebuild')");
            $this->info('✅ FTS tables updated successfully');
        } catch (\Exception $e) {
            $this->warn('⚠️ FTS update failed: ' . $e->getMessage());
        }

        $this->newLine();
        $this->info('🎉 Spacing fix completed!');
        $this->line("   ✅ Processed: {$processedCount} verses");
        if ($errorCount > 0) {
            $this->line("   ⚠️ Errors: {$errorCount} verses");
        }
    }

    private function fixVerseText($originalXml)
    {
        if (empty($originalXml)) {
            return ['text' => '', 'formatted' => ''];
        }

        // Extract plain text with proper spacing
        $plainText = $this->extractPlainTextWithSpacing($originalXml);

        // Format to HTML with proper spacing
        $formattedText = $this->formatToHTML($originalXml);

        return [
            'text' => trim($plainText),
            'formatted' => trim($formattedText)
        ];
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

    private function formatToHTML($content)
    {
        $html = $content;

        // Basic transformations
        $html = str_replace('<transChange type="added">', '<em>', $html);
        $html = str_replace('</transChange>', '</em>', $html);
        $html = str_replace('<divineName>', '<span class="divine-name">', $html);
        $html = str_replace('</divineName>', '</span>', $html);

        // Remove word markup for display while preserving spacing
        $html = preg_replace('/<w[^>]*>/', '', $html);
        // Add space after </w> if not followed by whitespace or punctuation
        $html = preg_replace('/<\/w>(?![.,:;!?\'")\]\s\-])/', ' ', $html);
        $html = str_replace('</w>', '', $html); // Clean up remaining </w> tags

        // Remove notes for basic display
        $html = preg_replace('/<note[^>]*>.*?<\/note>/s', '', $html);

        // Clean up multiple spaces
        $html = preg_replace('/\s+/', ' ', $html);

        return $html;
    }
}
