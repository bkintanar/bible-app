<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class BookGroup extends Model
{
    use HasFactory;

    protected $table = 'book_groups';

    protected $fillable = [
        'name',
        'description',
        'sort_order',
    ];

    protected $casts = [
        'sort_order' => 'integer',
    ];

    /**
     * Get all books in this group
     */
    public function books(): HasMany
    {
        return $this->hasMany(Book::class, 'book_group_id')->orderBy('sort_order');
    }
}
