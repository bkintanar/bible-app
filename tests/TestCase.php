<?php

namespace Tests;

use Illuminate\Foundation\Testing\TestCase as BaseTestCase;

abstract class TestCase extends BaseTestCase
{
    /**
     * RefreshDatabase migrates once and then re-attaches each in-memory connection's PDO to every
     * later test - but only for the connections listed here. The 'runtime' connection (cache,
     * queue; config/database.php) is a second in-memory database in tests, so it must be listed
     * too, or its tables vanish after the first test. null = the default connection.
     *
     * @var array<int, string|null>
     */
    protected $connectionsToTransact = [null, 'runtime'];
}
