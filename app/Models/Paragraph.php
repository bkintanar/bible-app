<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class Paragraph extends Model
{
    use HasFactory;

    protected $table = 'paragraphs';

    protected $fillable = [
        'chapter_id',
        'start_verse_id',
        'end_verse_id',
        'paragraph_type',
        'text_content',
    ];

    /**
     * Get the chapter this paragraph belongs to
     */
    public function chapter(): BelongsTo
    {
        return $this->belongsTo(Chapter::class);
    }

    /**
     * Get the starting verse of this paragraph
     */
    public function startVerse(): BelongsTo
    {
        return $this->belongsTo(Verse::class, 'start_verse_id');
    }

    /**
     * Get the ending verse of this paragraph
     */
    public function endVerse(): BelongsTo
    {
        return $this->belongsTo(Verse::class, 'end_verse_id');
    }
}
