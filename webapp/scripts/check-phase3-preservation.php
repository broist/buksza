<?php

use Illuminate\Contracts\Console\Kernel;

require __DIR__.'/../vendor/autoload.php';
$app = require __DIR__.'/../bootstrap/app.php';
$app->make(Kernel::class)->bootstrap();
$backupPath = database_path('before-phase3-20261014.sqlite');
if (! file_exists($backupPath) || basename(config('database.connections.sqlite.database')) !== 'database.sqlite') {
    throw new RuntimeException('A saját adatbázis és a fázis előtti mentés szükséges.');
}
$before = new PDO('sqlite:'.$backupPath);
$after = new PDO('sqlite:'.config('database.connections.sqlite.database'));
foreach ([$before, $after] as $connection) {
    $connection->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    $connection->exec('PRAGMA query_only = ON');
}
foreach (['users', 'accounts', 'categories', 'year_settings', 'monthly_plans', 'transactions', 'daily_notes', 'account_modes', 'loans', 'loan_versions', 'account_snapshots'] as $table) {
    $columns = $before->query('PRAGMA table_info("'.$table.'")')->fetchAll(PDO::FETCH_ASSOC);
    $names = implode(',', array_map(fn ($c) => '"'.$c['name'].'"', $columns));
    $key = array_values(array_filter($columns, fn ($c) => $c['pk'] === 1))[0]['name'];
    $find = $after->prepare('SELECT '.$names.' FROM "'.$table.'" WHERE "'.$key.'" = ?');
    foreach ($before->query('SELECT '.$names.' FROM "'.$table.'"')->fetchAll(PDO::FETCH_ASSOC) as $row) {
        $find->execute([$row[$key]]);
        if ($find->fetch(PDO::FETCH_ASSOC) !== $row) {
            throw new RuntimeException('Az ellenőrzött korábbi adatok eltérnek: '.$table);
        }
    }
}
echo "A korábbi felhasználói, számla- és pénzügyi adatok változatlanul megmaradtak.\n";
