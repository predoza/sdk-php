<?php

declare(strict_types=1);

namespace Predoza\Tests;

use Closure;
use Predoza\Doctor\HttpResponse;
use Predoza\Doctor\HttpTransport;

final class FakeTransport implements HttpTransport
{
    /** @var list<array{method: string, url: string, headers: array<string, string>, body: string}> */
    public array $sent = [];

    /**
     * @param Closure(string, string, array<string, string>, string): HttpResponse $answer
     */
    public function __construct(private Closure $answer)
    {
    }

    public function send(string $method, string $url, array $headers = [], string $body = ''): HttpResponse
    {
        $this->sent[] = ['method' => $method, 'url' => $url, 'headers' => $headers, 'body' => $body];

        return ($this->answer)($method, $url, $headers, $body);
    }
}
