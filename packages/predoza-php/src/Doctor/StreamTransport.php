<?php

declare(strict_types=1);

namespace Predoza\Doctor;

use RuntimeException;

/**
 * Plain PHP streams, so the doctor needs no HTTP library. Redirects are not followed: a redirect is itself a finding.
 */
class StreamTransport implements HttpTransport
{
    private const TIMEOUT_SECONDS = 10;

    public function send(string $method, string $url, array $headers = [], string $body = ''): HttpResponse
    {
        $lines = [];

        foreach ($headers as $name => $value) {
            $lines[] = "{$name}: {$value}";
        }
        $context = stream_context_create(['http' => [
            'method' => $method,
            'header' => implode("\r\n", $lines),
            'content' => $body,
            'ignore_errors' => true,
            'follow_location' => 0,
            'timeout' => self::TIMEOUT_SECONDS,
        ]]);
        $stream = @fopen($url, 'r', false, $context);

        if ($stream === false) {
            throw new RuntimeException("{$url} could not be reached");
        }
        $headerLines = stream_get_meta_data($stream)['wrapper_data'] ?? [];
        $responseBody = stream_get_contents($stream);
        fclose($stream);

        if (!is_array($headerLines) || $responseBody === false) {
            throw new RuntimeException("{$url} answered nothing readable");
        }

        return self::parse($headerLines, $responseBody);
    }

    /**
     * @param array<mixed> $headerLines
     */
    private static function parse(array $headerLines, string $body): HttpResponse
    {
        $status = 0;
        $headers = [];

        foreach ($headerLines as $line) {
            if (!is_string($line)) {
                continue;
            }
            if (preg_match('#^HTTP/\S+\s+(\d{3})#', $line, $match)) {
                $status = (int) $match[1];
                $headers = [];

                continue;
            }
            [$name, $value] = array_pad(explode(':', $line, 2), 2, '');
            $headers[strtolower(trim($name))] = trim($value);
        }

        return new HttpResponse($status, $headers, $body);
    }
}
