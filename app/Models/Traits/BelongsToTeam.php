<?php

namespace App\Models\Traits;

use App\Models\Team;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

trait BelongsToTeam
{
    public static function bootBelongsToTeam(): void
    {
        static::creating(function (Model $model) {
            if (empty($model->team_id) && auth()->check()) {
                $model->team_id = auth()->user()->current_team_id;
            }
        });

        static::addGlobalScope('team', function (Builder $builder) {
            if (auth()->check() && auth()->user()->current_team_id) {
                $builder->where($builder->getModel()->getTable() . '.team_id', auth()->user()->current_team_id);
            }
        });
    }

    public function team(): BelongsTo
    {
        return $this->belongsTo(Team::class);
    }
}
