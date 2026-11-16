<?php

declare(strict_types=1);

namespace Predoza\Doctor;

class HttpResponse
{
    /**
     * @param array<string, string> $headers lower-cased names
     */
    public function __construct(
        public readonly int $status,
        public readonly array $headers,
        public readonly string $body,
    ) {
    }

    public function header(string $name): ?string
    {
        return $this->headers[strtolower($name)] ?? null;
    }
}
