<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class WorkflowDefinition extends Model
{
    protected $guarded = ['id'];

    protected function casts(): array
    {
        return ['configuration' => 'array'];
    }

    public function steps(): HasMany
    {
        return $this->hasMany(WorkflowStep::class, 'definition_id')->orderBy('step_order');
    }
}
