<?php

namespace App\Jobs;

use App\Events\GenerationCompleted;
use App\Models\Generation;
use App\Models\User;
use App\Models\AiModel;
use App\Services\GenerationService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Log;

class ProcessGenerationJob implements ShouldQueue
{
    use Queueable;

    /**
     * Create a new job instance.
     */
    public function __construct(
        public int $generationId,
        public int $userId,
        public int $modelId
    ) {}

    /**
     * Execute the job.
     */
    public function handle(): void
    {
        $generation = Generation::find($this->generationId);
        
        if (!$generation) {
            Log::error("[ProcessGenerationJob] Generation not found: {$this->generationId}");
            return;
        }

        try {
            $user = User::find($this->userId);
            $model = AiModel::find($this->modelId);
            
            if (!$model || !$user) {
                throw new \Exception('Model or user not found');
            }

            // Use GenerationService to process and update the existing generation
            $generationService = app(GenerationService::class);
            $generation = $generationService->generate($user, $model, $generation->input_data, $generation);

            // Broadcast completion event
            broadcast(new GenerationCompleted($generation, $this->userId))->toOthers();

        } catch (\Exception $e) {
            Log::error("[ProcessGenerationJob] Generation failed", [
                'generation_id' => $this->generationId,
                'error' => $e->getMessage(),
            ]);

            $generation->refresh();
            if ($generation->status !== 'failed') {
                $generation->update([
                    'status' => 'failed',
                    'output_data' => ['error' => $e->getMessage()],
                ]);
            }

            // Broadcast failure event
            broadcast(new GenerationCompleted($generation, $this->userId))->toOthers();
        }
    }
}
