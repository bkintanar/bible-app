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
        'number',
        'osis_id',
        'se_id',
        'verse_count',
        'canonical',
    ];

    protected $casts = [
        'canonical' => 'boolean',
    ];

    /**
     * Get the book this chapter belongs to
     */
    public function book(): BelongsTo
    {
        return $this->belongsTo(Book::class);
    }

    /**
     * Get the verses in this chapter
     */
    public function verses(): HasMany
    {
        return $this->hasMany(Verse::class);
    }
}
