<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;

#[Fillable(['name', 'email', 'password', 'status', 'mfa_secret', 'mfa_confirmed_at', 'suspended_at', 'failed_login_count', 'locked_until'])]
#[Hidden(['password', 'remember_token', 'mfa_secret'])]
class User extends Authenticatable
{
    /** @use HasFactory<UserFactory> */
    use HasApiTokens, HasFactory, Notifiable;

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
            'mfa_secret' => 'encrypted',
            'mfa_confirmed_at' => 'immutable_datetime',
            'suspended_at' => 'immutable_datetime',
            'locked_until' => 'immutable_datetime',
        ];
    }

    public function hasPermission(string $permission, string $scopeType = 'global', string $scopeId = 'global'): bool
    {
        if ($this->status !== 'active') {
            return false;
        }

        return $this->roles()
            ->wherePivot('effective_from', '<=', now())
            ->where(function ($query): void {
                $query->whereNull('iam_user_role_assignments.effective_to')
                    ->orWhere('iam_user_role_assignments.effective_to', '>', now());
            })
            ->where('iam_user_role_assignments.status', 'active')
            ->where(function ($query) use ($scopeType, $scopeId): void {
                $query->where(function ($global): void {
                    $global->where('iam_user_role_assignments.scope_type', 'global')->where('iam_user_role_assignments.scope_id', 'global');
                })->orWhere(function ($scoped) use ($scopeType, $scopeId): void {
                    $scoped->where('iam_user_role_assignments.scope_type', $scopeType)->where('iam_user_role_assignments.scope_id', $scopeId);
                });
            })
            ->whereHas('permissions', fn ($query) => $query->where('code', $permission))
            ->exists();
    }

    public function roles(): \Illuminate\Database\Eloquent\Relations\BelongsToMany
    {
        return $this->belongsToMany(Role::class, 'iam_user_role_assignments', 'user_id', 'role_id')
            ->withPivot(['scope_type', 'scope_id', 'effective_from', 'effective_to', 'status', 'approval_workflow_id']);
    }
}
