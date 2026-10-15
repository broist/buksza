<?php

namespace Tests\Unit;

use App\Finance\RecurringPlanner;
use PHPUnit\Framework\TestCase;

class RecurringPlannerTest extends TestCase
{
    private function data(array $extra = []): array
    {
        return ['name' => 'Havi sablon', 'active' => true, 'frequency' => 'monthly', 'interval' => 1, 'due_day' => 31, 'due_month' => 2, 'start_date' => '2024-01-01', 'end_date' => null, 'loan_terms' => false, 'payload' => ['type' => 'expense', 'amount' => 100, 'loan_id' => 1], ...$extra];
    }

    private function month(array $data, int $year, int $month): array
    {
        return (new RecurringPlanner)->month([['id' => 1, 'name' => $data['name']]], [['id' => 1, 'template_id' => 1, 'effective_date' => $data['start_date'], 'data' => $data]], [], [], [], $year, $month);
    }

    public function test_monthly_last_day_interval_and_end_date(): void
    {
        foreach ([[2024, 2, '2024-02-29'], [2026, 2, '2026-02-28'], [2026, 4, '2026-04-30'], [2026, 10, '2026-10-31']] as [$year, $month, $expected]) {
            self::assertSame($expected, $this->month($this->data(), $year, $month)[0]['due_date']);
        }
        self::assertCount(0, $this->month($this->data(['interval' => 2]), 2026, 2));
        self::assertCount(1, $this->month($this->data(['interval' => 2]), 2026, 3));
        self::assertCount(0, $this->month($this->data(['end_date' => '2026-02-27']), 2026, 2));
        self::assertCount(0, $this->month($this->data(['active' => false]), 2026, 2));
    }

    public function test_weekly_anchor_crosses_month_boundary_and_yearly_leap_day(): void
    {
        $weekly = $this->data(['frequency' => 'weekly', 'start_date' => '2026-09-28', 'interval' => 2]);
        self::assertSame(['2026-10-12', '2026-10-26'], array_column($this->month($weekly, 2026, 10), 'due_date'));
        $yearly = $this->data(['frequency' => 'yearly', 'interval' => 2]);
        self::assertSame('2026-02-28', $this->month($yearly, 2026, 2)[0]['due_date']);
        self::assertSame('2028-02-29', $this->month($yearly, 2028, 2)[0]['due_date']);
        self::assertCount(0, $this->month($yearly, 2027, 2));
        self::assertCount(0, $this->month($yearly, 2026, 3));
    }

    public function test_each_loan_template_uses_its_own_contract_version_and_closure(): void
    {
        $planner = new RecurringPlanner;
        $templates = [['id' => 1], ['id' => 2]];
        $templateVersions = array_map(fn ($id) => ['id' => $id, 'template_id' => $id, 'effective_date' => '2026-01-01', 'data' => $this->data(['loan_terms' => true, 'payload' => ['loan_id' => $id, 'amount' => 1]])], [1, 2]);
        $terms = ['start_date' => '2026-01-01', 'end_date' => null, 'closed_date' => null, 'due_day' => 10, 'installment' => 50000];
        $loans = [['id' => 1], ['id' => 2]];
        $versions = [['id' => 1, 'loan_id' => 1, 'effective_date' => '2026-01-01', 'data' => $terms], ['id' => 2, 'loan_id' => 1, 'effective_date' => '2026-10-14', 'data' => [...$terms, 'installment' => 55000]], ['id' => 3, 'loan_id' => 2, 'effective_date' => '2026-01-01', 'data' => [...$terms, 'due_day' => 31, 'installment' => 30000, 'closed_date' => '2026-10-31']]];
        $october = $planner->month($templates, $templateVersions, [], $loans, $versions, 2026, 10);
        self::assertSame([50000, 30000], array_column(array_column($october, 'payload'), 'amount'));
        self::assertSame(['2026-10-10', '2026-10-31'], array_column($october, 'due_date'));
        $november = $planner->month($templates, $templateVersions, [], $loans, $versions, 2026, 11);
        self::assertCount(1, $november);
        self::assertSame(55000, $november[0]['payload']['amount']);
    }

    public function test_early_paid_item_remains_visible_after_template_is_disabled(): void
    {
        $data = $this->data(['active' => false]);
        $rows = (new RecurringPlanner)->month([['id' => 1, 'name' => 'Korai fizetés']], [['id' => 1, 'template_id' => 1, 'effective_date' => '2026-10-14', 'data' => $data]], [['id' => 10, 'template_id' => 1, 'occurrence_date' => '2026-10-31', 'date' => '2026-10-12', 'amount' => 100, 'deleted_at' => null]], [], [], 2026, 10);
        self::assertCount(1, $rows);
        self::assertTrue($rows[0]['paid']);
        self::assertSame(100, $rows[0]['record_amount']);
    }
}
