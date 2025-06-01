<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class BookGroup extends Model
{
    use HasFactory;

    protected $fillable = [
        'name',
        'canonical',
        'sub_type',
        'sort_order',
    ];

    protected $casts = [
        'canonical' => 'boolean',
    ];

    /**
     * Get the books in this group
     */
    public function books(): HasMany
    {
        return $this->hasMany(Book::class);
    }
}
