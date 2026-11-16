<?php

declare(strict_types=1);

namespace Predoza\Doctor;

class CheckResult
{
    public function __construct(
        public readonly string $name,
        public readonly CheckStatus $status,
        public readonly string $detail,
    ) {
    }
}
