<?php

namespace App\Jobs;

use App\Models\AppExecution;
use App\Services\AppExecutionService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Log;

class ProcessAppExecutionJob implements ShouldQueue
{
    use Queueable;

    /**
     * Create a new job instance.
     */
    public function __construct(
        public int $executionId,
        public bool $skipApproval = false
    ) {}

    /**
     * Execute the job.
     */
    public function handle(): void
    {
        $execution = AppExecution::find($this->executionId);

        if (!$execution) {
            Log::error("[ProcessAppExecutionJob] Execution not found: {$this->executionId}");
            return;
        }

        if ($execution->status === 'completed' || $execution->status === 'failed') {
            Log::info("[ProcessAppExecutionJob] Execution {$this->executionId} already finished with status: {$execution->status}");
            return;
        }

        try {
            Log::info("[ProcessAppExecutionJob] Resuming execution for ID: {$this->executionId}");
            
            $appExecutionService = app(AppExecutionService::class);
            
            // Execute the current step
            $appExecutionService->executeNextStep($execution, $this->skipApproval);

        } catch (\Exception $e) {
            Log::error("[ProcessAppExecutionJob] Execution failed: " . $e->getMessage(), [
                'execution_id' => $this->executionId,
                'trace' => $e->getTraceAsString()
            ]);

            $execution->update([
                'status' => 'failed'
            ]);
            
            // Broadcast failure? 
            // AppExecutionCompleted event already exists and can signal failure
            \App\Events\AppExecutionCompleted::dispatch($execution);
        }
    }
}
