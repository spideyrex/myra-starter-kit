<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class DashboardLayout extends Model
{
    protected $fillable = ['user_id', 'dashboard_key', 'payload', 'team_id'];

    protected $casts = ['payload' => 'array'];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function team(): BelongsTo
    {
        return $this->belongsTo(Team::class);
    }

    /** Always scoped to one user_id — there is no team or shared read path. */
    public function scopeForUser(Builder $q, User $user): Builder
    {
        return $q->where('user_id', $user->id);
    }

    public function isOwnedBy(?User $user): bool
    {
        return $user !== null && (int) $this->user_id === (int) $user->id;
    }
}
