<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class EmailTemplate extends Model
{
    use HasFactory;

    protected $fillable = [
        'name',
        'slug',
        'subject',
        'body_html',
        'variables',
        'created_by',
    ];

    protected function casts(): array
    {
        return [
            'variables' => 'array',
        ];
    }
}
