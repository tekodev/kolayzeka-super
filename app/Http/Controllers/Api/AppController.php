<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\App;
use App\Models\AppExecution;
use App\Services\AppExecutionService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class AppController extends Controller
{
    public function __construct(
        protected AppExecutionService $appExecutionService
    ) {}

    /**
     * Execute a Dynamic App.
     */
    public function execute($slug, Request $request)
    {
        $app = App::where('slug', $slug)->where('is_active', true)->firstOrFail();

        // Standard inputs from request body
        $inputs = $request->json()->all();
        
        // Handle multipart/form-data if needed (optional for pure JSON APIs but good to have)
        if (empty($inputs)) {
            $inputs = $request->except(['_token']);
        }

        // Handle file uploads recursively (important for API if users send multipart)
        $handleFiles = function (&$data) use (&$handleFiles) {
            foreach ($data as $key => &$value) {
                if ($value instanceof \Illuminate\Http\UploadedFile) {
                    $path = $value->store('apps/inputs', 's3');
                    // Use temporary URL with long expiration for background job safety
                    $value = \Illuminate\Support\Facades\Storage::disk('s3')->temporaryUrl($path, now()->addHours(24));
                } elseif (is_array($value)) {
                    $handleFiles($value);
                }
            }
        };
        $handleFiles($inputs);

        try {
            $execution = $this->appExecutionService->startApp($app, $request->user(), $inputs);
            
            // Dispatch background job
            \App\Jobs\ProcessAppExecutionJob::dispatch($execution->id);

            return response()->json([
                'status' => 'success',
                'message' => 'Application execution started.',
                'execution' => $execution
            ], 202); // 202 Accepted for async processing
            
        } catch (\Exception $e) {
            Log::error("[Api\AppController] Execution Failed: " . $e->getMessage());
            return response()->json([
                'status' => 'error',
                'message' => $e->getMessage()
            ], 422);
        }
    }

    /**
     * Get Execution Status and Progress.
     */
    public function status($id, Request $request)
    {
        $execution = AppExecution::findOrFail($id);
        
        if ($execution->user_id !== $request->user()->id) {
            return response()->json(['message' => 'Forbidden'], 403);
        }

        return response()->json($execution);
    }
}
