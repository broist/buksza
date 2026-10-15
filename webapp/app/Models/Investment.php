<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Investment extends Model
{
    protected $guarded = [];

    protected function casts(): array
    {
        return ['opening_amount' => 'integer', 'account_id' => 'integer'];
    }
}
