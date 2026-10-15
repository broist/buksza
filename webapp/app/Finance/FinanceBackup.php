<?php

namespace App\Finance;

use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

final class FinanceBackup
{
    public const TABLES = ['categories', 'accounts', 'account_modes', 'account_snapshots', 'year_settings', 'monthly_plans', 'loans', 'loan_versions', 'recurring_templates', 'recurring_versions', 'transactions', 'daily_notes', 'month_observations', 'investments', 'investment_plans', 'investment_values'];

    private function schema(): string
    {
        return hash('sha256', json_encode(array_map(fn ($table) => Schema::getColumnListing($table), self::TABLES)));
    }

    public function create(): string
    {
        $tables = DB::transaction(fn () => collect(self::TABLES)->mapWithKeys(fn ($table) => [$table => DB::table($table)->get()->map(fn ($row) => (array) $row)->all()])->all());

        return Crypt::encryptString(gzencode(json_encode(['format' => 'buksza-finance-1', 'schema' => $this->schema(), 'created_at' => now()->toIso8601String(), 'tables' => $tables], JSON_THROW_ON_ERROR), 9));
    }

    public function decode(string $contents): array
    {
        try {
            $json = gzdecode(Crypt::decryptString($contents), 20 * 1024 * 1024);
            $data = json_decode($json, true, 64, JSON_THROW_ON_ERROR);
            if (($data['format'] ?? null) !== 'buksza-finance-1' || ($data['schema'] ?? null) !== $this->schema() || ! is_array($data['tables'] ?? null) || array_keys($data['tables']) !== self::TABLES) {
                throw new \RuntimeException;
            }
            foreach (self::TABLES as $table) {
                $columns = Schema::getColumnListing($table);
                if (! is_array($data['tables'][$table]) || count($data['tables'][$table]) > 200000) {
                    throw new \RuntimeException;
                }
                foreach ($data['tables'][$table] as $row) {
                    if (! is_array($row) || array_diff(array_keys($row), $columns) || count($row) !== count($columns)) {
                        throw new \RuntimeException;
                    }
                    foreach ($row as $value) {
                        if (! is_scalar($value) && $value !== null) {
                            throw new \RuntimeException;
                        }
                    }
                }
            }

            return $data;
        } catch (\Throwable) {
            throw ValidationException::withMessages(['backup' => 'A mentés sérült, más alkalmazáskulccsal készült, vagy más adatmodell-verzióhoz tartozik. A pénzügyek nem változtak.']);
        }
    }

    public function restore(array $data): void
    {
        $path = 'finance-backups/'.now()->format('Ymd-His').'-'.Str::uuid().'.buksza';
        if (! Storage::disk('local')->put($path, $this->create())) {
            throw ValidationException::withMessages(['backup' => 'Nem sikerült elmenteni a jelenlegi pénzügyeket. A visszaállítás nem indult el.']);
        }
        try {
            DB::transaction(function () use ($data) {
                // SQLite defers even cross-table/self references until the whole
                // consistent snapshot has been restored; never disable FK checks.
                DB::statement('PRAGMA defer_foreign_keys = ON');
                foreach (array_reverse(self::TABLES) as $table) {
                    DB::table($table)->delete();
                }
                foreach (self::TABLES as $table) {
                    foreach (array_chunk($data['tables'][$table], 100) as $rows) {
                        DB::table($table)->insert($rows);
                    }
                }
                if (DB::select('PRAGMA foreign_key_check')) {
                    throw new \RuntimeException;
                }
            });
        } catch (\Throwable) {
            throw ValidationException::withMessages(['backup' => 'A mentés adatai nem állíthatók vissza következetesen. Az eddigi pénzügyek változatlanok maradtak.']);
        }
    }
}
