<?php

namespace Tests;

use Illuminate\Foundation\Testing\TestCase as BaseTestCase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Tests\Support\BypassPermissionMiddleware;

abstract class TestCase extends BaseTestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        // In tests, bypass permission/plan checks so we can focus on business logic.
        // Tests that need to verify enforcement can override these.
        $router = app('router');
        $router->aliasMiddleware('permission', BypassPermissionMiddleware::class);
        $router->aliasMiddleware('plan.feature', BypassPermissionMiddleware::class);
        $router->aliasMiddleware('plan.limit', BypassPermissionMiddleware::class);
        $router->aliasMiddleware('plan.active', BypassPermissionMiddleware::class);

        // Disable Postgres FK enforcement in tests to match prior SQLite behavior
        // (the test schema and many test fixtures don't honor FK relationships).
        if (DB::connection()->getDriverName() === 'pgsql') {
            try {
                DB::statement("SET session_replication_role = 'replica'");
            } catch (\Throwable $e) {
                // ignore (non-superuser may not be allowed)
            }
        }

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
