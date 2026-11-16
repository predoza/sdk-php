<?php

declare(strict_types=1);

namespace Predoza\HostSignIn;

/**
 * The handoff request as your framework received it. pageOrigin is your page's own origin as the browser sees it
 * ("https://www.example.com"): behind a proxy, take it from the framework's trusted-proxy aware request.
 */
class HandoffInput
{
    /** @var array<string, string> lower-cased header names */
    public readonly array $headers;

    /**
     * @param array<array-key, mixed> $headers as frameworks give them: a value or a list of values per name
     */
    public function __construct(
        public readonly string $method,
        array $headers,
        public readonly string $rawBody,
        public readonly string $pageOrigin,
    ) {
        $normalized = [];

        foreach ($headers as $name => $value) {
            $first = is_array($value) ? reset($value) : $value;
            $normalized[strtolower((string) $name)] = is_string($first) ? $first : '';
        }
        $this->headers = $normalized;
    }

    public function header(string $name): ?string
    {
        return $this->headers[strtolower($name)] ?? null;
    }
}
