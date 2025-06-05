<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Factories\HasFactory;

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

    protected $casts = [
        'verse_number' => 'integer',
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
        return $this->hasMany(WordElement::class)->orderBy('word_order');
    }

    /**
     * Get the titles for this verse
     */
    public function titles(): HasMany
    {
        return $this->hasMany(Title::class)->orderBy('title_order');
    }

    /**
     * Get the translator changes for this verse
     */
    public function translatorChanges(): HasMany
    {
        return $this->hasMany(TranslatorChange::class)->orderBy('text_order');
    }

    /**
     * Get the divine names for this verse
     */
    public function divineNames(): HasMany
    {
        return $this->hasMany(DivineName::class);
    }

    /**
     * Get the study notes for this verse
     */
    public function studyNotes(): HasMany
    {
        return $this->hasMany(StudyNote::class);
    }

    /**
     * Get the poetry structure for this verse
     */
    public function poetryStructure(): HasMany
    {
        return $this->hasMany(PoetryStructure::class)->orderBy('line_order');
    }

    /**
     * Get a formatted reference for this verse (e.g., "John 3:16")
     */
    public function getReferenceAttribute(): string
    {
        // Use relationship to get book name instead of hardcoded mapping
        $chapter = $this->chapter()->with('book')->first();
        if ($chapter && $chapter->book) {
            return "{$chapter->book->name} {$chapter->chapter_number}:{$this->verse_number}";
        }

        // Fallback to OSIS parsing
        if (preg_match('/^([^.]+)\.(\d+)\.(\d+)$/', $this->osis_id, $matches)) {
            $bookOsis = $matches[1];
            $chapterNum = $matches[2];
            $verseNum = $matches[3];

            $bookName = $this->getBookNameFromOsis($bookOsis);
            return "{$bookName} {$chapterNum}:{$verseNum}";
        }

        return $this->osis_id;
    }

    /**
     * Get book name from OSIS ID (fallback method)
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
