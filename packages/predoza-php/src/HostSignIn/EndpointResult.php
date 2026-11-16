<?php

declare(strict_types=1);

namespace Predoza\HostSignIn;

/**
 * An HTTP answer for your framework to send as it is: status, headers and body.
 */
class EndpointResult
{
    /**
     * @param array<string, string> $headers
     */
    public function __construct(
        public readonly int $status,
        public readonly array $headers,
        public readonly string $body = '',
    ) {
    }

    /**
     * @param array<string, string> $headers
     */
    public static function empty(int $status, array $headers = []): self
    {
        return new self($status, ['Cache-Control' => 'no-store', ...$headers]);
    }

    /**
     * @param array<string, mixed> $data
     */
    public static function json(int $status, array $data): self
    {
        return new self($status, ['Cache-Control' => 'no-store', 'Content-Type' => 'application/json'], json_encode($data, JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES));
    }
}
