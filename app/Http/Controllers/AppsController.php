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
        $dynamicApps = \App\Models\App::where('is_active', true)->get()->map(function ($app) {
            return [
                'id' => $app->slug,
                'name' => $app->name,
                'description' => $app->description,
                'icon' => $app->icon,
                'image_url' => $app->image_url,
                'route' => route('apps.show', $app->slug),
            ];
        });

        $hardcodedApps = collect([
            [
                'id' => 'luna-influencer',
                'name' => 'Luna Influencer',
                'description' => 'Create consistent, high-fidelity influencer photos with professional camera controls.',
                'icon' => 'camera',
                'image_url' => null,
                'route' => route('apps.luna-influencer.show'),
            ],
            [
                'id' => 'ai-influencer',
                'name' => 'AI Influencer',
                'description' => 'Create custom influencer photos with your own reference images.',
                'icon' => 'user-group',
                'image_url' => null,
                'route' => route('apps.ai-influencer.show'),
            ]
        ]);

        return Inertia::render('Apps/Index', [
            'apps' => $hardcodedApps->merge($dynamicApps)->values()->toArray()
        ]);
    }

    public function showLunaInfluencer()
    {
        $model = AiModel::where('slug', 'nano-banana-pro')->first();
        $latestGeneration = null;

        if ($model) {
            // Optimization: Fetch only ID first to avoid "Out of sort memory" error with large JSON blobs
            $latestId = \App\Models\Generation::where('user_id', auth()->id())
                ->where('ai_model_id', $model->id)
                // ->where('status', 'completed') // Removed to show processing videos
                ->latest()
                ->value('id');
            
            if ($latestId) {
                $latestGeneration = \App\Models\Generation::find($latestId);
                if ($latestGeneration) $latestGeneration->prepareVideoUrl();
            }
        }

        return Inertia::render('Apps/LunaInfluencer/Show', [
            'latest_generation' => $latestGeneration
        ]);
    }

    public function generateLunaInfluencer(Request $request)
    {


        // Increase memory limit for this request to handle multiple image uploads and processing
        ini_set('memory_limit', '4096M');
        set_time_limit(300);

        $request->validate([
            'aspect_ratio' => 'required|string|in:1:1,2:3,3:2,3:4,4:3,4:5,5:4,9:16,16:9,21:9',
            'framing_type' => 'required|string',
            'camera_distance' => 'required|string',
            'frame_coverage' => 'required|numeric',
            'image_resolution' => 'required|string|in:1K,2K,4K',
            'lens_type' => 'required|string',
            // 'identity_reference_images' validation removed as it is now hardcoded
            'clothing_reference_images' => 'nullable|array',
            'clothing_reference_images.*' => 'image|max:20480', // Increased to 20MB
            'location_description' => 'required|string',
            'activity_style' => 'required|string',
            'pose_style' => 'required|string',
            'gaze_direction' => 'required|string',
        ]);



        $model = AiModel::where('slug', 'nano-banana-pro')->firstOrFail();

        // Local File Paths
        $identityPath1 = storage_path('app/public/luna_identity.jpg');
        $identityPath2 = storage_path('app/public/luna_face.png');
        
        // Auto-restore if missing in storage but present in public assets (for production deploy)
        if (!file_exists($identityPath1) && file_exists(public_path('luna-assets/luna_identity.jpg'))) {
            copy(public_path('luna-assets/luna_identity.jpg'), $identityPath1);
        }
        if (!file_exists($identityPath2) && file_exists(public_path('luna-assets/luna_face.png'))) {
            copy(public_path('luna-assets/luna_face.png'), $identityPath2);
        }

        // Ensure files exist
        if (!file_exists($identityPath1) || !file_exists($identityPath2)) {
            Log::error('Luna Identity Image(s) missing.');
            return redirect()->back()->with('error', 'System Error: Identity reference images missing.');
        }

        // Get Dynamic URIs via Manager (Handles Caching & Auto-Reupload)
        $identityFileUri1 = $this->googleFileManager->getUri($identityPath1, 'image/jpeg');
        $identityFileUri2 = $this->googleFileManager->getUri($identityPath2, 'image/png');

        if (!$identityFileUri1 || !$identityFileUri2) {
             return redirect()->back()->with('error', 'System Error: Failed to retrieve Google File URIs.');
        }
        


        // Construct Prompt (Pass empty identity list because we handle it manually in prompt logic below)
        $prompt = $this->constructLunaPrompt($request->all(), true); 
        


        $clothingRef = $request->file('clothing_reference_images');
        $clothingRefArray = is_array($clothingRef) ? $clothingRef : ($clothingRef ? [$clothingRef] : []);
        $identityArray = [
             [
                 'file_uri' => $identityFileUri1,
                 'mime_type' => 'image/jpeg'
             ],
             [
                 'file_uri' => $identityFileUri2,
                 'mime_type' => 'image/png'
             ]
        ];

        // Prepare Input Data for GenerationService
        // We inject the File URI structure for BOTH images
        $inputData = [
             'prompt' => $prompt,
             'identity_reference_images' => $identityArray,
             'clothing_reference_images' => $clothingRefArray,
             'images' => array_merge($identityArray, $clothingRefArray),
        ];
        // Merge other fields
        $inputData = array_merge($request->except(['identity_reference_images', 'clothing_reference_images']), $inputData);


        
        // Ensure output_format is set if needed
        $inputData['output_format'] = 'jpeg';

        try {
            $generation = $this->generationService->generate($request->user(), $model, $inputData);

            return redirect()->back()->with('generation_result', $generation);
        } catch (\Exception $e) {
            Log::error('Luna Generation Failed: ' . $e->getMessage());
            return redirect()->back()->with('error', 'Generation failed: ' . $e->getMessage());
        }
    }

    public function showAiInfluencer()
    {
        $model = AiModel::where('slug', 'nano-banana-pro')->first();
        $latestGeneration = null;

        if ($model) {
            // Optimization: Fetch only ID first to avoid "Out of sort memory" error
            $latestId = \App\Models\Generation::where('user_id', auth()->id())
                ->where('ai_model_id', $model->id)
                // ->where('status', 'completed') // Removed to show processing videos
                ->latest()
                ->value('id');

            if ($latestId) {
                $latestGeneration = \App\Models\Generation::find($latestId);
                if ($latestGeneration) $latestGeneration->prepareVideoUrl();
            }
        }

        return Inertia::render('Apps/AiInfluencer/Show', [
            'latest_generation' => $latestGeneration
        ]);
    }

    public function generateAiInfluencer(Request $request)
    {
        // Increase memory limit for this request to handle multiple image uploads and processing
        ini_set('memory_limit', '4096M');
        set_time_limit(300);

        $request->validate([
            'aspect_ratio' => 'required|string|in:1:1,2:3,3:2,3:4,4:3,4:5,5:4,9:16,16:9,21:9',
            'framing_type' => 'required|string',
            'camera_distance' => 'required|string',
            'frame_coverage' => 'required|numeric',
            'image_resolution' => 'required|string|in:1K,2K,4K',
            'lens_type' => 'required|string',
            'identity_reference_images' => 'required|array|min:1',
            'identity_reference_images.*' => 'image|max:20480', // 20MB max
            'clothing_reference_images' => 'nullable|array',
            'clothing_reference_images.*' => 'image|max:20480', // 20MB max
            'location_description' => 'required|string',
            'activity_style' => 'required|string',
            'pose_style' => 'required|string',
            'gaze_direction' => 'required|string',
        ]);
        


        $model = AiModel::where('slug', 'nano-banana-pro')->firstOrFail();

        // Construct Prompt (Dynamic Identity)
        $prompt = $this->constructLunaPrompt($request->all(), false);
        


        $identityRef = $request->file('identity_reference_images');
        $identityRefArray = is_array($identityRef) ? $identityRef : ($identityRef ? [$identityRef] : []);
        
        $clothingRef = $request->file('clothing_reference_images');
        $clothingRefArray = is_array($clothingRef) ? $clothingRef : ($clothingRef ? [$clothingRef] : []);

        // Prepare Input Data for GenerationService
        // Standard flow: Upload files to S3 via GenerationService
        $inputData = [
             'prompt' => $prompt,
             'identity_reference_images' => $identityRefArray,
             'clothing_reference_images' => $clothingRefArray,
             'images' => array_merge($identityRefArray, $clothingRefArray),
        ];
        // Merge other fields
        $inputData = array_merge($request->except(['identity_reference_images', 'clothing_reference_images']), $inputData);


        
        $inputData['output_format'] = 'jpeg';

        try {
            $generation = $this->generationService->generate($request->user(), $model, $inputData);

            return redirect()->back()->with('generation_result', $generation);
        } catch (\Exception $e) {
            Log::error('AI Influencer Generation Failed: ' . $e->getMessage());
            return redirect()->back()->with('error', 'Generation failed: ' . $e->getMessage());
        }
    }

    protected function constructLunaPrompt(array $data, bool $fixedIdentity = false): string
    {
        // Calculate image indices strings
        
        // Identity: Fixed [image_0, image_1] if fixedIdentity is true, else dynamic
        if ($fixedIdentity) {
            // Updated for 2 static images
            $identityString = '[image_0, image_1]';
            $identityCount = 2;
        } else {
            $identityCount = isset($data['identity_reference_images']) ? count($data['identity_reference_images']) : 0;
            $identityIndices = [];
            for ($i = 0; $i < $identityCount; $i++) {
                $identityIndices[] = "image_{$i}";
            }
            $identityString = '[' . implode(', ', $identityIndices) . ']';
        }

        $clothingCount = isset($data['clothing_reference_images']) ? count($data['clothing_reference_images']) : 0;
        $clothingIndices = [];
        for ($i = 0; $i < $clothingCount; $i++) {
            $currentIndex = $identityCount + $i;
            $clothingIndices[] = "image_{$currentIndex}";
        }
        $clothingString = !empty($clothingIndices) ? '[' . implode(', ', $clothingIndices) . ']' : '(no clothing reference)';

        // Enhance 'head-to-toe' instruction to strictly enforce full visibility including feet
        $framingInstruction = $data['framing_type'];
        if ($data['framing_type'] === 'head-to-toe') {
            $framingInstruction = "Head-to-toe full body shot. STRICTLY ensure the entire subject is visible from the top of the head to the soles of the shoes. ABSOLUTELY NO CROPPING OF THE FEET. Wide shot to include the full figure.";
        } elseif ($data['framing_type'] === 'portrait') {
            $framingInstruction = "Head and shoulders portrait only. Focus on the face. Do not include the lower body.";
            if ($data['camera_distance'] === 'close-up') {
                 $framingInstruction .= " Extreme close-up on the face. Fill the frame with the face.";
            }
        }

        $template = "Generate an ultra-high-resolution, hyper-realistic image.

{framing_instruction}.
{camera_distance}.
The subject occupies approximately {frame_coverage}% of the frame.
The framing is intentional, balanced, and editorial.
Background remains recognizable and realistic, not overly blurred.

Camera and lens:
Professionally shot using a high-end full-frame camera.
Using a {lens_type}.
Natural perspective with realistic optical compression.
Lens choice is optimized for the selected framing and camera distance.
Moderate depth of field suitable for professional fashion editorial photography.
No wide-angle distortion.
No perspective warping.
No optical deformation.

The subject is the exact same woman shown in {identity_reference_images}.
Her facial structure, bone anatomy, freckles, skin details, facial proportions,
and hair must be ABSOLUTELY IDENTICAL to the reference images.
The person must be unmistakably the same individual.
Perfect identity consistency and absolute reference fidelity are mandatory.

She is wearing the exact clothing from {clothing_reference_images}.
The fabric texture, weave, stitching, seams, drape, folds, thickness,
and patterns must be a PIXEL-PERFECT MATCH to the reference images.
Flawless real-world material reproduction down to the smallest detail.

The scene takes place in {location_description}.
The environment is elegant, clean, realistic, and high-end.

She is {activity_style},
captured as a PROFESSIONAL lifestyle fashion photograph.

Her pose is {pose_style}.
Her facial expression is calm, confident, and natural.

The face must be EXTREMELY SHARP, TACK-SHARP, and CRYSTAL CLEAR.
Ultra-high-definition facial detail with realistic skin texture,
visible pores, fine skin details, and natural depth.
The face must remain perfectly recognizable and identical to the reference model.

She is looking {gaze_direction} with confident, natural eye contact.

Lighting is natural daylight with controlled contrast,
professionally balanced to enhance facial structure,
skin texture, fabric detail, and depth.
Soft, realistic shadows with editorial-level polish.

Masterpiece quality.
8K resolution.
Ultra-photorealistic.
Professional fashion photography.
Editorial-level sharpness.
High micro-contrast.
Perfect focus across the entire visible subject.

No noise artifacts.
No distortions.
No cropping errors.
No facial changes.
No anatomical errors.
No identity drift.
Absolute realism and maximum fidelity to all reference images.";

        // Replace placeholders
        $replacements = [
            '{framing_instruction}' => $framingInstruction,
            '{camera_distance}' => $data['camera_distance'],
            '{frame_coverage}' => $data['frame_coverage'],
            '{lens_type}' => $data['lens_type'],
            '{identity_reference_images}' => $identityString,
            '{clothing_reference_images}' => $clothingString,
            '{location_description}' => $data['location_description'],
            '{activity_style}' => $data['activity_style'],
            '{pose_style}' => $data['pose_style'],
            '{gaze_direction}' => $data['gaze_direction'],
        ];

        return str_replace(array_keys($replacements), array_values($replacements), $template);
    }

    /**
     * Generate video from Luna Influencer image
     */
    public function generateLunaVideo(Request $request)
    {
        return $this->processVideoGeneration($request);
    }

    /**
     * Generate video from AI Influencer image
     */
    public function generateAiVideo(Request $request)
    {
        return $this->processVideoGeneration($request);
    }

    /**
     * Common Video Processing Logic
     */
    protected function processVideoGeneration(Request $request)
    {
        Log::info("[AppsController] processVideoGeneration called", ['request_all' => $request->all()]);
        $validated = $request->validate([
            'generation_id' => 'required|exists:generations,id',
            'video_prompt' => 'required|string|max:2000',
            'camera_movement' => 'nullable|string',
            'duration' => 'nullable|in:4,6,8',
            'resolution' => 'nullable|in:720p,1080p,4k', // Kept for validation, though Veo might ignore
            'negative_prompt' => 'nullable|string',
        ]);

        try {
            // 1. Fetch Veo Model
            $videoModelSlug = 'veo-3.1-fast-generate-preview'; // Ensure this matches DB seed
            $model = AiModel::where('slug', $videoModelSlug)->first();
            
            if (!$model) {
                 // Fallback or Error
                 Log::error("[AppsController] Video model {$videoModelSlug} not found in DB.");
                 return back()->withErrors(['error' => 'System Error: Video model configuration missing.']);
            }

            // 2. Fetch Original Generation
            $originalGeneration = \App\Models\Generation::findOrFail($validated['generation_id']);
            
            // 3. Prepare Image Input (S3 URL preferred, Base64 fallback)
            $imageUrl = null;
            $uploadedFile = null;
            $tempFilePath = null;

            $outputData = $originalGeneration->output_data;
            if (is_string($outputData)) $outputData = json_decode($outputData, true);

            // Strategy A: Check 'result' for S3 URL (New Standard)
            if (isset($outputData['result']) && filter_var($outputData['result'], FILTER_VALIDATE_URL)) {
                $imageUrl = $outputData['result'];
                Log::info("[AppsController] Found image URL in original generation result: {$imageUrl}");
            }
            
            // Strategy B: Extract Base64 from raw output (Legacy support)
            if (!$imageUrl) {
                // ... (Keep existing Base64 extraction logic as fallback) ...
                 // Check recursive
                $findKey = function($array, $key) use (&$findKey) {
                    foreach ($array as $k => $v) {
                        if ($k === $key) return $v;
                        if (is_array($v)) {
                            $result = $findKey($v, $key);
                            if ($result) return $result;
                        }
                    }
                    return null;
                };
                
                $inlineData = $findKey($outputData, 'inlineData');
                $imageBase64 = $inlineData['data'] ?? null;
                
                if (!$imageBase64) {
                     $bytesBase64 = $findKey($outputData, 'bytesBase64Encoded');
                     $imageBase64 = $bytesBase64;
                }

                if ($imageBase64) {
                    // Create Temp File for GenerationService expecting UploadedFile
                    $tempFilePath = sys_get_temp_dir() . '/' . uniqid() . '.jpg'; // Assume JPG
                    file_put_contents($tempFilePath, base64_decode($imageBase64));
                    
                    $uploadedFile = new \Illuminate\Http\UploadedFile(
                        $tempFilePath,
                        'source_image.jpg',
                        'image/jpeg',
                        null,
                        true // Test mode = true allows local file
                    );
                    Log::info("[AppsController] Converted Base64 to Temp File: {$tempFilePath}");
                }
            }

            if (!$imageUrl && !$uploadedFile) {
                return back()->withErrors(['error' => 'Could not retrieve source image from original generation.']);
            }

            // 4. Prepare Input Data for GenerationService
            // Note: Schema expects 'image', 'prompt', 'aspectRatio', 'durationSeconds'
            // We map our form fields to what schema likely expects, OR rely on schema mapping.
            // Let's passed sanitized keys.
            
            $inputData = [
                'image' => $uploadedFile ?? $imageUrl, // Pass File or URL
                'prompt' => $validated['video_prompt'],
                'aspectRatio' => '9:16', // Fixed for apps as requested/seen in previous code
                'durationSeconds' => $validated['duration'] ?? '8',
                // Persist other UI fields for context
                'camera_movement' => $validated['camera_movement'] ?? null,
                'resolution' => $validated['resolution'] ?? null,
            ];

            // 5. Cleanup Temp File (After request? No, GenerationService handles upload immediately)
            
            // 6. Call Generation Service
            Log::info("[AppsController] Calling GenerationService for Video...");
            $videoGeneration = $this->generationService->generate($request->user(), $model, $inputData);
            
            // 7. Link to Parent Generation
            $videoGeneration->update([
                'parent_generation_id' => $originalGeneration->id
            ]);

            // Note: GenerationService dispatching job automatically for processing status.
            
            return back()->with('success', 'Video generation started! This may take 1-6 minutes.');

        } catch (\Exception $e) {
            Log::error('[AppsController] Video generation failed', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            return back()->withErrors(['error' => 'Video generation failed: ' . $e->getMessage()]);
        }
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
