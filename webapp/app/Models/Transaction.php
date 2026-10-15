<?php

namespace App\Models;

use App\Finance\MonthObservation;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Transaction extends Model
{
    use SoftDeletes;

    protected $guarded = [];

    protected static function booted(): void
    {
        static::saved(function (Transaction $tx) {
            if ($tx->wasRecentlyCreated || $tx->wasChanged(['date', 'amount', 'type', 'family_type', 'informational', 'category_id', 'account_id', 'target_account_id', 'fee_amount'])) {
                app(MonthObservation::class)->invalidate([$tx->date, $tx->getRawOriginal('date')]);
            }
        });
        static::deleted(fn (Transaction $tx) => app(MonthObservation::class)->invalidate([$tx->date]));
        static::restored(fn (Transaction $tx) => app(MonthObservation::class)->invalidate([$tx->date]));
    }

    protected function casts(): array
    {
        return ['amount' => 'integer', 'fee_amount' => 'integer', 'business' => 'boolean', 'informational' => 'boolean'];
    }
}
