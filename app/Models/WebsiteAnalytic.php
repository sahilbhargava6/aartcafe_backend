<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class WebsiteAnalytic extends Model
{
    protected $fillable = [
        'date',
        'daily_user_count',
        'weekly_user_count',
        'bounce_rate',
        'session_duration'
    ];
}
