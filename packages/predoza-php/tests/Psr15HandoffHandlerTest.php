<?php

declare(strict_types=1);

namespace Predoza\Tests;

use Nyholm\Psr7\Factory\Psr17Factory;
use PHPUnit\Framework\TestCase;
use Predoza\Bridge\Psr15\HandoffHandler;
use Predoza\HostSignIn\HostSignIn;
use Predoza\Testing\FakeFrame;
use Predoza\Testing\TestKeys;
use Psr\Http\Message\ServerRequestInterface;

final class Psr15HandoffHandlerTest extends TestCase
{
    public function testTheHandlerAnswersATicketForTheRequestsUser(): void
    {
        $keys = TestKeys::create();
        $factory = new Psr17Factory();
        $frame = new FakeFrame();
        $handler = new HandoffHandler(
            HostSignIn::create($keys->envValue, new StaticMembers()),
            static fn (ServerRequestInterface $request): ?object => $request->getAttribute('user'),
            $factory,
            $factory,
        );
        $request = $factory->createServerRequest('POST', 'https://www.example.com/predoza/handoff')
            ->withBody($factory->createStream($frame->body()))
            ->withAttribute('user', new SiteUser('48213'));

        foreach ($frame->headers('https://www.example.com') as $name => $value) {
            $request = $request->withHeader($name, $value);
        }
        $response = $handler->handle($request);

        self::assertSame(200, $response->getStatusCode());
        self::assertSame('no-store', $response->getHeaderLine('Cache-Control'));
        $ticket = json_decode((string) $response->getBody(), true)['ticket'];
        self::assertSame('48213', $keys->opener()->open($ticket, $frame->feature, $frame->verifier, time())->claims['sub']);

        self::assertSame(204, $handler->handle($request->withAttribute('user', null))->getStatusCode());
        self::assertSame(403, $handler->handle($request->withHeader('Origin', 'https://evil.example.net'))->getStatusCode());
    }
}
