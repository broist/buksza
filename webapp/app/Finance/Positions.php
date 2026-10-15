<?php

namespace App\Finance;

use App\Models\Transaction;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/** Positions allocate existing ledger entries; they never create a second cash ledger. */
final class Positions
{
    public const LOAN_TYPES = ['loan_given', 'loan_given_return', 'loan_received', 'loan_repaid'];

    public function types(string $kind): array
    {
        return match ($kind) {
            'receivable' => ['loan_given', 'loan_given_return'],
            'payable' => ['loan_received', 'loan_repaid'],
            default => ['saving', 'saving_return'],
        };
    }

    public function rows(array $position): array
    {
        return Transaction::join('position_transactions as link', 'link.transaction_id', '=', 'transactions.id')->where('link.position_id', $position['id'])->orderBy('date')->orderBy('transactions.id')->get('transactions.*')->toArray();
    }

    public function balance(array $position, array $rows, string $through, array $transfers = []): int
    {
        if ($position['start_date'] > $through) {
            return 0;
        }
        $balance = (int) $position['opening_amount'];
        [$add] = $this->types($position['kind']);
        foreach ($rows as $tx) {
            if ($tx['date'] <= $through) {
                $balance += $tx['type'] === $add ? (int) $tx['amount'] : -(int) $tx['amount'];
            }
        }
        foreach ($transfers as $tx) {
            if ($tx['date'] <= $through) {
                $balance += $tx['from_id'] == $position['id'] ? -(int) $tx['amount'] : (int) $tx['amount'];
            }
        }

        return $balance;
    }

    public function assertValid(array $position, array $rows, ?array $transfers = null): void
    {
        $transfers ??= $this->transfers($position['id']);
        $dates = [];
        foreach ($rows as $row) {
            if (! in_array($row['type'], $this->types($position['kind']), true) || $row['date'] < $position['start_date']) {
                $this->error('A mozgás típusa vagy dátuma nem egyezik a kapcsolódó nyilvántartással.');
            }
            $dates[] = $row['date'];
        }
        foreach ($transfers as $row) {
            if ($row['date'] < $position['start_date']) {
                $this->error('A zsebek közti átcsoportosítás nem előzheti meg a cél kezdetét.');
            }
            $dates[] = $row['date'];
        }
        foreach (array_unique($dates) as $date) {
            if ($this->balance($position, $rows, $date, $transfers) < 0) {
                $this->error('Ez a változtatás túlfizetést vagy negatív zsebegyenleget okozna '.$date.' napján. Előbb javítsd a kapcsolódó későbbi mozgásokat.');
            }
        }
    }

    public function guard(Transaction $tx, ?array $replacement): void
    {
        $id = DB::table('position_transactions')->where('transaction_id', $tx->id)->value('position_id');
        if (! $id) {
            return;
        }
        $position = (array) DB::table('finance_positions')->find($id);
        $rows = array_values(array_filter($this->rows($position), fn ($r) => $r['id'] != $tx->id));
        if ($replacement) {
            $rows[] = $replacement;
        }
        $this->assertValid($position, $rows);
    }

    public function transfers(int $id): array
    {
        return DB::table('goal_transfers')->where(fn ($q) => $q->where('from_id', $id)->orWhere('to_id', $id))->orderBy('date')->orderBy('id')->get()->map(fn ($r) => (array) $r)->all();
    }

    public function report(string $through): array
    {
        $items = [];
        foreach (DB::table('finance_positions')->orderBy('id')->get() as $row) {
            $p = (array) $row;
            foreach (['opening_amount', 'target_amount', 'monthly_income'] as $key) {
                $p[$key] = (int) $p[$key];
            }
            $rows = $this->rows($p);
            $transfers = $this->transfers($p['id']);
            $balance = $this->balance($p, $rows, $through, $transfers);
            $target = $p['kind'] === 'reserve' ? $p['monthly_income'] * 6 : $p['target_amount'];
            $items[] = [...$p, 'balance' => $balance, 'target' => $target, 'remaining' => max(0, $target - $balance), 'started' => $p['start_date'] <= $through, 'payments' => array_values(array_filter($rows, fn ($tx) => $tx['date'] <= $through)), 'transfers' => array_values(array_filter($transfers, fn ($tx) => $tx['date'] <= $through))];
        }
        $assigned = DB::table('position_transactions')->pluck('transaction_id');

        return ['through' => $through, 'items' => $items, 'unassigned' => Transaction::whereIn('type', ['saving', 'saving_return'])->whereNotIn('id', $assigned)->where('date', '<=', $through)->orderByDesc('date')->orderByDesc('id')->get()->toArray()];
    }

    public function error(string $message): never
    {
        throw ValidationException::withMessages(['position' => $message]);
    }
}
