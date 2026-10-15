<?php

namespace Tests\Unit;

use App\Finance\Calculator;
use PHPUnit\Framework\TestCase;

class LedgerTest extends TestCase
{
    private function tx(array $extra): array
    {
        return ['id' => 1, 'type' => 'transfer', 'family_type' => 'transfer', 'date' => '2026-10-14', 'amount' => 50000, 'account_id' => 1, 'target_account_id' => 5, 'category_id' => null, 'informational' => false, 'fee_amount' => 0, 'person' => 'Isti', ...$extra];
    }

    public function test_projection_and_reprojection_count_topup_fee_once_and_ignore_internal_purchase(): void
    {
        $calculator = new Calculator;
        $rows = [$this->tx(['family_type' => 'expense', 'category_id' => 21, 'target_account_id' => 4, 'fee_amount' => 100, 'fee_category_id' => 18]), $this->tx(['id' => 2, 'type' => 'expense', 'family_type' => 'expense', 'account_id' => 4, 'target_account_id' => null, 'informational' => true, 'amount' => 3000])];
        $categories = [['id' => 21, 'carryover' => true], ['id' => 18, 'carryover' => true]];
        foreach ([$rows, $calculator->familyTransactions($rows)] as $input) {
            $year = $calculator->year(2026, $categories, [], $input, ['savings_rate' => '10'], '2026-10-14');
            self::assertSame(50100, $year['months'][10]['expense']);
            self::assertSame(50100, $year['months'][10]['days'][14]['expense']);
            self::assertSame(50100, $year['annual']['expense']);
            self::assertSame('−50 100', $year['annual']['cf']->jsonSerialize()['text']);
        }
        self::assertSame(-50100, $calculator->accountBalance(['id' => 1, 'opening_balance' => 0, 'opening_date' => '2026-01-01'], $rows, '2026-10-31'));
        self::assertSame(47000, $calculator->accountBalance(['id' => 4, 'opening_balance' => 0, 'opening_date' => '2026-01-01'], $rows, '2026-10-31'));
    }

    public function test_reserve_round_trip_is_only_balance_movement_and_actual_debit_is_one_expense(): void
    {
        $calculator = new Calculator;
        $rows = [$this->tx(['amount' => 70000]), $this->tx(['account_id' => 5, 'target_account_id' => 1, 'amount' => 50000]), $this->tx(['type' => 'expense', 'family_type' => 'expense', 'target_account_id' => null, 'category_id' => 13])];
        $year = $calculator->year(2026, [['id' => 13, 'carryover' => true]], [], $rows, [], '2026-10-14');
        self::assertSame(50000, $year['annual']['expense']);
        self::assertSame(-70000, $calculator->accountBalance(['id' => 1, 'opening_balance' => 0, 'opening_date' => '2026-01-01'], $rows, '2026-10-31'));
        self::assertSame(20000, $calculator->accountBalance(['id' => 5, 'opening_balance' => 0, 'opening_date' => '2026-01-01'], $rows, '2026-10-31'));
    }

    public function test_loan_due_dates_respect_leap_year_and_actual_payments_do_not_reduce_principal(): void
    {
        $calculator = new Calculator;
        $loan = ['id' => 1, 'outstanding_amount' => 900000];
        $terms = ['start_date' => '2024-01-01', 'end_date' => null, 'closed_date' => null, 'installment' => 50000, 'due_day' => 31];
        $versions = [['id' => 1, 'loan_id' => 1, 'effective_date' => '2024-01-01', 'data' => $terms]];
        $payments = [$this->tx(['type' => 'expense', 'family_type' => 'expense', 'loan_id' => 1, 'date' => '2024-02-29'])];
        $r = $calculator->loans([$loan], $versions, $payments, 2024, 2)[0];
        self::assertSame('2024-02-29', $r['due_date']);
        self::assertSame(50000, $r['paid']);
        self::assertSame(0, $r['remaining']);
        self::assertSame(900000, $r['outstanding_amount']);
        self::assertSame('2026-02-28', $calculator->loans([$loan], $versions, [], 2026, 2)[0]['due_date']);
    }

    public function test_balance_groups_exclude_snapshots_and_do_not_present_unknown_balances_as_zero(): void
    {
        $accounts = [
            ['kind' => 'bank', 'active' => true, 'balance' => 10000],
            ['kind' => 'subaccount', 'active' => true, 'balance' => 3000],
            ['kind' => 'snapshot', 'active' => true, 'balance' => null],
            ['kind' => 'health', 'active' => true, 'balance' => 4000],
            ['kind' => 'szep', 'active' => true, 'balance' => null],
            ['kind' => 'child', 'active' => true, 'balance' => 2000],
        ];
        $groups = (new Calculator)->balanceGroups($accounts);
        self::assertCount(3, $groups);
        self::assertSame(13000, $groups[0]['total']);
        self::assertNull($groups[1]['total']);
        self::assertSame(1, $groups[1]['unknown']);
        self::assertSame(2000, $groups[2]['total']);
    }

    public function test_installment_change_after_due_date_preserves_that_month_and_changes_the_next(): void
    {
        $terms = ['start_date' => '2026-01-01', 'end_date' => null, 'closed_date' => null, 'installment' => 50000, 'due_day' => 10];
        $versions = [
            ['id' => 1, 'loan_id' => 1, 'effective_date' => '2026-01-01', 'data' => $terms],
            ['id' => 2, 'loan_id' => 1, 'effective_date' => '2026-10-14', 'data' => [...$terms, 'installment' => 55000]],
        ];
        $calculator = new Calculator;
        self::assertSame(50000, $calculator->loans([['id' => 1]], $versions, [], 2026, 10)[0]['expected']);
        self::assertSame(55000, $calculator->loans([['id' => 1]], $versions, [], 2026, 11)[0]['expected']);
        $versions[1]['effective_date'] = '2026-10-09';
        self::assertSame(55000, $calculator->loans([['id' => 1]], $versions, [], 2026, 10)[0]['expected']);
    }
}
