<?php

declare(strict_types=1);

namespace Predoza\HostSignIn;

use Psr\Clock\ClockInterface;
use SensitiveParameter;

/**
 * Everything your site needs for Predoza host sign-in, built from PREDOZA_SITE_KEY and your MemberDirectory.
 *
 *     $hostSignIn = HostSignIn::create((string) getenv('PREDOZA_SITE_KEY'), new MyMembers());
 *     $result = $hostSignIn->handoff()->handle($input, $currentUser);
 */
class HostSignIn
{
    public const CHECK_URL = 'https://play.predoza.com/v1/host-sign-in/check';

    private TicketSealer $sealer;

    public function __construct(
        private SiteKey $siteKey,
        private MemberDirectory $members,
        private ClockInterface $clock,
        private string $checkUrl = self::CHECK_URL,
        string $sdk = Version::SDK,
    ) {
        $this->sealer = new TicketSealer($siteKey, $clock, $sdk);
    }

    public static function create(
        #[SensitiveParameter]
        string $siteKey,
        MemberDirectory $members,
        ?ClockInterface $clock = null,
        string $checkUrl = self::CHECK_URL,
    ): self {
        return new self(new SiteKey($siteKey), $members, $clock ?? new SystemClock(), $checkUrl);
    }

    public function handoff(): HandoffEndpoint
    {
        return new HandoffEndpoint($this->sealer, $this->members);
    }

    public function sealer(): TicketSealer
    {
        return $this->sealer;
    }

    public function siteKey(): SiteKey
    {
        return $this->siteKey;
    }

    public function clock(): ClockInterface
    {
        return $this->clock;
    }

    public function checkUrl(): string
    {
        return $this->checkUrl;
    }
}
