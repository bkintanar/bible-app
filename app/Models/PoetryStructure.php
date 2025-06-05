<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class PoetryStructure extends Model
{
    use HasFactory;

    protected $table = 'poetry_structure';

    protected $fillable = [
        'verse_id',
        'structure_type',
        'level',
        'line_text',
        'line_order',
    ];

    protected $casts = [
        'level' => 'integer',
        'line_order' => 'integer',
    ];

    /**
     * Get the verse this poetry structure belongs to
     */
    public function verse(): BelongsTo
    {
        return $this->belongsTo(Verse::class);
    }
}
