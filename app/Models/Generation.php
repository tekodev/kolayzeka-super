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

    /**
     * Prepare the output_data with a signed URL for display if it's a private S3 video.
     * This modifies the model instance in memory, does not save to DB.
     */
    public function prepareVideoUrl()
    {
        $outputData = $this->output_data;
        if (!$outputData) return $this;

        // Condition 1: Explicitly marked as S3 path (New method)
        if (isset($outputData['is_s3_path']) && $outputData['is_s3_path']) {
            try {
                $path = $outputData['result'];
                // Check if result is already a signed URL (just in case)
                if (str_contains($path, 'X-Amz-Signature')) return $this;

                $signedUrl = \Illuminate\Support\Facades\Storage::disk('s3')->temporaryUrl(
                    $path,
                    now()->addMinutes(120) // Valid for 2 hours
                );
                $outputData['result'] = $signedUrl;
                
                // Do not save to DB, just modify object in memory
                $this->output_data = $outputData;
            } catch (\Exception $e) {
                \Illuminate\Support\Facades\Log::error("Failed to sign S3 URL for generation {$this->id}: " . $e->getMessage());
            }
        }
        // Condition 2: Fallback for older records stored as full URLs but potentially private
        elseif (isset($outputData['result']) && is_string($outputData['result']) && str_contains($outputData['result'], 'amazonaws.com') && !str_contains($outputData['result'], 'X-Amz-Signature')) {
             try {
                // Extract path from URL
                $parsed = parse_url($outputData['result'], PHP_URL_PATH);
                $path = ltrim($parsed, '/'); // Remove leading slash
                
                // Sign it
                $signedUrl = \Illuminate\Support\Facades\Storage::disk('s3')->temporaryUrl(
                    $path,
                    now()->addMinutes(120)
                );
                
                $outputData['result'] = $signedUrl;
                $this->output_data = $outputData;
             } catch (\Exception $e) {
                 // Log warning but don't fail, maybe public access still works via policy
                 \Illuminate\Support\Facades\Log::warning("Could not sign existing URL for generation {$this->id}: " . $e->getMessage());
             }
        }

        return $this;
    }
}
