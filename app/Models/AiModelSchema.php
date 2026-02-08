<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class AiModelSchema extends Model
{
    use \Illuminate\Database\Eloquent\Factories\HasFactory;

    protected $fillable = [
        'ai_model_provider_id',
        'version',
        'input_schema',
        'output_schema',
        'field_mapping',
        'default_values',
    ];

    protected $casts = [
        'field_mapping' => 'array',
        'default_values' => 'array',
    ];

    // Laravel 12 Attribute accessors to parse JSON strings
    protected function inputSchema(): \Illuminate\Database\Eloquent\Casts\Attribute
    {
        return \Illuminate\Database\Eloquent\Casts\Attribute::make(
            get: fn ($value) => is_string($value) ? json_decode($value, true) : $value,
            set: fn ($value) => is_array($value) ? json_encode($value) : $value,
        );
    }

    protected function outputSchema(): \Illuminate\Database\Eloquent\Casts\Attribute
    {
        return \Illuminate\Database\Eloquent\Casts\Attribute::make(
            get: fn ($value) => is_string($value) ? json_decode($value, true) : $value,
            set: fn ($value) => is_array($value) ? json_encode($value) : $value,
        );
    }

    public function provider()
    {
        return $this->belongsTo(AiModelProvider::class, 'ai_model_provider_id');
    }
}
