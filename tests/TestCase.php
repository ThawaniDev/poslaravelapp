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

        // Clear Sanctum guard user-cache when a DIFFERENT bearer token is bound.
        // RequestGuard::setRequest() updates $this->request but NOT $this->user,
        // so successive requests with different Bearer tokens would return the first
        // user for all subsequent requests.
        // We track the last-seen token: only clear when the token actually changes,
        // so that actingAs() + leftover withToken() header does not override the user
        // explicitly set by actingAs().
        $lastToken = null;
        $this->app->rebinding('request', function ($app, $newRequest) use (&$lastToken) {
            $newToken = $newRequest->bearerToken();
            // Token unchanged (or no token) — nothing to do.
            if (!$newToken || $newToken === $lastToken) {
                return;
            }
            // Token changed — record it. Only clear the cached guard user if auth has
            // already been resolved (i.e., a guard instance exists to clear).
            $lastToken = $newToken;
            if (!$app->resolved('auth')) {
                return;
            }
            $auth = $app->make('auth');
            foreach (['sanctum', 'admin-api'] as $guardName) {
                try {
                    $guard = $auth->guard($guardName);
                    $prop = (new \ReflectionObject($guard))->getProperty('user');
                    $prop->setAccessible(true);
                    $prop->setValue($guard, null);
                } catch (\Throwable) {
                    // guard may not exist or property inaccessible — skip
                }
            }
        });

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
