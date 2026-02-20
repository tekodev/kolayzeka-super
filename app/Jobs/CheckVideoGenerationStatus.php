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
use App\Events\GenerationCompleted;

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
    /**
     * Execute the job.
     */
    public function handle(): void
    {
        // 1. Retrieve operation name
        $outputData = $this->generation->output_data;
        $operationName = $outputData['operation_name'] ?? $this->generation->input_data['operation_name'] ?? null;
        
        Log::info("[CheckVideoGenerationStatus] Job started", ['generation_id' => $this->generation->id, 'operation' => $operationName]);

        if (!$operationName) {
            Log::error("[CheckVideoGenerationStatus] Generation {$this->generation->id} has no operation_name");
            $this->generation->update(['status' => 'failed', 'error_message' => 'Missing operation name']);
            broadcast(new GenerationCompleted($this->generation, $this->generation->user_id))->toOthers();
            return;
        }

        try {
            // 2. Resolve Provider Dynamically
            $providerType = $this->generation->aiModel?->aiModelProviders?->first()?->provider?->type ?? 'google'; 
            
            if (!$this->generation->aiModel) {
                Log::warning("[CheckVideoGenerationStatus] aiModel relationship is null for Generation {$this->generation->id}");
            }
            
            /** @var \App\Services\AiProviders\AsyncAiProviderInterface $providerService */
            $providerService = app(\App\Services\AiProviders\AiProviderFactory::class)->make($providerType);

            if (!($providerService instanceof \App\Services\AiProviders\AsyncAiProviderInterface)) {
                throw new \Exception("Provider {$providerType} does not support async operations.");
            }

            // 3. Check Status
            Log::info("[CheckVideoGenerationStatus] Checking status via {$providerType}...");
            $status = $providerService->checkOperationStatus($operationName);
            Log::info("[CheckVideoGenerationStatus] Status received", ['status_summary' => $status]);

            if ($status['done']) {
                if ($status['error']) {
                    // Failed
                    $errorMessage = $status['error'];
                    
                    // Refund credits
                    if ($this->generation->user_credit_cost > 0) {
                        try {
                            app(\App\Services\CreditService::class)->deposit(
                                $this->generation->user,
                                $this->generation->user_credit_cost,
                                'refund',
                                ['reason' => 'generation_failed', 'generation_id' => $this->generation->id]
                            );
                            Log::info("[CheckVideoGenerationStatus] Refunded credits");
                        } catch (\Exception $e) {
                            Log::error("[CheckVideoGenerationStatus] Refund failed: " . $e->getMessage());
                        }
                    }

                    $this->generation->update([
                        'status' => 'failed',
                        'error_message' => $errorMessage,
                        'output_data' => array_merge($this->generation->output_data ?? [], ['raw_status' => $status['raw'] ?? []])
                    ]);

                    broadcast(new GenerationCompleted($this->generation, $this->generation->user_id))->toOthers();

                } elseif ($status['result_url']) {
                    // Success
                    try {
                        Log::info("[CheckVideoGenerationStatus] Downloading result...");
                        $content = $providerService->downloadResult($status['result_url']);
                        
                        // Determine extension ? usually mp4 for video. 
                        // We can improve this by getting extension from mime/url or just assume mp4 for now.
                        $filename = "uploads/generations/videos/" . $this->generation->user_id . "/" . $this->generation->id . ".mp4";
                        
                        Log::info("[CheckVideoGenerationStatus] Saving to storage: {$filename}");
                        \Illuminate\Support\Facades\Storage::disk('s3')->put($filename, $content);
                        
                        // Final Update
                        $finalOutputData = $this->generation->output_data ?? [];
                        $finalOutputData['result'] = $filename; // Store relative path for prepareVideoUrl() compatibility
                        $finalOutputData['is_s3_path'] = true;
                        $finalOutputData['raw_status'] = $status['raw'] ?? [];

                        $this->generation->update([
                            'status' => 'completed',
                            'output_data' => $finalOutputData,
                            'profit_usd' => 0, 
                        ]);

                        // Prepare the signed URL for immediate broadcast
                        $this->generation->prepareVideoUrl();
                        $signedUrl = $this->generation->output_data['result'] ?? null;

                        broadcast(new GenerationCompleted($this->generation, $this->generation->user_id, $signedUrl));
                        Log::info("[CheckVideoGenerationStatus] Broadcasted completion event with signed URL", ['url' => $signedUrl]);

                    } catch (\Exception $e) {
                        Log::error("[CheckVideoGenerationStatus] Download/Save check failed: " . $e->getMessage());
                        $this->generation->update([
                            'status' => 'failed',
                            'error_message' => 'Video generated but failed to save: ' . $e->getMessage(),
                        ]);
                        broadcast(new GenerationCompleted($this->generation, $this->generation->user_id))->toOthers();
                    }
                }
            } else {
                // Still processing
                Log::info("[CheckVideoGenerationStatus] Operation processing. Releasing...");
                $this->release(10);
            }

        } catch (\Exception $e) {
            Log::error("[CheckVideoGenerationStatus] Error: " . $e->getMessage());
            if ($this->attempts() > 5) {
                $this->generation->update(['status' => 'failed', 'error_message' => $e->getMessage()]);
                broadcast(new GenerationCompleted($this->generation, $this->generation->user_id))->toOthers();
            } else {
                $this->release(30);
            }
        }
    }
}
