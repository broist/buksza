<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Loan extends Model
{
    protected $guarded = [];

    protected function casts(): array
    {
        return ['original_amount' => 'integer', 'outstanding_amount' => 'integer', 'installment' => 'integer', 'due_day' => 'integer'];
    }
}
