<?php

use Illuminate\Contracts\Console\Kernel;
use Illuminate\Support\Facades\DB;

require __DIR__.'/../vendor/autoload.php';
$app = require __DIR__.'/../bootstrap/app.php';
$app->make(Kernel::class)->bootstrap();
$path = database_path('before-phase4-20261015.sqlite');
if (basename(config('database.connections.sqlite.database')) !== 'database.sqlite') {
    throw new RuntimeException('A mentéshez a saját adatbázis szükséges.');
}
if (! file_exists($path)) {
    DB::statement('VACUUM INTO ?', [$path]);
}
echo "A 4. fázis előtti adatbázismentés megvan.\n";
