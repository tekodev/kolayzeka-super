<?php

namespace App\Services\AiProviders;

use App\Models\Provider;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class GoogleProvider implements AiProviderInterface, AsyncAiProviderInterface
{
    // ... (existing code) ...


    public function generate(Provider $provider, string $providerModelId, array $payload): array
    {
        $key = $this->getProviderKey($provider, 'services.google.key');

        if (!$key) {
            Log::error('Google API Key is missing');
            throw new \Exception('Google API Key is missing (Check provider api_key_env or .env)');
        }
        $key = trim($key); 

        $startTime = microtime(true);

        Log::info("[GoogleProvider] Starting generation", [
            'model' => $providerModelId, 
            'payload_keys' => array_keys($payload),
            'has_ordered_images' => isset($payload['ordered_images']),
            'ordered_images_count' => isset($payload['ordered_images']) ? count($payload['ordered_images']) : 0
        ]);

        // Retrieve Schema
        $aiModelProvider = \App\Models\AiModelProvider::where('provider_id', $provider->id)
            ->where('provider_model_id', $providerModelId)
            ->with('schema')
            ->first();
            
        $schema = $aiModelProvider?->schema;
        $requestTemplate = $schema?->request_template;
        $responsePath = $schema?->response_path;

        $isLongRunning = str_contains($providerModelId, ':predictLongRunning');

        if (!$requestTemplate) {
            throw new \Exception("Configuration Error: Request Template is missing for this model. Please configure it in the Admin Panel.");
        }
        
        if (!$responsePath && !$isLongRunning) {
             throw new \Exception("Configuration Error: Response Path is missing for this model. Please configure it in the Admin Panel.");
        }

        // --- DYNAMIC MODE ---
        $jsonBody = json_encode($requestTemplate);
        
        // Replace placeholders & Handle Images
        $base64ToUrlMapping = []; 
        $collectedImages = []; // To store all processed images for auto-append

        foreach ($payload as $fieldKey => $value) {
            if (!$value) continue;

            $items = is_array($value) ? $value : [$value];
            $isImageField = false;

            foreach ($items as $item) {
                $path = is_array($item) ? ($item['file_uri'] ?? null) : $item;
                
                if (is_string($path)) {
                    $isImage = false;
                    $isBase64 = false;
                    $mimeType = 'image/jpeg';

                    if (filter_var($path, FILTER_VALIDATE_URL)) {
                        $parsedPath = parse_url($path, PHP_URL_PATH);
                        $ext = strtolower(pathinfo($parsedPath, PATHINFO_EXTENSION));
                        if (in_array($ext, ['jpg', 'jpeg', 'png', 'webp', 'heic'])) {
                            $isImage = true;
                            $mimeType = 'image/' . ($ext === 'jpg' ? 'jpeg' : $ext);
                        }
                    } elseif (str_starts_with($path, 'data:image')) {
                        $isImage = true;
                        $isBase64 = true;
                        if (preg_match('/^data:(image\/\w+);base64,/', $path, $matches)) {
                            $mimeType = $matches[1];
                        }
                    }

                    if ($isImage) {
                        $isImageField = true;
                        /** @var \App\Services\MediaService $mediaService */
                        $mediaService = app(\App\Services\MediaService::class);
                        $base64 = $isBase64 ? preg_replace('/^data:image\/\w+;base64,/', '', $path) : $mediaService->convertUrlToBase64($path);

                        if ($base64) {
                            $base64ToUrlMapping[$base64] = $isBase64 ? '[BASE64_IMAGE]' : $path;
                            
                            $imageStructure = $isLongRunning ? [
                                'mimeType' => $mimeType,
                                'bytesBase64Encoded' => $base64
                            ] : [
                                'inline_data' => [
                                    'mime_type' => $mimeType,
                                    'data' => $base64
                                ]
                            ];

                            $collectedImages[] = $imageStructure;

                            // Handle explicit mapping if placeholder exists
                            $search = '"{{' . $fieldKey . '}}"';
                            if (str_contains($jsonBody, $search)) {
                                $jsonBody = str_replace($search, json_encode($imageStructure), $jsonBody);
                            }
                        }
                    }
                }
            }

            if (!$isImageField) {
                // TEXT HANDLING
                $replaced = false;

                // 1. Integer Modifier: "{{key|int}}"
                $patternInt = '/"\{\{\s*' . preg_quote($fieldKey) . '\|int\s*\}\}"/';
                if (preg_match($patternInt, $jsonBody)) {
                    $jsonBody = preg_replace($patternInt, (int) $value, $jsonBody);
                    $replaced = true;
                }

                // 2. Standard Replacement
                if (!$replaced) {
                    $quotedPattern = '/"\{\{\s*' . preg_quote($fieldKey) . '\s*\}\}"/';
                    if (preg_match($quotedPattern, $jsonBody)) {
                        $jsonBody = preg_replace($quotedPattern, json_encode($value), $jsonBody);
                        $replaced = true;
                    } else {
                        $patternStd = '/\{\{\s*' . preg_quote($fieldKey) . '\s*\}\}/';
                        if (preg_match($patternStd, $jsonBody)) {
                            $encodedValue = json_encode($value);
                            if (str_starts_with($encodedValue, '"') && str_ends_with($encodedValue, '"')) {
                                $encodedValue = substr($encodedValue, 1, -1);
                            }
                            $jsonBody = preg_replace($patternStd, $encodedValue, $jsonBody);
                            $replaced = true;
                        }
                    }
                }
            }
        }
        
        // Cleanup placeholders
        $jsonBody = preg_replace('/,\s*"(?<!\\\\)\{\{[^}]+\}\}"/', '', $jsonBody); 
        $jsonBody = preg_replace('/"(?<!\\\\)\{\{[^}]+\}\}"\s*,/', '', $jsonBody);
        $jsonBody = preg_replace('/"(?<!\\\\)\{\{[^}]+\}\}"/', '""', $jsonBody); 

        // Build Google Payload
        $googlePayload = json_decode($jsonBody, true);

        if (json_last_error() !== JSON_ERROR_NONE || !is_array($googlePayload)) {
            Log::error("[GoogleProvider] JSON Construction Failed", [
                'error' => json_last_error_msg(),
                'json' => $jsonBody
            ]);
            throw new \Exception("Failed to construct valid JSON payload for Google API.");
        }

        // Veo/predictLongRunning payload must be instances+parameters.
        // If stale templates include Gemini-style fields, normalize here.
        if ($isLongRunning) {
            unset($googlePayload['contents'], $googlePayload['generationConfig']);

            if (!isset($googlePayload['instances']) || !is_array($googlePayload['instances']) || empty($googlePayload['instances'])) {
                $googlePayload['instances'] = [[
                    'prompt' => $payload['prompt'] ?? '',
                ]];
            }

            if (!isset($googlePayload['instances'][0]) || !is_array($googlePayload['instances'][0])) {
                $googlePayload['instances'][0] = ['prompt' => $payload['prompt'] ?? ''];
            }

            if (!isset($googlePayload['parameters']) || !is_array($googlePayload['parameters'])) {
                $googlePayload['parameters'] = [];
            }

            if (isset($payload['aspectRatio']) && !isset($googlePayload['parameters']['aspectRatio'])) {
                $googlePayload['parameters']['aspectRatio'] = $payload['aspectRatio'];
            }
            if (isset($payload['durationSeconds']) && !isset($googlePayload['parameters']['durationSeconds'])) {
                $googlePayload['parameters']['durationSeconds'] = (int) $payload['durationSeconds'];
            }
            if (!isset($googlePayload['parameters']['personGeneration'])) {
                $googlePayload['parameters']['personGeneration'] = 'allow_adult';
            }

            $instanceImage = $googlePayload['instances'][0]['image'] ?? null;
            if (is_string($instanceImage)) {
                $mimeType = 'image/jpeg';
                $base64 = null;

                if (str_starts_with($instanceImage, 'data:image')) {
                    if (preg_match('/^data:(image\/\w+);base64,/', $instanceImage, $matches)) {
                        $mimeType = $matches[1];
                    }
                    $base64 = preg_replace('/^data:image\/\w+;base64,/', '', $instanceImage);
                } elseif (filter_var($instanceImage, FILTER_VALIDATE_URL)) {
                    $parsedPath = parse_url($instanceImage, PHP_URL_PATH);
                    $ext = strtolower(pathinfo($parsedPath ?? '', PATHINFO_EXTENSION));
                    if ($ext === 'png') {
                        $mimeType = 'image/png';
                    } elseif ($ext === 'webp') {
                        $mimeType = 'image/webp';
                    }

                    /** @var \App\Services\MediaService $mediaService */
                    $mediaService = app(\App\Services\MediaService::class);
                    $base64 = $mediaService->convertUrlToBase64($instanceImage);
                }

                if ($base64) {
                    $base64ToUrlMapping[$base64] = $instanceImage;
                    $googlePayload['instances'][0]['image'] = [
                        'mimeType' => $mimeType,
                        'bytesBase64Encoded' => $base64,
                    ];
                }
            }
        }

        // --- ORDERED IMAGES OVERRIDE ---
        // If ordered_images is present, it means the caller (AppExecutionService) 
        // has already mapped [image_N] placeholders in the prompt.
        // We MUST preserve this order in the parts array.
        $orderedImagesPayload = $payload['ordered_images'] ?? null;
        if ($orderedImagesPayload && !$isLongRunning) {
            $parts = [];
            
            // Part 0: The prompt (with [image_N] placeholders already replaced)
            $promptText = $googlePayload['contents'][0]['parts'][0]['text'] ?? $payload['prompt'] ?? '';
            $parts[] = ['text' => $promptText];
            
            foreach ($orderedImagesPayload as $img) {
                $url = $img['file_uri'];
                $mimeType = $img['mime_type'];
                
                /** @var \App\Services\MediaService $mediaService */
                $mediaService = app(\App\Services\MediaService::class);
                $base64 = $mediaService->convertUrlToBase64($url);
                
                if ($base64) {
                    $base64ToUrlMapping[$base64] = $url;
                    
                    if ($isLongRunning) {
                        $parts[] = [
                            'mimeType' => $mimeType,
                            'bytesBase64Encoded' => $base64
                        ];
                    } else {
                        $parts[] = [
                            'inline_data' => [
                                'mime_type' => $mimeType,
                                'data' => $base64
                            ]
                        ];
                    }
                } else {
                    Log::warning("[GoogleProvider] Failed to convert URL to Base64: {$url}");
                }
            }
            $googlePayload['contents'][0]['parts'] = $parts;
            Log::info("[GoogleProvider] Payload constructed using ORDERED IMAGES. Parts count: " . count($parts));
        } 
        else {
            // Standard Part Filtering & Auto-Append (Fallback for direct API calls)
            if (isset($googlePayload['contents'][0]['parts']) && is_array($googlePayload['contents'][0]['parts'])) {
                $existingParts = array_values(array_filter($googlePayload['contents'][0]['parts']));
                
                foreach ($collectedImages as $img) {
                    $isMapped = false;
                    foreach ($existingParts as $existing) {
                        $existingData = $existing['inline_data']['data'] ?? $existing['bytesBase64Encoded'] ?? null;
                        $newData = $img['inline_data']['data'] ?? $img['bytesBase64Encoded'] ?? null;
                        if ($existingData && $newData && $existingData === $newData) {
                            $isMapped = true;
                            break;
                        }
                    }
                    if (!$isMapped) {
                        $existingParts[] = $img;
                    }
                }
                $googlePayload['contents'][0]['parts'] = $existingParts;
            }
        }

        // API Endpoint Logic
        $endpointAction = $isLongRunning ? 'predictLongRunning' : 'generateContent';
        $baseUrl = $this->provider->base_url ?? 'https://generativelanguage.googleapis.com/v1beta/models';
        $baseUrl = rtrim($baseUrl, '/');
        $url = "{$baseUrl}/{$providerModelId}";
        
        // DEBUG: Log Request Details
        $sanitizedPayload = $googlePayload;
        array_walk_recursive($sanitizedPayload, function (&$value) {
            if (is_string($value) && strlen($value) > 200) {
                $value = '[BASE64_DATA_' . strlen($value) . '_BYTES]';
            }
        });

        $response = Http::timeout(240)
            ->withHeaders([
                'x-goog-api-key' => $key,
                'Content-Type' => 'application/json',
            ])->post($url, $googlePayload);

        if (!$response->successful()) {
            Log::error('Google API execution failed: ' . $response->body());
            
            // Sanitize request body before passing to exception (replace Base64 with S3 URLs)
            $sanitizedRequestBodyForException = json_decode(json_encode($googlePayload), true);
            foreach ($base64ToUrlMapping as $base64 => $s3Url) {
                array_walk_recursive($sanitizedRequestBodyForException, function (&$value) use ($base64, $s3Url) {
                    if (is_string($value) && $value === $base64) {
                        $value = $s3Url;
                    }
                });
            }
            
            throw new \App\Exceptions\ProviderRequestException(
                'Google API error: ' . $response->status(),
                $sanitizedRequestBodyForException, // Use sanitized version
                $response->body()
            );
        }

        $result = $response->json();
        $duration = microtime(true) - $startTime;

        // Handle Long Running Operation (Video)
        if ($isLongRunning) {
            $operationName = $result['name'] ?? null;
            if (!$operationName) {
                throw new \Exception("Google Veo API did not return an operation name.");
            }

            // Create sanitized request body for DB
            $sanitizedRequestBody = json_decode(json_encode($googlePayload), true);
            foreach ($base64ToUrlMapping as $base64 => $s3Url) {
                array_walk_recursive($sanitizedRequestBody, function (&$value) use ($base64, $s3Url) {
                    if (is_string($value) && $value === $base64) {
                        $value = $s3Url;
                    }
                });
            }

            return [
                'output' => [
                    'result' => null, // Result is not available yet
                    'operation_name' => $operationName, // Pass operation name to caller
                    'status' => 'processing', // Signal that it's processing
                    'raw' => $result,
                    'request_body' => $sanitizedRequestBody,
                ],
                'metrics' => [
                    'duration' => $duration,
                    'count' => 1,
                    'tokens' => 0, // Tokens not available yet
                ],
            ];
        }

        // Handle Standard Generation (Text/Image)
        // Extract result using response_path
        $extractedData = $responsePath ? data_get($result, $responsePath) : null;
        
        // If extracted data is Base64 image, upload to S3 and return URL
        $finalResult = $extractedData;
        $thumbnailUrl = null;
        
        if (is_string($extractedData) && strlen($extractedData) > 1000) {
            // This is likely Base64 image data, upload to S3
            
            /** @var \App\Services\MediaService $mediaService */
            $mediaService = app(\App\Services\MediaService::class);
            
            // Determine mime type from response
            $mimeType = data_get($result, 'candidates.0.content.parts.0.inlineData.mimeType', 'image/jpeg');
            $extension = explode('/', $mimeType)[1] ?? 'jpg';
            
            // Upload Base64 to S3
            $s3Url = $mediaService->uploadBase64ToS3($extractedData, 'uploads/generations', $extension);
            
            if ($s3Url) {
                $finalResult = $s3Url; // Return S3 URL instead of Base64
                
                // Generate thumbnail from same Base64 data
                $thumbnailUrl = $mediaService->generateThumbnail($extractedData, 300, 60);
                
                Log::info('[GoogleProvider] Successfully uploaded generated image to S3', [
                    'url' => $s3Url,
                    'has_thumbnail' => !empty($thumbnailUrl)
                ]);
            } else {
                Log::error('[GoogleProvider] Failed to upload generated image to S3');
            }
        }
        
        Log::info('Google Gemini execution completed', [
            'duration' => $duration,
            'has_result' => !empty($finalResult),
            'result_type' => is_string($finalResult) && filter_var($finalResult, FILTER_VALIDATE_URL) ? 'url' : 'other',
        ]);

        // Create a sanitized version of request body for DB storage (replace Base64 with S3 URLs)
        // Deep copy to avoid modifying original
        $sanitizedRequestBody = json_decode(json_encode($googlePayload), true);
        
        $replacementCount = 0;
        foreach ($base64ToUrlMapping as $base64 => $s3Url) {
            $base64Length = strlen($base64);
            // Log::info("[GoogleProvider] Attempting to replace Base64 (length: {$base64Length}) with S3 URL: {$s3Url}");
            
            array_walk_recursive($sanitizedRequestBody, function (&$value) use ($base64, $s3Url, &$replacementCount) {
                if (is_string($value) && $value === $base64) {
                    $value = $s3Url; // Replace Base64 with original S3 URL
                    $replacementCount++;
                }
            });
        }
        
        return [
            'output' => [
                'result' => $finalResult, // S3 URL for images, text for text responses
                'thumbnail_url' => $thumbnailUrl, // Thumbnail for images
                'raw' => $result,
                'request_body' => $sanitizedRequestBody, // S3 URLs instead of Base64
            ],
            'metrics' => [
                'duration' => $duration,
                'count' => 1,
                'tokens' => $result['usageMetadata']['totalTokenCount'] ?? 0,
            ],
        ];
    }

    public function fetchSchema(string $providerModelId): ?array
    {
        // Google Generative AI (Gemini) does not have a public standard OpenAPI schema endpoint for models like Replicate/Fal.
        // It uses a fixed request structure.
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

    /**
     * Check operation status (Public method for Polling Jobs)
     */
    public function checkOperationStatus(string $operationName): array
    {
        // Fetch Default Key from DB for "google" provider
        $provider = \App\Models\Provider::where('slug', 'google')->first();
        $key = null;
        if ($provider) {
             $key = $this->getProviderKey($provider, 'services.google.key');
        }

        if (!$key) {
            $key = config('services.google.key'); 
        }

        if (!$key) {
             throw new \Exception("Google API Key missing for status check.");
        }
        
        // Trim key to be safe
        $key = trim($key);

        $url = "https://generativelanguage.googleapis.com/v1beta/{$operationName}";

        $response = Http::withHeaders([
            'x-goog-api-key' => $key,
        ])->get($url);

        if (!$response->successful()) {
            throw new \Exception("Failed to poll operation status: " . $response->body());
        }

        $data = $response->json();
        
        // Normalize Response for AsyncAiProviderInterface
        $done = $data['done'] ?? false;
        $resultUrl = null;
        $error = $data['error']['message'] ?? null;

        if ($done && !$error) {
            // Extract Video URI specific to Google Veo
            $resultUrl = $data['response']['generateVideoResponse']['generatedSamples'][0]['video']['uri'] ?? null;
            
            // Check for safety filters if no URL
            if (!$resultUrl) {
                 if (isset($data['response']['generateVideoResponse']['raiMediaFilteredReasons'])) {
                    $reasons = $data['response']['generateVideoResponse']['raiMediaFilteredReasons'];
                    $error = 'İçerik politikalarına takıldı: ' . implode(', ', $reasons);
                 } else {
                     $error = 'Video oluşturuldu fakat URL alınamadı.';
                 }
            }
        }

        return [
            'done' => $done,
            'result_url' => $resultUrl,
            'error' => $error,
            'raw' => $data // Keep raw data for debugging/logging
        ];
    }

    /**
     * Download video from Google (for Polling Job)
     */
    public function downloadVideo(string $videoUri): string
    {
        $provider = \App\Models\Provider::where('slug', 'google')->first();
        $key = null;
        if ($provider) {
             $key = $this->getProviderKey($provider, 'services.google.key');
        }
        if (!$key) $key = config('services.google.key');
        
        $key = trim($key);

        try {
            $response = Http::withHeaders([
                'x-goog-api-key' => $key,
            ])->get($videoUri);

            if (!$response->successful()) {
                throw new \Exception("Failed to download video: " . $response->body());
            }

            return $response->body();
        } catch (\Exception $e) {
            Log::error('[GoogleProvider] Failed to download video', [
                'uri' => $videoUri,
                'error' => $e->getMessage()
            ]);
            throw $e;
        }
    }

    /**
     * Download the result (video/image) from the provider.
     * Interface implementation.
     */
    public function downloadResult(string $url): string
    {
        return $this->downloadVideo($url);
    }
}
