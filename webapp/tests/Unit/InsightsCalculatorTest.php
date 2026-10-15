<?php

namespace Tests\Unit;

use App\Finance\Calculator;
use App\Finance\RecurringPlanner;
use PHPUnit\Framework\TestCase;

class InsightsCalculatorTest extends TestCase
{
    public function test_exact_budget_thresholds_zero_budget_and_negative_carry(): void
    {
        $calculator = new Calculator;
        foreach ([[1000, 799, 'none'], [1000, 800, 'near'], [1000, 999, 'near'], [1000, 1000, 'reached'], [1000, 1001, 'over'], [0, 0, 'none'], [0, 1, 'unplanned'], [-100, 0, 'over'], [-100, 1, 'unplanned'], [10000000001, 8000000000, 'none'], [10000000001, 8000000001, 'near']] as [$available, $spent, $state]) {
            self::assertSame($state, $calculator->budgetState($available, $spent));
        }
    }

    public function test_budget_warning_uses_actual_carry_and_recalculates_after_a_removed_payment(): void
    {
        $calculator = new Calculator;
        $categories = [['id' => 1, 'name' => 'Étel', 'carryover' => true]];
        $transactions = [['id' => 1, 'date' => '2026-01-02', 'type' => 'expense', 'amount' => 200, 'category_id' => 1], ['id' => 2, 'date' => '2026-02-02', 'type' => 'expense', 'amount' => 1440, 'category_id' => 1]];
        $report = $calculator->year(2026, $categories, [1 => [1 => 1000], 2 => [1 => 1000]], $transactions, [], '2026-02-02');
        $february = $report['months'][2]['categories'][0];
        self::assertSame(1800, $february['available']);
        self::assertSame('near', $february['budgetState']);
        self::assertSame('80', (string) $february['utilization']->number);
        $after = $calculator->year(2026, $categories, [1 => [1 => 1000], 2 => [1 => 1000]], [$transactions[0]], [], '2026-02-02');
        self::assertSame('none', $after['months'][2]['categories'][0]['budgetState']);
        self::assertSame(1800, $after['months'][2]['categories'][0]['remaining']);
    }

    public function test_business_and_personal_reconcile_across_years_with_fees_pocket_funding_and_savings(): void
    {
        $raw = [];
        foreach ([
            ['income', 1000, true, '2025-12-31', []], ['income', 600, false, '2026-01-01', []],
            ['expense', 100, true, '2026-01-02', []], ['expense', 200, false, '2026-01-02', []],
            ['transfer', 10000, true, '2026-01-03', ['fee_amount' => 5, 'fee_category_id' => 18]],
            ['transfer', 300, false, '2026-01-04', ['family_type' => 'expense']],
            ['expense', 999, false, '2026-01-05', ['informational' => true]],
            ['child_saving', 500, false, '2026-01-06', []], ['saving', 2000, true, '2026-01-06', []],
            ['saving_return', 500, true, '2026-01-06', []], ['expense', 123, false, '2026-02-01', []],
        ] as $index => [$type, $amount, $business, $date, $extra]) {
            $raw[] = ['id' => $index + 1, 'type' => $type, 'amount' => $amount, 'business' => $business, 'date' => $date, 'category_id' => 1, 'account_id' => 1, 'fee_amount' => 0, ...$extra];
        }
        $report = (new Calculator)->businessReport($raw, '2025-12-31', '2026-01-31');
        self::assertSame(['income' => 1600, 'expense' => 605, 'difference' => 995], $report['totals']['all']);
        self::assertSame(['income' => 1000, 'expense' => 105, 'difference' => 895], $report['totals']['business']);
        self::assertSame(['income' => 600, 'expense' => 500, 'difference' => 100], $report['totals']['personal']);
        self::assertCount(6, $report['rows']);
        self::assertCount(1, array_filter($report['rows'], fn ($row) => $row['id'] === 5));
        self::assertSame(605, array_sum(array_column($report['categories'], 'all')));
        self::assertSame(['2025-12', '2026-01'], array_keys($report['months']));
        foreach (['income', 'expense', 'difference'] as $field) {
            self::assertSame($report['totals']['all'][$field], $report['totals']['business'][$field] + $report['totals']['personal'][$field]);
            self::assertSame($report['totals']['all'][$field], array_sum(array_map(fn ($month) => $month['all'][$field], $report['months'])));
        }
        self::assertSame(0, (new Calculator)->businessReport($raw, '2025-01-01', '2025-12-30')['totals']['all']['expense']);
    }

    public function test_recurring_changes_prefer_the_plan_then_previous_actual_and_ignore_deleted_or_internal_records(): void
    {
        $planner = new RecurringPlanner;
        $base = ['template_id' => 1, 'type' => 'expense', 'informational' => false, 'deleted_at' => null];
        $previous = [...$base, 'id' => 1, 'occurrence_date' => '2026-09-10', 'date' => '2026-09-10', 'amount' => 1000];
        $current = [...$base, 'id' => 2, 'occurrence_date' => '2026-10-10', 'date' => '2026-10-10', 'amount' => 1200];
        $occurrence = ['paid' => true, 'transaction_id' => 2, 'template_id' => 1, 'name' => 'Előfizetés', 'due_date' => '2026-10-10', 'payload' => ['amount' => 1100]];
        $change = $planner->amountChanges([$occurrence], [$previous, $current])[0];
        self::assertSame('plan', $change['reference_kind']);
        self::assertSame(100, $change['difference']);
        $occurrence['payload']['amount'] = 1200;
        $change = $planner->amountChanges([$occurrence], [$previous, $current])[0];
        self::assertSame('previous', $change['reference_kind']);
        self::assertSame(200, $change['difference']);
        self::assertSame([], $planner->amountChanges([$occurrence], [[...$previous, 'deleted_at' => '2026-10-01'], $current]));
        self::assertSame([], $planner->amountChanges([$occurrence], [$previous, [...$current, 'informational' => true]]));
        self::assertSame([], $planner->amountChanges([$occurrence], [$previous, [...$current, 'deleted_at' => '2026-10-15']]));
        self::assertSame([], $planner->amountChanges([[...$occurrence, 'paid' => false]], [$previous, $current]));
    }
}
