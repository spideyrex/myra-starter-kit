<?php

namespace App\Models;

use App\Models\Traits\OwnedByUser;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class MyraLesson extends Model
{
    use OwnedByUser;

    protected $table = 'myra_lessons';

    /** `course_id` comes from the URL through the relation, never from the body. */
    protected $fillable = [
        'title',
        'position',
    ];

    protected $casts = [
        'position' => 'integer',
    ];

    public function course(): BelongsTo
    {
        return $this->belongsTo(MyraCourse::class, 'course_id');
    }
}
