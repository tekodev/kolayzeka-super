<?php

namespace App\Services;

use App\Models\Provider;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class ProviderApiService
{
    /**
     * Fetch schema from the provider's API.
     * 
     * @param Provider $provider
     * @param string $providerModelId
     * @return array|null Raw schema data or null on failure
     */
    public function fetchSchema(Provider $provider, string $providerModelId): ?array
    {
        return match ($provider->type) {
            'fal_ai' => $this->fetchFalAiSchema($providerModelId),
            'replicate' => $this->fetchReplicateSchema($providerModelId),
            'fal_ai' => $this->fetchFalAiSchema($providerModelId),
            'replicate' => $this->fetchReplicateSchema($providerModelId),
            'google' => $this->fetchGoogleSchema($providerModelId),
            default => null,
        };
    }

    protected function fetchGoogleSchema(string $modelId): ?array
    {
        // Google Gemini doesn't have a standard JSON schema endpoint in the same way
        // Returning null allows the Dynamic Form to use manual schema or fallback
        return null; // Or return a hardcoded schema if needed
    }

    protected function fetchFalAiSchema(string $modelId): ?array
    {
        $key = config('services.fal_ai.key');
        if (!$key) {
            throw new \Exception('Fal.ai API Key is missing in .env');
        }

        // Fal.ai expects endpoint_id like "fal-ai/flux/dev"
        // API: GET https://api.fal.ai/v1/models?endpoint_id={id}&expand=openapi-3.0
        // Fal might return a list, or we can use specific endpoint if valid.
        // User example says: https://api.fal.ai/v1/models?endpoint_id=...
        
        $response = Http::withHeaders([
            'Authorization' => 'Key ' . $key,
            'Accept' => 'application/json',
        ])->get('https://api.fal.ai/v1/models', [
            'endpoint_id' => $modelId,
            'expand' => 'openapi-3.0',
        ]);

        if (!$response->successful()) {
            Log::error('Fal.ai fetch failed: ' . $response->body());
            return null;
        }

        $data = $response->json();
        \Log::info('Fal.ai Raw Response keys:', ['keys' => is_array($data) ? array_keys($data) : 'not an array']);
        
        $models = [];
        if (isset($data['models'])) {
            $models = $data['models'];
            \Log::info('Fal.ai: Found models key with ' . count($models) . ' items');
        } elseif (isset($data[0])) {
            $models = $data;
            \Log::info('Fal.ai: Response is direct array of ' . count($models) . ' items');
        } elseif (is_array($data) && !isset($data['openapi_schema'])) {
            // Might be a single model object wrapped in some other way? Or just the model itself.
            $models = [$data];
        }

        foreach ($models as $index => $item) {
            \Log::info('Checking model item at index ' . $index, ['keys' => array_keys($item)]);
            if (isset($item['openapi_schema'])) {
                \Log::info('Found openapi_schema at index ' . $index);
                return $item['openapi_schema'];
            }
            if (isset($item['openapi'])) {
                \Log::info('Found openapi at index ' . $index);
                return $item['openapi'];
            }
        }
        
        // If it's a single object directly containing the schema
        if (isset($data['openapi_schema'])) {
             \Log::info('Found openapi_schema in direct object');
             return $data['openapi_schema'];
        }
        if (isset($data['openapi'])) {
             \Log::info('Found openapi in direct object');
             return $data['openapi'];
        }

        \Log::warning('No openapi_schema found in Fal.ai response');
        return $data; // Fallback
    }

    protected function fetchReplicateSchema(string $modelId): ?array
    {
        $token = config('services.replicate.api_token');
        if (!$token) {
            throw new \Exception('Replicate API Token is missing in .env');
        }

        // Parse owner/name from "owner/name" or "owner/name:version"
        $parts = explode(':', $modelId);
        $slug = $parts[0]; // owner/name
        
        // GET https://api.replicate.com/v1/models/{model_owner}/{model_name}
        // This returns details including latest_version.
        
        $response = Http::withToken($token)->get("https://api.replicate.com/v1/models/{$slug}");

        if (!$response->successful()) {
            Log::error('Replicate fetch failed: ' . $response->body());
            return null;
        }

        $data = $response->json();
        
        if (isset($data['latest_version']['openapi_schema'])) {
             return $data['latest_version']['openapi_schema'];
        }

        return $data;
    }

    /**
     * Execute generation request to the provider.
     * 
     * @param Provider $provider
     * @param string $providerModelId
     * @param array $payload
     * @return array Result with output and metrics
     */
    public function execute(Provider $provider, string $providerModelId, array $payload): array
    {
        // Increase execution time for AI generation (5 minutes)
        set_time_limit(300);
        // Increase memory limit for handling large image payloads (Gemini)
        ini_set('memory_limit', '4096M');

        Log::info("[ProviderApiService] Executing provider", ['type' => $provider->type, 'model_id' => $providerModelId]);

        return match ($provider->type) {
            'fal_ai' => $this->executeFalAi($providerModelId, $payload),
            'replicate' => $this->executeReplicate($providerModelId, $payload),
            'fal_ai' => $this->executeFalAi($providerModelId, $payload),
            'replicate' => $this->executeReplicate($providerModelId, $payload),
            'google' => $this->executeGoogle($providerModelId, $payload),
            default => throw new \Exception('Unknown provider type: ' . $provider->type),
        };
    }

    protected function executeFalAi(string $modelId, array $payload): array
    {
        $key = config('services.fal_ai.key');
        if (!$key) {
            throw new \Exception('Fal.ai API Key is missing in .env');
        }

        $startTime = microtime(true);
        
        // Fal.ai POST to /fal-ai/{endpoint_id}
        Log::info("[ProviderApiService] Calling Fal.ai", ['url' => "https://fal.run/{$modelId}"]);

        $response = Http::withHeaders([
            'Authorization' => 'Key ' . $key,
            'Content-Type' => 'application/json',
        ])->post("https://fal.run/{$modelId}", $payload);

        if (!$response->successful()) {
            Log::error('Fal.ai execution failed: ' . $response->body());
            throw new \Exception('Fal.ai API error: ' . $response->status());
        }

        $result = $response->json();
        Log::info("[ProviderApiService] Fal.ai Response", ['status' => $response->status(), 'keys' => array_keys($result)]);
        $duration = microtime(true) - $startTime;

        // More robust result extraction
        $outputUrl = null;
        if (isset($result['images'][0]['url'])) {
            $outputUrl = $result['images'][0]['url'];
        } elseif (isset($result['image']['url'])) {
            $outputUrl = $result['image']['url'];
        } elseif (isset($result['output'])) {
            $outputUrl = is_array($result['output']) ? ($result['output'][0]['url'] ?? $result['output'][0]) : $result['output'];
        }

        return [
            'output' => [
                'result' => $outputUrl ?? $result,
                'raw' => $result,
            ],
            'metrics' => [
                'duration' => $duration,
                'count' => isset($result['images']) ? count($result['images']) : 1,
                'tokens' => 0, // Fal doesn't return token count for images
            ],
        ];
    }

    protected function executeReplicate(string $modelId, array $payload): array
    {
        $token = config('services.replicate.api_token');
        if (!$token) {
            throw new \Exception('Replicate API Token is missing in .env');
        }

        $startTime = microtime(true);

        // Log payload for debugging
        Log::info('Replicate payload:', ['model' => $modelId, 'payload' => $payload]);

        // Fix: Replicate API expects "jpg" or "png", but some schemas send "jpeg"
        if (isset($payload['output_format']) && $payload['output_format'] === 'jpeg') {
            $payload['output_format'] = 'jpg';
        }

        // Replicate uses predictions API
        $response = Http::withToken($token)->post('https://api.replicate.com/v1/predictions', [
            'version' => $this->getReplicateVersion($modelId),
            'input' => $payload,
        ]);

        if (!$response->successful()) {
            Log::error('Replicate execution failed: ' . $response->body());
            throw new \Exception('Replicate API error: ' . $response->status());
        }

        $prediction = $response->json();
        
        // Wait for completion (polling)
        $predictionUrl = $prediction['urls']['get'];
        $maxAttempts = 60; // 60 seconds timeout
        $attempt = 0;

        while ($prediction['status'] !== 'succeeded' && $prediction['status'] !== 'failed' && $attempt < $maxAttempts) {
            sleep(1);
            $attempt++;
            
            $statusResponse = Http::withToken($token)->get($predictionUrl);
            $prediction = $statusResponse->json();
        }

        if ($prediction['status'] === 'failed') {
            throw new \Exception('Replicate generation failed: ' . ($prediction['error'] ?? 'Unknown error'));
        }

        $duration = microtime(true) - $startTime;

        return [
            'output' => [
                'result' => is_array($prediction['output']) ? $prediction['output'][0] : $prediction['output'],
                'raw' => $prediction,
            ],
            'metrics' => [
                'duration' => $duration,
                'count' => is_array($prediction['output']) ? count($prediction['output']) : 1,
                'tokens' => $prediction['metrics']['predict_time'] ?? 0,
            ],
        ];
    }

    protected function getReplicateVersion(string $modelId): string
    {
        // If modelId includes version (owner/name:version), extract it
        if (str_contains($modelId, ':')) {
            return explode(':', $modelId)[1];
        }

        // Otherwise fetch latest version
        $token = config('services.replicate.api_token');
        $slug = explode(':', $modelId)[0];
        
        $response = Http::withToken($token)->get("https://api.replicate.com/v1/models/{$slug}");
        $data = $response->json();
        
        return $data['latest_version']['id'] ?? throw new \Exception('Could not find Replicate version');
    }

    protected function executeGoogle(string $modelId, array $payload): array
    {
        $key = config('services.google.key');
        if (!$key) {
            Log::error('Google API Key is missing in .env');
            throw new \Exception('Google API Key is missing in .env');
        }
        $key = trim($key); // Remove any accidental whitespace

        $startTime = microtime(true);

        $contents = [];
        $parts = [];

        // 1. Add Prompt
        if (!empty($payload['prompt'])) {
             $parts[] = ['text' => $payload['prompt']];
        }

        // 2. Handle Inputs (Images)
        // We iterate through payload to find image URLs and convert them to inline_data
        foreach ($payload as $payloadKey => $value) { // FIX: Renamed $key to $payloadKey to avoid overwriting API Key
            if ($payloadKey === 'prompt') continue;
            
            // Check if value is a URL and looks like an image
            if (is_string($value) && filter_var($value, FILTER_VALIDATE_URL)) {
                // Basic check for image extension or if it comes from our S3
                // Ideally, AppsController should classify these better, but we can try to detect
                $imageContent = @file_get_contents($value);
                if ($imageContent) {
                    $mimeType = 'image/jpeg'; // Default or detect
                    $finfo = new \finfo(FILEINFO_MIME_TYPE);
                    $detectedMime = $finfo->buffer($imageContent);
                    if ($detectedMime) $mimeType = $detectedMime;

                    $parts[] = [
                        'inline_data' => [
                            'mime_type' => $mimeType,
                            'data' => base64_encode($imageContent)
                        ]
                    ];
                }
            } elseif (is_array($value)) {
                 // Array of URLs or FileData
                 foreach ($value as $item) {
                    if (is_array($item) && isset($item['file_uri']) && isset($item['mime_type'])) {
                        // Optimized Google File API usage
                        $parts[] = [
                            'file_data' => [
                                'mime_type' => $item['mime_type'],
                                'file_uri' => $item['file_uri']
                            ]
                        ];
                    } elseif (is_string($item) && filter_var($item, FILTER_VALIDATE_URL)) {
                        $imageContent = @file_get_contents($item);
                        if ($imageContent) {
                            $mimeType = 'image/jpeg'; 
                            $finfo = new \finfo(FILEINFO_MIME_TYPE);
                            $detectedMime = $finfo->buffer($imageContent);
                            if ($detectedMime) $mimeType = $detectedMime;
        
                            $parts[] = [
                                'inline_data' => [
                                    'mime_type' => $mimeType,
                                    'data' => base64_encode($imageContent)
                                ]
                            ];
                        }
                        // Explicitly unset to free memory inside loop
                        unset($imageContent);
                    }
                 }
            }
        }

        $contents[] = ['parts' => $parts];

        // API Endpoint
        // POST https://generativelanguage.googleapis.com/v1beta/models/{modelId}:generateContent
        // We use header for API key as documented and cleaner
        $url = "https://generativelanguage.googleapis.com/v1beta/models/{$modelId}:generateContent";

        // 3. Configure Image Generation Parameters
        $generationConfig = [
            'responseModalities' => ['TEXT', 'IMAGE'],
        ];

        // Map aspect_ratio and image_resolution to Gemini's imageConfig
        $imageConfig = [];
        if (!empty($payload['aspect_ratio'])) {
            $imageConfig['aspectRatio'] = $payload['aspect_ratio'];
        }
        if (!empty($payload['image_resolution'])) {
            $imageConfig['imageSize'] = $payload['image_resolution'];
        }

        if (!empty($imageConfig)) {
            $generationConfig['imageConfig'] = $imageConfig;
        }

        Log::info("[ProviderApiService] Calling Google Gemini", [
            'url' => $url,
            'parts_count' => count($parts),
            'generation_config' => $generationConfig
        ]);

        $response = Http::withHeaders([
            'Content-Type' => 'application/json',
            'x-goog-api-key' => $key,
        ])->timeout(180)->post($url, [
            'contents' => $contents,
            'generationConfig' => $generationConfig
        ]);

        if (!$response->successful()) {
            Log::error('Google Gemini execution failed: ' . $response->body());
            throw new \Exception('Google API error: ' . $response->status() . ' - ' . $response->body());
        }

        $result = $response->json();
        Log::info("[ProviderApiService] Google Gemini Response", [
            'status' => $response->status(), 
            'candidates_count' => count($result['candidates'] ?? [])
        ]);
        $duration = microtime(true) - $startTime;
        
        // Extract Output
        // Extract Output
        // Gemini output usually structure: candidates[0].content.parts[0].text (or inline_data for image)
        // For Image Generation models, it might verify differently.
        // Assuming getting an Image back (base64) or a URL. 
        // If Gemini returns base64 image:
        $outputData = $result;
        $finalOutput = null;

        // Check for inlineData (camelCase) or inline_data (snake_case)
        $firstPart = $result['candidates'][0]['content']['parts'][0] ?? null;

        if ($firstPart) {
            $base64Image = null;

            if (isset($firstPart['inlineData'])) {
                 // CamelCase (Standard API Response)
                 $base64Image = $firstPart['inlineData']['data'];
            } elseif (isset($firstPart['inline_data'])) {
                 // SnakeCase (Older versions or SDK wrappers)
                 $base64Image = $firstPart['inline_data']['data'];
            } elseif (isset($firstPart['text'])) {
                 // It's text
                 $finalOutput = $firstPart['text'];
            }

            if ($base64Image) {
                 // Decode Base64
                 $imageData = base64_decode($base64Image);
                 
                 // Generate Unique Filename Base
                 $fileBase = \Illuminate\Support\Str::random(40);
                 $fileName = 'generations/' . $fileBase . '.jpg';
                 $thumbName = 'generations/' . $fileBase . '_thumb.jpg';

                 // Upload Original to S3
                 \Storage::disk('s3')->put($fileName, $imageData);
                 $finalOutput = \Storage::disk('s3')->url($fileName);
                 
                 // Generate Thumbnail (Requires GD)
                 if (extension_loaded('gd')) {
                     try {
                        $src = imagecreatefromstring($imageData);
                        if ($src) {
                            $width = imagesx($src);
                            $height = imagesy($src);
                            
                            // Calculate new dimensions (Max width 300)
                            $newWidth = 300;
                            $newHeight = floor($height * ($newWidth / $width));

                            $dst = imagecreatetruecolor($newWidth, $newHeight);
                            imagecopyresampled($dst, $src, 0, 0, 0, 0, $newWidth, $newHeight, $width, $height);
                            
                            // Buffer to variable
                            ob_start();
                            imagejpeg($dst, null, 75); // 75% quality for thumbnail
                            $thumbData = ob_get_clean();
                            
                            // Upload Thumbnail
                            \Storage::disk('s3')->put($thumbName, $thumbData);
                            $thumbOutput = \Storage::disk('s3')->url($thumbName);

                            imagedestroy($src);
                            imagedestroy($dst);
                        }
                     } catch (\Exception $e) {
                         Log::warning('Thumbnail generation failed: ' . $e->getMessage());
                     }
                 }
            }
        }

        return [
            'output' => [
                'result' => $finalOutput ?? $result, // Fallback
                'thumbnail' => $thumbOutput ?? null,
                'raw' => $result,
            ],
            'metrics' => [
                'duration' => $duration,
                'count' => 1,
                'tokens' => $result['usageMetadata']['totalTokenCount'] ?? 0,
            ],
        ];
    }
}
