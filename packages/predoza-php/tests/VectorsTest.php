<?php

declare(strict_types=1);

namespace Predoza\Tests;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Predoza\HostSignIn\Base64Url;
use Predoza\HostSignIn\HandoffInput;
use Predoza\HostSignIn\HostSignIn;
use Predoza\HostSignIn\SiteKey;
use Predoza\Testing\TicketOpener;
use Predoza\Testing\TicketRefused;

/**
 * Tickets and requests Predoza's own tests use too, so the SDK and Predoza agree.
 */
final class VectorsTest extends TestCase
{
    /** @var array<string, mixed>|null */
    private static ?array $vectors = null;

    public function testTheSiteKeyFromTheEnvValueHasTheVectorsId(): void
    {
        $siteKey = new SiteKey(self::vectors()['site_key']['env_value']);

        self::assertSame(self::vectors()['site_key']['key_id'], $siteKey->id);
        self::assertSame(self::vectors()['predoza_keys'][0]['kid'], $siteKey->predozaKid);
    }

    /**
     * @param array<string, mixed> $case
     */
    #[DataProvider('openCases')]
    public function testTheOpenerAgreesWithPredoza(array $case): void
    {
        try {
            $claims = self::opener()->open($case['ticket'], self::vectors()['feature'], $case['verifier'], self::vectors()['now'])->claims;
            $outcome = ['ok' => true, 'claims' => $claims];
        } catch (TicketRefused $refused) {
            $outcome = ['ok' => false, 'reason' => $refused->reason];
        }

        self::assertSame($case['expect'], $outcome);
    }

    /**
     * @param array<string, mixed> $case
     */
    #[DataProvider('checkCases')]
    public function testTheOpenerAnswersChecksLikePredoza(array $case): void
    {
        try {
            $opened = self::opener()->check($case['ticket'], self::vectors()['now']);
            $outcome = ['ok' => true, 'site_key' => $opened->siteKeyId];
        } catch (TicketRefused $refused) {
            $outcome = ['ok' => false, 'reason' => $refused->reason];
        }

        self::assertSame($case['expect'], $outcome);
    }

    /**
     * @param array<string, mixed> $case
     */
    #[DataProvider('handoffCases')]
    public function testTheHandoffEndpointAnswersEveryRequestAsTheSpecSays(array $case): void
    {
        $vectors = self::vectors();
        $hostSignIn = HostSignIn::create($vectors['site_key']['env_value'], new StaticMembers(), new FrozenClock($vectors['now']));

        $result = $hostSignIn->handoff()->handle(
            new HandoffInput($case['method'], $case['headers'], $case['body'], $vectors['page_origin']),
            $case['member'] ? new SiteUser('48213') : null,
        );

        self::assertSame($case['expect_status'], $result->status);
        self::assertSame('no-store', $result->headers['Cache-Control']);
        self::assertArrayNotHasKey('Access-Control-Allow-Origin', $result->headers);

        if ($result->status === 200) {
            $ticket = json_decode($result->body, true)['ticket'];
            $claims = self::opener()->open($ticket, $vectors['feature'], $vectors['pkce']['verifier'], $vectors['now'])->claims;
            self::assertSame('48213', $claims['sub']);
        }
    }

    /**
     * @return iterable<string, array{array<string, mixed>}>
     */
    public static function openCases(): iterable
    {
        foreach (self::vectors()['open'] as $case) {
            yield $case['name'] => [$case];
        }
    }

    /**
     * @return iterable<string, array{array<string, mixed>}>
     */
    public static function checkCases(): iterable
    {
        foreach (self::vectors()['check'] as $case) {
            yield $case['name'] => [$case];
        }
    }

    /**
     * @return iterable<string, array{array<string, mixed>}>
     */
    public static function handoffCases(): iterable
    {
        foreach (self::vectors()['handoff'] as $case) {
            yield $case['name'] => [$case];
        }
    }

    private static function opener(): TicketOpener
    {
        $vectors = self::vectors();
        $predoza = $vectors['predoza_keys'][0];

        return new TicketOpener(
            [$predoza['kid'] => (string) Base64Url::decode($predoza['secret_key'])],
            [(string) Base64Url::decode($vectors['site_key']['public_key'])],
        );
    }

    /**
     * @return array<string, mixed>
     */
    private static function vectors(): array
    {
        return self::$vectors ??= json_decode((string) file_get_contents(__DIR__ . '/Fixtures/vectors.json'), true, 512, JSON_THROW_ON_ERROR);
    }
}
