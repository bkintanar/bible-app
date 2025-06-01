<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Verse extends Model
{
    use HasFactory;

    protected $fillable = [
        'chapter_id',
        'verse_number',
        'osis_id',
        'se_id',
        'text',
        'formatted_text',
        'original_xml',
    ];

    /**
     * Get the chapter this verse belongs to
     */
    public function chapter(): BelongsTo
    {
        return $this->belongsTo(Chapter::class);
    }

    /**
     * Get the word elements for this verse
     */
    public function wordElements(): HasMany
    {
        return $this->hasMany(WordElement::class);
    }

    /**
     * Get a formatted reference for this verse (e.g., "John 3:16")
     */
    public function getReferenceAttribute(): string
    {
        // Extract book, chapter, and verse from OSIS ID
        if (preg_match('/^([^.]+)\.(\d+)\.(\d+)$/', $this->osis_id, $matches)) {
            $bookOsis = $matches[1];
            $chapterNum = $matches[2];
            $verseNum = $matches[3];

            // Get book name (simplified mapping)
            $bookName = $this->getBookNameFromOsis($bookOsis);

            return "{$bookName} {$chapterNum}:{$verseNum}";
        }

        return $this->osis_id;
    }

    /**
     * Get book name from OSIS ID
     */
    private function getBookNameFromOsis(string $osis): string
    {
        $bookNames = [
            'Gen' => 'Genesis',
            'Exod' => 'Exodus',
            'Lev' => 'Leviticus',
            'Num' => 'Numbers',
            'Deut' => 'Deuteronomy',
            'Josh' => 'Joshua',
            'Judg' => 'Judges',
            'Ruth' => 'Ruth',
            '1Sam' => '1 Samuel',
            '2Sam' => '2 Samuel',
            '1Kgs' => '1 Kings',
            '2Kgs' => '2 Kings',
            '1Chr' => '1 Chronicles',
            '2Chr' => '2 Chronicles',
            'Ezra' => 'Ezra',
            'Neh' => 'Nehemiah',
            'Esth' => 'Esther',
            'Job' => 'Job',
            'Ps' => 'Psalms',
            'Prov' => 'Proverbs',
            'Eccl' => 'Ecclesiastes',
            'Song' => 'Song of Songs',
            'Isa' => 'Isaiah',
            'Jer' => 'Jeremiah',
            'Lam' => 'Lamentations',
            'Ezek' => 'Ezekiel',
            'Dan' => 'Daniel',
            'Hos' => 'Hosea',
            'Joel' => 'Joel',
            'Amos' => 'Amos',
            'Obad' => 'Obadiah',
            'Jonah' => 'Jonah',
            'Mic' => 'Micah',
            'Nah' => 'Nahum',
            'Hab' => 'Habakkuk',
            'Zeph' => 'Zephaniah',
            'Hag' => 'Haggai',
            'Zech' => 'Zechariah',
            'Mal' => 'Malachi',
            'Matt' => 'Matthew',
            'Mark' => 'Mark',
            'Luke' => 'Luke',
            'John' => 'John',
            'Acts' => 'Acts',
            'Rom' => 'Romans',
            '1Cor' => '1 Corinthians',
            '2Cor' => '2 Corinthians',
            'Gal' => 'Galatians',
            'Eph' => 'Ephesians',
            'Phil' => 'Philippians',
            'Col' => 'Colossians',
            '1Thess' => '1 Thessalonians',
            '2Thess' => '2 Thessalonians',
            '1Tim' => '1 Timothy',
            '2Tim' => '2 Timothy',
            'Titus' => 'Titus',
            'Phlm' => 'Philemon',
            'Heb' => 'Hebrews',
            'Jas' => 'James',
            '1Pet' => '1 Peter',
            '2Pet' => '2 Peter',
            '1John' => '1 John',
            '2John' => '2 John',
            '3John' => '3 John',
            'Jude' => 'Jude',
            'Rev' => 'Revelation',
        ];

        return $bookNames[$osis] ?? $osis;
    }
}
