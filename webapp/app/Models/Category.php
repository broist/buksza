<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Category extends Model
{
    protected $guarded = [];

    protected function casts(): array
    {
        return ['subcategories' => 'array', 'active' => 'boolean', 'carryover' => 'boolean', 'default_amount' => 'integer'];
    }
}
