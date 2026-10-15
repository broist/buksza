<?php

namespace App\Finance;

use DateTimeImmutable;

final class Calculator
{
    public function balanceGroups(array $accounts): array
    {
        $groups = [
            ['name' => 'Szabad bankszámlák', 'kinds' => ['bank', 'subaccount']],
            ['name' => 'Kötött egyenlegek', 'kinds' => ['szep', 'health']],
            ['name' => 'Családi megtakarítás', 'kinds' => ['saving']],
            ['name' => 'Örs gyermekmegtakarítása', 'kinds' => ['child']],
        ];
        $result = [];
        foreach ($groups as $group) {
            $rows = array_filter($accounts, fn ($a) => in_array($a['kind'], $group['kinds']) && ($a['active'] || $a['balance'] !== 0));
            if (! $rows) {
                continue;
            }
            $unknown = count(array_filter($rows, fn ($a) => $a['balance'] === null));
            $result[] = ['name' => $group['name'], 'total' => $unknown ? null : array_sum(array_column($rows, 'balance')), 'unknown' => $unknown];
        }

        return $result;
    }

    public function familyTransactions(array $transactions): array
    {
        $rows = [];
        foreach ($transactions as $tx) {
            if (! ($tx['informational'] ?? false)) {
                $rows[] = [...$tx, 'type' => $tx['family_type'] ?? $tx['type'], 'fee_amount' => 0];
            }
            if (($tx['fee_amount'] ?? 0) > 0) {
                $rows[] = [...$tx, 'type' => 'expense', 'family_type' => 'expense', 'category_id' => $tx['fee_category_id'], 'amount' => $tx['fee_amount'], 'loan_id' => null, 'person' => $tx['fee_person'] ?? $tx['person'] ?? null, 'fee_amount' => 0, 'informational' => false, 'is_fee' => true];
            }
        }

        return $rows;
    }

    public function loans(array $loans, array $versions, array $transactions, int $year, int $month): array
    {
        foreach ($versions as &$version) {
            foreach (['installment', 'due_day', 'original_amount', 'outstanding_amount'] as $key) {
                if (isset($version['data'][$key])) {
                    $version['data'][$key] = (int) $version['data'][$key];
                }
            }
        }
        unset($version);
        $end = (new DateTimeImmutable(sprintf('%04d-%02d-01', $year, $month)))->modify('last day of this month')->format('Y-m-d');
        $prefix = substr($end, 0, 7);

        return array_map(function ($loan) use ($versions, $transactions, $end, $prefix) {
            $history = array_values(array_filter($versions, fn ($v) => $v['loan_id'] === $loan['id']));
            usort($history, fn ($a, $b) => [$a['effective_date'], $a['id']] <=> [$b['effective_date'], $b['id']]);
            $terms = null;
            foreach ($history as $version) {
                if ($version['effective_date'] <= $end) {
                    $terms = $version['data'];
                }
            }
            $due = $prefix.'-'.str_pad((string) min((int) ($terms['due_day'] ?? 1), (int) substr($end, 8, 2)), 2, '0', STR_PAD_LEFT);
            $expected = 0;
            // Use the conditions active on the due date, not the latest month-end values.
            foreach ($history as $index => $version) {
                $candidate = $prefix.'-'.str_pad((string) min($version['data']['due_day'], (int) substr($end, 8, 2)), 2, '0', STR_PAD_LEFT);
                $next = $history[$index + 1]['effective_date'] ?? '9999-12-31';
                $data = $version['data'];
                if ($version['effective_date'] <= $candidate && $candidate < $next && $data['start_date'] <= $candidate && (! $data['end_date'] || $candidate <= $data['end_date']) && (! $data['closed_date'] || $candidate <= $data['closed_date'])) {
                    $terms = $data;
                    $due = $candidate;
                    $expected = $data['installment'];
                    break;
                }
            }
            $payments = array_values(array_filter($transactions, fn ($tx) => ($tx['loan_id'] ?? null) == $loan['id'] && str_starts_with($tx['date'], $prefix) && ($tx['family_type'] ?? $tx['type']) === 'expense' && $tx['type'] === 'expense' && ! ($tx['informational'] ?? false)));
            $paid = array_sum(array_column($payments, 'amount'));
            $annualPaid = array_sum(array_column(array_filter($transactions, fn ($tx) => ($tx['loan_id'] ?? null) == $loan['id'] && ($tx['family_type'] ?? $tx['type']) === 'expense' && $tx['type'] === 'expense' && ! ($tx['informational'] ?? false)), 'amount'));
            $reserves = array_values(array_filter($transactions, fn ($tx) => ($tx['loan_id'] ?? null) == $loan['id'] && str_starts_with($tx['date'], $prefix) && $tx['type'] === 'transfer'));

            return [...$loan, 'terms' => $terms, 'due_date' => $due, 'expected' => $expected, 'paid' => $paid, 'remaining' => max(0, $expected - $paid), 'annual_paid' => $annualPaid, 'payments' => $payments, 'reserves' => $reserves, 'history' => $history];
        }, $loans);
    }

    /** All screens use this single source of truth. Dates are Budapest calendar dates. */
    public function year(int $year, array $categories, array $plans, array $transactions, array $settings, string $today): array
    {
        $transactions = $this->familyTransactions($transactions);
        $months = [];
        $opening = new Exact($settings['opening_amount'] ?? 0);
        $rate = new Exact($settings['savings_rate'] ?? '10');
        $carry = [];
        $annualCategories = [];
        $annualIncome = ['Munka' => 0, 'Egyéb' => 0];
        $totalCf = new Exact;
        $totalSavings = new Exact;
        $totalActual = 0;
        $totalChild = 0;
        $yearDays = (int) (new DateTimeImmutable("$year-12-31"))->format('z') + 1;

        for ($month = 1; $month <= 12; $month++) {
            $prefix = sprintf('%04d-%02d', $year, $month);
            $daysCount = (int) (new DateTimeImmutable($prefix.'-01'))->format('t');
            $divisor = substr($today, 0, 7) === $prefix ? (int) substr($today, 8, 2) : $daysCount;
            $days = array_fill(1, $daysCount, ['expense' => 0, 'income' => 0]);
            $byCategory = [];
            $income = ['Munka' => 0, 'Egyéb' => 0];
            $actual = 0;
            $child = 0;

            foreach ($transactions as $tx) {
                if (! str_starts_with($tx['date'], $prefix.'-') || ($tx['informational'] ?? false)) {
                    continue;
                }
                $day = (int) substr($tx['date'], 8, 2);
                $amount = (int) $tx['amount'];
                if ($tx['type'] === 'expense') {
                    $id = $tx['category_id'];
                    $byCategory[$id] = ($byCategory[$id] ?? 0) + $amount;
                    $days[$day]['expense'] += $amount;
                } elseif ($tx['type'] === 'income') {
                    $source = $tx['source'] ?? 'Egyéb';
                    $income[$source] = ($income[$source] ?? 0) + $amount;
                    $days[$day]['income'] += $amount;
                } elseif ($tx['type'] === 'saving') {
                    $actual += $amount;
                } elseif ($tx['type'] === 'saving_return') {
                    $actual -= $amount;
                } elseif ($tx['type'] === 'child_saving') {
                    $child += $amount;
                }
            }

            $categoryRows = [];
            $totalPlan = 0;
            foreach ($categories as $category) {
                $id = $category['id'];
                $budget = (int) ($plans[$month][$id] ?? 0);
                $spent = $byCategory[$id] ?? 0;
                $previous = $category['carryover'] ? ($carry[$id] ?? 0) : 0;
                $remaining = $previous + $budget - $spent;
                $carry[$id] = $remaining;
                $totalPlan += $budget;
                $categoryRows[] = [...$category, 'plan' => $budget, 'spent' => $spent, 'previous' => $previous, 'remaining' => $remaining, 'difference' => $spent - $budget, 'differencePercent' => $budget === 0 ? null : (new Exact($spent - $budget))->divide($budget)->times(100)];
                $annualCategories[$id]['spent'] = ($annualCategories[$id]['spent'] ?? 0) + $spent;
                $annualCategories[$id]['plan'] = ($annualCategories[$id]['plan'] ?? 0) + $budget;
            }

            $expense = array_sum($byCategory);
            $grossIncome = array_sum($income);
            $savings = (new Exact($grossIncome))->times($rate)->divide(100);
            $usable = (new Exact($grossIncome))->minus($savings);
            $cf = $usable->minus($expense)->minus($child);
            $closing = $opening->plus($cf);
            $max = max(array_column($days, 'expense'));
            $mostExpensive = $max > 0 ? sprintf('%s-%02d', $prefix, array_search($max, array_column($days, 'expense'), true) + 1) : null;
            $months[$month] = ['month' => $month, 'daysCount' => $daysCount, 'elapsedDays' => $divisor, 'days' => $days, 'categories' => $categoryRows, 'expense' => $expense, 'income' => $grossIncome, 'incomeSources' => $income, 'plan' => $totalPlan, 'savingsTarget' => $savings, 'actualSavings' => $actual, 'savingsRemaining' => $savings->minus($actual), 'childSavings' => $child, 'usableIncome' => $usable, 'cf' => $cf, 'opening' => $opening, 'closing' => $closing, 'dailyAverage' => (new Exact($expense))->divide($divisor), 'mostExpensiveDay' => $mostExpensive, 'mostExpensiveAmount' => $max];
            $opening = $closing;
            $totalCf = $totalCf->plus($cf);
            $totalSavings = $totalSavings->plus($savings);
            $totalActual += $actual;
            $totalChild += $child;
            foreach (['Munka', 'Egyéb'] as $source) {
                $annualIncome[$source] += $income[$source];
            }
        }

        $annualRows = [];
        foreach ($categories as $category) {
            $row = $annualCategories[$category['id']];
            $annualRows[] = [...$category, ...$row, 'monthlyAverage' => (new Exact($row['spent']))->divide(12), 'dailyAverage' => (new Exact($row['spent']))->divide($yearDays), 'difference' => $row['spent'] - $row['plan'], 'differencePercent' => $row['plan'] === 0 ? null : (new Exact($row['spent'] - $row['plan']))->divide($row['plan'])->times(100)];
        }
        $annualExpense = array_sum(array_column($annualRows, 'spent'));
        $gross = array_sum($annualIncome);
        $sources = [];
        foreach ($annualIncome as $name => $amount) {
            $sources[$name] = ['total' => $amount, 'monthlyAverage' => (new Exact($amount))->divide(12), 'dailyAverage' => (new Exact($amount))->divide($yearDays)];
        }

        return ['months' => $months, 'annual' => ['daysCount' => $yearDays, 'categories' => $annualRows, 'incomeSources' => $sources, 'expense' => $annualExpense, 'income' => $gross, 'monthlyExpenseAverage' => (new Exact($annualExpense))->divide(12), 'dailyExpenseAverage' => (new Exact($annualExpense))->divide($yearDays), 'monthlyIncomeAverage' => (new Exact($gross))->divide(12), 'dailyIncomeAverage' => (new Exact($gross))->divide($yearDays), 'savingsTarget' => $totalSavings, 'usableIncome' => (new Exact($gross))->minus($totalSavings), 'savingsRemaining' => $totalSavings->minus($totalActual), 'actualSavings' => $totalActual, 'childSavings' => $totalChild, 'cf' => $totalCf, 'monthlyCf' => $totalCf->divide(12), 'dailyCf' => $totalCf->divide($yearDays), 'closing' => $opening]];
    }

    public function accountBalance(array $account, array $transactions, string $through): int
    {
        $balance = (int) $account['opening_balance'];
        foreach ($transactions as $tx) {
            if ($tx['date'] > $through || $tx['date'] < $account['opening_date']) {
                continue;
            }
            $amount = (int) $tx['amount'];
            if (($tx['target_account_id'] ?? null) == $account['id']) {
                $balance += $tx['type'] === 'saving_return' ? -$amount : $amount;
            }
            if ($tx['account_id'] != $account['id']) {
                continue;
            }
            $balance += in_array($tx['type'], ['income', 'saving_return', 'loan_repayment'], true) ? $amount : -$amount;
            $balance -= (int) ($tx['fee_amount'] ?? 0);
        }

        return $balance;
    }
}
