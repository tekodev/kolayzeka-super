<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Generation extends Model
{
    use \Illuminate\Database\Eloquent\Factories\HasFactory;

    protected $fillable = [
        'user_id',
        'ai_model_id',
        'ai_model_provider_id',
        'status',
        'input_data',
        'output_data',
        'provider_cost_usd',
        'user_credit_cost',
        'profit_usd',
        'error_message',
    ];

    protected $casts = [
        'input_data' => 'array',
        'output_data' => 'array',
        'provider_cost_usd' => 'decimal:6',
        'profit_usd' => 'decimal:6',
    ];

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function aiModel()
    {
        return $this->belongsTo(AiModel::class);
    }

    public function provider()
    {
        return $this->belongsTo(AiModelProvider::class, 'ai_model_provider_id');
    }
}
