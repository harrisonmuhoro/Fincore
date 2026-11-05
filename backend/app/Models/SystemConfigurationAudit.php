<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class SystemConfigurationAudit extends Model
{
    public $timestamps = false;

    protected $guarded = ['id'];

    protected function casts(): array
    {
        return ['changed_at' => 'immutable_datetime'];
    }
}
