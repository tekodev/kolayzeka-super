<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class App extends Model
{
    protected $fillable = [
        'name',
        'slug',
        'description',
        'icon',
        'cost_multiplier',
        'is_active',
    ];

    protected $casts = [
        'is_active' => 'boolean',
        'cost_multiplier' => 'decimal:2',
    ];

    public function steps()
    {
        return $this->hasMany(AppStep::class)->orderBy('order');
    }
}
