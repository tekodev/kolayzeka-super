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
        'request_template',
        'response_path',
    ];

    protected $casts = [
        'input_schema' => 'array',
        'output_schema' => 'array',
        'field_mapping' => 'array',
        'default_values' => 'array',
        'request_template' => 'array',
    ];

    public function provider()
    {
        return $this->belongsTo(AiModelProvider::class, 'ai_model_provider_id');
    }
}
