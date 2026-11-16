<?php

declare(strict_types=1);

namespace Predoza\Testing;

class OpenedTicket
{
    /**
     * @param array<string, mixed> $claims
     */
    public function __construct(
        public readonly array $claims,
        public readonly string $siteKeyId,
        public readonly int $issuedAt,
    ) {
    }
}
