<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class WordRelationship extends Model
{
    use HasFactory;

    protected $fillable = [
        'source_strongs',
        'target_strongs',
        'relationship_type',
        'description',
        'strength',
    ];

    /**
     * Get the source lexicon entry
     */
    public function sourceLexicon(): BelongsTo
    {
        return $this->belongsTo(StrongsLexicon::class, 'source_strongs', 'strongs_number');
    }

    /**
     * Get the target lexicon entry
     */
    public function targetLexicon(): BelongsTo
    {
        return $this->belongsTo(StrongsLexicon::class, 'target_strongs', 'strongs_number');
    }

    /**
     * Relationship type constants
     */
    public const TYPE_SYNONYM = 'synonym';
    public const TYPE_ANTONYM = 'antonym';
    public const TYPE_DERIVATIVE = 'derivative';
    public const TYPE_ROOT = 'root';
    public const TYPE_VARIANT = 'variant';
    public const TYPE_COGNATE = 'cognate';

    /**
     * Get available relationship types
     */
    public static function getRelationshipTypes(): array
    {
        return [
            self::TYPE_SYNONYM => 'Synonym',
            self::TYPE_ANTONYM => 'Antonym',
            self::TYPE_DERIVATIVE => 'Derivative',
            self::TYPE_ROOT => 'Root',
            self::TYPE_VARIANT => 'Variant',
            self::TYPE_COGNATE => 'Cognate',
        ];
    }

    /**
     * Get emoji for relationship type
     */
    public function getTypeEmojiAttribute(): string
    {
        return match($this->relationship_type) {
            self::TYPE_SYNONYM => '🔄',
            self::TYPE_ANTONYM => '↔️',
            self::TYPE_DERIVATIVE => '🌱',
            self::TYPE_ROOT => '🌳',
            self::TYPE_VARIANT => '🔀',
            self::TYPE_COGNATE => '🔗',
            default => '📝'
        };
    }
}
