<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Account extends Model
{
    protected $guarded = [];

    protected function casts(): array
    {
        return ['opening_balance' => 'integer', 'active' => 'boolean'];
    }
}
