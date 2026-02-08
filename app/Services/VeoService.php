<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Exception;

class VeoService
{
    private string $apiKey;
    private string $baseUrl = 'https://generativelanguage.googleapis.com/v1beta';
    
    public function __construct()
    {
        $this->apiKey = config('services.google.key');
    }

    /**
     * Generate video from image using Veo 3.1
     * 
     * @param string $imageBase64 Base64 encoded image
     * @param string $prompt Video generation prompt
     * @param array $config Video configuration (aspect_ratio, resolution, duration, etc.)
     * @return array Operation data
     */
    public function generateVideoFromImage(
        string $imageBase64,
        string $prompt,
        array $config = []
    ): array {
        Log::info('[VeoService] Starting video generation', [
            'prompt_length' => strlen($prompt),
            'base64_length' => strlen($imageBase64),
            'config' => $config
        ]);

        $payload = [
            'instances' => [[
                'prompt' => $prompt,
                'image' => [
                    'mimeType' => 'image/png',
                    'bytesBase64Encoded' => $imageBase64
                ]
            ]],
            'parameters' => [
                'aspectRatio' => $config['aspectRatio'] ?? '9:16',
                'personGeneration' => 'allow_adult',
                'durationSeconds' => (float) ($config['durationSeconds'] ?? 8),
            ]
        ];

        Log::info('[VeoService] Sending request to Veo API', ['payload_structure' => array_keys($payload)]);

        try {
            $response = Http::withHeaders([
                'x-goog-api-key' => $this->apiKey,
                'Content-Type' => 'application/json',
            ])->post("{$this->baseUrl}/models/veo-3.1-generate-preview:predictLongRunning", $payload);

            if (!$response->successful()) {
                Log::error('[VeoService] API request failed', [
                    'status' => $response->status(),
                    'body' => $response->body()
                ]);
                throw new Exception("Veo API request failed: " . $response->body());
            }

            $data = $response->json();
            Log::info('[VeoService] Operation started', ['operation_name' => $data['name'] ?? 'unknown']);

            return $data;
        } catch (Exception $e) {
            Log::error('[VeoService] Exception during video generation', [
                'message' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            throw $e;
        }
    }

    /**
     * Poll operation status
     * 
     * @param string $operationName Operation name from initial request
     * @return array Operation status data
     */
    public function pollOperationStatus(string $operationName): array
    {
        try {
            $response = Http::withHeaders([
                'x-goog-api-key' => $this->apiKey,
            ])->get("{$this->baseUrl}/{$operationName}");

            if (!$response->successful()) {
                throw new Exception("Failed to poll operation status: " . $response->body());
            }

            return $response->json();
        } catch (Exception $e) {
            Log::error('[VeoService] Failed to poll operation', [
                'operation' => $operationName,
                'error' => $e->getMessage()
            ]);
            throw $e;
        }
    }

    /**
     * Download video from Google
     * 
     * @param string $videoUri Video URI from completed operation
     * @return string Video content (binary)
     */
    public function downloadVideo(string $videoUri): string
    {
        try {
            $response = Http::withHeaders([
                'x-goog-api-key' => $this->apiKey,
            ])->get($videoUri);

            if (!$response->successful()) {
                throw new Exception("Failed to download video: " . $response->body());
            }

            return $response->body();
        } catch (Exception $e) {
            Log::error('[VeoService] Failed to download video', [
                'uri' => $videoUri,
                'error' => $e->getMessage()
            ]);
            throw $e;
        }
    }

    /**
     * Wait for operation to complete (blocking)
     * 
     * @param string $operationName Operation name
     * @param int $maxWaitSeconds Maximum wait time (default 6 minutes)
     * @param int $pollIntervalSeconds Poll interval (default 10 seconds)
     * @return array Completed operation data
     */
    public function waitForCompletion(
        string $operationName,
        int $maxWaitSeconds = 360,
        int $pollIntervalSeconds = 5  // Changed to 5 seconds as per user requirement
    ): array {
        $startTime = time();
        
        while (true) {
            $operation = $this->pollOperationStatus($operationName);
            
            if ($operation['done'] ?? false) {
                Log::info('[VeoService] Operation completed', [
                    'operation' => $operationName,
                    'duration' => time() - $startTime
                ]);
                
                // Extract video URL from response
                $videoUrl = $operation['response']['generateVideoResponse']['generatedSamples'][0]['video']['uri'] ?? null;
                
                if ($videoUrl) {
                    Log::info('[VeoService] Video URL extracted', ['url' => $videoUrl]);
                    $operation['video_url'] = $videoUrl;
                } else {
                    Log::warning('[VeoService] No video URL found in response', ['response' => $operation]);
                }
                
                return $operation;
            }

            if (time() - $startTime > $maxWaitSeconds) {
                throw new Exception("Operation timed out after {$maxWaitSeconds} seconds");
            }

            Log::info('[VeoService] Waiting for operation...', [
                'operation' => $operationName,
                'elapsed' => time() - $startTime
            ]);

            sleep($pollIntervalSeconds);
        }
    }

    /**
     * Check operation status (Public wrapper for pollOperationStatus)
     */
    public function checkOperationStatus(string $operationName): array
    {
        return $this->pollOperationStatus($operationName);
    }
}
