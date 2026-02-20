<?php

namespace App\Services;

use App\Models\User;
use App\Models\AiModel;
use App\Models\Generation;
use Exception;
use Illuminate\Support\Facades\Log;

class GenerationService
{
    public function __construct(
        protected CreditService $creditService,
        protected CostCalculatorService $costCalculator,
        protected ProviderApiService $providerApi,
        protected MediaService $mediaService
    ) {}

    public function generate(User $user, AiModel $model, array $inputData)
    {
        $start = microtime(true);
        Log::info("[Performance-Service] Generation Logic Started");

        // 1. Select Provider (Primary)
        $provider = $model->providers()->where('is_primary', true)->with(['schema', 'provider'])->first();
        
        if (!$provider) {
             Log::error("[GenerationService] No active provider found", ['model_id' => $model->id, 'model_slug' => $model->slug]);
            throw new Exception("No active provider found for model: {$model->name}");
        }
        Log::info("[GenerationService] Provider selected", ['provider' => $provider->provider->name, 'model_id' => $provider->provider_model_id]);

        // 2. Prepare Schema & Field Types for normalization
        $schema = $provider->schema;
        $inputSchema = $schema?->input_schema ?? [];
        $fieldTypes = [];
        foreach ($inputSchema as $field) {
            if (isset($field['key'])) {
                $fieldTypes[$field['key']] = $field;
            }
        }

        // 3. Process Input Data (Files, Casting, Normalization)
        $processingStart = microtime(true);
        foreach ($inputData as $key => $value) {
            $fieldConfig = $fieldTypes[$key] ?? null;
            $type = $fieldConfig['type'] ?? 'text';
            
            // Cast numeric/boolean values if they are strings (common in multipart/form-data)
            if (is_string($value)) {
                if ($type === 'number') {
                    $inputData[$key] = str_contains($value, '.') ? (float) $value : (int) $value;
                } elseif ($type === 'integer') {
                    $inputData[$key] = (int) $value;
                } elseif ($type === 'toggle' || $type === 'boolean') {
                    $inputData[$key] = filter_var($value, FILTER_VALIDATE_BOOLEAN);
                }
            }

            // Handle File Uploads (S3)
            if ($value instanceof \Illuminate\Http\UploadedFile) {
                $url = $this->mediaService->upload($value);
                if ($url) {
                    $isArrayField = isset($fieldConfig['default']) && is_array($fieldConfig['default']);
                    $inputData[$key] = $isArrayField ? [$url] : $url;
                }
            } elseif (is_array($value)) {
                // Secondary check for array of files
                $urls = [];
                foreach ($value as $item) {
                    if ($item instanceof \Illuminate\Http\UploadedFile) {
                        $url = $this->mediaService->upload($item);
                        if ($url) $urls[] = $url;
                    }
                }
                if (!empty($urls)) {
                    $inputData[$key] = $urls;
                }
            }
        }
        $processingDuration = microtime(true) - $processingStart;
        Log::info("[Performance-Service] Input Processing (S3 Uploads) took: " . number_format($processingDuration, 4) . "s");

        // Value normalization for common provider requirements
        if (isset($inputData['output_format']) && $inputData['output_format'] === 'jpg') {
            $inputData['output_format'] = 'jpeg';
        }

        if (isset($inputData['aspect_ratio']) && $inputData['aspect_ratio'] === 'match_input_image') {
            $options = $fieldTypes['aspect_ratio']['options'] ?? [];
            if (!collect($options)->contains('value', 'match_input_image')) {
                $inputData['aspect_ratio'] = '1:1';
            }
        }

        // 4. Apply field mapping for the provider payload
        $mappedPayload = [];
        if ($schema && $schema->field_mapping) {
            foreach ($schema->field_mapping as $standardField => $providerField) {
                if (isset($inputData[$standardField])) {
                    $mappedPayload[$providerField] = $inputData[$standardField];
                }
            }
            
            // Allow fields that are NOT in mapping but ARE in inputData (fallback for pass-through)
            foreach ($inputData as $key => $val) {
                if (!isset($schema->field_mapping[$key]) && !isset($mappedPayload[$key])) {
                     // Check if this key exists as a VALUE in field_mapping (meaning it's already mapped)
                     if (!in_array($key, $schema->field_mapping)) {
                        $mappedPayload[$key] = $val;
                     }
                }
            }
            
            // Explicit Pass-through for internal metadata
            if (isset($inputData['ordered_images'])) {
                $mappedPayload['ordered_images'] = $inputData['ordered_images'];
            }
        } else {
            $mappedPayload = $inputData;
        }
        
        Log::info("[GenerationService] Payload prepared", ['mapped_keys' => array_keys($mappedPayload)]);

        // 1. Validate & Prepare Input
        Log::info("[GenerationService] Raw Input Data Keys", ['keys' => array_keys($inputData)]);
        if (isset($inputData['image'])) {
             Log::info("[GenerationService] Input 'image' type: " . gettype($inputData['image']));
             if (is_array($inputData['image'])) {
                 Log::info("[GenerationService] Input 'image' content: " . json_encode($inputData['image']));
             } else {
                 Log::info("[GenerationService] Input 'image' value (first 50 chars): " . substr((string)$inputData['image'], 0, 50));
             }
        } else {
            Log::info("[GenerationService] Input 'image' is NOT set in inputData");
        }

        // 5. Execute real API call
        try {
            Log::info("[Performance-Service] Calling Provider API: {$provider->provider->name}");
            $apiStart = microtime(true);
            $executionResult = $this->providerApi->execute(
                $provider->provider,
                $provider->provider_model_id,
                $mappedPayload
            );
            $apiDuration = microtime(true) - $apiStart;
            Log::info("[Performance-Service] Provider API Execution took: " . number_format($apiDuration, 4) . "s");
        } catch (\App\Exceptions\ProviderRequestException $e) {
            $fullErrorMessage = $e->getMessage();
            if ($e->getResponseBody()) {
                $fullErrorMessage .= " - Details: " . $e->getResponseBody();
            }

            Generation::create([
                'user_id' => $user->id,
                'ai_model_id' => $model->id,
                'ai_model_provider_id' => $provider->id,
                'status' => 'failed',
                'input_data' => $this->sanitizePayload($inputData),
                'provider_request_body' => $this->sanitizePayload($e->getRequestBody()),
                'error_message' => $fullErrorMessage,
            ]);
            throw $e;
        } catch (Exception $e) {
            Generation::create([
                'user_id' => $user->id,
                'ai_model_id' => $model->id,
                'ai_model_provider_id' => $provider->id,
                'status' => 'failed',
                'input_data' => $this->sanitizePayload($inputData),
                'error_message' => $e->getMessage(),
            ]);
            throw $e;
        }
        
        Log::info("[GenerationService] API Execution successful");

        // 4. Calculate Costs
        $costData = $this->costCalculator->calculate($provider, $executionResult['metrics']);

        // 5. Deduct Credits
        $this->creditService->withdraw(
            $user, 
            $costData['credits'], 
            'usage', 
            [
                'model_slug' => $model->slug,
                'provider' => $provider->provider->name
            ]
        );

        // 6. Record Generation
        $finalDuration = microtime(true) - $start;
        Log::info("[Performance-Service] Full Service Flow took: " . number_format($finalDuration, 4) . "s");

        $outputData = $executionResult['output'];
        $providerRequestBody = $this->sanitizePayload($outputData['request_body'] ?? null);
        
        // Handle Async/Processing Status (Video Generation)
        if (isset($outputData['status']) && $outputData['status'] === 'processing') {
            Log::info("[GenerationService] Async generation started (Operation: {$outputData['operation_name']})");
            
            unset($outputData['request_body']); // Clean up
            
             // Sanitize output_data (may contain Base64 images or long strings)
            $sanitizedOutputData = $outputData;
            array_walk_recursive($sanitizedOutputData, function (&$value) {
                if (is_string($value) && strlen($value) > 1000 && !filter_var($value, FILTER_VALIDATE_URL)) {
                    // If it's a long string and NOT a URL, it's probably Base64 - truncate it
                    $value = '[LARGE_DATA_REMOVED_' . strlen($value) . '_BYTES]';
                }
            });

            // Sanitize input_data
            $sanitizedInputData = $inputData;
             array_walk_recursive($sanitizedInputData, function (&$value) {
                if (is_string($value) && strlen($value) > 1000 && !filter_var($value, FILTER_VALIDATE_URL)) {
                    $value = '[LARGE_DATA_REMOVED_' . strlen($value) . '_BYTES]';
                }
            });

            $generation = Generation::create([
                'user_id' => $user->id,
                'ai_model_id' => $model->id,
                'ai_model_provider_id' => $provider->id,
                'status' => 'processing',
                'input_data' => $sanitizedInputData, // Ensure sanitized
                'output_data' => $sanitizedOutputData, // Contains operation_name
                'provider_request_body' => $providerRequestBody,
                'provider_cost_usd' => $costData['provider_cost'],
                'user_credit_cost' => $costData['credits'],
                'profit_usd' => $costData['profit_usd'],
                'duration' => $finalDuration, // Init duration
            ]);

            // Dispatch Job to Check Status
            \App\Jobs\CheckVideoGenerationStatus::dispatch($generation)->delay(now()->addSeconds(10));

            return $generation;
        }

        // --- Standard Sync Flow continues ---

        if ($providerRequestBody) {
            Log::info("[GenerationService] Captured Provider Request Body", ['keys' => array_keys($providerRequestBody)]);
        } else {
            Log::warning("[GenerationService] Provider Request Body MISSING in execution result");
        }

        unset($outputData['request_body']); // Remove from output to avoid duplication

        $sanitizedInputData = $this->sanitizePayload($inputData);
        $sanitizedOutputData = $this->sanitizePayload($outputData);

        // Determine Thumbnail URL with Compression
        $thumbnailUrl = $sanitizedOutputData['thumbnail_url'] ?? null;
        
        // If provider didn't generate thumbnail, try to generate it ourselves
        if (!$thumbnailUrl && isset($sanitizedOutputData['result']) && is_string($sanitizedOutputData['result']) && filter_var($sanitizedOutputData['result'], FILTER_VALIDATE_URL)) {
            $path = parse_url($sanitizedOutputData['result'], PHP_URL_PATH);
            $ext = strtolower(pathinfo($path, PATHINFO_EXTENSION));
            
            if (in_array($ext, ['jpg', 'jpeg', 'png', 'webp', 'gif'])) {
                // Determine quality based on extension or default
                // Convert URL to Base64 first
                $base64Image = $this->mediaService->convertUrlToBase64($sanitizedOutputData['result']);
                
                if ($base64Image) {
                    // Generate compressed thumbnail (max width 300, quality 60)
                    $thumbnailUrl = $this->mediaService->generateThumbnail($base64Image, 300, 60);
                } else {
                    // Fallback to original URL if conversion fails
                    Log::warning("[GenerationService] Failed to convert result URL to Base64 for thumbnail generation. Using original URL.");
                    $thumbnailUrl = $sanitizedOutputData['result'];
                }
            }
        }

        return Generation::create([
            'user_id' => $user->id,
            'ai_model_id' => $model->id,
            'ai_model_provider_id' => $provider->id,
            'status' => 'completed',
            'input_data' => $sanitizedInputData,
            'output_data' => $sanitizedOutputData,
            'thumbnail_url' => $thumbnailUrl,
            'provider_request_body' => $providerRequestBody,
            'provider_cost_usd' => $costData['provider_cost'],
            'user_credit_cost' => $costData['credits'],
            'profit_usd' => $costData['profit_usd'],
            'duration' => $finalDuration,
        ]);
    }

    /**
     * Sanitize payload (input, output or request body) by truncating massive Base64 strings.
     * Prevents memory exhaustion and database bloat.
     */
    private function sanitizePayload($payload)
    {
        if (!is_array($payload)) return $payload;
        
        $sanitized = $payload;
        array_walk_recursive($sanitized, function (&$value) {
            if (is_string($value) && strlen($value) > 1000 && !filter_var($value, FILTER_VALIDATE_URL)) {
                $value = '[LARGE_DATA_REMOVED_' . strlen($value) . '_BYTES]';
            }
        });
        
        return $sanitized;
    }
}
