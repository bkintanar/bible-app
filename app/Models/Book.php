<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Book extends Model
{
    use HasFactory;

    protected $fillable = [
        'osis_id',
        'name',
        'short_name',
        'testament',
        'book_order',
        'chapter_count'
    ];

    protected $casts = [
        'book_order' => 'integer',
        'chapter_count' => 'integer'
    ];

    public function chapters()
    {
        return $this->hasMany(Chapter::class);
    }

    public function verses()
    {
        return $this->hasManyThrough(Verse::class, Chapter::class);
    }
}
