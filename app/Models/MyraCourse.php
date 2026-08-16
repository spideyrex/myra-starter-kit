<?php

namespace App\Models;

use App\Models\Traits\OwnedByUser;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class MyraCourse extends Model
{
    use OwnedByUser;

    protected $table = 'myra_courses';

    /** `created_by` is stamped by OwnedByUser and is deliberately not fillable. */
    protected $fillable = [
        'title',
        'summary',
    ];

    public function lessons(): HasMany
    {
        return $this->hasMany(MyraLesson::class, 'course_id');
    }
}
