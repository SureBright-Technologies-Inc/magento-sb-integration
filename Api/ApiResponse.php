<?php

namespace Surebright\Integration\Api;

class ApiResponse {
    public bool $isError;
    public string $message;
    public mixed $apiPayload;

    public function __construct(bool $isError, string $message, mixed $apiPayload = null) {
        $this->isError = $isError;
        $this->message = $message;
        $this->apiPayload = $apiPayload;
    }
}