<?php

namespace App\Finance;

use App\Models\Loan;
use App\Models\RecurringTemplate;
use App\Models\Transaction;
use Illuminate\Support\Facades\DB;

final class RecurringStore
{
    public function month(int $year, int $month): array
    {
        $templates = RecurringTemplate::orderBy('id')->get()->toArray();
        $versions = DB::table('recurring_versions')->get()->map(fn ($v) => [...(array) $v, 'data' => json_decode($v->data, true)])->all();
        $loanVersions = DB::table('loan_versions')->get()->map(fn ($v) => [...(array) $v, 'data' => json_decode($v->data, true)])->all();
        $transactions = Transaction::withTrashed()->whereNotNull('template_id')->get()->toArray();
        $start = new \DateTimeImmutable(sprintf('%04d-%02d-01', $year, $month));

        $planner = new RecurringPlanner;
        $occurrences = $planner->month($templates, $versions, $transactions, Loan::all()->toArray(), $loanVersions, $year, $month);

        return ['templates' => $templates, 'occurrences' => $occurrences, 'changes' => $planner->amountChanges($occurrences, $transactions),
            'candidates' => Transaction::whereNull('template_id')->whereBetween('date', [$start->modify('-1 month')->format('Y-m-d'), $start->modify('+2 months')->modify('-1 day')->format('Y-m-d')])->orderByDesc('date')->get()->toArray()];
    }
}
