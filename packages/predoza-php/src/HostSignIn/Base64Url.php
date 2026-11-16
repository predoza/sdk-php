<?php

declare(strict_types=1);

namespace Predoza\HostSignIn;

/**
 * RFC 4648 §5 without padding, as the protocol uses everywhere.
 */
class Base64Url
{
    public static function encode(string $bytes): string
    {
        return rtrim(strtr(base64_encode($bytes), '+/', '-_'), '=');
    }

    public static function decode(string $text): ?string
    {
        if (!preg_match('/^[A-Za-z0-9_-]*$/', $text)) {
            return null;
        }
        $bytes = base64_decode(strtr($text, '-_', '+/'), true);

        return $bytes === false ? null : $bytes;
    }
}
