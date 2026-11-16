<?php

declare(strict_types=1);

namespace Predoza\Doctor;

use DateTimeImmutable;
use DateTimeZone;
use Predoza\HostSignIn\HostSignIn;
use RuntimeException;

/**
 * Checks a site's host sign-in setup from its own server, before any member plays: the site key, Predoza's view of
 * it (a check ticket that signs nobody in), the clock, and optionally the page origin and the handoff route.
 */
class Doctor
{
    // the HTTP Date header, always in GMT
    public const HTTP_DATE = 'D, d M Y H:i:s \G\M\T';

    private const CLOCK_WARNING_SECONDS = 15;

    // Predoza accepts tickets up to 30 s from its own clock
    private const CLOCK_LIMIT_SECONDS = 30;

    private HttpTransport $transport;

    public function __construct(private HostSignIn $hostSignIn, ?HttpTransport $transport = null)
    {
        $this->transport = $transport ?? new StreamTransport();
    }

    /**
     * @param string|null $pageOrigin your pages' origin as your app builds URLs, e.g. "https://www.example.com"
     * @param string|null $handoffUrl the full URL of your handoff route, to call it as a browser would
     */
    public function run(?string $pageOrigin = null, ?string $handoffUrl = null): DoctorReport
    {
        $siteKey = $this->hostSignIn->siteKey();
        $results = [new CheckResult('Site key', CheckStatus::Ok, "{$siteKey->id} · Predoza key {$siteKey->predozaKid}")];

        if ($pageOrigin !== null) {
            $results[] = $this->pageOrigin($pageOrigin);
        }
        array_push($results, ...$this->predoza());

        if ($handoffUrl !== null) {
            array_push($results, ...$this->handoffRoute($handoffUrl));
        }

        return new DoctorReport($results);
    }

    private function pageOrigin(string $pageOrigin): CheckResult
    {
        $parts = parse_url($pageOrigin);

        if (($parts['scheme'] ?? null) !== 'https' || isset($parts['path']) && $parts['path'] !== '' && $parts['path'] !== '/') {
            return new CheckResult('Page origin', CheckStatus::Failed, "{$pageOrigin} — pages must be https; behind a proxy, set trusted proxies");
        }

        return new CheckResult('Page origin', CheckStatus::Ok, $pageOrigin);
    }

    /**
     * @return CheckResult[]
     */
    private function predoza(): array
    {
        try {
            $response = $this->transport->send('POST', $this->hostSignIn->checkUrl(), [
                'Content-Type' => 'application/json',
                'Accept' => 'application/json',
            ], json_encode(['ticket' => $this->hostSignIn->sealer()->sealCheck()], JSON_THROW_ON_ERROR));
        } catch (RuntimeException $exception) {
            return [new CheckResult('Predoza check', CheckStatus::Failed, $exception->getMessage())];
        }
        $answer = json_decode($response->body, true);
        $results = [match (true) {
            is_array($answer) && ($answer['ok'] ?? false) === true => new CheckResult('Predoza check', CheckStatus::Ok, sprintf(
                'ticket opened · client %s',
                is_string($answer['client'] ?? null) ? $answer['client'] : '?',
            )),
            is_array($answer) && is_string($answer['reason'] ?? null) => new CheckResult('Predoza check', CheckStatus::Failed, "refused: {$answer['reason']}"),
            default => new CheckResult('Predoza check', CheckStatus::Failed, "unexpected answer {$response->status}"),
        }];
        $date = $response->header('Date');

        if ($date !== null && ($predozaTime = DateTimeImmutable::createFromFormat(self::HTTP_DATE, $date, new DateTimeZone('UTC'))) !== false) {
            $results[] = $this->clock($predozaTime);
        }

        return $results;
    }

    private function clock(DateTimeImmutable $predozaTime): CheckResult
    {
        $drift = $this->hostSignIn->clock()->now()->getTimestamp() - $predozaTime->getTimestamp();
        $detail = $drift === 0 ? 'in step with Predoza' : sprintf('%d s %s Predoza', abs($drift), $drift < 0 ? 'behind' : 'ahead of');

        return match (true) {
            abs($drift) >= self::CLOCK_LIMIT_SECONDS => new CheckResult('Clock', CheckStatus::Failed, "{$detail} — tickets are refused at " . self::CLOCK_LIMIT_SECONDS . ' s'),
            abs($drift) >= self::CLOCK_WARNING_SECONDS => new CheckResult('Clock', CheckStatus::Warning, "{$detail} — fails at " . self::CLOCK_LIMIT_SECONDS . ' s'),
            default => new CheckResult('Clock', CheckStatus::Ok, $detail),
        };
    }

    /**
     * @return CheckResult[]
     */
    private function handoffRoute(string $handoffUrl): array
    {
        $parts = parse_url($handoffUrl);
        $origin = ($parts['scheme'] ?? 'https') . '://' . ($parts['host'] ?? '') . (isset($parts['port']) ? ":{$parts['port']}" : '');
        $json = json_encode(['challenge' => str_repeat('A', 43), 'feature' => 'doctor'], JSON_THROW_ON_ERROR);

        try {
            $guest = $this->transport->send('POST', $handoffUrl, ['Content-Type' => 'application/json', 'Origin' => $origin, 'X-Predoza-Handoff' => '1', 'Sec-Fetch-Site' => 'same-origin'], $json);
            $unmarked = $this->transport->send('POST', $handoffUrl, ['Content-Type' => 'application/json', 'Origin' => $origin], $json);
            $preflight = $this->transport->send('OPTIONS', $handoffUrl, ['Origin' => 'https://doctor.invalid', 'Access-Control-Request-Method' => 'POST', 'Access-Control-Request-Headers' => 'content-type,x-predoza-handoff']);
        } catch (RuntimeException $exception) {
            return [new CheckResult('Handoff route', CheckStatus::Failed, $exception->getMessage())];
        }

        return [
            match ($guest->status) {
                204 => new CheckResult('Handoff route', CheckStatus::Ok, "POST {$handoffUrl} → 204 for a guest"),
                419 => new CheckResult('Handoff route', CheckStatus::Failed, '419: the route checks a CSRF token; register it without'),
                default => new CheckResult('Handoff route', CheckStatus::Failed, "a guest got {$guest->status}, expected 204; is the route behind a login or firewall?"),
            },
            $unmarked->status === 403
                ? new CheckResult('Handoff guard', CheckStatus::Ok, 'a call without X-Predoza-Handoff → 403')
                : new CheckResult('Handoff guard', CheckStatus::Failed, "a call without X-Predoza-Handoff got {$unmarked->status}, expected 403"),
            $preflight->header('Access-Control-Allow-Origin') === null
                ? new CheckResult('CORS', CheckStatus::Ok, 'no CORS headers for another site')
                : new CheckResult('CORS', CheckStatus::Failed, 'another site gets Access-Control-Allow-Origin; exclude the handoff path from CORS'),
        ];
    }
}
