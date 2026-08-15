<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class TableView extends Model
{
    /** Reserved name for a future server-side column-preference record. */
    public const COLUMNS_NAME = '__columns';

    protected $fillable = ['table_key', 'name', 'payload', 'visibility', 'is_default', 'team_id', 'sort'];

    protected $casts = [
        'payload' => 'array',
        'is_default' => 'boolean',
        'sort' => 'integer',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function team(): BelongsTo
    {
        return $this->belongsTo(Team::class);
    }

    /**
     * Own views, plus team views of the caller's CURRENT team only.
     *
     * The double closure wrapping is mandatory — an unwrapped orWhere here
     * returns every team view in the system.
     */
    public function scopeVisibleTo(Builder $q, User $user): Builder
    {
        return $q->where(function (Builder $q) use ($user) {
            $q->where('user_id', $user->id)
                ->orWhere(function (Builder $q) use ($user) {
                    $q->where('visibility', 'team')
                        ->whereNotNull('team_id')
                        ->where('team_id', $user->current_team_id)
                        ->whereIn('team_id', $user->teams()->select('teams.id'));
                });
        });
    }

    public function isOwnedBy(User $user): bool
    {
        return (int) $this->user_id === (int) $user->id;
    }

    /** Mirrors scopeVisibleTo() for a single loaded record. */
    public function isVisibleTo(User $user): bool
    {
        if ($this->isOwnedBy($user)) {
            return true;
        }

        return $this->visibility === 'team'
            && $this->team_id !== null
            && (int) $this->team_id === (int) $user->current_team_id
            && $user->teams()->whereKey($this->team_id)->exists();
    }

    /** @return array<string, mixed> The SavedView shape the client consumes. */
    public function toClientArray(User $viewer): array
    {
        return [
            'id' => $this->id,
            'slug' => $this->slug,
            'name' => $this->name,
            'visibility' => $this->visibility,
            'is_default' => (bool) $this->is_default,
            'owned' => $this->isOwnedBy($viewer),
            'payload' => $this->payload ?? [],
        ];
    }
}
