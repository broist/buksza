<?php

namespace Tests\Unit;

use App\Finance\Calculator;
use App\Finance\Exact;
use PHPUnit\Framework\TestCase;

class PersonalLoansCalculatorTest extends TestCase
{
    public function test_exact_monthly_annual_and_account_cash_flows_keep_loans_out_of_income_expenses_and_targets(): void
    {
        $tx = fn ($type, $amount, $date) => ['type' => $type, 'amount' => $amount, 'date' => $date, 'account_id' => 1, 'category_id' => 1, 'source' => 'Munka'];
        $rows = [$tx('income', 100001, '2026-01-01'), $tx('loan_given', 100000, '2026-01-02'), $tx('loan_given_return', 33333, '2026-02-01'), $tx('loan_received', 50000, '2026-02-02'), $tx('loan_repaid', 12345, '2026-03-01'), $tx('expense', 1000, '2026-03-02'), $tx('child_saving', 5000, '2026-03-03')];
        $r = (new Calculator)->year(2026, [['id' => 1, 'carryover' => true]], [], $rows, ['savings_rate' => '12.3456', 'opening_amount' => 123], '2026-10-15');
        $this->assertSame(100001, $r['annual']['income']);
        $this->assertSame(1000, $r['annual']['expense']);
        $this->assertSame(-29012, $r['annual']['loanNet']);
        $this->assertSame('192901929/15625', (string) $r['annual']['savingsTarget']->number);
        $sum = new Exact;
        foreach ($r['months'] as $m) {
            $sum = $sum->plus($m['cf']);
        }
        $this->assertSame((string) $sum->number, (string) $r['annual']['cf']->number);
        $this->assertSame((string) $sum->plus(123)->number, (string) $r['annual']['closing']->number);
        $this->assertSame(64989, (new Calculator)->accountBalance(['id' => 1, 'opening_date' => '2026-01-01', 'opening_balance' => 0], $rows, '2026-12-31'));
    }
}
