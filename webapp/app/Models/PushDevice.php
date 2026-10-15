<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class PushDevice extends Model
{
    protected $guarded = [];

    protected $hidden = ['subscription', 'endpoint_hash'];

    protected function casts(): array
    {
        return ['subscription' => 'encrypted:array', 'enabled' => 'boolean'];
    }
}
