<?php

declare(strict_types=1);

namespace Predoza\Testing;

use Predoza\HostSignIn\Base64Url;

/**
 * What the game frame does before asking your handoff route: a PKCE pair, and the request embed.js sends.
 */
class FakeFrame
{
    public readonly string $verifier;

    public readonly string $challenge;

    public function __construct(public readonly string $feature = 'sf_testgame')
    {
        $this->verifier = Base64Url::encode(random_bytes(32));
        $this->challenge = Base64Url::encode(hash('sha256', $this->verifier, true));
    }

    /**
     * @return array<string, string>
     */
    public function headers(string $pageOrigin): array
    {
        return ['Content-Type' => 'application/json', 'Origin' => $pageOrigin, 'Sec-Fetch-Site' => 'same-origin', 'X-Predoza-Handoff' => '1'];
    }

    public function body(): string
    {
        return json_encode(['challenge' => $this->challenge, 'feature' => $this->feature], JSON_THROW_ON_ERROR);
    }
}
