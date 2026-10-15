<?php
require __DIR__.'/../vendor/autoload.php';
$app = require __DIR__.'/../bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();
echo json_encode(['database'=>basename(config('database.connections.sqlite.database')), 'users'=>App\Models\User::count(), 'transactions'=>App\Models\Transaction::count(), 'categories'=>App\Models\Category::count()], JSON_PRETTY_PRINT).PHP_EOL;
