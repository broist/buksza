<?php

namespace App\Finance;

use App\Models\Account;
use App\Models\Category;
use App\Models\Investment;
use App\Models\Loan;
use App\Models\Transaction;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

final class TransactionInput
{
    public function validate(Request $request, ?Transaction $old = null, ?array $position = null): array
    {
        $request->merge(['account_id' => $request->input('account_id', 1), 'fee_amount' => $request->input('fee_amount', 0)]);
        $data = $request->validate([
            'client_id' => 'required|uuid', 'date' => 'required|date_format:Y-m-d|after_or_equal:2000-01-01|before_or_equal:2100-12-31',
            'type' => ['required', Rule::in(['expense', 'income', 'transfer', 'saving', 'saving_return', 'child_saving', ...Positions::LOAN_TYPES])],
            'amount' => 'required|integer|between:1,10000000000', 'account_id' => 'required|integer|exists:accounts,id',
            'target_account_id' => 'nullable|integer|exists:accounts,id',
            'category_id' => 'nullable|integer|exists:categories,id', 'subcategory' => 'nullable|string|max:150',
            'source' => ['nullable', 'required_if:type,income', Rule::in(['Munka', 'Egyéb'])],
            'note' => 'nullable|string|max:2000', 'business' => 'required|boolean',
            'person' => 'nullable|string|max:60', 'loan_id' => 'nullable|integer|exists:loans,id',
            'fee_amount' => 'required|integer|between:0,10000000000',
        ]);
        $source = Account::findOrFail($data['account_id']);
        $target = Account::find($data['target_account_id'] ?? null);
        $type = $data['type'];
        if ($old) {
            $positionId = DB::table('position_transactions')->where('transaction_id', $old->id)->value('position_id');
            $position = $positionId ? (array) DB::table('finance_positions')->find($positionId) : null;
        }
        if (in_array($type, Positions::LOAN_TYPES, true) && ! $position) {
            $this->error('type', 'Személyes kölcsönt a Kintlévőségeim oldalon rögzíts.');
        }
        if ($position) {
            if (! in_array($type, app(Positions::class)->types($position['kind']), true) || $data['date'] < $position['start_date']) {
                $this->error('type', 'A mozgás a kapcsolódó kölcsönhöz vagy megtakarítási célhoz tartozik; típusa és dátuma ezt kövesse.');
            }
            if (! in_array($source->kind, ['bank', 'subaccount']) || ! empty($data['loan_id']) || ! empty($data['subcategory'])) {
                $this->error('account_id', 'Ehhez saját bankszámlát vagy alszámlát válassz, bankhitel-kapcsolat nélkül.');
            }
            $data['category_id'] = null;
            if (in_array($type, Positions::LOAN_TYPES, true)) {
                $data['target_account_id'] = null;
                $target = null;
            }
        }
        foreach ([$source, $target] as $account) {
            if (! $account) {
                continue;
            }
            if ($account->kind === 'snapshot') {
                $this->error('account_id', 'Anna hozzájárulását a főszámlán bevételként rögzítsd. Az ő számlájához havi helyzetjelentés tartozik.');
            }
            if (! $account->active && ! in_array($account->id, [$old?->account_id, $old?->target_account_id])) {
                $this->error('account_id', 'Új mozgáshoz aktív számlát válassz.');
            }
        }
        $mode = fn (Account $a) => (array) (DB::table('account_modes')->where('account_id', $a->id)->where('effective_date', '<=', $data['date'])->orderByDesc('effective_date')->first() ?? $a->toArray());
        $sourceMode = $mode($source);
        $inPlace = in_array($type, ['saving', 'saving_return'], true) && $source->id === $target?->id && ($position || ($old && in_array($old->type, ['saving', 'saving_return'], true) && $old->account_id === $old->target_account_id));
        if ($source->id === $target?->id && ! $inPlace) {
            $this->error('target_account_id', 'Különböző forrás- és célszámlát válassz. Azonos számlán csak célzsebhez lehet pénzt elkülöníteni.');
        }
        if ($position && $sourceMode['mode'] !== 'detailed') {
            $this->error('account_id', 'Részletesen követett bankszámlát válassz.');
        }
        $targetMode = $target ? $mode($target) : null;
        $sameRoute = $old && $old->type === $type && $old->account_id == $source->id && $old->target_account_id == $target?->id && $old->date === $data['date'];
        $informational = $type === 'expense' && $sourceMode['mode'] !== 'detailed';
        $family = $type;
        if ($type === 'transfer') {
            if (! $target) {
                $this->error('target_account_id', 'Válassz célszámlát.');
            }
            if (in_array($target->kind, ['child', 'saving'])) {
                $this->error('type', 'Örsnek a babakötvény-befizetést, családi megtakarításhoz a félretételt válaszd.');
            }
            $family = $targetMode['mode'] === 'detailed' ? 'transfer' : 'expense';
            $data['category_id'] = $family === 'expense' ? $targetMode['funding_category_id'] : null;
        } elseif ($type === 'child_saving') {
            if (! $target || $target->kind !== 'child') {
                $this->error('target_account_id', 'Örs MÁK-számláját válaszd célnak.');
            }
            $data['category_id'] = null;
        } elseif (in_array($type, ['saving', 'saving_return'])) {
            if ($target && $target->kind !== 'saving' && ! $inPlace) {
                $this->error('target_account_id', 'Családi megtakarítási számlát válassz, vagy külső félretételt.');
            }
            $data['category_id'] = null;
        } elseif ($target) {
            $data['target_account_id'] = null;
        }
        if ($source->kind === 'child') {
            $this->error('account_id', 'A gyermekmegtakarítás értékkövetése a későbbi befektetési nézetben készül; itt befizetés rögzíthető.');
        }
        if ($sourceMode['mode'] !== 'detailed' && in_array($type, ['saving', 'saving_return', 'child_saving', 'transfer'])) {
            $this->error('account_id', 'Ezt a mozgást részletesen követett forrásszámláról rögzítsd. A zsebpénzes/összevont keret belső költése külön összegkövetés.');
        }
        if ($sameRoute) {
            $family = $old->family_type ?? $old->type;
            $informational = $old->informational;
            if ($type === 'transfer') {
                $data['category_id'] = $old->category_id;
            }
        }
        if ($type === 'expense' && ! $informational) {
            $category = Category::find($data['category_id'] ?? null);
            if (! $category || (! $category->active && $old?->category_id !== $category->id)) {
                $this->error('category_id', 'Válassz aktív kategóriát.');
            }
            if (! empty($data['subcategory']) && $data['subcategory'] !== $old?->subcategory && ! in_array($data['subcategory'], $category->subcategories, true)) {
                $this->error('subcategory', 'Válassz a kategória alkategóriái közül.');
            }
        } elseif ($informational) {
            $data['category_id'] = null;
            $data['subcategory'] = 'kp';
        } else {
            $data['subcategory'] = null;
        }
        if (! empty($data['loan_id'])) {
            $loan = Loan::findOrFail($data['loan_id']);
            if ($data['date'] < $loan->start_date || ($loan->closed_date && $data['date'] > $loan->closed_date)) {
                $this->error('loan_id', 'A hitel kezdete és lezárása közötti dátumot adj meg.');
            }
            if (! in_array($type, ['expense', 'transfer']) || ($type === 'expense' && ($data['category_id'] != 13 || $informational)) || ($type === 'transfer' && ($family === 'expense' || ($source->kind !== 'subaccount' && $target?->kind !== 'subaccount')))) {
                $this->error('loan_id', 'Hitelhez valódi hitelkiadás vagy alszámlás fedezetátvezetés kapcsolható.');
            }
        }
        if ($type !== 'income') {
            $data['source'] = null;
        }
        if ($type === 'income' && ($data['person'] ?? null) === 'Anna' && $source->id === 1) {
            $data['source'] = 'Egyéb';
        }
        if ($source->kind === 'szep' && $type === 'income') {
            $data['source'] = 'Munka';
        }
        if ($type !== 'transfer') {
            $data['fee_amount'] = 0;
        }
        if ($old?->investment_id) {
            $investment = Investment::findOrFail($old->investment_id);
            if ($type !== 'child_saving' || $target?->id != $investment->account_id || $data['date'] < $investment->start_date) {
                $this->error('type', 'Előbb bontsd a babakötvény kapcsolatát a Befektetéseim oldalon.');
            }
        }
        $person = $data['person'] ?? ($sameRoute ? $old->person : null);
        $people = Account::pluck('owner')->push('Isti')->push('Anna')->push('közös')->push('Örs')->unique()->all();
        if ($person && ! in_array($person, $people) && ! DB::table('transactions')->where('person', $person)->exists()) {
            $this->error('person', 'Válassz a nyilvántartott személyek közül.');
        }

        return [...$data, 'target_account_id' => $data['target_account_id'] ?? null, 'loan_id' => $data['loan_id'] ?? null, 'family_type' => $family, 'informational' => $informational, 'person' => $person ?: ($family === 'expense' && $type === 'transfer' ? $target->owner : $source->owner), 'fee_category_id' => $data['fee_amount'] ? 18 : null];
    }

    private function error(string $field, string $message): never
    {
        throw ValidationException::withMessages([$field => $message]);
    }
}
