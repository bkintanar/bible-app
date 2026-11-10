<?php

use Illuminate\Support\Facades\DB;

it('reports healthy when the Bible can be read', function () {
    projectFixture();

    $this->get('/up')->assertOk();
});

it('reports unhealthy when the scripture database cannot be read', function () {
    // As in production: with debug on, the health route rethrows instead of answering 500.
    config(['app.debug' => false]);

    // SQLite DDL is transactional: RefreshDatabase rolls this back after the test. (Purging the
    // in-memory connection instead broke every later test in the process.)
    DB::statement('ALTER TABLE verses RENAME TO verses_unreadable');

    $this->get('/up')->assertStatus(500);
});
