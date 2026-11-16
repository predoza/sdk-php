<?php

declare(strict_types=1);

namespace Predoza\Tests;

use PHPUnit\Framework\TestCase;
use Predoza\Doctor\CheckStatus;
use Predoza\Doctor\Doctor;
use Predoza\Doctor\HttpResponse;
use Predoza\HostSignIn\HostSignIn;
use Predoza\Testing\TestKeys;

final class DoctorTest extends TestCase
{
    private const NOW = 1763290000;

    public function testAWorkingSetupPasses(): void
    {
        $keys = TestKeys::create();
        $transport = $this->predoza($keys, drift: 3);

        $report = (new Doctor(HostSignIn::create($keys->envValue, new StaticMembers(), new FrozenClock(self::NOW)), $transport))->run('https://www.example.com');

        self::assertTrue($report->passed(), implode("\n", $report->lines()));
        self::assertSame(['Site key', 'Page origin', 'Predoza check', 'Clock'], array_map(static fn ($result) => $result->name, $report->results));
        self::assertStringContainsString('client protipster', $report->lines()[2]);
        self::assertSame('https://play.predoza.com/v1/host-sign-in/check', $transport->sent[0]['url']);
        self::assertSame('4 checks · 4 passed · 0 failed · 0 warnings', $report->summary());
    }

    public function testAnHttpOriginAndAWrongClockFail(): void
    {
        $keys = TestKeys::create();

        $report = (new Doctor(HostSignIn::create($keys->envValue, new StaticMembers(), new FrozenClock(self::NOW)), $this->predoza($keys, drift: -45)))->run('http://www.example.com');

        self::assertFalse($report->passed());
        self::assertSame(CheckStatus::Failed, $report->results[1]->status);
        self::assertSame(CheckStatus::Failed, $report->results[3]->status);
        self::assertStringContainsString('45 s behind Predoza', $report->results[3]->detail);
    }

    public function testPredozasReasonIsShown(): void
    {
        $transport = new FakeTransport(static fn (): HttpResponse => new HttpResponse(422, [], '{"ok":false,"reason":"unknown_site_key"}'));

        $report = (new Doctor(HostSignIn::create(TestKeys::create()->envValue, new StaticMembers()), $transport))->run();

        self::assertSame('refused: unknown_site_key', $report->results[1]->detail);
        self::assertFalse($report->passed());
    }

    public function testTheHandoffRouteIsCalledAsABrowserWould(): void
    {
        $keys = TestKeys::create();
        $predoza = $this->predoza($keys, drift: 0);
        $transport = new FakeTransport(static fn (string $method, string $url, array $headers, string $body): HttpResponse => match (true) {
            str_starts_with($url, 'https://play.predoza.com') => ($predoza->send($method, $url, $headers, $body)),
            $method === 'OPTIONS' => new HttpResponse(204, ['access-control-allow-origin' => '*'], ''),
            isset($headers['X-Predoza-Handoff']) => new HttpResponse(419, [], ''),
            default => new HttpResponse(403, [], ''),
        });

        $report = (new Doctor(HostSignIn::create($keys->envValue, new StaticMembers(), new FrozenClock(self::NOW)), $transport))->run(handoffUrl: 'https://www.example.com/predoza/handoff');
        $byName = array_combine(array_map(static fn ($result) => $result->name, $report->results), $report->results);

        self::assertStringContainsString('CSRF', $byName['Handoff route']->detail);
        self::assertSame(CheckStatus::Ok, $byName['Handoff guard']->status);
        self::assertSame(CheckStatus::Failed, $byName['CORS']->status);
    }

    private function predoza(TestKeys $keys, int $drift): FakeTransport
    {
        return new FakeTransport(static function (string $method, string $url, array $headers, string $body) use ($keys, $drift): HttpResponse {
            $opened = $keys->opener()->check(json_decode($body, true)['ticket'], self::NOW);

            return new HttpResponse(200, ['date' => gmdate(Doctor::HTTP_DATE, self::NOW - $drift)], json_encode(['ok' => true, 'client' => 'protipster', 'site_key' => $opened->siteKeyId]));
        });
    }
}
