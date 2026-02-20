<?php

namespace App\Services\AiProviders;

use App\Models\Provider;

interface AiProviderInterface
{
    /**
     * Execute a generation request.
     *
     * @param Provider $provider The provider entity (for dynamic keys)
     * @param string $providerModelId The model ID on the provider's system
     * @param array $payload The generation parameters/payload
     * @return array The standardized result
     */
    public function generate(Provider $provider, string $providerModelId, array $payload): array;

    /**
     * Fetch the input schema for a specific model from the provider.
     *
     * @param string $providerModelId
     * @return array|null
     */
    public function fetchSchema(string $providerModelId): ?array;
}
