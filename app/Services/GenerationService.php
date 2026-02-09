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
        protected ProviderApiService $providerApi
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
                if ($value->isValid()) {
                    $path = $value->store('uploads/generations', 's3');
                    if ($path) {
                        $url = \Storage::disk('s3')->temporaryUrl($path, \Illuminate\Support\Carbon::now()->addHours(1));
                        
                        // Check if field expects array
                        $isArrayField = isset($fieldConfig['default']) && is_array($fieldConfig['default']);
                        $inputData[$key] = $isArrayField ? [$url] : $url;
                    }
                }
            } elseif (is_array($value)) {
                // Secondary check for array of files
                $urls = [];
                foreach ($value as $item) {
                    if ($item instanceof \Illuminate\Http\UploadedFile && $item->isValid()) {
                        $path = $item->store('uploads/generations', 's3');
                        if ($path) {
                            $urls[] = \Storage::disk('s3')->temporaryUrl($path, \Illuminate\Support\Carbon::now()->addHours(1));
                        }
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
        } else {
            $mappedPayload = $inputData;
        }
        
        Log::info("[GenerationService] Payload prepared", ['mapped_keys' => array_keys($mappedPayload)]);

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
        } catch (Exception $e) {
            Generation::create([
                'user_id' => $user->id,
                'ai_model_id' => $model->id,
                'ai_model_provider_id' => $provider->id,
                'status' => 'failed',
                'input_data' => $inputData,
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

        return Generation::create([
            'user_id' => $user->id,
            'ai_model_id' => $model->id,
            'ai_model_provider_id' => $provider->id,
            'status' => 'completed',
            'input_data' => $inputData,
            'output_data' => $executionResult['output'],
            'provider_cost_usd' => $costData['provider_cost'],
            'user_credit_cost' => $costData['credits'],
            'profit_usd' => $costData['profit_usd'],
        ]);
    }
}
