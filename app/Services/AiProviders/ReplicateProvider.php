<?php

namespace App\Services\AiProviders;

use App\Models\Provider;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class ReplicateProvider implements AiProviderInterface
{
    public function generate(Provider $provider, string $providerModelId, array $payload): array
    {
        $token = $this->getProviderKey($provider, 'services.replicate.api_token');
        if (!$token) {
            throw new \Exception('Replicate API Token is missing (Check provider api_key_env or .env)');
        }

        $startTime = microtime(true);

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

        Log::info("[ReplicateProvider] Using Dynamic Request Template");
        $jsonBody = json_encode($requestTemplate);
        
        // Replace placeholders
        foreach ($payload as $key => $value) {
            if (is_string($value) || is_numeric($value)) {
                $jsonBody = str_replace('{{'.$key.'}}', $value, $jsonBody);
            }
        }
        $finalPayload = json_decode($jsonBody, true);

        // Replicate uses predictions API
        // NOTE: The template MUST include "version" and "input" keys as per Replicate API
        $response = Http::withToken($token)->post('https://api.replicate.com/v1/predictions', $finalPayload);

        if (!$response->successful()) {
            Log::error('Replicate execution failed: ' . $response->body());
            throw new \App\Exceptions\ProviderRequestException(
                'Replicate API error: ' . $response->status(),
                $finalPayload,
                $response->body()
            );
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

        // Dynamic Path Extraction
        $resultData = data_get($prediction, $responsePath);
        
        if (!$resultData) {
             Log::warning('[ReplicateProvider] Returned no content for path: ' . $responsePath, ['response' => $prediction]);
             throw new \Exception('Replicate returned no content (Path: ' . $responsePath . ')');
        }

        return [
            'output' => [
                'result' => $resultData,
                'raw' => $prediction,
                'request_body' => $finalPayload, // Add for logging
            ],
            'metrics' => [
                'duration' => $duration,
                'count' => is_array($resultData) ? count($resultData) : 1,
                'tokens' => $prediction['metrics']['predict_time'] ?? 0,
            ],
        ];
    }

    public function fetchSchema(string $providerModelId): ?array
    {
        $token = config('services.replicate.api_token');
        if (!$token) {
             return null;
        }

        // Replicate API: GET https://api.replicate.com/v1/models/{owner}/{name}
        // Input providerModelId usually is "owner/name:version" or just "owner/name"
        $parts = explode(':', $providerModelId);
        $slug = $parts[0]; // owner/name

        $response = Http::withToken($token)->get("https://api.replicate.com/v1/models/{$slug}");
        
        if ($response->successful()) {
             $data = $response->json();
             return $data['latest_version']['openapi_schema'] ?? null;
        }

        return null;
    }

    protected function getReplicateVersion(string $modelId, string $token): string
    {
        // If modelId includes version (owner/name:version), extract it
        if (str_contains($modelId, ':')) {
            return explode(':', $modelId)[1];
        }

        // Otherwise fetch latest version
        $slug = explode(':', $modelId)[0];
        
        $response = Http::withToken($token)->get("https://api.replicate.com/v1/models/{$slug}");
        $data = $response->json();
        
        return $data['latest_version']['id'] ?? throw new \Exception('Could not find Replicate version');
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
