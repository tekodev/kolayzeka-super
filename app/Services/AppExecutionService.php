<?php

namespace App\Services;

use App\Models\App;
use App\Models\AppStep;
use App\Models\User;
use App\Models\Generation;
use App\Models\AppExecution;
use Illuminate\Support\Facades\Log;

class AppExecutionService
{
    public function __construct(
        protected GenerationService $generationService
    ) {}

    /**
     * Start a new App Execution.
     */
    public function startApp(App $app, User $user, array $inputs): AppExecution
    {
        Log::info("[AppExecution] Starting App: {$app->name} (ID: {$app->id}) for User: {$user->id}");

        $execution = AppExecution::create([
            'app_id' => $app->id,
            'user_id' => $user->id,
            'status' => 'pending',
            'current_step' => 1,
            'inputs' => $inputs,
            'history' => [],
        ]);

        return $execution;
    }

    public function approveStep(AppExecution $execution)
    {
        if ($execution->status !== 'waiting_approval') {
            return;
        }
        
        Log::info("[AppExecution] Step approved. Resuming execution {$execution->id}");
        $execution->update(['status' => 'processing']);
        
        // Broadcast that we are resuming
        \App\Events\AppExecutionCompleted::dispatch($execution);

        // Resume with skipApproval = true
        \App\Jobs\ProcessAppExecutionJob::dispatch($execution->id, true);
    }

    /**
     * Execute the next step in the sequence.
     */
    public function executeNextStep(AppExecution $execution, $skipApproval = false)
    {
        $step = $execution->app->steps()->where('order', $execution->current_step)->first();
        
        if (!$step) {
            Log::info("[AppExecution] No more steps. Marking execution {$execution->id} as completed.");
            $execution->update(['status' => 'completed']);
            
            // Dispatch completion event
            \App\Events\AppExecutionCompleted::dispatch($execution);
            
            return;
        }

        // Check if current step requires approval (and we haven't skipped it)
        if (!$skipApproval && $step->requires_approval) {
             Log::info("[AppExecution] Step {$step->order} requires approval. Pausing.");
             $execution->update(['status' => 'waiting_approval']);
             \App\Events\AppExecutionCompleted::dispatch($execution);
             return;
        }

        Log::info("[AppExecution] Executing Step: {$step->name} (Order: {$step->order})");
        $execution->update(['status' => 'processing']);

        $context = [
            'inputs' => $execution->inputs,
            'history' => $execution->history ?? [],
        ];

        try {
            // 1. Resolve Inputs
            $inputData = $this->resolveInputs($step, $context);
            
            // 2. Call Generation Service
            $model = $step->aiModel;
            
            // Generate
            $generation = $this->generationService->generate($execution->user, $model, $inputData);
            
            // Link Generation to Execution
            $generation->update([
                'app_execution_id' => $execution->id,
                'app_step_id' => $step->id,
            ]);

            // If Synchronous completion
            if ($generation->status === 'completed') {
                $this->handleStepCompletion($execution, $generation);
            }

        } catch (\Exception $e) {
            Log::error("[AppExecution] Step Failed: " . $e->getMessage());
            $execution->update(['status' => 'failed']);
            \App\Events\AppExecutionCompleted::dispatch($execution);
        }
    }

    /**
     * Handle completion of a step (called by Observer or synchronously).
     */
    public function handleStepCompletion(AppExecution $execution, Generation $generation)
    {
        Log::info("[AppExecution] Handling completion for Step Order: {$execution->current_step}");

        $step = $execution->app->steps()->where('order', $execution->current_step)->first();
        if ($step && $generation->app_step_id !== $step->id) {
            Log::warning("[AppExecution] Mismatch in step ID for completion. Ignoring.");
        }

        if (!$step) return;

        // Update History
        $history = $execution->history ?? [];
        $history[$step->order] = $generation->output_data; 
        
        $execution->update([
            'history' => $history,
            'current_step' => $execution->current_step + 1,
        ]);

        // Trigger next step via Job. executeNextStep will handle the pause if needed.
        \App\Jobs\ProcessAppExecutionJob::dispatch($execution->id);
    }

    /**
     * Resolve inputs based on step configuration and current context.
     */
    public function resolveInputs(AppStep $step, array $context): array
    {
        $resolved = [];
        $config = $step->config ?? []; 
        $model = $step->aiModel;
        $providerSlug = $model->primaryProvider?->provider?->slug ?? '';
        $isGoogleModel = str_contains(strtolower($model->name), 'gemini') || $providerSlug === 'google';

        // 1. Resolve individual fields from config
        foreach ($config as $key => $fieldConfig) {
            $source = $fieldConfig['source'] ?? 'user';

            if ($source === 'static') {
                $val = $fieldConfig['value'] ?? null;
                if (is_string($val) && (str_starts_with($val, '[') || str_starts_with($val, '{'))) {
                    $decoded = json_decode($val, true);
                    if (json_last_error() === JSON_ERROR_NONE) {
                        $val = $decoded;
                    }
                }
                $resolved[$key] = $val;
            } 
            elseif ($source === 'user') {
                $inputKey = $fieldConfig['input_key'] ?? $key;
                $resolved[$key] = $context['inputs'][$inputKey] ?? null;
            } 
            elseif ($source === 'previous') {
                $stepIndex = $fieldConfig['step_index'] ?? null;
                $outputKey = $fieldConfig['output_key'] ?? 'result'; 

                if ($stepIndex && isset($context['history'][$stepIndex])) {
                    $previousResult = $context['history'][$stepIndex];
                    $resolved[$key] = data_get($previousResult, $outputKey);
                } else {
                    Log::warning("[AppExecution] Missing history for step {$stepIndex}");
                    $resolved[$key] = null;
                }
            }
        }

        // 1.5 Fallback resolution for UI-based apps where config keys may be partially saved.
        // Fill from ui_schema input/default and prompt placeholders to avoid losing critical fields.
        $uiSchema = is_array($step->ui_schema) ? $step->ui_schema : [];
        foreach ($uiSchema as $field) {
            $uiKey = $field['key'] ?? null;
            if (!$uiKey || array_key_exists($uiKey, $resolved)) {
                continue;
            }

            if (array_key_exists($uiKey, $context['inputs'] ?? [])) {
                $resolved[$uiKey] = $context['inputs'][$uiKey];
                continue;
            }

            if (array_key_exists('default', $field)) {
                $resolved[$uiKey] = $field['default'];
            }
        }

        $templateTokens = [];
        if (is_string($step->prompt_template) && $step->prompt_template !== '') {
            preg_match_all('/\{([a-zA-Z0-9_]+)\}/', $step->prompt_template, $matches);
            $templateTokens = array_values(array_unique($matches[1] ?? []));

            foreach ($templateTokens as $token) {
                if (array_key_exists($token, $resolved)) {
                    continue;
                }

                if (array_key_exists($token, $context['inputs'] ?? [])) {
                    $resolved[$token] = $context['inputs'][$token];
                }
            }

            // Luna step fallback: preserve built-in identity references even if config was truncated.
            if (in_array('identity_reference_images', $templateTokens, true) && !isset($resolved['identity_reference_images'])) {
                $defaultIdentityPaths = [
                    'app_static_assets/luna_identity.jpg',
                    'app_static_assets/luna_face.png',
                ];

                $availableIdentityPaths = array_values(array_filter($defaultIdentityPaths, function ($path) {
                    return file_exists(storage_path('app/public/' . $path));
                }));

                if (!empty($availableIdentityPaths)) {
                    $resolved['identity_reference_images'] = $availableIdentityPaths;
                    Log::info('[AppExecution] Applied fallback identity_reference_images from static assets.', [
                        'count' => count($availableIdentityPaths),
                    ]);
                }
            }
        }
        
        Log::info("[AppExecution] Step {$step->order} resolved raw inputs", [
            'keys' => array_keys($resolved),
            'has_identity' => isset($resolved['identity_reference_images']),
            'has_image' => isset($resolved['image'])
        ]);

        // 2. Standardize Paths to URLs
        foreach ($resolved as $key => &$value) {
            if (!$value) continue;

            $items = is_array($value) ? $value : [$value];
            $resolvedItems = [];

            foreach ($items as $item) {
                $path = is_array($item) ? ($item['file_uri'] ?? null) : $item;
                
                if (is_string($path) && str_starts_with($path, 'app_static_assets/')) {
                    $fullPath = storage_path('app/public/' . $path);
                    if (file_exists($fullPath)) {
                        $url = asset('storage/' . $path);
                        Log::info("[AppExecution] Resolved static asset path to URL: {$url}");
                        
                        if (is_array($item)) {
                            $item['file_uri'] = $url;
                            $resolvedItems[] = $item;
                        } else {
                            $resolvedItems[] = $url;
                        }
                        continue;
                    } else {
                        Log::warning("[AppExecution] Static asset not found: {$fullPath}");
                    }
                }
                $resolvedItems[] = $item;
            }
            $value = is_array($value) ? $resolvedItems : $resolvedItems[0];
            Log::info("[AppExecution] Standardized field '{$key}'", ['value' => is_array($value) ? count($value) . " items" : (string)$value]);
        }
        unset($value); 

        // 3. Gemini Identity Indexing ([image_1], [image_2]...)
        $allImages = [];
        $imageMapping = []; // key => [index1, index2]

        if ($isGoogleModel) {
            // Priority 1: Static Fields (Identity)
            foreach ($config as $key => $fieldConfig) {
                if (($fieldConfig['source'] ?? '') === 'static' && isset($resolved[$key])) {
                    $this->mapImages($key, $resolved[$key], $allImages, $imageMapping);
                }
            }
            // Priority 2: User/Previous Fields
            foreach ($config as $key => $fieldConfig) {
                if (($fieldConfig['source'] ?? '') !== 'static' && isset($resolved[$key])) {
                    $this->mapImages($key, $resolved[$key], $allImages, $imageMapping);
                }
            }
            
            Log::info("[AppExecution] Gemini indexing complete", [
                'ordered_images_count' => count($allImages),
                'image_mapping' => $imageMapping
            ]);
        }

        // 4. Handle Prompt Template
        if ($step->prompt_template) {
            $mergedPrompt = $step->prompt_template;

            foreach ($resolved as $key => $value) {
                if ($isGoogleModel && isset($imageMapping[$key])) {
                    // Replace {key} with [image_1, image_2]
                    $indices = array_map(fn($idx) => "[image_{$idx}]", $imageMapping[$key]);
                    $replacement = implode(', ', $indices);
                } else {
                    if (is_array($value)) {
                        $replacement = implode(', ', array_map(function($item) {
                            return is_array($item) ? ($item['file_uri'] ?? json_encode($item)) : (string)$item;
                        }, $value));
                    } else {
                        $replacement = (string)$value;
                    }
                }

                $mergedPrompt = str_replace('{' . $key . '}', $replacement, $mergedPrompt);
            }
            $resolved['prompt'] = $mergedPrompt;
            Log::info("[AppExecution] Prompt templating complete. Prompt length: " . strlen($mergedPrompt));
        }

        if ($isGoogleModel && !empty($allImages)) {
            $resolved['ordered_images'] = $allImages;
        }

        return $resolved;
    }

    /**
     * Map images to indices for Gemini [image_N] prompt referencing.
     */
    protected function mapImages(string $key, $value, array &$allImages, array &$imageMapping): void
    {
        if (!$value) return;
        $items = is_array($value) ? $value : [$value];

        foreach ($items as $item) {
            $url = is_array($item) ? ($item['file_uri'] ?? null) : $item;
            
            if (is_string($url) && (filter_var($url, FILTER_VALIDATE_URL) || str_starts_with($url, 'data:image'))) {
                // Check if already in allImages to avoid duplicates
                $index = array_search($url, array_column($allImages, 'file_uri'));
                
                if ($index === false) {
                    $allImages[] = [
                        'file_uri' => $url,
                        'mime_type' => str_contains($url, '.png') ? 'image/png' : 'image/jpeg'
                    ];
                    $index = count($allImages) - 1;
                }
                
                $finalIndex = $index + 1;

                if (!isset($imageMapping[$key])) $imageMapping[$key] = [];
                if (!in_array($finalIndex, $imageMapping[$key])) {
                    $imageMapping[$key][] = $finalIndex;
                }
            }
        }
    }
}
