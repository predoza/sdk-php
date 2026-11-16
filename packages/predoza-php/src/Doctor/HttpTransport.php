<?php

declare(strict_types=1);

namespace Predoza\Doctor;

use RuntimeException;

interface HttpTransport
{
    /**
     * @param array<string, string> $headers
     *
     * @throws RuntimeException when the server can't be reached
     */
    public function send(string $method, string $url, array $headers = [], string $body = ''): HttpResponse;
}
