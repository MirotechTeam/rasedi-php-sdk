<?php

namespace Rasedi\Sdk\Interfaces;

final class HttpResponse
{
    public function __construct(
        public array|string $body,
        public array $headers,
        public int $statusCode
    ) {
    }
}
