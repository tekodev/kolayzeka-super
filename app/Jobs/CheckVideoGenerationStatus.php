<?php

namespace App\Jobs;

use App\Models\Generation;
use App\Services\VeoService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

class CheckVideoGenerationStatus implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    protected $generation;

    /**
     * The number of times the job may be attempted.
     *
     * @var int
     */
    public $tries = 60;

    /**
     * The number of seconds to wait before retrying the job.
     *
     * @var int
     */
    public $backoff = 10;

    /**
     * Create a new job instance.
     */
    public function __construct(Generation $generation)
    {
        $this->generation = $generation;
    }

    /**
     * Execute the job.
     */
    public function handle(VeoService $veoService): void
    {
        $operationName = $this->generation->input_data['operation_name'] ?? null;
        Log::info("[CheckVideoGenerationStatus] Job started", ['generation_id' => $this->generation->id, 'operation' => $operationName]);

        if (!$operationName) {
            Log::error("[CheckVideoGenerationStatus] Generation {$this->generation->id} has no operation_name");
            $this->generation->update(['status' => 'failed', 'error_message' => 'Missing operation name']);
            return;
        }

        try {
            Log::info("[CheckVideoGenerationStatus] Checking status for generation {$this->generation->id}...");
            $status = $veoService->checkOperationStatus($operationName);
            Log::info("[CheckVideoGenerationStatus] Status received", ['status_data' => $status]);

            if ($status['done'] ?? false) {
                // Operation completed
                Log::info("[CheckVideoGenerationStatus] Generation {$this->generation->id} completed");
                
                $videoUrl = $status['response']['generateVideoResponse']['generatedSamples'][0]['video']['uri'] ?? null;
                Log::info("[CheckVideoGenerationStatus] Video URL extracted: " . ($videoUrl ?? 'NULL'));

                if ($videoUrl) {
                    try {
                        Log::info("[CheckVideoGenerationStatus] Downloading video from Google URL...");
                        $videoContent = $veoService->downloadVideo($videoUrl);
                        
                        $filename = "videos/" . $this->generation->user_id . "/" . $this->generation->id . ".mp4";
                        Log::info("[CheckVideoGenerationStatus] Saving video to storage: {$filename}");
                        
                        // Use S3 disk explicitly (without ACL)
                        \Illuminate\Support\Facades\Storage::disk('s3')->put($filename, $videoContent);
                        $storedUrl = \Illuminate\Support\Facades\Storage::disk('s3')->url($filename);
                        
                        Log::info("[CheckVideoGenerationStatus] Video saved successfully to S3. URL: {$storedUrl}");

                        $this->generation->update([
                            'status' => 'completed',
                            'output_data' => ['result' => $storedUrl], // Store permanent URL
                            'profit_usd' => 0, // Calculate cost if needed
                        ]);
                    } catch (\Exception $e) {
                         Log::error("[CheckVideoGenerationStatus] Failed to download/save video: " . $e->getMessage());
                         $this->generation->update([
                            'status' => 'failed',
                            'error_message' => 'Video generated but failed to save: ' . $e->getMessage(),
                            'output_data' => array_merge($status, ['google_url' => $videoUrl]) // Keep original URL as backup
                        ]);
                    }
                } else {
                    // Check for safety filters or other errors
                    $errorMessage = 'No video URL in response';
                    
                    if (isset($status['response']['generateVideoResponse']['raiMediaFilteredReasons'])) {
                        $reasons = $status['response']['generateVideoResponse']['raiMediaFilteredReasons'];
                        $errorMessage = 'Video generation blocked by safety filters: ' . implode(', ', $reasons);
                    }

                    $this->generation->update([
                        'status' => 'failed',
                        'error_message' => $errorMessage,
                        'output_data' => $status
                    ]);
                }
            } else {
                // Still processing, release back to queue
                Log::info("[CheckVideoGenerationStatus] Generation {$this->generation->id} still processing. Releasing...");
                $this->release(10); // Check again in 10 seconds
            }

        } catch (\Exception $e) {
            Log::error("[CheckVideoGenerationStatus] Error checking status: " . $e->getMessage());
            // Don't fail immediately, maybe retry a few times?
            // For now, let Laravel's standard retry mechanism handle it or fail
            if ($this->attempts() > 5) {
                $this->generation->update(['status' => 'failed', 'error_message' => $e->getMessage()]);
            } else {
                $this->release(30);
            }
        }
    }
}
