<?php

namespace App\Http\Controllers;

use App\Models\Account;
use Carbon\CarbonImmutable;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class AccountController extends Controller
{
    public function save(Request $request, ?Account $account = null)
    {
        $request->merge(['funding_category_id' => $request->input('funding_category_id'), 'parent_id' => $request->input('parent_id')]);
        $data = $request->validate([
            'name' => 'required|string|max:120', 'owner' => 'required|string|max:60',
            'kind' => ['required', Rule::in(['bank', 'subaccount', 'szep', 'health', 'child', 'snapshot', 'saving'])],
            'mode' => ['required', Rule::in(['detailed', 'aggregate', 'pocket'])],
            'bank' => 'nullable|string|max:120', 'role' => 'nullable|string|max:2000', 'active' => 'required|boolean',
            'parent_id' => 'nullable|integer|exists:accounts,id', 'funding_category_id' => 'nullable|integer|exists:categories,id',
            'opening_balance' => 'required|integer|between:-10000000000,10000000000',
            'opening_date' => 'required|date_format:Y-m-d|after_or_equal:2000-01-01|before_or_equal:2100-12-31',
            'effective_date' => 'required|date_format:Y-m-d|after_or_equal:2000-01-01|before_or_equal:2100-12-31',
        ]);
        $effective = $data['effective_date'];
        unset($data['effective_date']);
        if ($account?->id === 1 && ($data['kind'] !== 'bank' || $data['mode'] !== 'detailed' || ! $data['active'])) {
            throw ValidationException::withMessages(['kind' => 'A főszámla aktív, részletes bankszámla marad.']);
        }
        if ($account && $account->kind !== $data['kind'] && (DB::table('transactions')->where('account_id', $account->id)->orWhere('target_account_id', $account->id)->exists() || DB::table('account_snapshots')->where('account_id', $account->id)->exists())) {
            throw ValidationException::withMessages(['kind' => 'A már használt számla típusa nem változtatható meg. Új számlát vehetsz fel.']);
        }
        if ($data['kind'] === 'subaccount') {
            $parent = Account::find($data['parent_id']);
            if (! $parent || $parent->kind !== 'bank' || $parent->id === $account?->id) {
                throw ValidationException::withMessages(['parent_id' => 'Az alszámlához külön bankszámlát válassz.']);
            }
        } else {
            $data['parent_id'] = null;
        }
        if (in_array($data['kind'], ['subaccount', 'szep', 'health', 'child', 'snapshot', 'saving']) && $data['mode'] !== 'detailed') {
            throw ValidationException::withMessages(['mode' => 'Ez a számlatípus részletes elszámolást használ.']);
        }
        if ($data['mode'] === 'pocket') {
            $data['funding_category_id'] = 21;
        }
        if ($data['mode'] === 'aggregate' && ! $data['funding_category_id']) {
            throw ValidationException::withMessages(['funding_category_id' => 'Válaszd ki a feltöltés költési kategóriáját.']);
        }
        $changed = ! $account || $account->mode !== $data['mode'] || $account->funding_category_id != $data['funding_category_id'];
        if ($account && $changed && $effective < now()->toDateString()) {
            throw ValidationException::withMessages(['effective_date' => 'A módváltás ma vagy később kezdődhet; a régi elszámolás megmarad.']);
        }
        DB::transaction(function () use ($account, $data, $changed, $effective) {
            $saved = $account ?? new Account;
            $saved->fill($data)->save();
            if ($changed) {
                DB::table('account_modes')->updateOrInsert(['account_id' => $saved->id, 'effective_date' => $account ? $effective : '2000-01-01'], ['mode' => $data['mode'], 'funding_category_id' => $data['funding_category_id'], 'created_at' => now(), 'updated_at' => now()]);
            }
        });

        return back()->with('message', 'Számla elmentve. A korábbi tételek elszámolása megmaradt.');
    }

    public function snapshot(Request $request, Account $account)
    {
        abort_unless($account->kind === 'snapshot', 422);
        $data = $request->validate(['date' => 'required|date_format:Y-m-d|after_or_equal:2000-01-01|before_or_equal:today', 'amount' => 'required|integer|between:-10000000000,10000000000', 'note' => 'nullable|string|max:2000']);
        if (CarbonImmutable::parse($data['date'])->endOfMonth()->toDateString() !== $data['date']) {
            throw ValidationException::withMessages(['date' => 'A hónap utolsó napi egyenlegét add meg.']);
        }
        DB::table('account_snapshots')->updateOrInsert(['account_id' => $account->id, 'date' => $data['date']], ['amount' => $data['amount'], 'note' => $data['note'], 'created_at' => now(), 'updated_at' => now()]);

        return back()->with('message', 'Havi helyzetjelentés elmentve. Nem keletkezett pénzmozgás.');
    }
}
