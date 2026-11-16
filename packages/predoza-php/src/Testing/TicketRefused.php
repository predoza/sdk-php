<?php

declare(strict_types=1);

namespace Predoza\Testing;

use RuntimeException;

class TicketRefused extends RuntimeException
{
    public function __construct(public readonly string $reason)
    {
        parent::__construct("Ticket refused: {$reason}");
    }
}
