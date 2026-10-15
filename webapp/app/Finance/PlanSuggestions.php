<?php

namespace App\Finance;

use DateTimeImmutable;

final class PlanSuggestions
{
    public function calculate(int $year, int $month, array $categories, array $transactions, array $observations): array
    {
        $start = new DateTimeImmutable(sprintf('%04d-%02d-01', $year, $month));
        $periods = [];
        for ($i = 3; $i >= 1; $i--) {
            $periods[] = $start->modify("-$i months")->format('Y-m');
        }
        $complete = array_map(fn ($row) => sprintf('%04d-%02d', $row['year'], $row['month']), array_filter($observations, fn ($row) => $row['complete']));
        $missing = array_values(array_diff($periods, $complete));
        $rows = (new Calculator)->familyTransactions($transactions);
        $suggestions = [];
        if (! $missing) {
            foreach ($categories as $category) {
                $total = array_sum(array_column(array_filter($rows, fn ($tx) => $tx['type'] === 'expense' && $tx['category_id'] == $category['id'] && in_array(substr($tx['date'], 0, 7), $periods)), 'amount'));
                $average = (new Exact($total))->divide(3);
                $suggestions[$category['id']] = $average->jsonSerialize();
            }
        }

        return ['periods' => $periods, 'missing' => $missing, 'amounts' => $suggestions];
    }
}
