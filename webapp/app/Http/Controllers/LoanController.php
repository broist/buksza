<?php

namespace App\Http\Controllers;

use App\Models\Loan;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class LoanController extends Controller
{
    public function save(Request $request, ?Loan $loan = null)
    {
        foreach (['lender', 'original_amount', 'outstanding_amount', 'balance_date', 'annual_rate', 'rate_terms', 'end_date', 'closed_date', 'note'] as $key) {
            $request->merge([$key => $request->input($key)]);
        }
        $money = 'nullable|integer|between:0,10000000000';
        $date = 'nullable|date_format:Y-m-d|after_or_equal:2000-01-01|before_or_equal:2100-12-31';
        $data = $request->validate([
            'name' => 'required|string|max:120', 'lender' => 'nullable|string|max:120',
            'original_amount' => $money, 'outstanding_amount' => $money, 'balance_date' => $date,
            'annual_rate' => ['nullable', 'regex:/^\d{1,3}(\.\d{1,4})?$/', 'numeric', 'between:0,100'],
            'rate_terms' => 'nullable|string|max:2000', 'installment' => 'required|integer|between:0,10000000000',
            'due_day' => 'required|integer|between:1,31', 'start_date' => str_replace('nullable', 'required', $date),
            'end_date' => $date.'|after_or_equal:start_date', 'closed_date' => $date.'|after_or_equal:start_date',
            'note' => 'nullable|string|max:2000', 'effective_date' => str_replace('nullable', 'required', $date).'|after_or_equal:start_date',
        ]);
        if ($data['outstanding_amount'] !== null && ! $data['balance_date']) {
            throw ValidationException::withMessages(['balance_date' => 'A fennálló tartozáshoz adj meg állapotdátumot.']);
        }
        if ($loan && DB::table('transactions')->where('loan_id', $loan->id)->whereNull('deleted_at')->where(function ($query) use ($data) {
            $query->where('date', '<', $data['start_date']);
            if ($data['closed_date']) {
                $query->orWhere('date', '>', $data['closed_date']);
            }
        })->exists()) {
            throw ValidationException::withMessages(['start_date' => 'A hitel kezdete és lezárása foglalja magába a már hozzárendelt pénzmozgásokat.']);
        }
        $effective = $data['effective_date'];
        if ($loan && $loan->due_day != $data['due_day'] && substr($effective, 8, 2) !== '01') {
            throw ValidationException::withMessages(['effective_date' => 'Az esedékességi nap váltását hónap elejétől add meg, hogy egy hónapra egy törlesztő tartozzon.']);
        }
        unset($data['effective_date']);
        DB::transaction(function () use ($loan, $data, $effective) {
            $saved = $loan ?? new Loan;
            $saved->fill($data)->save();
            $saved->refresh();
            DB::table('loan_versions')->insert(['loan_id' => $saved->id, 'effective_date' => $effective, 'data' => json_encode($saved->only(array_keys($data)), JSON_UNESCAPED_UNICODE), 'created_at' => now(), 'updated_at' => now()]);
        });

        return back()->with('message', 'Hitel elmentve. A feltételek előzménye megmaradt.');
    }
}
