<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Provider extends Model
{
    use \Illuminate\Database\Eloquent\Factories\HasFactory;

    protected $fillable = [
        'name',
        'slug',
        'api_key_env',
        'type',
        'logo_url',
        'description',
        'active',
    ];

    protected $casts = [
        'active' => 'boolean',
    ];
}
