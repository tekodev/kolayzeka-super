<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Inertia\Inertia;
use App\Models\AiModel;
use App\Services\GenerationService;
use Illuminate\Support\Facades\Log;

class AppsController extends Controller
{
    public function __construct(
        protected GenerationService $generationService,
        protected \App\Services\AppExecutionService $appExecutionService
    ) {}

    public function index()
    {
        $apps = \App\Models\App::where('is_active', true)->get()->map(function ($app) {
            return [
                'id' => $app->slug,
                'name' => $app->name,
                'description' => $app->description,
                'icon' => $app->icon,
                'image_url' => $app->image_url,
                'route' => route('apps.show', $app->slug),
            ];
        });

        return Inertia::render('Apps/Index', [
            'apps' => $apps->values()->toArray()
        ]);
    }



    public function download(\App\Models\Generation $generation)
    {
        if ($generation->user_id !== auth()->id()) {
            abort(403);
        }

        // Get the raw result from DB
        $result = $generation->output_data['result'] ?? null;
        $isS3 = $generation->output_data['is_s3_path'] ?? false;

        if (!$result) {
            abort(404);
        }

        // Case 1: Explicitly marked as S3 path or looks like a relative path (no http prefix)
        if ($isS3 || !preg_match('/^https?:\/\//', $result)) {
             $relativePath = $result;
             
             // Ensure it exists
             if (\Storage::disk('s3')->exists($relativePath)) {
                 return redirect()->to(
                    \Storage::disk('s3')->temporaryUrl(
                        $relativePath,
                        now()->addMinutes(10),
                        [
                            'ResponseContentDisposition' => 'attachment; filename="generated-' . $generation->id . '.' . pathinfo($relativePath, PATHINFO_EXTENSION) . '"',
                        ]
                    )
                );
             } else {
                 abort(404, 'File missing from storage');
             }
        }

        // Case 2: Full URL (S3 or External)
        if (str_contains($result, 'amazonaws.com') || str_contains($result, 'digitaloceanspaces.com')) {
             // Try to parse relative path if it's a full S3 URL
             $path = ltrim(parse_url($result, PHP_URL_PATH), '/');
             // Attempt to match generations/...
             if (\Storage::disk('s3')->exists($path)) {
                  // Serve as attachment
                  return redirect()->to(
                    \Storage::disk('s3')->temporaryUrl(
                        $path,
                        now()->addMinutes(10),
                        [
                            'ResponseContentDisposition' => 'attachment; filename="generated-' . $generation->id . '.' . pathinfo($path, PATHINFO_EXTENSION) . '"',
                        ]
                    )
                );
             }
        }

        // Case 3: External URL (Fal.ai, Replicate, etc.)
        return redirect()->away($result);
    }

    /**
     * Show Dynamic App Page
     */
    public function show($slug)
    {
        // 1. Check hardcoded apps first (legacy support)
        if ($slug === 'luna-influencer') return $this->showLunaInfluencer();
        if ($slug === 'ai-influencer') return $this->showAiInfluencer();

        // 2. Dynamic App
        $app = \App\Models\App::where('slug', $slug)->where('is_active', true)->with('steps.aiModel.schema')->firstOrFail();
        
        return Inertia::render('Apps/DynamicApp', [
            'app' => $app,
        ]);
    }

    /**
     * Start Dynamic App Execution
     */
    public function execute($slug, Request $request)
    {
        $app = \App\Models\App::where('slug', $slug)->where('is_active', true)->firstOrFail();
        
        // Inputs
        $inputs = $request->except(['_token']); 
        
        // Handle file uploads recursively
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
        
        \Log::info("[AppsController] Incoming inputs before service execution:", [
             'has_luna_identity' => isset($inputs['luna_identity']),
             'luna_identity_type' => isset($inputs['luna_identity']) ? gettype($inputs['luna_identity']) : null,
             'luna_identity_value' => isset($inputs['luna_identity']) && is_string($inputs['luna_identity']) ? $inputs['luna_identity'] : 'not_string',
             'raw_keys' => array_keys($inputs)
        ]);

        try {
            $execution = $this->appExecutionService->startApp($app, $request->user(), $inputs);
            
            // Dispatch background job
            \App\Jobs\ProcessAppExecutionJob::dispatch($execution->id);

            $execution->prepareUrls();
            return redirect()->back()->with('execution', $execution)->with('message', 'Application started successfully.');
        } catch (\Exception $e) {
            Log::error("[AppsController] App Execution Failed: " . $e->getMessage());
            return redirect()->back()->with('error', $e->getMessage()); 
        }
    }

    public function docs(string $slug)
    {
        $app = \App\Models\App::with(['steps.aiModel.schema'])->where('slug', $slug)->firstOrFail();

        // Compute all user-facing fields for Step 1 (like DynamicApp.tsx does)
        $fields = [];
        foreach ($app->steps as $step) {
            $config = $step->config ?? [];
            $uiSchema = $step->ui_schema ?? [];

            if (!empty($uiSchema)) {
                foreach ($uiSchema as $field) {
                    $fieldConfig = $config[$field['key']] ?? null;
                    if ($fieldConfig && in_array($fieldConfig['source'], ['static', 'previous'])) continue;
                    $fields[] = array_merge($field, [
                        'defaultValue' => $fieldConfig['value'] ?? $field['default'] ?? null,
                    ]);
                }
            }
        }

        return Inertia::render('Apps/Docs', [
            'app' => $app,
            'fields' => $fields,
        ]);
    }

    public function executionStatus(\App\Models\AppExecution $execution)
    {
        if ($execution->user_id !== auth()->id()) abort(403);
        
        $execution->load('app.steps');
        $execution->prepareUrls();
        return response()->json($execution);
    }

    public function approve(\App\Models\AppExecution $execution, Request $request)
    {
        if ($execution->user_id !== auth()->id()) abort(403);
        
        // Merge any new inputs provided during approval (Step 2+ inputs)
        $newInputs = $request->except(['_token']);
        
        $handleFiles = function (&$data) use (&$handleFiles) {
            foreach ($data as $key => &$value) {
                if ($value instanceof \Illuminate\Http\UploadedFile) {
                    $path = $value->store('apps/inputs', 's3');
                    $value = \Illuminate\Support\Facades\Storage::disk('s3')->temporaryUrl($path, now()->addHours(24));
                } elseif (is_array($value)) {
                    $handleFiles($value);
                }
            }
        };
        $handleFiles($newInputs);

        if (!empty($newInputs)) {
            $execution->update([
                'inputs' => array_merge($execution->inputs ?? [], $newInputs)
            ]);
        }

        $this->appExecutionService->approveStep($execution);
        
        $execution->prepareUrls();
        return back()->with('execution', $execution);
    }
}
