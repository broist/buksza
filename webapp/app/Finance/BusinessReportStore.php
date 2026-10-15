<?php

namespace App\Finance;

use App\Models\Account;
use App\Models\Category;
use App\Models\Transaction;
use Carbon\CarbonImmutable;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

final class BusinessReportStore
{
    public function forRequest(Request $request): array
    {
        $data = $request->validate([
            'year' => 'sometimes|integer|between:2000,2100', 'month' => 'sometimes|integer|between:1,12',
            'from' => 'nullable|date_format:Y-m-d|after_or_equal:2000-01-01|before_or_equal:2100-12-31',
            'to' => 'nullable|date_format:Y-m-d|after_or_equal:2000-01-01|before_or_equal:2100-12-31',
            'group' => 'sometimes|in:all,business,personal',
        ]);
        $start = CarbonImmutable::create((int) ($data['year'] ?? now()->year), (int) ($data['month'] ?? now()->month), 1);
        $from = $data['from'] ?? $start->toDateString();
        $to = $data['to'] ?? $start->endOfMonth()->toDateString();
        if ($from > $to) {
            throw ValidationException::withMessages(['to' => 'A zárónap nem lehet a kezdőnap előtt.']);
        }
        $raw = Transaction::whereBetween('date', [$from, $to])->orderByDesc('date')->orderByDesc('id')->get()->toArray();
        $report = app(Calculator::class)->businessReport($raw, $from, $to);
        $group = $data['group'] ?? 'business';
        $report['rows'] = array_values(array_filter($report['rows'], fn ($row) => $group === 'all' || $row['business'] === ($group === 'business')));

        return [...$report, 'from' => $from, 'to' => $to, 'group' => $group, 'rawTransactions' => $raw];
    }

    public function sheets(array $report): array
    {
        $names = ['all' => 'Összesen', 'business' => 'Üzleti', 'personal' => 'Magán'];
        $categoryNames = Category::pluck('name', 'id');
        $accounts = Account::pluck('name', 'id');
        $summary = [['Időszak kezdete', $report['from']], ['Időszak vége', $report['to']], ['Tétellista szűrése', $names[$report['group']]], ['Bontás', 'Bevétel (Ft)', 'Kiadás (Ft)', 'Bevétel mínusz kiadás (Ft)']];
        foreach ($report['totals'] as $key => $total) {
            $summary[] = [$names[$key], $total['income'], $total['expense'], $total['difference']];
        }
        $monthly = [['Hónap', 'Bontás', 'Bevétel (Ft)', 'Kiadás (Ft)', 'Különbség (Ft)']];
        foreach ($report['months'] as $month => $groups) {
            foreach ($groups as $key => $total) {
                $monthly[] = [$month, $names[$key], $total['income'], $total['expense'], $total['difference']];
            }
        }
        $categories = [['Kategória', 'Összes kiadás (Ft)', 'Üzleti kiadás (Ft)', 'Magánkiadás (Ft)']];
        foreach ($report['categories'] as $category) {
            $categories[] = [$categoryNames[$category['id']] ?? 'Kategória', $category['all'], $category['business'], $category['personal']];
        }
        $rows = [['Azonosító', 'Dátum', 'Típus', 'Üzleti/magán', 'Kategória', 'Rögzítés számlája', 'Célszámla', 'Összeg (Ft)', 'Megjegyzés']];
        foreach ($report['rows'] as $row) {
            $rows[] = [$row['id'], $row['date'], $row['is_fee'] ? 'Banki díj' : ($row['type'] === 'expense' ? 'Kiadás' : 'Bevétel'), $row['business'] ? 'Üzleti' : 'Magán', $categoryNames[$row['category_id']] ?? '', $accounts[$row['account_id']] ?? '', $accounts[$row['target_account_id'] ?? null] ?? '', $row['amount'], $row['note'] ?? ''];
        }

        return ['Összegzés' => $summary, 'Hónapok' => $monthly, 'Kategóriák' => $categories, 'Tételek' => $rows];
    }
}
