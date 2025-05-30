<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Chapter extends Model
{
    use HasFactory;

    protected $fillable = [
        'book_id',
        'chapter_number',
        'osis_ref',
        'verse_count'
    ];

    protected $casts = [
        'book_id' => 'integer',
        'chapter_number' => 'integer',
        'verse_count' => 'integer'
    ];

    public function book()
    {
        return $this->belongsTo(Book::class);
    }

    public function verses()
    {
        return $this->hasMany(Verse::class);
    }
}
