<?php

namespace Tests;

use Illuminate\Foundation\Testing\TestCase as BaseTestCase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

abstract class TestCase extends BaseTestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        // Register PostgreSQL functions for SQLite test database
        if (DB::connection()->getDriverName() === 'sqlite') {
            /** @var \PDO $pdo */
            $pdo = DB::connection()->getPdo();

            // gen_random_uuid() — PostgreSQL UUID generation function
            $pdo->sqliteCreateFunction('gen_random_uuid', function () {
                return Str::uuid()->toString();
            }, 0);
        }
    }
}
