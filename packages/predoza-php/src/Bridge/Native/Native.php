<?php

declare(strict_types=1);

namespace Predoza\Bridge\Native;

use Predoza\HostSignIn\EndpointResult;
use Predoza\HostSignIn\HandoffInput;
use Predoza\HostSignIn\HostSignIn;

/**
 * For a plain PHP file as the handoff route:
 *
 *     Native::handoff($hostSignIn, current_user(), 'https://www.example.com');
 */
class Native
{
    /**
     * @param string $pageOrigin your pages' origin, e.g. "https://www.example.com"
     */
    public static function handoff(HostSignIn $hostSignIn, ?object $user, string $pageOrigin): void
    {
        self::send($hostSignIn->handoff()->handle(self::input($pageOrigin), $user));
    }

    public static function input(string $pageOrigin): HandoffInput
    {
        return new HandoffInput(
            is_string($_SERVER['REQUEST_METHOD'] ?? null) ? $_SERVER['REQUEST_METHOD'] : 'GET',
            self::headers(),
            (string) file_get_contents('php://input'),
            $pageOrigin,
        );
    }

    public static function send(EndpointResult $result): void
    {
        http_response_code($result->status);

        foreach ($result->headers as $name => $value) {
            header("{$name}: {$value}");
        }

        echo $result->body;
    }

    /**
     * @return array<string, string>
     */
    private static function headers(): array
    {
        $headers = [];

        foreach ($_SERVER as $key => $value) {
            if (is_string($key) && is_string($value) && str_starts_with($key, 'HTTP_')) {
                $headers[str_replace('_', '-', substr($key, 5))] = $value;
            }
        }
        if (is_string($_SERVER['CONTENT_TYPE'] ?? null)) {
            $headers['Content-Type'] = $_SERVER['CONTENT_TYPE'];
        }

        return $headers;
    }
}
