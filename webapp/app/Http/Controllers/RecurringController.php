<?php

namespace App\Http\Controllers;

use App\Finance\Positions;
use App\Finance\RecurringStore;
use App\Finance\TransactionInput;
use App\Models\RecurringTemplate;
use App\Models\Transaction;
use App\Notifications\BudgetNotifier;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class RecurringController extends Controller
{
    public function save(Request $request, ?RecurringTemplate $template = null)
    {
        $data = $request->validate([
            'name' => 'required|string|max:120', 'active' => 'required|boolean',
            'frequency' => ['required', Rule::in(['monthly', 'weekly', 'yearly'])], 'interval' => 'required|integer|between:1,12',
            'due_day' => 'required|integer|between:1,31', 'due_month' => 'required|integer|between:1,12',
            'start_date' => 'required|date_format:Y-m-d|after_or_equal:2000-01-01|before_or_equal:2100-12-31',
            'end_date' => 'nullable|date_format:Y-m-d|after_or_equal:start_date|before_or_equal:2100-12-31',
            'effective_date' => 'required|date_format:Y-m-d|after_or_equal:start_date|before_or_equal:2100-12-31',
            'loan_terms' => 'required|boolean', 'payload' => 'required|array',
        ]);
        $effective = $data['effective_date'];
        $data['end_date'] ??= null;
        unset($data['effective_date']);
        if ($template && $effective < now()->toDateString()) {
            throw ValidationException::withMessages(['effective_date' => 'A sablon új változata ma vagy később kezdődhet. A korábbi előzmények megmaradnak.']);
        }
        if ($template && $data['frequency'] !== 'weekly' && ($template->data['due_day'] != $data['due_day'] || $template->data['frequency'] !== $data['frequency']) && substr($effective, 8, 2) !== '01') {
            throw ValidationException::withMessages(['effective_date' => 'Az esedékesség vagy gyakoriság megváltoztatását hónap elejétől add meg.']);
        }
        $payload = app(TransactionInput::class)->validate(Request::create('/', 'POST', [...$data['payload'], 'client_id' => (string) Str::uuid(), 'date' => $data['start_date']]));
        if ($data['loan_terms'] && ($payload['type'] !== 'expense' || $payload['category_id'] != 13 || ! $payload['loan_id'] || $data['frequency'] !== 'monthly' || $data['interval'] != 1)) {
            throw ValidationException::withMessages(['loan_terms' => 'A szerződés szerinti törlesztőhöz havi, hitelhez kapcsolt hitelkiadást válassz.']);
        }
        foreach (['amount', 'fee_amount', 'account_id', 'target_account_id', 'category_id', 'loan_id'] as $key) {
            $payload[$key] = isset($payload[$key]) ? (int) $payload[$key] : null;
        }
        unset($payload['client_id'], $payload['date'], $payload['family_type'], $payload['informational'], $payload['fee_category_id']);
        $data['payload'] = $payload;
        foreach (['interval', 'due_day', 'due_month'] as $key) {
            $data[$key] = (int) $data[$key];
        }
        DB::transaction(function () use ($template, $data, $effective) {
            $saved = $template ?? new RecurringTemplate;
            $saved->fill(['name' => $data['name'], 'data' => $data])->save();
            DB::table('recurring_versions')->insert(['template_id' => $saved->id, 'effective_date' => $effective, 'data' => json_encode($data, JSON_UNESCAPED_UNICODE), 'created_at' => now(), 'updated_at' => now()]);
        });

        return back()->with('message', 'Sablon elmentve. Nem keletkezett fizetett tétel.');
    }

    public function confirm(Request $request, RecurringTemplate $template, RecurringStore $store)
    {
        $data = $request->validate(['due_date' => 'required|date_format:Y-m-d', 'date' => 'required|date_format:Y-m-d|after_or_equal:2000-01-01|before_or_equal:today', 'amount' => 'required|integer|between:1,10000000000', 'existing_id' => 'nullable|integer|exists:transactions,id']);
        $occurrence = collect($store->month((int) substr($data['due_date'], 0, 4), (int) substr($data['due_date'], 5, 2))['occurrences'])->first(fn ($o) => $o['template_id'] === $template->id && $o['due_date'] === $data['due_date']);
        if (! $occurrence) {
            throw ValidationException::withMessages(['due_date' => 'Ehhez a dátumhoz nincs esedékes sablon.']);
        }
        $notifier = app(BudgetNotifier::class);
        $before = $notifier->before($data['date']);
        $tx = DB::transaction(function () use ($data, $template, $occurrence) {
            $paid = Transaction::withTrashed()->where('template_id', $template->id)->where('occurrence_date', $data['due_date'])->first();
            if ($paid && ! $paid->trashed()) {
                return $paid;
            }
            if (! empty($data['existing_id'])) {
                $existing = Transaction::findOrFail($data['existing_id']);
                if ($existing->date > now()->toDateString()) {
                    throw ValidationException::withMessages(['existing_id' => 'Jövőbeli tétel nem jelölhet tényleges teljesítést.']);
                }
                $expected = $occurrence['payload'];
                foreach (['type', 'account_id', 'target_account_id', 'category_id', 'loan_id'] as $field) {
                    if ($existing->$field != ($expected[$field] ?? null)) {
                        throw ValidationException::withMessages(['existing_id' => 'A tétel típusa, számlája, kategóriája és hitelkapcsolata egyezzen a sablonnal.']);
                    }
                }
                if ($existing->template_id || $paid) {
                    throw ValidationException::withMessages(['existing_id' => 'Ez az esedékesség vagy tétel már kapcsolt.']);
                }
                $existing->update(['template_id' => $template->id, 'occurrence_date' => $data['due_date']]);

                return $existing;
            }
            $payload = app(TransactionInput::class)->validate(Request::create('/', 'POST', [...$occurrence['payload'], 'client_id' => $paid?->client_id ?? (string) Str::uuid(), 'date' => $data['date'], 'amount' => $data['amount']]), $paid);
            if ($paid) {
                app(Positions::class)->guard($paid, $payload);
                $paid->fill($payload)->save();
                $paid->restore();

                return $paid;
            }

            return Transaction::firstOrCreate(['template_id' => $template->id, 'occurrence_date' => $data['due_date']], $payload);
        });
        $notifier->after($before);
        $response = back()->with('message', empty($data['existing_id']) ? 'A tényleges fizetés rögzítve.' : 'A korábbi tétel a sablonhoz kapcsolva. Nem számoltuk újra.');

        return empty($data['existing_id']) ? $response->with('undoId', $tx->id) : $response;
    }

    public function observation(Request $request)
    {
        $data = $request->validate(['year' => 'required|integer|between:2000,2100', 'month' => 'required|integer|between:1,12', 'complete' => 'required|boolean']);
        if (sprintf('%04d-%02d', $data['year'], $data['month']) >= now()->format('Y-m')) {
            throw ValidationException::withMessages(['month' => 'Csak már lezárult hónap rögzítése jelölhető teljesnek.']);
        }
        DB::table('month_observations')->updateOrInsert(['year' => $data['year'], 'month' => $data['month']], ['complete' => $data['complete'], 'created_at' => now(), 'updated_at' => now()]);

        return back()->with('message', $data['complete'] ? 'A hónap rögzítése teljesnek jelölve.' : 'A hónap még nincs teljesen rögzítve.');
    }
}
