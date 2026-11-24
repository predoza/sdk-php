<?php

declare(strict_types=1);

namespace Predoza\HostSignIn;

/**
 * The URL embed.js asks on your page's own origin (data-auth-url). It answers a ticket for a logged-in member, 204
 * for a guest, and refuses anything that isn't a same-origin JSON call from your own page: the ticket vouches for
 * the member whose session cookie came with the request.
 */
class HandoffEndpoint
{
    private const CHALLENGE = '/^[A-Za-z0-9_-]{43}$/';

    private const GAME = '/^[A-Za-z0-9_-]{1,64}$/';

    public function __construct(
        private TicketSealer $sealer,
        private MemberDirectory $members,
    ) {
    }

    public function handle(HandoffInput $input, ?object $user): EndpointResult
    {
        if (strtoupper($input->method) !== 'POST') {
            return EndpointResult::empty(405, ['Allow' => 'POST']);
        }
        if (!$this->isSameOriginCall($input)) {
            return EndpointResult::empty(403);
        }
        if (!$this->isJson($input)) {
            return EndpointResult::empty(415);
        }
        $request = $this->parse($input->rawBody);

        if ($request === null) {
            return EndpointResult::empty(400);
        }
        $member = $user === null ? null : $this->members->memberFor($user);

        if ($member === null) {
            return EndpointResult::empty(204);
        }

        return EndpointResult::json(200, ['ticket' => $this->sealer->seal($member, $request['challenge'], $request['game'])]);
    }

    /**
     * A cross-site page can't send the custom header or a JSON body without a CORS preflight, which this endpoint
     * never answers. Sibling subdomains still get Lax cookies, so the exact Origin is checked too.
     */
    private function isSameOriginCall(HandoffInput $input): bool
    {
        $fetchSite = $input->header('Sec-Fetch-Site');

        return $input->header('X-Predoza-Handoff') === '1'
            && $input->header('Origin') === $input->pageOrigin
            && ($fetchSite === null || $fetchSite === 'same-origin');
    }

    private function isJson(HandoffInput $input): bool
    {
        $mediaType = strtolower(trim(explode(';', (string) $input->header('Content-Type'))[0]));

        return $mediaType === 'application/json';
    }

    /**
     * @return array{challenge: string, game: string}|null
     */
    private function parse(string $rawBody): ?array
    {
        $body = json_decode($rawBody, true, 3);

        if (!is_array($body) || array_is_list($body)) {
            return null;
        }
        $challenge = $body['challenge'] ?? null;
        $game = $body['game'] ?? null;

        if (!is_string($challenge) || !preg_match(self::CHALLENGE, $challenge) || !is_string($game) || !preg_match(self::GAME, $game)) {
            return null;
        }

        return ['challenge' => $challenge, 'game' => $game];
    }
}
