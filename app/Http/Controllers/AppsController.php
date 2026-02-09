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
        protected \App\Services\GoogleFileManager $googleFileManager
    ) {}

    public function index()
    {
        return Inertia::render('Apps/Index', [
            'apps' => [
                [
                    'id' => 'luna-influencer',
                    'name' => 'Luna Influencer',
                    'description' => 'Create consistent, high-fidelity influencer photos with professional camera controls.',
                    'icon' => 'camera', // Placeholder for now
                    'route' => route('apps.luna-influencer.show'),
                ],
                [
                    'id' => 'ai-influencer',
                    'name' => 'AI Influencer',
                    'description' => 'Create custom influencer photos with your own reference images.',
                    'icon' => 'user-group', // Placeholder
                    'route' => route('apps.ai-influencer.show'),
                ]
            ]
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
        $startTime = microtime(true);
        Log::info("[Performance] Luna Generation Request Started");

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
            'clothing_reference_images.*' => 'image|max:10240',
            'location_description' => 'required|string',
            'activity_style' => 'required|string',
            'pose_style' => 'required|string',
            'gaze_direction' => 'required|string',
        ]);
        
        $validationTime = microtime(true) - $startTime;
        Log::info("[Performance] Validation took: " . number_format($validationTime, 4) . "s");

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
        
        $fileManagerTime = microtime(true) - $startTime - $validationTime;
        Log::info("[Performance] GoogleFileManager (URI Retrieval) took: " . number_format($fileManagerTime, 4) . "s");

        // Construct Prompt (Pass empty identity list because we handle it manually in prompt logic below)
        $prompt = $this->constructLunaPrompt($request->all(), true); 
        
        $promptTime = microtime(true) - $startTime - $validationTime - $fileManagerTime;
        Log::info("[Performance] Prompt Construction took: " . number_format($promptTime, 4) . "s");

        // Prepare Input Data for GenerationService
        // We inject the File URI structure for BOTH images
        $inputData = [
             'prompt' => $prompt,
             'identity_reference_images' => [
                 [
                     'file_uri' => $identityFileUri1,
                     'mime_type' => 'image/jpeg'
                 ],
                 [
                     'file_uri' => $identityFileUri2,
                     'mime_type' => 'image/png'
                 ]
             ], 
             'clothing_reference_images' => $request->file('clothing_reference_images'),
        ];
        // Merge other fields
        $inputData = array_merge($request->except(['identity_reference_images', 'clothing_reference_images']), $inputData);

        Log::info('Luna Generation Started (2 Static Images)', [
            'user_id' => $request->user()->id,
            'aspect_ratio' => $request->aspect_ratio,
            'resolution' => $request->image_resolution,
            'framing' => $request->framing_type,
            'clothing_images_count' => count($request->file('clothing_reference_images') ?? []),
        ]);
        
        // Ensure output_format is set if needed
        $inputData['output_format'] = 'jpeg';

        try {
            $serviceStart = microtime(true);
            $generation = $this->generationService->generate($request->user(), $model, $inputData);
            $serviceDuration = microtime(true) - $serviceStart;
            Log::info("[Performance] GenerationService::generate took: " . number_format($serviceDuration, 4) . "s");
            
            $totalDuration = microtime(true) - $startTime;
            Log::info("[Performance] Total Request Duration: " . number_format($totalDuration, 4) . "s");

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
        $startTime = microtime(true);
        Log::info("[Performance] AI Influencer Generation Request Started");

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
            'identity_reference_images.*' => 'image|max:10240', // 10MB max
            'clothing_reference_images' => 'nullable|array',
            'clothing_reference_images.*' => 'image|max:10240',
            'location_description' => 'required|string',
            'activity_style' => 'required|string',
            'pose_style' => 'required|string',
            'gaze_direction' => 'required|string',
        ]);
        
        $validationTime = microtime(true) - $startTime;
        Log::info("[Performance] Validation took: " . number_format($validationTime, 4) . "s");

        $model = AiModel::where('slug', 'nano-banana-pro')->firstOrFail();

        // Construct Prompt (Dynamic Identity)
        $prompt = $this->constructLunaPrompt($request->all(), false);
        
        $promptTime = microtime(true) - $startTime - $validationTime;
        Log::info("[Performance] Prompt Construction took: " . number_format($promptTime, 4) . "s");

        // Prepare Input Data for GenerationService
        // Standard flow: Upload files to S3 via GenerationService
        $inputData = [
             'prompt' => $prompt,
             'identity_reference_images' => $request->file('identity_reference_images'),
             'clothing_reference_images' => $request->file('clothing_reference_images'),
        ];
        // Merge other fields
        $inputData = array_merge($request->except(['identity_reference_images', 'clothing_reference_images']), $inputData);

        Log::info('AI Influencer Generation Started', [
            'user_id' => $request->user()->id,
            'aspect_ratio' => $request->aspect_ratio,
            'resolution' => $request->image_resolution,
            'framing' => $request->framing_type,
            'identity_images_count' => count($request->file('identity_reference_images') ?? []),
            'clothing_images_count' => count($request->file('clothing_reference_images') ?? []),
            // 'constructed_prompt' => $prompt
        ]);
        
        $inputData['output_format'] = 'jpeg';

        try {
            $serviceStart = microtime(true);
            $generation = $this->generationService->generate($request->user(), $model, $inputData);
            $serviceDuration = microtime(true) - $serviceStart;
            Log::info("[Performance] GenerationService::generate took: " . number_format($serviceDuration, 4) . "s");
            
            $totalDuration = microtime(true) - $startTime;
            Log::info("[Performance] Total Request Duration: " . number_format($totalDuration, 4) . "s");
            
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
        Log::info("[AppsController] generateLunaVideo called", ['request_all' => $request->all()]);
        $validated = $request->validate([
            'generation_id' => 'required|exists:generations,id',
            'video_prompt' => 'required|string|max:2000',
            'camera_movement' => 'nullable|string',
            'action' => 'nullable|string',
            'duration' => 'nullable|in:4,6,8',
            'resolution' => 'nullable|in:720p,1080p,4k',
            'negative_prompt' => 'nullable|string',
        ]);

        try {
            $veoService = app(\App\Services\VeoService::class);
            
            // Fetch original generation
            $originalGeneration = \App\Models\Generation::findOrFail($validated['generation_id']);
            
            // Extract base64 from output_data
            $outputData = $originalGeneration->output_data;

            // Handle string/json case just in case
            if (is_string($outputData)) {
                $outputData = json_decode($outputData, true);
            }

            // Path 1: Direct inlineData
            $imageBase64 = $outputData['inlineData']['data'] ?? null;

            // Path 2: Nested candidates (Gemini standard)
            if (!$imageBase64) {
                $imageBase64 = $outputData['candidates'][0]['content']['parts'][0]['inlineData']['data'] ?? null;
            }

            // Path 3: bytesBase64Encoded (Veo standard)
            if (!$imageBase64) {
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
            }
            
            if (!$imageBase64) {
                Log::error('[AppsController] No base64 found in output_data', [
                    'generation_id' => $validated['generation_id'],
                    'output_data_keys' => is_array($outputData) ? array_keys($outputData) : 'not_array'
                ]);
                return back()->withErrors(['error' => 'Original generation has no base64 image data']);
            }

            Log::info('[AppsController] Preparing video generation', [
                'generation_id' => $validated['generation_id'],
                'base64_length' => strlen($imageBase64),
            ]);

            // Build video config
            $videoConfig = [
                'aspectRatio' => '9:16',
                // 'resolution' is not supported by Veo 3.1 directly in this payload format (default 720p)
                'durationSeconds' => $validated['duration'] ?? '8',
                'personGeneration' => 'allow_adult',
            ];

                // Start video generation
                Log::info('[AppsController] Calling VeoService::generateVideoFromImage...');
                $operation = $veoService->generateVideoFromImage(
                    $imageBase64,
                    $validated['video_prompt'],
                    $videoConfig
                );
                Log::info('[AppsController] Veo API returned operation', ['operation' => $operation]);

                // Create new generation record for video
                Log::info('[AppsController] Creating video generation record in DB...');
                $videoGeneration = \App\Models\Generation::create([
                    'user_id' => auth()->id(),
                    'ai_model_id' => $originalGeneration->ai_model_id,
                    'ai_model_provider_id' => $originalGeneration->ai_model_provider_id,
                    'parent_generation_id' => $originalGeneration->id,
                    'status' => 'processing',
                    'video_prompt' => $validated['video_prompt'],
                    'video_config' => $videoConfig,
                    'input_data' => [
                        'video_prompt' => $validated['video_prompt'],
                        'camera_movement' => $validated['camera_movement'] ?? null,
                        'action' => $validated['action'] ?? null,
                        'operation_name' => $operation['name'] ?? null,
                    ],
                ]);
                Log::info('[AppsController] Video generation record created', ['id' => $videoGeneration->id]);

                // Dispatch job to check status
                Log::info('[AppsController] Dispatching CheckVideoGenerationStatus job', ['generation_id' => $videoGeneration->id]);
                \App\Jobs\CheckVideoGenerationStatus::dispatch($videoGeneration)->delay(now()->addSeconds(10));
                Log::info('[AppsController] Job dispatched successfully');

                return back()->with('success', 'Video generation started! This may take 1-6 minutes.');

            } catch (\Exception $e) {
                Log::error('[AppsController] Video generation failed', [
                    'error' => $e->getMessage(),
                    'trace' => $e->getTraceAsString()
                ]);
                return back()->withErrors(['error' => 'Video generation failed: ' . $e->getMessage()]);
            }
    }

    /**
     * Generate video from AI Influencer image
     */
    public function generateAiVideo(Request $request)
    {
        Log::info("[AppsController] generateAiVideo called", ['request_all' => $request->all()]);
        $validated = $request->validate([
            'generation_id' => 'required|exists:generations,id',
            'video_prompt' => 'required|string|max:2000',
            'camera_movement' => 'nullable|string',
            'action' => 'nullable|string',
            'duration' => 'nullable|in:4,6,8',
            'resolution' => 'nullable|in:720p,1080p,4k',
            'negative_prompt' => 'nullable|string',
        ]);

        try {
            $veoService = app(\App\Services\VeoService::class);
            
            // Fetch original generation
            $originalGeneration = \App\Models\Generation::findOrFail($validated['generation_id']);
            
            // Extract base64 from output_data
            $outputData = $originalGeneration->output_data;

            // Handle string/json case just in case
            if (is_string($outputData)) {
                $outputData = json_decode($outputData, true);
            }

            // Path 1: Direct inlineData
            $imageBase64 = $outputData['inlineData']['data'] ?? null;

            // Path 2: Nested candidates (Gemini standard)
            if (!$imageBase64) {
                $imageBase64 = $outputData['candidates'][0]['content']['parts'][0]['inlineData']['data'] ?? null;
            }

            // Path 3: bytesBase64Encoded (Veo standard - recursive search)
            if (!$imageBase64) {
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
            }
            
            if (!$imageBase64) {
                 Log::error('[AppsController] No base64 found in output_data', [
                    'generation_id' => $validated['generation_id'],
                    'output_data_keys' => is_array($outputData) ? array_keys($outputData) : 'not_array'
                ]);
                return back()->withErrors(['error' => 'Original generation has no base64 image data']);
            }

            Log::info('[AppsController] Preparing AI video generation', [
                'generation_id' => $validated['generation_id'],
                'base64_length' => strlen($imageBase64),
            ]);

            // Build video config
            $videoConfig = [
                'aspectRatio' => '9:16',
                // 'resolution' is not supported by Veo 3.1 directly in this payload format (default 720p)
                'durationSeconds' => $validated['duration'] ?? '8',
                'personGeneration' => 'allow_adult',
            ];

                // Start video generation
                Log::info('[AppsController] AI Video - Calling VeoService...');
                $operation = $veoService->generateVideoFromImage(
                    $imageBase64,
                    $validated['video_prompt'],
                    $videoConfig
                );
                Log::info('[AppsController] AI Video - Veo API returned operation', ['operation' => $operation]);

                // Create new generation record for video
                Log::info('[AppsController] AI Video - Creating DB record...');
                $videoGeneration = \App\Models\Generation::create([
                    'user_id' => auth()->id(),
                    'ai_model_id' => $originalGeneration->ai_model_id,
                    'ai_model_provider_id' => $originalGeneration->ai_model_provider_id,
                    'parent_generation_id' => $originalGeneration->id,
                    'status' => 'processing',
                    'video_prompt' => $validated['video_prompt'],
                    'video_config' => $videoConfig,
                    'input_data' => [
                        'video_prompt' => $validated['video_prompt'],
                        'camera_movement' => $validated['camera_movement'] ?? null,
                        'action' => $validated['action'] ?? null,
                        'operation_name' => $operation['name'] ?? null,
                    ],
                ]);
                Log::info('[AppsController] AI Video - Record created', ['id' => $videoGeneration->id]);

                // Dispatch job to check status
                Log::info('[AppsController] AI Video - Dispatching job...', ['generation_id' => $videoGeneration->id]);
                \App\Jobs\CheckVideoGenerationStatus::dispatch($videoGeneration)->delay(now()->addSeconds(10));
                Log::info('[AppsController] AI Video - Job dispatched');

                return back()->with('success', 'Video generation started! This may take 1-6 minutes.');
        } catch (\Exception $e) {
            Log::error('[AppsController] AI video generation failed', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            return back()->withErrors(['error' => 'Video generation failed: ' . $e->getMessage()]);
    }
    }

}
