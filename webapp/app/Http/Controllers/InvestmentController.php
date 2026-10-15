<?php

namespace App\Http\Controllers;

use App\Models\Investment;
use App\Models\Transaction;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

class InvestmentController extends Controller
{
    public function save(Request $request, ?Investment $investment = null)
    {
        $data = $request->validate(['name' => 'required|string|max:100', 'series' => 'nullable|string|max:100', 'beneficiary' => 'required|string|max:60', 'account_id' => ['required', Rule::exists('accounts', 'id')->where('kind', 'child')], 'start_date' => 'required|date_format:Y-m-d|after_or_equal:2000-01-01|before_or_equal:today', 'opening_amount' => 'required|integer|between:0,10000000000', 'note' => 'nullable|string|max:2000']);
        if ($investment?->exists) {
            $linked = Transaction::where('investment_id', $investment->id)->get();
            abort_if($linked->contains(fn ($tx) => $tx->target_account_id != $data['account_id'] || $tx->date < $data['start_date']), 422, 'A kapcsolt befizetések nem illenek az új számlához vagy kezdődátumhoz.');
            abort_if(DB::table('investment_values')->where('investment_id', $investment->id)->where('date', '<', $data['start_date'])->exists(), 422, 'Már van értékjelentés a megadott új kezdődátum előtt.');
            $investment->update($data);
        } else {
            Investment::create($data);
        }

        return back()->with('message', 'Babakötvény elmentve.');
    }

    public function plan(Request $request, Investment $investment)
    {
        $data = $request->validate(['effective_date' => 'required|date_format:Y-m-d|after_or_equal:2000-01-01|before_or_equal:2100-12-31', 'amount' => 'required|integer|between:0,10000000000']);
        abort_unless(substr($data['effective_date'], 8) === '01', 422);
        DB::table('investment_plans')->updateOrInsert(['investment_id' => $investment->id, 'effective_date' => $data['effective_date']], ['amount' => $data['amount'], 'created_at' => now(), 'updated_at' => now()]);

        return back()->with('message', 'Havi befizetési terv elmentve. A korábbi hónapok változatlanok.');
    }

    public function value(Request $request, Investment $investment)
    {
        $data = $request->validate(['date' => 'required|date_format:Y-m-d|before_or_equal:today|after_or_equal:'.$investment->start_date, 'amount' => 'required|integer|between:0,100000000000', 'note' => 'nullable|string|max:2000']);
        DB::table('investment_values')->updateOrInsert(['investment_id' => $investment->id, 'date' => $data['date']], [...$data, 'created_at' => now(), 'updated_at' => now()]);

        return back()->with('message', 'Dátumozott MÁK-érték elmentve. Ez nem új befizetés.');
    }

    public function link(Request $request, Investment $investment)
    {
        $data = $request->validate(['transaction_id' => 'required|integer|exists:transactions,id', 'unlink' => 'sometimes|boolean']);
        $tx = Transaction::findOrFail($data['transaction_id']);
        abort_unless($tx->type === 'child_saving' && $tx->target_account_id == $investment->account_id && $tx->date >= $investment->start_date, 422);
        abort_if($tx->investment_id && $tx->investment_id != $investment->id, 422);
        $tx->update(['investment_id' => ($data['unlink'] ?? false) ? null : $investment->id]);

        return back()->with('message', 'A meglévő befizetés kapcsolata elmentve. Nem keletkezett új pénzmozgás.');
    }
}
