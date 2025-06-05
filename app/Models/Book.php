<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class Book extends Model
{
    use HasFactory;

    protected $fillable = [
        'osis_id',
        'book_group_id',
        'number',
        'name',
        'full_title',
        'short_name',
        'canonical',
        'sort_order',
    ];

    protected $casts = [
        'canonical' => 'boolean',
        'number' => 'integer',
        'sort_order' => 'integer',
    ];

    /**
     * Get the book group this book belongs to
     */
    public function bookGroup(): BelongsTo
    {
        return $this->belongsTo(BookGroup::class);
    }

    /**
     * Get the chapters in this book
     */
    public function chapters(): HasMany
    {
        return $this->hasMany(Chapter::class)->orderBy('chapter_number');
    }

    /**
     * Get all verses in this book through chapters
     */
    public function verses()
    {
        return $this->hasManyThrough(Verse::class, Chapter::class);
    }

    /**
     * Scope to get only canonical books
     * @param mixed $query
     */
    public function scopeCanonical($query)
    {
        return $query->where('canonical', true);
    }

    /**
     * Scope to order by book order
     * @param mixed $query
     */
    public function scopeOrdered($query)
    {
        return $query->orderBy('sort_order');
    }
}
