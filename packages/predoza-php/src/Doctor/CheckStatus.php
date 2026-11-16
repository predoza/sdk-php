<?php

declare(strict_types=1);

namespace Predoza\Doctor;

enum CheckStatus: string
{
    case Ok = 'ok';
    case Warning = 'warning';
    case Failed = 'failed';

    public function symbol(): string
    {
        return match ($this) {
            self::Ok => '✓',
            self::Warning => '!',
            self::Failed => '✗',
        };
    }
}
