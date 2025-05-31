# Migration Test Commands

## Test the migrations step by step

```bash
# 1. Fresh migrate with seeding
php artisan migrate:fresh --seed

# 2. Verify tables exist
php artisan tinker
>>> DB::select("SELECT name FROM sqlite_master WHERE type='table'");

# 3. Check book groups
>>> DB::table('book_groups')->get();

# 4. Check books count
>>> DB::table('books')->count();

# 5. Test foreign key relationships
>>> DB::table('books')->join('book_groups', 'books.book_group_id', '=', 'book_groups.id')->select('books.name', 'book_groups.name as group_name')->take(5)->get();

# 6. Test views
>>> DB::table('verse_details')->take(1)->get();
>>> DB::table('search_results')->take(1)->get();
```

## Add sample data for testing

```php
// In tinker
use Illuminate\Support\Facades\DB;

// Add a Bible version
DB::table('bible_versions')->insert([
    'osis_work' => 'Bible.en.kjv',
    'abbreviation' => 'KJV',
    'title' => 'King James Version',
    'language' => 'en',
    'description' => 'Test version',
    'created_at' => now(),
    'updated_at' => now(),
]);

// Add a chapter
$bookId = DB::table('books')->where('osis_id', 'Gen')->value('id');
$versionId = DB::table('bible_versions')->where('abbreviation', 'KJV')->value('id');

DB::table('chapters')->insert([
    'book_id' => $bookId,
    'version_id' => $versionId,
    'chapter_number' => 1,
    'osis_ref' => 'Gen.1',
    'created_at' => now(),
    'updated_at' => now(),
]);

// Add a verse
$chapterId = DB::table('chapters')->where('osis_ref', 'Gen.1')->value('id');

DB::table('verses')->insert([
    'chapter_id' => $chapterId,
    'verse_number' => 1,
    'osis_id' => 'Gen.1.1',
    'text' => 'In the beginning God created the heaven and the earth.',
    'formatted_text' => 'In the beginning God created the heaven and the earth.',
    'created_at' => now(),
    'updated_at' => now(),
]);

// Add italics
$verseId = DB::table('verses')->where('osis_id', 'Gen.1.1')->value('id');

DB::table('translator_changes')->insert([
    'verse_id' => $verseId,
    'change_type' => 'added',
    'text_content' => 'supplied word',
    'start_position' => 10,
    'end_position' => 23,
    'created_at' => now(),
    'updated_at' => now(),
]);

// Test the views work
DB::table('verse_details')->where('osis_id', 'Gen.1.1')->get();
DB::table('search_results')->where('verse_id', $verseId)->get();
```

## Performance Test

```bash
# Create performance test script
php artisan make:command TestBiblePerformance

# Then add timing tests in the command
```
