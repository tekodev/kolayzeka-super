<?php

namespace App\Services;

use App\Models\Provider;
use App\Services\AiProviders\AiProviderFactory;
use Illuminate\Support\Facades\Log;

class ProviderApiService
{
    protected AiProviderFactory $providerFactory;

    public function __construct(AiProviderFactory $providerFactory)
    {
        $this->providerFactory = $providerFactory;
    }

    /**
     * Fetch the input schema for a specific model from the provider.
     * 
     * @param Provider $provider
     * @param string $providerModelId
     * @return array|null
     */
    public function fetchSchema(Provider $provider, string $providerModelId): ?array
    {
        try {
            $providerInstance = $this->providerFactory->make($provider->type);
            return $providerInstance->fetchSchema($providerModelId);
        } catch (\Exception $e) {
            Log::warning("Failed to fetch schema for {$provider->slug}: " . $e->getMessage());
            return null;
        }
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

        $providerInstance = $this->providerFactory->make($provider->type);
        return $providerInstance->generate($provider, $providerModelId, $payload);
    }
}
