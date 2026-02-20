<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class AppStep extends Model
{
    protected $fillable = [
        'app_id',
        'order',
        'ai_model_id',
        'prompt_template',
        'ui_schema',
        'name',
        'description',
        'requires_approval',
        'config',
        'options',
    ];

    protected $casts = [
        'config' => 'array',
        'options' => 'array',
        'ui_schema' => 'array',
        'requires_approval' => 'boolean',
        'order' => 'integer',
    ];

    public function app()
    {
        return $this->belongsTo(App::class);
    }

    public function aiModel()
    {
        return $this->belongsTo(AiModel::class);
    }
}
