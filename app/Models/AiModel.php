<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class AiModel extends Model
{
    /** @use HasFactory<\Database\Factories\AiModelFactory> */
    use HasFactory;

    protected $fillable = [
        'name',
        'slug',
        'description',
        'image_url',
        'is_active',
    ];

    public function categories(): \Illuminate\Database\Eloquent\Relations\BelongsToMany
    {
        return $this->belongsToMany(Category::class, 'ai_model_category');
    }

    protected $casts = [
        'is_active' => 'boolean',
    ];

    protected $appends = ['input_type', 'output_type'];

    public function getInputTypeAttribute()
    {
        return $this->categories->first()?->input_type ?? 'text';
    }

    public function getOutputTypeAttribute()
    {
        return $this->categories->first()?->output_type ?? 'image';
    }

    public function providers()
    {
        return $this->hasMany(AiModelProvider::class);
    }

    public function primaryProvider()
    {
        return $this->hasOne(AiModelProvider::class)->where('is_primary', true);
    }

    public function schema()
    {
        return $this->hasOneThrough(
            AiModelSchema::class,
            AiModelProvider::class,
            'ai_model_id', // Foreign key on AiModelProvider table...
            'ai_model_provider_id', // Foreign key on AiModelSchema table...
            'id', // Local key on AiModel table...
            'id' // Local key on AiModelProvider table...
        )->where('ai_model_providers.is_primary', true);
    }
}
