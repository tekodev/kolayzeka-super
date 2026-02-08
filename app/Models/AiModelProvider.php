<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class AiModelProvider extends Model
{
    use \Illuminate\Database\Eloquent\Factories\HasFactory;

    protected $fillable = [
        'ai_model_id',
        'provider_id',
        'provider_model_id',
        'is_primary',
        'price_mode',
        'cost_strategy_id',
    ];

    public function provider()
    {
        return $this->belongsTo(Provider::class);
    }

    protected $casts = [
        'is_primary' => 'boolean',
    ];

    public function aiModel()
    {
        return $this->belongsTo(AiModel::class);
    }

    public function costStrategy()
    {
        return $this->belongsTo(CostStrategy::class);
    }

    public function schema()
    {
        return $this->hasOne(AiModelSchema::class);
    }
}
