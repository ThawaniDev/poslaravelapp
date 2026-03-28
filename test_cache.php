<?php
// Quick test: verify caching works and count remaining queries on a page load

require __DIR__.'/vendor/autoload.php';
$app = require_once __DIR__.'/bootstrap/app.php';
$app->make(\Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Cache;

// Clear permission caches first
Cache::flush();
echo "=== Cache flushed ===\n\n";

DB::enableQueryLog();

// 1. Load admin user
$admin = \App\Domain\AdminPanel\Models\AdminUser::first();
echo "Admin: {$admin->name} (ID: {$admin->id})\n";

// 2. isSuperAdmin (cold cache)
$isSuperAdmin = $admin->isSuperAdmin();
echo "Is super admin: " . ($isSuperAdmin ? 'yes' : 'no') . "\n";

// 3. hasPermission (cold cache)
$admin->hasPermission('view_stores');

$queries = DB::getQueryLog();
echo "\nCold cache queries: " . count($queries) . "\n";
foreach ($queries as $q) {
    echo "  " . round($q['time']) . "ms: " . substr($q['query'], 0, 120) . "\n";
}

// Now simulate second request (new model instance, cache should be warm)
DB::flushQueryLog();
DB::enableQueryLog();

$admin2 = \App\Domain\AdminPanel\Models\AdminUser::find($admin->id);
$admin2->isSuperAdmin();
$admin2->hasPermission('view_stores');

$queries2 = DB::getQueryLog();
echo "\nWarm cache queries: " . count($queries2) . "\n";
foreach ($queries2 as $q) {
    echo "  " . round($q['time']) . "ms: " . substr($q['query'], 0, 120) . "\n";
}

echo "\n=== Done ===\n";
