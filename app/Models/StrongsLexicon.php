<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class StrongsLexicon extends Model
{
    use HasFactory;

    protected $table = 'strongs_lexicon';

    protected $fillable = [
        'strongs_number',
        'language',
        'original_word',
        'transliteration',
        'pronunciation',
        'short_definition',
        'detailed_definition',
        'outline_definition',
        'part_of_speech',
        'etymology',
        'occurrence_count',
        'related_words',
        'variants',
        'synonyms',
        'antonyms',
        'derived_words',
        'root_words',
    ];

    protected $casts = [
        'related_words' => 'array',
        'variants' => 'array',
        'synonyms' => 'array',
        'antonyms' => 'array',
        'derived_words' => 'array',
        'root_words' => 'array',
    ];

    /**
     * Get word elements using this Strong's number
     */
    public function wordElements(): HasMany
    {
        return $this->hasMany(WordElement::class, 'strongs_number', 'strongs_number');
    }

    /**
     * Get all verses that contain this Strong's number
     */
    public function verses()
    {
        return $this->hasManyThrough(
            Verse::class,
            WordElement::class,
            'strongs_number', // Foreign key on word_elements table
            'id', // Foreign key on verses table
            'strongs_number', // Local key on strongs_lexicon table
            'verse_id' // Local key on word_elements table
        );
    }

    /**
     * Get relationships where this is the source
     */
    public function sourceRelationships(): HasMany
    {
        return $this->hasMany(WordRelationship::class, 'source_strongs', 'strongs_number');
    }

    /**
     * Get relationships where this is the target
     */
    public function targetRelationships(): HasMany
    {
        return $this->hasMany(WordRelationship::class, 'target_strongs', 'strongs_number');
    }

    /**
     * Check if this is a Hebrew word
     */
    public function isHebrew(): bool
    {
        return str_starts_with($this->strongs_number, 'H');
    }

    /**
     * Check if this is a Greek word
     */
    public function isGreek(): bool
    {
        return str_starts_with($this->strongs_number, 'G');
    }

    /**
     * Get all related words by relationship type
     */
    public function getRelatedWords(string $relationshipType = null): array
    {
        $query = $this->sourceRelationships();

        if ($relationshipType) {
            $query->where('relationship_type', $relationshipType);
        }

        return $query->with('targetLexicon')->get()
            ->pluck('targetLexicon')
            ->filter()
            ->toArray();
    }

    /**
     * Get synonyms
     */
    public function getSynonyms(): array
    {
        return $this->getRelatedWords('synonym');
    }

    /**
     * Get antonyms
     */
    public function getAntonyms(): array
    {
        return $this->getRelatedWords('antonym');
    }

    /**
     * Get derivative words
     */
    public function getDerivatives(): array
    {
        return $this->getRelatedWords('derivative');
    }

    /**
     * Get root words
     */
    public function getRoots(): array
    {
        return $this->getRelatedWords('root');
    }

    /**
     * Get variants
     */
    public function getVariants(): array
    {
        return $this->getRelatedWords('variant');
    }

    /**
     * Get formatted Strong's number with link
     */
    public function getFormattedNumberAttribute(): string
    {
        return $this->strongs_number;
    }

    /**
     * Get short language name
     */
    public function getLanguageCodeAttribute(): string
    {
        return match($this->language) {
            'Hebrew' => 'H',
            'Greek' => 'G',
            'Aramaic' => 'A',
            default => substr($this->language, 0, 1)
        };
    }

    /**
     * Get emoji for language
     */
    public function getLanguageEmojiAttribute(): string
    {
        return match($this->language) {
            'Hebrew' => '🇮🇱',
            'Greek' => '🇬🇷',
            'Aramaic' => '📜',
            default => '📖'
        };
    }

    /**
     * Get part of speech emoji
     */
    public function getPartOfSpeechEmojiAttribute(): string
    {
        return match(strtolower($this->part_of_speech ?? '')) {
            'noun' => '📋',
            'verb' => '⚡',
            'adjective' => '🎨',
            'adverb' => '🔄',
            'pronoun' => '👤',
            'preposition' => '🔗',
            'conjunction' => '➕',
            'interjection' => '❗',
            'particle' => '⚪',
            default => '📝'
        };
    }

    /**
     * Search Strong's lexicon
     */
    public static function search(string $query, int $limit = 50): \Illuminate\Database\Eloquent\Collection
    {
        // Use LIKE queries for SQLite compatibility
        return static::where(function ($q) use ($query) {
            $q->where('strongs_number', 'LIKE', "%{$query}%")
                ->orWhere('transliteration', 'LIKE', "%{$query}%")
                ->orWhere('original_word', 'LIKE', "%{$query}%")
                ->orWhere('short_definition', 'LIKE', "%{$query}%")
                ->orWhere('detailed_definition', 'LIKE', "%{$query}%")
                ->orWhere('outline_definition', 'LIKE', "%{$query}%");
        })
            ->orderByRaw('
                CASE
                    WHEN strongs_number LIKE ? THEN 1
                    WHEN transliteration LIKE ? THEN 2
                    WHEN short_definition LIKE ? THEN 3
                    ELSE 4
                END
            ', ["%{$query}%", "%{$query}%", "%{$query}%"])
            ->limit($limit)
            ->get();
    }
}
