<?php

declare(strict_types=1);

namespace Predoza\Tests;

final class SiteUser
{
    public function __construct(
        public readonly string $id,
        public readonly ?string $nickname = 'catenaccio',
        public readonly bool $emailConfirmed = true,
        public readonly bool $banned = false,
    ) {
    }
}
