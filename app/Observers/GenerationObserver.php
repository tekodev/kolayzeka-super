<?php

namespace App\Observers;

use App\Models\Generation;
use App\Services\AppExecutionService;
use Illuminate\Support\Facades\Log;

class GenerationObserver
{
    /**
     * Handle the Generation "updated" event.
     */
    public function updated(Generation $generation): void
    {
        // Check if status changed to completed
        if ($generation->isDirty('status') && $generation->status === 'completed') {
            
            // Check if it belongs to an App Execution
            if ($generation->app_execution_id) {
                Log::info("[GenerationObserver] Generation {$generation->id} completed for AppExecution {$generation->app_execution_id}");
                
                try {
                    $executionService = app(AppExecutionService::class);
                    // Fetch fresh execution to ensure latest state
                    $execution = \App\Models\AppExecution::find($generation->app_execution_id);
                    
                    if ($execution && $execution->status !== 'failed' && $execution->status !== 'completed') {
                        $executionService->handleStepCompletion($execution, $generation);
                    }
                } catch (\Exception $e) {
                    Log::error("[GenerationObserver] Failed to continue app execution: " . $e->getMessage());
                }
            }
        }
        
        // Also handle failure?
        if ($generation->isDirty('status') && $generation->status === 'failed') {
             if ($generation->app_execution_id) {
                Log::error("[GenerationObserver] Generation {$generation->id} failed for AppExecution {$generation->app_execution_id}");
                $execution = \App\Models\AppExecution::find($generation->app_execution_id);
                if ($execution) {
                    $execution->update(['status' => 'failed']);
                    Log::error("[GenerationObserver] Marking AppExecution {$execution->id} as FAILED and broadcasting.");
                    \App\Events\AppExecutionCompleted::dispatch($execution);
                }
             }
        }
    }
}
