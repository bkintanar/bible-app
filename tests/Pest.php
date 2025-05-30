<?php

/*
|--------------------------------------------------------------------------
| Test Case
|--------------------------------------------------------------------------
|
| The closure you provide to your test functions is always bound to a specific PHPUnit test
| case class. By default, that class is "PHPUnit\Framework\TestCase". Of course, you may
| need to change it using the "pest()" function to bind a different classes or traits.
|
*/

// Configure Feature tests to use Laravel's TestCase for HTTP testing
pest()->extend(Tests\TestCase::class)
    ->use(Illuminate\Foundation\Testing\RefreshDatabase::class)
    ->in('Feature');

// Configure Unit tests to use the base PHPUnit TestCase
pest()->extend(Tests\TestCase::class)
    ->in('Unit');

/*
|--------------------------------------------------------------------------
| Expectations
|--------------------------------------------------------------------------
|
| When you're writing tests, you often need to check that values meet certain conditions. The
| "expect()" function gives you access to a set of "expectations" methods that you can use
| to assert different things. Of course, you may extend the Expectation API at any time.
|
*/

expect()->extend('toBeOne', function () {
    return $this->toBe(1);
});

expect()->extend('toHaveKeys', function (array $keys, string $message = '') {
    $actualKeys = array_keys($this->value);

    foreach ($keys as $key) {
        if (!in_array($key, $actualKeys)) {
            $missingKey = $key;
            $actualKeysStr = implode(', ', $actualKeys);
            $expectedKeysStr = implode(', ', $keys);

            $failureMessage = $message ?: "Expected array to have key '{$missingKey}'. Actual keys: [{$actualKeysStr}], Expected keys: [{$expectedKeysStr}]";

            throw new Exception($failureMessage);
        }
    }

    return $this;
});

/*
|--------------------------------------------------------------------------
| Functions
|--------------------------------------------------------------------------
|
| While Pest is very powerful out-of-the-box, you may have some testing code specific to your
| project that you don't want to repeat in every file. Here you can also expose helpers as
| global functions to help you to reduce the number of lines of code in your test files.
|
*/

function createOsisReader(string $translation = 'kjv'): App\Services\OsisReader
{
    $filePath = base_path("assets/{$translation}.osis.xml");
    return new App\Services\OsisReader($filePath);
}

function getTestTranslations(): array
{
    return ['kjv', 'asv', 'mao'];
}
