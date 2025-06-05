<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class TranslatorChange extends Model
{
    use HasFactory;

    protected $table = 'translator_changes';

    protected $fillable = [
        'verse_id',
        'text_content',
        'change_type',
        'text_order',
    ];

    protected $casts = [
        'text_order' => 'integer',
    ];

    /**
     * Get the verse this translator change belongs to
     */
    public function verse(): BelongsTo
    {
        return $this->belongsTo(Verse::class);
    }
}
