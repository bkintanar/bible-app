<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class DivineName extends Model
{
    use HasFactory;

    protected $table = 'divine_names';

    protected $fillable = [
        'verse_id',
        'displayed_text',
        'original_name',
    ];

    /**
     * Get the verse this divine name belongs to
     */
    public function verse(): BelongsTo
    {
        return $this->belongsTo(Verse::class);
    }
}
