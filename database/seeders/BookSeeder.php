<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class BookSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $books = [
            // Old Testament (book_group_id = 1)
            ['Gen', 1, 1, 'Genesis', 'The First Book of Moses, called Genesis', 'Genesis', true, 1],
            ['Exod', 1, 2, 'Exodus', 'The Second Book of Moses, called Exodus', 'Exodus', true, 2],
            ['Lev', 1, 3, 'Leviticus', 'The Third Book of Moses, called Leviticus', 'Leviticus', true, 3],
            ['Num', 1, 4, 'Numbers', 'The Fourth Book of Moses, called Numbers', 'Numbers', true, 4],
            ['Deut', 1, 5, 'Deuteronomy', 'The Fifth Book of Moses, called Deuteronomy', 'Deuteronomy', true, 5],
            ['Josh', 1, 6, 'Joshua', 'The Book of Joshua', 'Joshua', true, 6],
            ['Judg', 1, 7, 'Judges', 'The Book of Judges', 'Judges', true, 7],
            ['Ruth', 1, 8, 'Ruth', 'The Book of Ruth', 'Ruth', true, 8],
            ['1Sam', 1, 9, '1 Samuel', 'The First Book of Samuel Otherwise Called the First Book of the Kings', '1 Samuel', true, 9],
            ['2Sam', 1, 10, '2 Samuel', 'The Second Book of Samuel Otherwise Called the Second Book of the Kings', '2 Samuel', true, 10],
            ['1Kgs', 1, 11, '1 Kings', 'The First Book of the Kings Commonly Called the Third Book of the Kings', '1 Kings', true, 11],
            ['2Kgs', 1, 12, '2 Kings', 'The Second Book of the Kings Commonly Called the Fourth Book of the Kings', '2 Kings', true, 12],
            ['1Chr', 1, 13, '1 Chronicles', 'The First Book of the Chronicles', '1 Chronicles', true, 13],
            ['2Chr', 1, 14, '2 Chronicles', 'The Second Book of the Chronicles', '2 Chronicles', true, 14],
            ['Ezra', 1, 15, 'Ezra', 'Ezra', 'Ezra', true, 15],
            ['Neh', 1, 16, 'Nehemiah', 'The Book of Nehemiah', 'Nehemiah', true, 16],
            ['Esth', 1, 17, 'Esther', 'The Book of Esther', 'Esther', true, 17],
            ['Job', 1, 18, 'Job', 'The Book of Job', 'Job', true, 18],
            ['Ps', 1, 19, 'Psalms', 'The Book of Psalms', 'Psalms', true, 19],
            ['Prov', 1, 20, 'Proverbs', 'The Proverbs', 'Proverbs', true, 20],
            ['Eccl', 1, 21, 'Ecclesiastes', 'Ecclesiastes or, The Preacher', 'Ecclesiastes', true, 21],
            ['Song', 1, 22, 'Song of Solomon', 'The Song of Songs, which is Solomon\'s', 'Song of Solomon', true, 22],
            ['Isa', 1, 23, 'Isaiah', 'The Book of the Prophet Isaiah', 'Isaiah', true, 23],
            ['Jer', 1, 24, 'Jeremiah', 'The Book of the Prophet Jeremiah', 'Jeremiah', true, 24],
            ['Lam', 1, 25, 'Lamentations', 'The Lamentations of Jeremiah', 'Lamentations', true, 25],
            ['Ezek', 1, 26, 'Ezekiel', 'The Book of the Prophet Ezekiel', 'Ezekiel', true, 26],
            ['Dan', 1, 27, 'Daniel', 'The Book of Daniel', 'Daniel', true, 27],
            ['Hos', 1, 28, 'Hosea', 'Hosea', 'Hosea', true, 28],
            ['Joel', 1, 29, 'Joel', 'Joel', 'Joel', true, 29],
            ['Amos', 1, 30, 'Amos', 'Amos', 'Amos', true, 30],
            ['Obad', 1, 31, 'Obadiah', 'Obadiah', 'Obadiah', true, 31],
            ['Jonah', 1, 32, 'Jonah', 'Jonah', 'Jonah', true, 32],
            ['Mic', 1, 33, 'Micah', 'Micah', 'Micah', true, 33],
            ['Nah', 1, 34, 'Nahum', 'Nahum', 'Nahum', true, 34],
            ['Hab', 1, 35, 'Habakkuk', 'Habakkuk', 'Habakkuk', true, 35],
            ['Zeph', 1, 36, 'Zephaniah', 'Zephaniah', 'Zephaniah', true, 36],
            ['Hag', 1, 37, 'Haggai', 'Haggai', 'Haggai', true, 37],
            ['Zech', 1, 38, 'Zechariah', 'Zechariah', 'Zechariah', true, 38],
            ['Mal', 1, 39, 'Malachi', 'Malachi', 'Malachi', true, 39],

            // New Testament (book_group_id = 2)
            ['Matt', 2, 40, 'Matthew', 'The Gospel According to Matthew', 'Matthew', true, 40],
            ['Mark', 2, 41, 'Mark', 'The Gospel According to Mark', 'Mark', true, 41],
            ['Luke', 2, 42, 'Luke', 'The Gospel According to Luke', 'Luke', true, 42],
            ['John', 2, 43, 'John', 'The Gospel According to John', 'John', true, 43],
            ['Acts', 2, 44, 'Acts', 'The Acts of the Apostles', 'Acts', true, 44],
            ['Rom', 2, 45, 'Romans', 'The Epistle of Paul the Apostle to the Romans', 'Romans', true, 45],
            ['1Cor', 2, 46, '1 Corinthians', 'The First Epistle of Paul the Apostle to the Corinthians', '1 Corinthians', true, 46],
            ['2Cor', 2, 47, '2 Corinthians', 'The Second Epistle of Paul the Apostle to the Corinthians', '2 Corinthians', true, 47],
            ['Gal', 2, 48, 'Galatians', 'The Epistle of Paul the Apostle to the Galatians', 'Galatians', true, 48],
            ['Eph', 2, 49, 'Ephesians', 'The Epistle of Paul the Apostle to the Ephesians', 'Ephesians', true, 49],
            ['Phil', 2, 50, 'Philippians', 'The Epistle of Paul the Apostle to the Philippians', 'Philippians', true, 50],
            ['Col', 2, 51, 'Colossians', 'The Epistle of Paul the Apostle to the Colossians', 'Colossians', true, 51],
            ['1Thess', 2, 52, '1 Thessalonians', 'The First Epistle of Paul the Apostle to the Thessalonians', '1 Thessalonians', true, 52],
            ['2Thess', 2, 53, '2 Thessalonians', 'The Second Epistle of Paul the Apostle to the Thessalonians', '2 Thessalonians', true, 53],
            ['1Tim', 2, 54, '1 Timothy', 'The First Epistle of Paul the Apostle to Timothy', '1 Timothy', true, 54],
            ['2Tim', 2, 55, '2 Timothy', 'The Second Epistle of Paul the Apostle to Timothy', '2 Timothy', true, 55],
            ['Titus', 2, 56, 'Titus', 'The Epistle of Paul to Titus', 'Titus', true, 56],
            ['Phlm', 2, 57, 'Philemon', 'The Epistle of Paul to Philemon', 'Philemon', true, 57],
            ['Heb', 2, 58, 'Hebrews', 'The Epistle of Paul the Apostle to the Hebrews', 'Hebrews', true, 58],
            ['Jas', 2, 59, 'James', 'The General Epistle of James', 'James', true, 59],
            ['1Pet', 2, 60, '1 Peter', 'The First Epistle General of Peter', '1 Peter', true, 60],
            ['2Pet', 2, 61, '2 Peter', 'The Second Epistle General of Peter', '2 Peter', true, 61],
            ['1John', 2, 62, '1 John', 'The First Epistle General of John', '1 John', true, 62],
            ['2John', 2, 63, '2 John', 'The Second Epistle of John', '2 John', true, 63],
            ['3John', 2, 64, '3 John', 'The Third Epistle of John', '3 John', true, 64],
            ['Jude', 2, 65, 'Jude', 'The General Epistle of Jude', 'Jude', true, 65],
            ['Rev', 2, 66, 'Revelation', 'The Revelation of Jesus Christ', 'Revelation', true, 66],
        ];

        foreach ($books as $book) {
            DB::table('books')->insert([
                'osis_id' => $book[0],
                'book_group_id' => $book[1],
                'number' => $book[2],
                'name' => $book[3],
                'full_title' => $book[4],
                'short_name' => $book[5],
                'canonical' => $book[6],
                'sort_order' => $book[7],
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }
    }
}
