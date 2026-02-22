<?php

namespace App\Services\AiProviders;

use App\Models\Provider;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class FalAiProvider implements AiProviderInterface
{
    public function generate(Provider $provider, string $providerModelId, array $payload): array
    {
        $key = $this->getProviderKey($provider, 'services.fal_ai.key');
        if (!$key) {
             throw new \Exception('Fal.ai API Key is missing (Check provider api_key_env or .env)');
        }
        $key = trim($key); // Ensure no whitespace

        $startTime = microtime(true);
        Log::info("[FalAiProvider] Calling Fal.ai", ['url' => "https://fal.run/{$providerModelId}"]);
        
        $maskedKey = substr($key, 0, 5) . '...' . substr($key, -5);
        Log::info("[FalAiProvider] Using Key: {$maskedKey} (Length: " . strlen($key) . ")");
        
        // Resolve Dynamic Template
        $aiModelProvider = \App\Models\AiModelProvider::where('provider_id', $provider->id)
            ->where('provider_model_id', $providerModelId)
            ->with('schema')
            ->first();
            
        $schema = $aiModelProvider?->schema;
        $requestTemplate = $schema?->request_template;
        $responsePath = $schema?->response_path;

        if (!$requestTemplate) {
            throw new \Exception("Configuration Error: Request Template is missing for this model. Please configure it in the Admin Panel.");
        }
        
        if (!$responsePath) {
             throw new \Exception("Configuration Error: Response Path is missing for this model. Please configure it in the Admin Panel.");
        }

        // --- DYNAMIC MODE ---
        Log::info("[FalAiProvider] Using Dynamic Request Template");
        Log::info("[FalAiProvider] Input Payload: ", $payload);

        // Recursive function to replace placeholders in array
        $processTemplate = function ($template, $data) use (&$processTemplate) {
            $result = [];
            foreach ($template as $key => $value) {
                if (is_array($value)) {
                    $result[$key] = $processTemplate($value, $data);
                    continue;
                }

                if (is_string($value) && preg_match('/\{\{\s*([^}]+)\s*\}\}/', $value, $matches)) {
                    $variable = $matches[1];
                    // Check if data has this variable
                    if (array_key_exists($variable, $data)) {
                         $val = $data[$variable];
                         
                         // Type casing based on value
                         if ($val === 'true') $val = true;
                         if ($val === 'false') $val = false;
                         
                         // Fix for Dict/Object fields (e.g. voice_setting) coming as JSON strings
                         if (is_string($val) && (str_starts_with(trim($val), '{') || str_starts_with(trim($val), '['))) {
                             $decoded = json_decode($val, true);
                             if (json_last_error() === JSON_ERROR_NONE) {
                                 $val = $decoded;
                             }
                         }

                         if (is_numeric($val) && strpos($variable, 'seed') !== false) $val = (int)$val; 
                         if (is_numeric($val) && strpos($variable, 'steps') !== false) $val = (int)$val;
                         if (is_numeric($val) && strpos($variable, 'images') !== false) $val = (int)$val;
                         
                         $result[$key] = $val;
                    } else {
                        // Variable not in payload. 
                        // If it's something like "seed" and we have no value, maybe null?
                        // For now, keep original or null? 
                        // If we return null, json_encode handles it.
                        $result[$key] = null;
                    }
                } else {
                    $result[$key] = $value;
                }
            }
            return $result;
        };

        $finalPayload = $processTemplate($requestTemplate, $payload);
        
        // Filter out null values if needed? Some APIs hate nulls, some ignore them.
        // For Fal.ai, 'seed': null usually implies ranom.
        $finalPayload = array_filter($finalPayload, fn($v) => !is_null($v));
        
        Log::info("[FalAiProvider] Final Payload: ", $finalPayload);

        $response = Http::timeout(300)
            ->withHeaders([
                'Authorization' => 'Key ' . $key,
                'Content-Type' => 'application/json',
            ])->post("https://fal.run/{$providerModelId}", $finalPayload);

        if (!$response->successful()) {
            Log::error('Fal.ai execution failed: ' . $response->body());
            throw new \App\Exceptions\ProviderRequestException(
                'Fal.ai API error: ' . $response->status(),
                $finalPayload,
                $response->body()
            );
        }

        $result = $response->json();
        Log::info("[FalAiProvider] Fal.ai Response", ['status' => $response->status(), 'keys' => array_keys($result)]);
        $duration = microtime(true) - $startTime;

        // Dynamic Path Extraction Only
        $outputUrl = data_get($result, $responsePath);
        
        if (!$outputUrl) {
             Log::warning('[FalAiProvider] Returned no content for path: ' . $responsePath, ['response' => $result]);
             throw new \Exception('Fal.ai returned no content (Path: ' . $responsePath . ')');
        }

        // Handle case where path resolves to an array of images/objects
        if (is_array($outputUrl)) {
            Log::info("[FalAiProvider] Output URL is an array, formatting as array of URLs.");
            
            $formattedUrls = [];
            foreach ($outputUrl as $item) {
                if (is_array($item) && isset($item['url'])) {
                    $formattedUrls[] = $item['url'];
                } elseif (is_string($item)) {
                    $formattedUrls[] = $item;
                }
            }
            
            if (!empty($formattedUrls)) {
                // If there's only 1, unwrap it (optional, but requested by user to see all, so let's keep array if originally array, or if multiple)
                // Actually, if they want to see "all of them if multiple", we should always pass array if there's multiple.
                $outputUrl = count($formattedUrls) === 1 ? $formattedUrls[0] : $formattedUrls;
                Log::info("[FalAiProvider] Successfully formatted URLs from array.", ['count' => count($formattedUrls)]);
            } else {
                Log::warning("[FalAiProvider] Could not extract string URLs from array. Encoding as JSON.");
                $outputUrl = empty($outputUrl) ? null : json_encode($outputUrl);
            }
        }

        return [
            'output' => [
                'result' => $outputUrl,
                'raw' => $result,
                'request_body' => $finalPayload, // Add for logging
            ],
            'metrics' => [
                'duration' => $duration,
                'count' => isset($result['images']) ? count($result['images']) : 1,
                'tokens' => 0, 
            ],
        ];
    }

    public function fetchSchema(string $providerModelId): ?array
    {
        $key = config('services.fal_ai.key'); 
        
        Log::info("[FalAiProvider] Fetching schema for: {$providerModelId}");

        $response = Http::withHeaders([
            'Authorization' => 'Key ' . $key,
        ])->get('https://api.fal.ai/v1/models', [
            'endpoint_id' => $providerModelId,
            'expand' => 'openapi-3.0',
        ]);

        Log::info("[FalAiProvider] Schema Response Status: " . $response->status());

        if ($response->successful()) {
            $data = $response->json();
            
            // Log the keys of the first item to see structure
            if (!empty($data) && isset($data['models']) && isset($data['models'][0])) {
                $firstModel = $data['models'][0];
                Log::info("[FalAiProvider] First model keys: " . implode(',', array_keys($firstModel)));
                
                if (isset($firstModel['openapi'])) {
                    Log::info("[FalAiProvider] Found 'openapi' key.");
                    return $firstModel['openapi'];
                }
                
                if (isset($firstModel['openapi_schema'])) {
                    Log::info("[FalAiProvider] Found 'openapi_schema' key.");
                    return $firstModel['openapi_schema'];
                }
                
                Log::warning("[FalAiProvider] Neither 'openapi' nor 'openapi_schema' found in first model.");
            } else {
                Log::warning("[FalAiProvider] 'models' key missing or empty.", ['data_keys' => array_keys($data)]);
            }
        } else {
             Log::error("[FalAiProvider] Schema available check failed: " . $response->body());
        }
        
        return null;
    }

    /**
     * Helper to get API key from Provider's dynamic env or fallback to config
     */
    protected function getProviderKey(?Provider $provider, string $configFallback): ?string
    {
        if ($provider && $provider->api_key_env) {
            $value = env($provider->api_key_env);
            if ($value) return $value;
        }

        return config($configFallback);
    }
}
