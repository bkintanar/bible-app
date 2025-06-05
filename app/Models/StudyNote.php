<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class StudyNote extends Model
{
    use HasFactory;

    protected $table = 'study_notes';

    protected $fillable = [
        'verse_id',
        'note_type',
        'note_text',
    ];

    /**
     * Get the verse this study note belongs to
     */
    public function verse(): BelongsTo
    {
        return $this->belongsTo(Verse::class);
    }
}
