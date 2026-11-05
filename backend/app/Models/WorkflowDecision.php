<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class WorkflowDecision extends Model
{
    public $timestamps = false;

    protected $guarded = ['id'];

    protected function casts(): array
    {
        return ['decided_at' => 'immutable_datetime'];
    }
}
