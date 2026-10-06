<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Banner extends Model
{
    protected $fillable = [
        'title',
        'subtitle',
        'image_url',
        'button_text',
        'button_url',
        'position',
        'is_active'
    ];

    protected $casts = [
        'is_active' => 'boolean',
    ];
}
