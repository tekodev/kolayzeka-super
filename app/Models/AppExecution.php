<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class AppExecution extends Model
{
    protected $fillable = [
        'app_id',
        'user_id',
        'status',
        'current_step',
        'inputs',
        'history',
    ];

    protected $casts = [
        'inputs' => 'array',
        'history' => 'array',
        'current_step' => 'integer',
    ];

    public function app()
    {
        return $this->belongsTo(App::class);
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }
}
