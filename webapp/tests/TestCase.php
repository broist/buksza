<?php

namespace Tests;

use Illuminate\Foundation\Testing\TestCase as BaseTestCase;

abstract class TestCase extends BaseTestCase
{
    public function createApplication()
    {
        // Docker/system environment variables must never select the real database in tests.
        foreach (['APP_ENV' => 'testing', 'DB_CONNECTION' => 'sqlite', 'DB_DATABASE' => ':memory:', 'DB_URL' => '', 'CACHE_STORE' => 'array', 'SESSION_DRIVER' => 'array'] as $key => $value) {
            putenv($key.'='.$value);
            $_ENV[$key] = $value;
            $_SERVER[$key] = $value;
        }
        $app = parent::createApplication();
        if (! $app->environment('testing') || config('database.connections.sqlite.database') !== ':memory:') {
            throw new \RuntimeException('A tesztek kizárólag memóriabeli tesztadatbázissal futtathatók.');
        }

        return $app;
    }
}
