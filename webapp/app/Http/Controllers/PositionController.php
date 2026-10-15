<?php

namespace App\Http\Controllers;

use App\Finance\Positions;
use App\Finance\TransactionInput;
use App\Models\Transaction;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class PositionController extends Controller
{
    public function save(Request $request, Positions $positions, ?int $id = null)
    {
        $data = $request->validate([
            'kind' => 'required|in:receivable,payable,reserve,project', 'name' => 'required|string|max:120',
            'start_date' => 'required|date_format:Y-m-d|after_or_equal:2000-01-01|before_or_equal:today',
            'opening_amount' => 'required|integer|between:0,10000000000', 'target_amount' => 'required|integer|between:0,10000000000',
            'monthly_income' => 'required|integer|between:0,10000000000', 'due_date' => 'nullable|date_format:Y-m-d|after_or_equal:start_date|before_or_equal:2100-12-31', 'note' => 'nullable|string|max:2000',
        ]);
        DB::transaction(function () use ($id, $data, $positions) {
            if ($id) {
                $old = (array) DB::table('finance_positions')->find($id);
                abort_unless($old, 404);
                abort_unless($old['kind'] === $data['kind'], 422, 'A nyilvántartás típusa nem változtatható.');
                $positions->assertValid([...$data, 'id' => $id], $positions->rows($old));
                DB::table('finance_positions')->where('id', $id)->update([...$data, 'updated_at' => now()]);
            } else {
                DB::table('finance_positions')->insert([...$data, 'created_at' => now(), 'updated_at' => now()]);
            }
        });

        return back()->with('message', 'Nyilvántartás elmentve.');
    }

    public function payment(Request $request, Positions $positions, int $id)
    {
        $p = (array) DB::table('finance_positions')->find($id);
        abort_unless($p, 404);
        $data = app(TransactionInput::class)->validate($request, null, $p);
        $tx = DB::transaction(function () use ($p, $data, $positions) {
            $existing = Transaction::withTrashed()->where('client_id', $data['client_id'])->first();
            if ($existing) {
                abort_unless(DB::table('position_transactions')->where('transaction_id', $existing->id)->where('position_id', $p['id'])->exists(), 422);

                return $existing;
            }
            $positions->assertValid($p, [...$positions->rows($p), $data]);
            $tx = Transaction::create($data);
            DB::table('position_transactions')->insert(['position_id' => $p['id'], 'transaction_id' => $tx->id, 'created_at' => now(), 'updated_at' => now()]);

            return $tx;
        });

        return back()->with('message', 'Pénzmozgás elmentve.')->with('undoId', $tx->id);
    }

    public function link(Request $request, Positions $positions, int $id)
    {
        $data = $request->validate(['transaction_id' => 'required|integer|exists:transactions,id', 'unlink' => 'sometimes|boolean']);
        DB::transaction(function () use ($data, $positions, $id) {
            $p = (array) DB::table('finance_positions')->find($id);
            abort_unless($p && in_array($p['kind'], ['reserve', 'project']), 404);
            $tx = Transaction::findOrFail($data['transaction_id']);
            $link = DB::table('position_transactions')->where('transaction_id', $tx->id)->first();
            if ($data['unlink'] ?? false) {
                abort_unless($link?->position_id == $id, 422);
                $positions->guard($tx, null);
                DB::table('position_transactions')->where('id', $link->id)->delete();
            } else {
                if ($link) {
                    abort_unless($link->position_id == $id, 422);

                    return;
                }
                $positions->assertValid($p, [...$positions->rows($p), $tx->toArray()]);
                DB::table('position_transactions')->insert(['position_id' => $id, 'transaction_id' => $tx->id, 'created_at' => now(), 'updated_at' => now()]);
            }
        });

        return back()->with('message', 'Célhoz rendelés módosítva. A számlaegyenlegek változatlanok.');
    }

    public function transfer(Request $request, Positions $positions)
    {
        $data = $request->validate(['client_id' => 'required|uuid', 'from_id' => 'required|integer|exists:finance_positions,id', 'to_id' => 'required|integer|different:from_id|exists:finance_positions,id', 'date' => 'required|date_format:Y-m-d|before_or_equal:today', 'amount' => 'required|integer|between:1,10000000000', 'note' => 'nullable|string|max:2000']);
        DB::transaction(function () use ($data, $positions) {
            if (DB::table('goal_transfers')->where('client_id', $data['client_id'])->exists()) {
                return;
            }
            foreach ([$data['from_id'], $data['to_id']] as $id) {
                $p = (array) DB::table('finance_positions')->find($id);
                abort_unless(in_array($p['kind'], ['reserve', 'project']), 422);
                $positions->assertValid($p, $positions->rows($p), [...$positions->transfers($id), $data]);
            }
            DB::table('goal_transfers')->insert([...$data, 'created_at' => now(), 'updated_at' => now()]);
        });

        return back()->with('message', 'Zsebek közti átcsoportosítás elmentve.');
    }

    public function deleteTransfer(Positions $positions, int $id)
    {
        DB::transaction(function () use ($positions, $id) {
            $tx = DB::table('goal_transfers')->find($id);
            abort_unless($tx, 404);
            foreach ([$tx->from_id, $tx->to_id] as $pid) {
                $p = (array) DB::table('finance_positions')->find($pid);
                $positions->assertValid($p, $positions->rows($p), array_values(array_filter($positions->transfers($pid), fn ($r) => $r['id'] != $id)));
            }
            DB::table('goal_transfers')->where('id', $id)->delete();
        });

        return back()->with('message', 'Átcsoportosítás törölve.');
    }
}
