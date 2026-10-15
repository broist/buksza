<?php

use App\Finance\FinanceBackup;
use Illuminate\Contracts\Console\Kernel;

require __DIR__.'/../vendor/autoload.php';
$app = require __DIR__.'/../bootstrap/app.php';
$app->make(Kernel::class)->bootstrap();
$before = database_path('before-phase7-20261015.sqlite');
$current = config('database.connections.sqlite.database');
if (! is_file($before) || ! is_file($current) || basename($current) === 'qa.sqlite') {
    throw new RuntimeException('A saját adatbázis és a fázis előtti pillanatkép szükséges.');
}
$old = new PDO('sqlite:'.$before);
$new = new PDO('sqlite:'.$current);
$old->exec('PRAGMA query_only = ON');
$new->exec('PRAGMA query_only = ON');
foreach (FinanceBackup::BASE_TABLES as $table) {
    $a = $old->query('SELECT * FROM "'.$table.'" ORDER BY rowid')->fetchAll(PDO::FETCH_ASSOC);
    $b = $new->query('SELECT * FROM "'.$table.'" ORDER BY rowid')->fetchAll(PDO::FETCH_ASSOC);
    if ($a !== $b) {
        throw new RuntimeException('Eltérés a fázis előtti pénzügyi állapottól: '.$table);
    }
    echo $table.': '.count($b).' rekord változatlan'.PHP_EOL;
}
