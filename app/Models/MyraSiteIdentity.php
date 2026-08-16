<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/** A singleton: one row for the whole install, so no ownership scope. */
class MyraSiteIdentity extends Model
{
    protected $table = 'myra_site_identity';

    protected $fillable = [
        'name',
        'tagline',
    ];
}
