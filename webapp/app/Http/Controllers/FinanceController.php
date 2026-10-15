<?php

namespace App\Http\Controllers;

use App\Finance\Calculator;
use App\Finance\PhaseFourReports;
use App\Finance\PlanStore;
use App\Finance\PlanSuggestions;
use App\Finance\RecurringStore;
use App\Finance\TransactionInput;
use App\Models\Account;
use App\Models\Category;
use App\Models\Loan;
use App\Models\Transaction;
use App\Notifications\PushSettings;
use App\Operations\SystemStatus;
use Carbon\CarbonImmutable;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;
use Inertia\Inertia;

class FinanceController extends Controller
{
    public function show(Request $request, Calculator $calculator, PlanStore $store)
    {
        $data = $request->validate(['year' => 'nullable|integer|between:2000,2100', 'month' => 'nullable|integer|between:1,12', 'account_id' => 'nullable|integer|exists:accounts,id', 'person' => 'nullable|string|max:60', 'date' => 'nullable|date_format:Y-m-d|after_or_equal:2000-01-01|before_or_equal:today']);
        $eveningDate = $data['date'] ?? now()->toDateString();
        if ($request->path() === 'esti-rogzites') {
            $data['year'] = (int) substr($eveningDate, 0, 4);
            $data['month'] = (int) substr($eveningDate, 5, 2);
            unset($data['account_id'], $data['person']);
        }
        $year = (int) ($data['year'] ?? now()->year);
        $month = (int) ($data['month'] ?? now()->month);
        $store->ensureYear($year);
        $categories = Category::orderBy('position')->get()->toArray();
        $accounts = Account::orderBy('id')->get()->toArray();
        $accountMap = array_column($accounts, null, 'id');
        $settings = (array) DB::table('year_settings')->where('year', $year)->first();
        $plans = [];
        foreach (DB::table('monthly_plans')->where('year', $year)->get() as $row) {
            $plans[$row->month][$row->category_id] = $row->amount;
        }
        $allTransactions = Transaction::where('date', '<=', "$year-12-31")->orderByDesc('date')->orderByDesc('id')->get()->toArray();
        foreach ($allTransactions as &$tx) {
            $tx['person'] ??= $accountMap[$tx['account_id']]['owner'];
            $tx['fee_person'] = $accountMap[$tx['account_id']]['owner'];
        }
        unset($tx);
        $transactions = array_values(array_filter($allTransactions, fn ($tx) => $tx['date'] >= "$year-01-01"));
        $report = $calculator->year($year, $categories, $plans, $transactions, $settings, now()->toDateString());
        $through = sprintf('%04d-%02d-%02d', $year, $month, $report['months'][$month]['daysCount']);
        $prefix = sprintf('%04d-%02d-', $year, $month);
        $scopeAccount = $data['account_id'] ?? null;
        $scopePerson = $data['person'] ?? null;
        $visible = fn ($tx) => (! $scopeAccount || $tx['account_id'] == $scopeAccount || ($tx['target_account_id'] ?? null) == $scopeAccount) && (! $scopePerson || $tx['person'] === $scopePerson || ($tx['fee_amount'] > 0 && $tx['fee_person'] === $scopePerson));
        $familyRows = $calculator->familyTransactions($transactions);
        $familyVisible = fn ($tx) => (! $scopeAccount || (($tx['family_type'] ?? null) === 'expense' && $tx['type'] === 'expense' && ! ($tx['is_fee'] ?? false) && ($tx['target_account_id'] ?? null) ? $tx['target_account_id'] : $tx['account_id']) == $scopeAccount) && (! $scopePerson || $tx['person'] === $scopePerson);
        $scoped = $calculator->year($year, $categories, $plans, array_values(array_filter($familyRows, $familyVisible)), $settings, now()->toDateString());
        foreach ($accounts as &$a) {
            $a['through'] = $through;
            $a['balance'] = $a['kind'] === 'snapshot' || $through < $a['opening_date'] ? null : $calculator->accountBalance($a, $allTransactions, $through);
            $movements = array_filter($allTransactions, fn ($tx) => str_starts_with($tx['date'], $prefix));
            $a['inflow'] = $a['outflow'] = 0;
            foreach ($movements as $tx) {
                $receives = in_array($tx['type'], ['income', 'saving_return', 'loan_repayment']);
                if ($tx['account_id'] == $a['id']) {
                    $a[$receives ? 'inflow' : 'outflow'] += $tx['amount'];
                    $a['outflow'] += $tx['fee_amount'];
                }
                if (($tx['target_account_id'] ?? null) == $a['id']) {
                    $a[$tx['type'] === 'saving_return' ? 'outflow' : 'inflow'] += $tx['amount'];
                }
            }
            $a['snapshots'] = DB::table('account_snapshots')->where('account_id', $a['id'])->where('date', '<=', $through)->orderByDesc('date')->get()->toArray();
            $a['latest_snapshot'] = $a['snapshots'][0] ?? null;
            $a['month_reported'] = collect($a['snapshots'])->contains(fn ($s) => str_starts_with($s->date, substr($prefix, 0, 7)));
            $a['modes'] = DB::table('account_modes')->where('account_id', $a['id'])->orderBy('effective_date')->get()->toArray();
        }
        unset($a);
        $versions = DB::table('loan_versions')->orderBy('id')->get()->map(fn ($v) => [...(array) $v, 'data' => json_decode($v->data, true)])->all();
        $loans = $calculator->loans(Loan::all()->toArray(), $versions, $transactions, $year, $month);
        $unassigned = array_sum(array_column(array_filter($transactions, fn ($tx) => $tx['type'] === 'expense' && $tx['category_id'] == 13 && ! $tx['loan_id'] && ! $tx['informational'] && str_starts_with($tx['date'], $prefix)), 'amount'));

        return Inertia::render('Finance', [
            'year' => $year, 'month' => $month, 'today' => now()->toDateString(),
            'view' => match ($request->path()) {
                'honap' => 'month', 'tervezes' => 'plans', 'beallitasok' => 'settings', 'eves' => 'annual', 'szamlak' => 'accounts', 'hitelek' => 'loans', 'rendszeres' => 'recurring', 'esti-rogzites' => 'evening', 'zsebpenzek' => 'pockets', 'befektetesek' => 'investments', default => 'overview'
            },
            'categories' => $categories, 'settings' => $settings, 'report' => $report['months'][$month], 'annual' => $report['annual'],
            'scopedReport' => $scoped['months'][$month], 'filters' => ['account_id' => $scopeAccount, 'person' => $scopePerson],
            'transactions' => array_values(array_filter($transactions, fn ($tx) => str_starts_with($tx['date'], $prefix) && $visible($tx))),
            'familyTransactions' => array_values(array_filter($familyRows, fn ($tx) => str_starts_with($tx['date'], $prefix) && $familyVisible($tx))),
            'notes' => DB::table('daily_notes')->whereBetween('date', [sprintf('%04d-%02d-01', $year, $month), $through])->pluck('note', 'date'),
            'account' => array_values(array_filter($accounts, fn ($a) => $a['id'] === 1))[0],
            'accounts' => $accounts, 'people' => array_values(array_unique([...array_column($accounts, 'owner'), ...DB::table('transactions')->whereNotNull('person')->distinct()->pluck('person')->all(), 'Anna', 'Örs', 'közös'])),
            'balanceGroups' => $calculator->balanceGroups(array_values(array_filter($accounts, fn ($a) => (! $scopeAccount || $a['id'] == $scopeAccount) && (! $scopePerson || $a['owner'] === $scopePerson)))),
            'loans' => $loans, 'loanTotals' => ['expected' => array_sum(array_column($loans, 'expected')), 'paid' => array_sum(array_column($loans, 'paid')), 'remaining' => array_sum(array_column($loans, 'remaining')), 'unassigned' => $unassigned],
            'eveningDate' => $eveningDate,
            'recurring' => app(RecurringStore::class)->month($year, $month),
            'monthComplete' => (bool) DB::table('month_observations')->where('year', $year)->where('month', $month)->value('complete'),
            'suggestions' => app(PlanSuggestions::class)->calculate($year, $month, $categories, $allTransactions, DB::table('month_observations')->get()->map(fn ($r) => (array) $r)->all()),
            'push' => app(PushSettings::class)->forUser($request->user()->id),
            'yearMonths' => array_values($report['months']), 'yearTransactions' => $transactions,
            'pockets' => app(PhaseFourReports::class)->pockets($year, $transactions, $accounts),
            'investments' => app(PhaseFourReports::class)->investments($year, $month, $allTransactions),
            'passkeys' => DB::table('passkeys')->where('user_id', $request->user()->id)->get(['id', 'name', 'created_at', 'last_used_at']),
            'hasRestoreBackup' => count(Storage::disk('local')->files('finance-backups')) > 0,
            'systemStatus' => $request->path() === 'beallitasok' ? app(SystemStatus::class)->forUser($request->user()->id) : null,
        ]);
    }

    private function transactionData(Request $request, ?Transaction $transaction = null): array
    {
        return app(TransactionInput::class)->validate($request, $transaction);
    }

    public function create(Request $request)
    {
        $data = $this->transactionData($request);
        $tx = Transaction::withTrashed()->firstOrCreate(['client_id' => $data['client_id']], $data);

        return back()->with('message', 'Tétel elmentve.')->with('undoId', $tx->id);
    }

    public function update(Request $request, Transaction $transaction)
    {
        $data = $this->transactionData($request, $transaction);
        abort_unless($data['client_id'] === $transaction->client_id, 422);
        $transaction->update($data);

        return back()->with('message', 'Módosítás elmentve.');
    }

    public function delete(Transaction $transaction)
    {
        $transaction->delete();

        return back()->with('message', 'Tétel törölve.')->with('undoId', $transaction->id);
    }

    public function undo(int $id)
    {
        $tx = Transaction::withTrashed()->findOrFail($id);
        $tx->trashed() ? $tx->restore() : $tx->delete();

        return back()->with('message', 'Visszavonva.');
    }

    public function note(Request $request)
    {
        $data = $request->validate(['date' => 'required|date_format:Y-m-d', 'note' => 'nullable|string|max:2000']);
        DB::table('daily_notes')->updateOrInsert(['date' => $data['date']], ['note' => $data['note'] ?? '', 'updated_at' => now(), 'created_at' => now()]);

        return back()->with('message', 'Napi megjegyzés elmentve.');
    }

    public function settings(Request $request, PlanStore $store)
    {
        $data = $request->validate(['year' => 'required|integer|between:2000,2100', 'opening_amount' => 'required|integer|between:-10000000000,10000000000', 'savings_rate' => ['required', 'regex:/^\d{1,3}(\.\d{1,4})?$/', 'numeric', 'between:0,100'], 'opening_balance' => 'required|integer|between:-10000000000,10000000000', 'opening_date' => 'required|date_format:Y-m-d']);
        $store->ensureYear($data['year']);
        DB::table('year_settings')->where('year', $data['year'])->update(['opening_amount' => $data['opening_amount'], 'savings_rate' => $data['savings_rate'], 'updated_at' => now()]);
        DB::table('accounts')->where('id', 1)->update(['opening_balance' => $data['opening_balance'], 'opening_date' => $data['opening_date'], 'updated_at' => now()]);

        return back()->with('message', 'Kezdőadatok elmentve.');
    }

    public function plans(Request $request)
    {
        $data = $request->validate(['year' => 'required|integer|between:2000,2100', 'month' => 'required|integer|between:1,12', 'mode' => ['required', Rule::in(['monthly', 'base', 'copy'])], 'amounts' => 'required|array', 'amounts.*' => 'required|integer|min:0|max:10000000000']);
        app(PlanStore::class)->ensureYear($data['year']);
        DB::transaction(function () use ($data) {
            foreach (Category::all() as $category) {
                if (! array_key_exists($category->id, $data['amounts'])) {
                    continue;
                }
                $amount = $data['amounts'][$category->id];
                if ($data['mode'] === 'base') {
                    $category->update(['default_amount' => $amount]);
                    DB::table('monthly_plans')->where('category_id', $category->id)->where('is_override', false)->where(function ($query) {
                        $query->where('year', '>', now()->year)->orWhere(fn ($q) => $q->where('year', now()->year)->where('month', '>', now()->month));
                    })->update(['amount' => $amount, 'updated_at' => now()]);
                } else {
                    if ($data['mode'] === 'copy') {
                        $previous = CarbonImmutable::create($data['year'], $data['month'], 1)->subMonth();
                        $amount = DB::table('monthly_plans')->where('year', $previous->year)->where('month', $previous->month)->where('category_id', $category->id)->value('amount') ?? $category->default_amount;
                    }
                    DB::table('monthly_plans')->where('year', $data['year'])->where('month', $data['month'])->where('category_id', $category->id)->update(['amount' => $amount, 'is_override' => true, 'updated_at' => now()]);
                }
            }
        });

        return back()->with('message', $data['mode'] === 'base' ? 'Alapterv elmentve. A következő hónapok ezt öröklik; a korábbi és felülírt tervek megmaradnak.' : 'Havi terv elmentve.');
    }

    public function category(Request $request, ?Category $category = null)
    {
        $data = $request->validate(['name' => 'required|string|max:100', 'color' => ['required', 'regex:/^#[0-9a-fA-F]{6}$/'], 'active' => 'required|boolean', 'carryover' => 'required|boolean', 'subcategories' => 'present|array|max:30', 'subcategories.*' => 'required|string|max:150']);
        if ($category?->exists) {
            $category->update($data);
        } else {
            Category::create([...$data, 'position' => (Category::max('position') ?? 0) + 1, 'default_amount' => 0]);
        }

        return back()->with('message', 'Kategória elmentve.');
    }

    public function reorder(Request $request)
    {
        $data = $request->validate(['ids' => 'required|array', 'ids.*' => 'required|integer|distinct|exists:categories,id']);
        abort_unless(count($data['ids']) === Category::count(), 422);
        DB::transaction(function () use ($data) {
            foreach ($data['ids'] as $position => $id) {
                Category::whereKey($id)->update(['position' => $position]);
            }
        });

        return back();
    }
}
