<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class WorkflowStep extends Model
{
    protected $guarded = ['id'];

    protected function casts(): array
    {
        return ['conditions' => 'array'];
    }
}
