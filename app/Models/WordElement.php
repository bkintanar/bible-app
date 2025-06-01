<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class WordElement extends Model
{
    use HasFactory;

    protected $fillable = [
        'verse_id',
        'word_text',
        'strongs_number',
        'morphology_code',
        'lemma',
        'position_start',
        'position_end',
        'word_order',
        'attributes',
    ];

    protected $casts = [
        'attributes' => 'array',
    ];

    /**
     * Get the verse this word belongs to
     */
    public function verse(): BelongsTo
    {
        return $this->belongsTo(Verse::class);
    }

    /**
     * Get the Strong's lexicon entry for this word
     */
    public function strongsLexicon(): BelongsTo
    {
        return $this->belongsTo(StrongsLexicon::class, 'strongs_number', 'strongs_number');
    }

    /**
     * Check if this word has Strong's data
     */
    public function hasStrongsData(): bool
    {
        return !empty($this->strongs_number);
    }

    /**
     * Get parsed lemma as array
     */
    public function getParsedLemmaAttribute(): array
    {
        if (empty($this->lemma)) {
            return [];
        }

        // Parse multiple Strong's references from lemma
        preg_match_all('/strong:([HG]\d+)/', $this->lemma, $matches);
        return $matches[1] ?? [];
    }

    /**
     * Get morphology information
     */
    public function getMorphologyInfoAttribute(): ?array
    {
        if (empty($this->morphology_code)) {
            return null;
        }

        // Parse strongMorph format
        if (str_contains($this->morphology_code, 'strongMorph:')) {
            $code = str_replace('strongMorph:', '', $this->morphology_code);
            return $this->parseMorphologyCode($code);
        }

        return null;
    }

    /**
     * Parse morphology code
     */
    private function parseMorphologyCode(string $code): array
    {
        // Basic Hebrew morphology parsing
        if (str_starts_with($code, 'TH')) {
            return [
                'language' => 'Hebrew',
                'code' => $code,
                'description' => $this->getHebrewMorphologyDescription($code)
            ];
        }

        // Basic Greek morphology parsing
        if (str_starts_with($code, 'TG')) {
            return [
                'language' => 'Greek',
                'code' => $code,
                'description' => $this->getGreekMorphologyDescription($code)
            ];
        }

        return [
            'language' => 'Unknown',
            'code' => $code,
            'description' => 'Unknown morphology'
        ];
    }

    /**
     * Get Hebrew morphology description
     */
    private function getHebrewMorphologyDescription(string $code): string
    {
        // Simplified Hebrew morphology
        $descriptions = [
            'TH8799' => 'Qal Perfect',
            'TH8804' => 'Qal Perfect 3rd person',
            'TH8686' => 'Hiphil Imperfect',
            'TH8764' => 'Piel Participle',
            // Add more as needed
        ];

        return $descriptions[$code] ?? "Hebrew: {$code}";
    }

    /**
     * Get Greek morphology description
     */
    private function getGreekMorphologyDescription(string $code): string
    {
        // Simplified Greek morphology
        $descriptions = [
            'TG5719' => 'Present Active Indicative',
            'TG5625' => 'Genitive Singular',
            'TG5707' => 'Present Active Participle',
            // Add more as needed
        ];

        return $descriptions[$code] ?? "Greek: {$code}";
    }
}
