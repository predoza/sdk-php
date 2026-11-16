<?php

declare(strict_types=1);

namespace Predoza\HostSignIn;

use Psr\Clock\ClockInterface;

/**
 * Builds tickets: the member's claims signed with your site key, then sealed to Predoza's public key, so only
 * Predoza can read them (page scripts see ciphertext) and only your site can have made them.
 */
class TicketSealer
{
    public function __construct(
        private SiteKey $siteKey,
        private ClockInterface $clock,
        private string $sdk = Version::SDK,
    ) {
    }

    public function seal(Member $member, string $challenge, string $feature): string
    {
        return $this->sealClaims([
            ...$this->baseClaims(),
            'sub' => $member->id,
            'nickname' => $member->nickname,
            'locale' => $member->locale,
            'email' => $member->confirmedEmail,
            'challenge' => $challenge,
            'feature' => $feature,
            'iat' => $this->clock->now()->getTimestamp(),
            'sdk' => $this->sdk,
        ]);
    }

    /**
     * A ticket for Predoza's setup check: it names no member and can't sign anyone in.
     */
    public function sealCheck(): string
    {
        return $this->sealClaims([
            ...$this->baseClaims(),
            'check' => true,
            'iat' => $this->clock->now()->getTimestamp(),
            'sdk' => $this->sdk,
        ]);
    }

    /**
     * @param array<string, mixed> $claims
     */
    private function sealClaims(array $claims): string
    {
        $json = json_encode($claims, JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
        $sealed = sodium_crypto_box_seal($this->siteKey->sign($json) . $json, $this->siteKey->predozaPublicKey);

        return 'v2.' . $this->siteKey->predozaKid . '.' . Base64Url::encode($sealed);
    }

    /**
     * @return array<string, mixed>
     */
    private function baseClaims(): array
    {
        return ['v' => 2, 'jti' => Base64Url::encode(random_bytes(16)), 'sk' => $this->siteKey->id];
    }
}
