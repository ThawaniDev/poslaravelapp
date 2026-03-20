<?php
require __DIR__ . '/vendor/autoload.php';
$app = require_once __DIR__ . '/bootstrap/app.php';
$kernel = $app->make('Illuminate\Contracts\Console\Kernel');
$kernel->bootstrap();

$tables = array_map('trim', file('/tmp/seeder_tables.txt', FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES));

foreach ($tables as $t) {
    $cols = DB::select(
        "SELECT column_name, data_type, is_nullable FROM information_schema.columns WHERE table_name = ? AND table_schema = 'public' ORDER BY ordinal_position",
        [$t]
    );
    if (empty($cols)) {
        echo "=== $t === [TABLE NOT FOUND]\n";
        continue;
    }
    echo "=== $t ===\n";
    foreach ($cols as $c) {
        echo "  {$c->column_name} ({$c->data_type}, nullable:{$c->is_nullable})\n";
    }
}
echo "\n=== DONE ===\n";
