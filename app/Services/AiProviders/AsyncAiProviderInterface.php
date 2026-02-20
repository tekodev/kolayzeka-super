<?php

namespace App\Services\AiProviders;

interface AsyncAiProviderInterface
{
    /**
     * Check the status of a long-running operation.
     *
     * @param string $operationId The operation ID returned by the provider
     * @return array Status response (must contain 'done' boolean)
     */
    public function checkOperationStatus(string $operationId): array;

    /**
     * Download the result (video/image) from the provider.
     *
     * @param string $url The result URL provided by the operation status
     * @return string The raw file content
     */
    public function downloadResult(string $url): string;
}
