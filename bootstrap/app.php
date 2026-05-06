<?php

use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Request;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        api: __DIR__.'/../routes/api.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->redirectGuestsTo(function (Request $request): ?string {
            if ($request->is('api/*') || $request->expectsJson()) {
                return null;
            }

            return '/admin/login';
        });

        $middleware->alias([
            'permission' => \App\Http\Middleware\CheckPermission::class,
            'branch.scope' => \App\Http\Middleware\BranchScope::class,
            'plan.feature' => \App\Http\Middleware\CheckPlanFeature::class,
            'plan.limit' => \App\Http\Middleware\CheckPlanLimit::class,
            'plan.active' => \App\Http\Middleware\CheckActiveSubscription::class,
        ]);

        $middleware->statefulApi();
        $middleware->throttleApi('api');

        // Apply branch scope to all API requests (resolves after auth:sanctum)
        $middleware->appendToGroup('api', [
            \App\Http\Middleware\BranchScope::class,
        ]);
        $middleware->validateCsrfTokens(except: [
            'api/v2/website/*',
            'payment/result',
            'api/v2/webhook/thawani/*',
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        $exceptions->shouldRenderJsonWhen(function (Request $request, \Throwable $e): bool {
            return $request->is('api/*') || $request->expectsJson();
        });

        // Convert PostgreSQL "invalid input syntax for type uuid" (SQLSTATE 22P02)
        // into a clean 404 instead of leaking a 500 when callers pass a malformed
        // UUID in a route parameter (e.g. /cash-sessions/1 against a uuid PK).
        $exceptions->render(function (\Illuminate\Database\QueryException $e, Request $request) {
            if (! ($request->is('api/*') || $request->expectsJson())) {
                return null;
            }
            $sqlState = $e->errorInfo[0] ?? null;
            $message = $e->getMessage();
            if ($sqlState === '22P02' || str_contains($message, 'invalid input syntax for type uuid')) {
                return response()->json([
                    'success' => false,
                    'message' => 'Resource not found.',
                    'errors' => null,
                ], 404);
            }
            return null;
        });
    })->create();
