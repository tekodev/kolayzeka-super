<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

use Illuminate\Database\Eloquent\Factories\HasFactory;

class CostStrategy extends Model
{
    use HasFactory;
    protected $fillable = [
        'name',
        'calc_type',
        'provider_unit_price',
        'markup_multiplier',
        'credit_conversion_rate',
        'min_credit_limit',
    ];

    protected $casts = [
        'provider_unit_price' => 'decimal:6',
        'markup_multiplier' => 'decimal:2',
    ];

    public function providers()
    {
        return $this->hasMany(AiModelProvider::class);
    }
}
