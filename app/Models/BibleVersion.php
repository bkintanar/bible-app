<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class BibleVersion extends Model
{
    use HasFactory;

    protected $table = 'bible_versions';

    protected $fillable = [
        'title',
        'abbreviation',
        'description',
        'publisher',
        'language',
        'year',
        'copyright',
    ];

    protected $casts = [
        'year' => 'integer',
    ];

    /**
     * Get all chapters for this version
     */
    public function chapters(): HasMany
    {
        return $this->hasMany(Chapter::class, 'version_id');
    }

    /**
     * Get all verses for this version through chapters
     */
    public function verses(): HasMany
    {
        return $this->hasManyThrough(Verse::class, Chapter::class, 'version_id', 'chapter_id');
    }
}
