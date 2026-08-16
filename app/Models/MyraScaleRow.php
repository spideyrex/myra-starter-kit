<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/** Demo dataset for the scale page. Not user data: no ownership, no tenancy. */
class MyraScaleRow extends Model
{
    protected $table = 'myra_scale_rows';

    protected $fillable = ['name', 'email', 'status', 'amount'];

    protected function casts(): array
    {
        return ['amount' => 'integer'];
    }
}
