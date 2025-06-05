<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class Chapter extends Model
{
    use HasFactory;

    protected $fillable = [
        'book_id',
        'version_id',
        'chapter_number',
        'osis_id',
        'se_id',
        'verse_count',
        'canonical',
    ];

    protected $casts = [
        'canonical' => 'boolean',
        'chapter_number' => 'integer',
        'verse_count' => 'integer',
    ];

    /**
     * Get the book this chapter belongs to
     */
    public function book(): BelongsTo
    {
        return $this->belongsTo(Book::class);
    }

    /**
     * Get the bible version this chapter belongs to
     */
    public function bibleVersion(): BelongsTo
    {
        return $this->belongsTo(BibleVersion::class, 'version_id');
    }

    /**
     * Get the verses in this chapter
     */
    public function verses(): HasMany
    {
        return $this->hasMany(Verse::class)->orderBy('verse_number');
    }

    /**
     * Get the paragraphs in this chapter
     */
    public function paragraphs(): HasMany
    {
        return $this->hasMany(Paragraph::class);
    }
}
