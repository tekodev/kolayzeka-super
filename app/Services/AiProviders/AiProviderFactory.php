<?php

namespace App\Services\AiProviders;

use InvalidArgumentException;

class AiProviderFactory
{
    public function make(string $type): AiProviderInterface
    {
        return match ($type) {
            'fal_ai' => new FalAiProvider(),
            'replicate' => new ReplicateProvider(),
            'google' => new GoogleProvider(),
            default => throw new InvalidArgumentException("Unknown provider type: {$type}"),
        };
    }
}
