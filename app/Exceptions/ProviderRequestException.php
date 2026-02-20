<?php

namespace App\Exceptions;

use Exception;

class ProviderRequestException extends Exception
{
    protected array $requestBody;
    protected ?string $responseBody;

    public function __construct(string $message, array $requestBody, ?string $responseBody = null, int $code = 0, ?\Throwable $previous = null)
    {
        parent::__construct($message, $code, $previous);
        $this->requestBody = $requestBody;
        $this->responseBody = $responseBody;
    }

    public function getRequestBody(): array
    {
        return $this->requestBody;
    }

    public function getResponseBody(): ?string
    {
        return $this->responseBody;
    }
}
