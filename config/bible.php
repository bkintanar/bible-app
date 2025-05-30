<?php

return [
    /*
    |--------------------------------------------------------------------------
    | Available Bible Translations
    |--------------------------------------------------------------------------
    |
    | This array contains all available Bible translations in the application.
    | Each translation should have a unique key and contain the necessary
    | metadata to identify and load the OSIS file.
    |
    */
    'translations' => [
        'kjv' => [
            'name' => 'King James Version',
            'short_name' => 'KJV',
            'language' => 'English',
            'year' => '1769',
            'filename' => 'kjv.osis.xml',
            'description' => 'The classic King James Version, widely regarded for its literary beauty and historical significance.',
            'is_default' => true,
        ],

        'asv' => [
            'name' => 'American Standard Version',
            'short_name' => 'ASV',
            'language' => 'English',
            'year' => '1901',
            'filename' => 'asv.osis.xml',
            'description' => 'The American Standard Version of 1901 is an Americanization of the English Revised Bible, which is an update of the KJV to less archaic spelling and greater accuracy of translation. It has been called "The Rock of Biblical Honesty."',
            'is_default' => false,
        ],

        'mao' => [
            'name' => 'Maori Version',
            'short_name' => 'MAO',
            'language' => 'Māori',
            'year' => '2009',
            'filename' => 'mao.osis.xml',
            'description' => 'Maori Bible prepared by Timothy Mora. Text reproduced by Dr. Cleve Barlow.',
            'is_default' => false,
        ],

        // To add more translations:
        // 1. Place your OSIS XML file in the assets/ directory
        // 2. Uncomment and configure a new translation entry below
        // 3. The dropdown will automatically appear when you have multiple translations

        // 'esv' => [
        //     'name' => 'English Standard Version',
        //     'short_name' => 'ESV',
        //     'language' => 'English',
        //     'year' => '2001',
        //     'filename' => 'esv.osis.xml', // Place this file in assets/
        //     'description' => 'A modern English translation that maintains literary excellence and accuracy.',
        //     'is_default' => false,
        // ],

        // 'niv' => [
        //     'name' => 'New International Version',
        //     'short_name' => 'NIV',
        //     'language' => 'English',
        //     'year' => '2011',
        //     'filename' => 'niv.osis.xml',
        //     'description' => 'A contemporary English translation balancing accuracy and readability.',
        //     'is_default' => false,
        // ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Default Translation
    |--------------------------------------------------------------------------
    |
    | The default translation to use when no specific translation is selected
    | or when the requested translation is not available.
    |
    */
    'default_translation' => 'kjv',

    /*
    |--------------------------------------------------------------------------
    | OSIS Files Directory
    |--------------------------------------------------------------------------
    |
    | The directory where OSIS XML files are stored, relative to the
    | application's base path.
    |
    */
    'osis_directory' => 'assets',
];
