<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use App\Models\Traits\BelongsToTenant;
use App\Models\Traits\OwnedByUser;

class Category extends Model
{
    // Inert until both tenancy locks are open. See app/Admin/Tenancy/Tenancy.php.
    use HasFactory, OwnedByUser, BelongsToTenant;

    protected $fillable = [
        'name',
        'slug',
        'description',
        'created_by',
    ];

    public function articles(): HasMany
    {
        return $this->hasMany(Article::class);
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }
}
