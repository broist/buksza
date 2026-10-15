<?php

namespace Database\Seeders;

use App\Finance\PlanStore;
use App\Models\Loan;
use App\Models\Transaction;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class QaSeeder extends Seeder
{
    public function run(): void
    {
        if (! app()->environment('testing') || basename(config('database.connections.sqlite.database')) !== 'qa.sqlite') {
            throw new \RuntimeException('QA-adatok csak az elkülönített qa.sqlite tesztadatbázisba írhatók.');
        }
        $this->call(DatabaseSeeder::class);
        User::create(['name' => 'Isti', 'email' => 'qa@example.test', 'password' => 'only-for-qa-2026']);
        $year = now()->year;
        $month = now()->month;
        app(PlanStore::class)->ensureYear($year);
        DB::table('accounts')->where('id', 1)->update(['opening_balance' => 85000]);
        DB::table('year_settings')->where('year', $year)->update(['opening_amount' => 85000]);
        $demoLoans = [];
        foreach ([['Lakáshitel – minta', 95000, '6.5', 10], ['Személyi kölcsön – minta', 65000, '9.25', 25]] as [$name, $installment, $rate, $due]) {
            $loan = Loan::create(['name' => $name, 'lender' => 'Példa bank', 'original_amount' => 2000000, 'outstanding_amount' => 1500000, 'balance_date' => "$year-01-01", 'annual_rate' => $rate, 'rate_terms' => 'Fiktív, kizárólag QA-adat', 'installment' => $installment, 'due_day' => $due, 'start_date' => "$year-01-01", 'end_date' => null, 'closed_date' => null, 'note' => 'Mintaadat']);
            $demoLoans[] = $loan;
            $loan->refresh();
            DB::table('loan_versions')->insert(['loan_id' => $loan->id, 'effective_date' => "$year-01-01", 'data' => json_encode($loan->toArray()), 'created_at' => now(), 'updated_at' => now()]);
        }
        $budgets = [1 => 150000, 2 => 35000, 3 => 70000, 4 => 30000, 5 => 40000, 6 => 20000, 10 => 20000, 13 => 160000, 14 => 25000, 16 => 30000, 21 => 40000];
        foreach ($budgets as $category => $amount) {
            DB::table('monthly_plans')->where('year', $year)->where('month', $month)->where('category_id', $category)->update(['amount' => $amount, 'is_override' => true]);
        }
        $rows = [['income', 720000, 1, null, 'Munka', 'Fizetés'], ['income', 120000, 2, null, 'Egyéb', 'Anna hozzájárulása'], ['expense', 24600, 2, 1, null, 'Heti bevásárlás'], ['expense', 8900, 3, 2, null, 'Családi ebéd'], ['expense', 18000, 5, 3, null, 'Áram'], ['expense', 18200, 6, 1, null, 'Bevásárlás'], ['expense', 2490, 7, 10, null, 'Előfizetés'], ['expense', 22000, 8, 5, null, 'Üzemanyag'], ['expense', 6500, 9, 6, null, 'Drogéria'], ['expense', 95000, 10, 13, null, 'Hitel 1 törlesztése'], ['expense', 12150, 11, 1, null, 'Bevásárlás'], ['expense', 6800, 12, 2, null, 'Ebéd'], ['expense', 4990, 13, 10, null, 'Előfizetés'], ['saving', 50000, 5, null, null, 'Családi félretétel'], ['child_saving', 20000, 6, null, null, 'Örs babakötvénye']];
        foreach ($rows as [$type,$amount,$day,$category,$source,$note]) {
            Transaction::create(['client_id' => (string) Str::uuid(), 'date' => sprintf('%d-%02d-%02d', $year, $month, $day), 'type' => $type, 'amount' => $amount, 'account_id' => 1, 'category_id' => $category, 'source' => $source, 'note' => $note, 'business' => false, 'loan_id' => $category === 13 ? $demoLoans[0]->id : null, 'target_account_id' => $type === 'child_saving' ? 8 : null]);
        }
    }
}
