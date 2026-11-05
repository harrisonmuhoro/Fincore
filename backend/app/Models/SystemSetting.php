<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class SystemSetting extends Model
{
    protected $guarded = ['id'];

    protected function casts(): array
    {
        return ['value_json' => 'array', 'effective_from' => 'immutable_datetime', 'effective_to' => 'immutable_datetime'];
    }
}
