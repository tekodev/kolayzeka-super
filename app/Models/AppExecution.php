<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class AppExecution extends Model
{
    protected $fillable = [
        'app_id',
        'user_id',
        'status',
        'current_step',
        'inputs',
        'history',
    ];

    protected $casts = [
        'inputs' => 'array',
        'history' => 'array',
        'current_step' => 'integer',
    ];

    public function app()
    {
        return $this->belongsTo(App::class);
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Prepare the history output_data with signed URLs for display if they are private S3 files.
     * This modifies the model instance in memory, does not save to DB.
     */
    public function prepareUrls()
    {
        $history = $this->history ?? [];
        $updated = false;

        foreach ($history as $stepOrder => $outputData) {
            if (!$outputData || !is_array($outputData)) continue;

            if (isset($outputData['is_s3_path']) && $outputData['is_s3_path']) {
                try {
                    $path = $outputData['result'];
                    if (!str_contains($path, 'X-Amz-Signature')) {
                        $signedUrl = \Illuminate\Support\Facades\Storage::disk('s3')->temporaryUrl(
                            $path,
                            now()->addMinutes(120) // Valid for 2 hours
                        );
                        $outputData['result'] = $signedUrl;
                        $history[$stepOrder] = $outputData;
                        $updated = true;
                    }
                } catch (\Exception $e) {
                    \Illuminate\Support\Facades\Log::error("Failed to sign S3 URL for AppExecution {$this->id}, Step {$stepOrder}: " . $e->getMessage());
                }
            } elseif (isset($outputData['result']) && is_string($outputData['result']) && str_contains($outputData['result'], 'amazonaws.com') && !str_contains($outputData['result'], 'X-Amz-Signature')) {
                try {
                    $parsed = parse_url($outputData['result'], PHP_URL_PATH);
                    $path = ltrim($parsed, '/');
                    
                    $signedUrl = \Illuminate\Support\Facades\Storage::disk('s3')->temporaryUrl(
                        $path,
                        now()->addMinutes(120)
                    );
                    
                    $outputData['result'] = $signedUrl;
                    $history[$stepOrder] = $outputData;
                    $updated = true;
                } catch (\Exception $e) {
                    \Illuminate\Support\Facades\Log::warning("Could not sign existing URL for AppExecution {$this->id}: " . $e->getMessage());
                }
            }
        }

        if ($updated) {
            $this->history = $history;
        }

        return $this;
    }
}
