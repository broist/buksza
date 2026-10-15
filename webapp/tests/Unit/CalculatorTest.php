<?php

namespace Tests\Unit;

use App\Finance\Calculator;
use App\Finance\Exact;
use PHPUnit\Framework\TestCase;

class CalculatorTest extends TestCase
{
    private function report(array $tx = [], array $plans = [], array $settings = [], int $year = 2026, string $today = '2026-10-14', bool $carry = true): array
    {
        return (new Calculator)->year($year, [['id' => 1, 'name' => 'élelmiszer', 'carryover' => $carry]], $plans, $tx, ['opening_amount' => 0, 'savings_rate' => '10', ...$settings], $today);
    }

    private function tx(string $type, int $amount, string $date = '2026-10-14', array $extra = []): array
    {
        return ['type' => $type, 'amount' => $amount, 'date' => $date, 'category_id' => 1, 'source' => 'Munka', 'account_id' => 1, ...$extra];
    }

    private function exact(int|string $expected, Exact $actual): void
    {
        self::assertSame((string) (new Exact($expected))->number, (string) $actual->number);
    }

    public function test_month_length_leap_year_and_elapsed_today(): void
    {
        foreach ([[2026, 2, 28], [2024, 2, 29], [2026, 4, 30], [2026, 1, 31], [2000, 2, 29], [2100, 2, 28]] as [$year,$month,$days]) {
            $r = $this->report(year: $year);
            self::assertSame($days, $r['months'][$month]['daysCount']);
            self::assertCount($days, $r['months'][$month]['days']);
        }
        $r = $this->report([$this->tx('expense', 14000)]);
        self::assertSame(14, $r['months'][10]['elapsedDays']);
        $this->exact(1000, $r['months'][10]['dailyAverage']);
        self::assertSame(30, $r['months'][9]['elapsedDays']);
        self::assertSame(31, $r['months'][12]['elapsedDays']);
    }

    public function test_empty_month_has_zero_average_and_no_most_expensive_day(): void
    {
        $m = $this->report()['months'][10];
        $this->exact(0, $m['dailyAverage']);
        self::assertNull($m['mostExpensiveDay']);
    }

    public function test_only_expenses_count_and_daily_sum_matches_month(): void
    {
        $tx = [$this->tx('expense', 2500), $this->tx('expense', 1500), $this->tx('expense', 4000, '2026-10-13')];
        foreach (['transfer', 'saving', 'child_saving', 'loan_given', 'loan_repayment', 'income'] as $type) {
            $tx[] = $this->tx($type, 10000);
        }
        $tx[] = $this->tx('expense', 99000, extra: ['informational' => true]);
        $m = $this->report($tx)['months'][10];
        self::assertSame(8000, $m['expense']);
        self::assertSame(4000, $m['days'][14]['expense']);
        self::assertSame(8000, array_sum(array_column($m['days'], 'expense')));
        self::assertSame('2026-10-13', $m['mostExpensiveDay']); // Earliest day wins ties.
    }

    public function test_income_sources_savings_and_child_are_separate(): void
    {
        $r = $this->report([$this->tx('income', 800000), $this->tx('income', 200000, extra: ['source' => 'Egyéb']), $this->tx('expense', 600000), $this->tx('saving', 50000), $this->tx('child_saving', 20000)]);
        $m = $r['months'][10];
        self::assertSame(1000000, $m['income']);
        self::assertSame(['Munka' => 800000, 'Egyéb' => 200000], $m['incomeSources']);
        $this->exact(100000, $m['savingsTarget']);
        $this->exact(900000, $m['usableIncome']);
        $this->exact(280000, $m['cf']);
        self::assertSame(50000, $m['actualSavings']);
        self::assertSame(20000, $m['childSavings']);
        $this->exact(50000, $m['savingsRemaining']);
        $this->exact(280000, $r['annual']['cf']);
    }

    public function test_actual_savings_do_not_get_deducted_from_planned_cf_twice(): void
    {
        $base = [$this->tx('income', 100000), $this->tx('expense', 20000)];
        $m = $this->report([...$base, $this->tx('saving', 40000), $this->tx('saving_return', 10000)])['months'][10];
        self::assertSame(30000, $m['actualSavings']);
        $this->exact(70000, $m['cf']);
        $this->exact(-20000, $m['savingsRemaining']);
    }

    public function test_zero_hundred_and_decimal_savings_rates_are_exact(): void
    {
        foreach (['0' => '1001', '100' => '0', '12.5' => '875.875'] as $rate => $expected) {
            $m = $this->report([$this->tx('income', 1001)], settings: ['savings_rate' => (string) $rate])['months'][10];
            $this->exact($expected, $m['cf']);
        }
    }

    public function test_monthly_opening_and_closing_chain_and_january_opening(): void
    {
        $r = $this->report([$this->tx('income', 100000, '2026-01-01'), $this->tx('expense', 10000, '2026-02-01')], settings: ['opening_amount' => 5000]);
        $this->exact(5000, $r['months'][1]['opening']);
        $this->exact(95000, $r['months'][1]['closing']);
        $this->exact(95000, $r['months'][2]['opening']);
        $this->exact(85000, $r['months'][2]['closing']);
        $this->exact(85000, $r['annual']['closing']);
    }

    public function test_positive_and_negative_carryover_and_no_year_carry(): void
    {
        $r = $this->report([$this->tx('expense', 80, '2026-01-01'), $this->tx('expense', 150, '2026-02-01'), $this->tx('expense', 999, '2025-12-31')], [1 => [1 => 100], 2 => [1 => 100], 3 => [1 => 100]]);
        self::assertSame(20, $r['months'][1]['categories'][0]['remaining']);
        self::assertSame(20, $r['months'][2]['categories'][0]['previous']);
        self::assertSame(-30, $r['months'][2]['categories'][0]['remaining']);
        self::assertSame(70, $r['months'][3]['categories'][0]['remaining']);
    }

    public function test_disabled_carryover_resets_every_month(): void
    {
        $r = $this->report([$this->tx('expense', 80, '2026-01-01')], [1 => [1 => 100], 2 => [1 => 100]], carry: false);
        self::assertSame(0, $r['months'][2]['categories'][0]['previous']);
        self::assertSame(100, $r['months'][2]['categories'][0]['remaining']);
    }

    public function test_plan_actual_difference_percentage_and_zero_plan(): void
    {
        $m = $this->report([$this->tx('expense', 125)], [10 => [1 => 100]])['months'][10];
        self::assertSame(25, $m['categories'][0]['difference']);
        $this->exact(25, $m['categories'][0]['differencePercent']);
        self::assertNull($this->report([$this->tx('expense', 125)])['months'][10]['categories'][0]['differencePercent']);
    }

    public function test_annual_totals_sources_averages_and_cf_equal_months(): void
    {
        foreach ([2026 => 365, 2024 => 366] as $year => $days) {
            $tx = [];
            $plans = [];
            for ($m = 1; $m <= 12; $m++) {
                $date = sprintf('%d-%02d-01', $year, $m);
                $plans[$m] = [1 => 20000];
                $tx[] = $this->tx('expense', 10000, $date);
                $tx[] = $this->tx('income', 20000, $date);
                $tx[] = $this->tx('income', 10000, $date, ['source' => 'Egyéb']);
                $tx[] = $this->tx('child_saving', 2000, $date);
            }
            $r = $this->report($tx, $plans, year: $year);
            $a = $r['annual'];
            self::assertSame($days, $a['daysCount']);
            self::assertSame(120000, $a['expense']);
            self::assertSame(360000, $a['income']);
            $this->exact(10000, $a['monthlyExpenseAverage']);
            $this->exact("120000/$days", $a['dailyExpenseAverage']);
            $this->exact(30000, $a['monthlyIncomeAverage']);
            $this->exact("360000/$days", $a['dailyIncomeAverage']);
            $this->exact(180000, $a['cf']);
            $this->exact(15000, $a['monthlyCf']);
            $this->exact("180000/$days", $a['dailyCf']);
            self::assertSame(240000, $a['incomeSources']['Munka']['total']);
            $this->exact(20000, $a['incomeSources']['Munka']['monthlyAverage']);
            self::assertSame(-120000, $a['categories'][0]['difference']);
            $this->exact(-50, $a['categories'][0]['differencePercent']);
        }
    }

    public function test_account_balance_is_actual_and_transfers_have_two_sides(): void
    {
        $c = new Calculator;
        $account = ['id' => 1, 'opening_balance' => 0, 'opening_date' => '2026-01-01'];
        $tx = [$this->tx('income', 1000000), $this->tx('expense', 600000), $this->tx('saving', 50000), $this->tx('child_saving', 20000), $this->tx('transfer', 100000, extra: ['target_account_id' => 2]), $this->tx('transfer', 100000, extra: ['account_id' => 2, 'target_account_id' => 1])];
        self::assertSame(330000, $c->accountBalance($account, $tx, '2026-10-31'));
        self::assertSame(0, $c->accountBalance(['id' => 2, 'opening_balance' => 0, 'opening_date' => '2026-01-01'], $tx, '2026-10-31'));
        self::assertSame(0, $c->accountBalance($account, $tx, '2026-10-01'));
    }

    public function test_exact_money_never_rounds_and_exposes_fraction(): void
    {
        $value = (new Exact(1000))->divide(3)->jsonSerialize();
        self::assertSame('1000/3',$value['exact']);
        self::assertSame('333,333333…',$value['text']);
        self::assertSame('875,875',(new Exact('875.875'))->jsonSerialize()['text']);
        self::assertSame('−1 234',(new Exact(-1234))->jsonSerialize()['text']);
    }
}
