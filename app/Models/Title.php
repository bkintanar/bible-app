<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class Title extends Model
{
    use HasFactory;

    protected $table = 'titles';

    protected $fillable = [
        'verse_id',
        'title_type',
        'title_text',
        'canonical',
        'placement',
        'title_order',
    ];

    protected $casts = [
        'canonical' => 'boolean',
        'title_order' => 'integer',
    ];

    /**
     * Get the verse this title belongs to
     */
    public function verse(): BelongsTo
    {
        return $this->belongsTo(Verse::class);
    }
}
