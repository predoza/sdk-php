<?php

declare(strict_types=1);

namespace Predoza\Tests;

use InvalidArgumentException;
use PHPUnit\Framework\TestCase;
use Predoza\HostSignIn\HostSignIn;
use Predoza\HostSignIn\Member;
use Predoza\HostSignIn\Version;
use Predoza\Testing\FakeFrame;
use Predoza\Testing\TestKeys;
use Predoza\Testing\TicketRefused;

final class TicketSealerTest extends TestCase
{
    private const NOW = 1763290000;

    public function testATicketCarriesTheMemberSignedAndSealedForPredoza(): void
    {
        $keys = TestKeys::create();
        $frame = new FakeFrame();
        $sealer = HostSignIn::create($keys->envValue, new StaticMembers(), new FrozenClock(self::NOW))->sealer();

        $ticket = $sealer->seal(new Member('01JD3QW7T0XGZ8F5N2K4M6P9RA', 'Łukasz K', 'pt_BR', 'lukasz@example.com'), $frame->challenge, $frame->feature);
        $claims = $keys->opener()->open($ticket, $frame->feature, $frame->verifier, self::NOW)->claims;

        self::assertSame(1, $claims['v']);
        self::assertSame('01JD3QW7T0XGZ8F5N2K4M6P9RA', $claims['sub']);
        self::assertSame('Łukasz K', $claims['nickname']);
        self::assertSame('pt_BR', $claims['locale']);
        self::assertSame('lukasz@example.com', $claims['email']);
        self::assertSame(self::NOW, $claims['iat']);
        self::assertSame(Version::SDK, $claims['sdk']);
        self::assertMatchesRegularExpression('/^[A-Za-z0-9_-]{22}$/', $claims['jti']);
        self::assertLessThan(4096, strlen($ticket));
    }

    public function testEveryTicketIsDifferentAndUnreadableWithoutPredozasKey(): void
    {
        $keys = TestKeys::create();
        $frame = new FakeFrame();
        $sealer = HostSignIn::create($keys->envValue, new StaticMembers())->sealer();
        $member = new Member('48213', 'catenaccio', null, 'cat@example.com');

        $first = $sealer->seal($member, $frame->challenge, $frame->feature);
        $second = $sealer->seal($member, $frame->challenge, $frame->feature);

        self::assertNotSame($first, $second);
        self::assertStringNotContainsString('cat@example.com', base64_decode(strtr(explode('.', $first)[2], '-_', '+/')));
    }

    public function testAnotherSitesKeyIsRefused(): void
    {
        $frame = new FakeFrame();
        $ticket = HostSignIn::create(TestKeys::create()->envValue, new StaticMembers())->sealer()->seal(new Member('1'), $frame->challenge, $frame->feature);

        $this->expectException(TicketRefused::class);

        TestKeys::create()->opener()->open($ticket, $frame->feature, $frame->verifier, time());
    }

    public function testACheckTicketNamesNoMember(): void
    {
        $keys = TestKeys::create();

        $claims = $keys->opener()->check(HostSignIn::create($keys->envValue, new StaticMembers())->sealer()->sealCheck(), time())->claims;

        self::assertTrue($claims['check']);
        self::assertArrayNotHasKey('sub', $claims);
    }

    public function testAnIdOutsideTheProtocolIsRefusedBeforeAnyTicketIsMade(): void
    {
        $this->expectException(InvalidArgumentException::class);

        new Member('email:cat@example.com');
    }
}
