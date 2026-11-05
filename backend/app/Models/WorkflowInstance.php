<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class WorkflowInstance extends Model
{
    protected $guarded = ['id'];

    protected function casts(): array
    {
        return ['context' => 'array'];
    }

    public function decisions(): HasMany
    {
        return $this->hasMany(WorkflowDecision::class, 'instance_id')->orderBy('decided_at');
    }

    public function definition(): BelongsTo
    {
        return $this->belongsTo(WorkflowDefinition::class, 'definition_id');
    }
}
