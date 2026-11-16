<?php

declare(strict_types=1);

namespace Predoza\Bridge\Psr15;

use Closure;
use Predoza\HostSignIn\HandoffInput;
use Predoza\HostSignIn\HostSignIn;
use Psr\Http\Message\ResponseFactoryInterface;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Message\StreamFactoryInterface;
use Psr\Http\Server\RequestHandlerInterface;

/**
 * The handoff route for PSR-15 apps (Slim, Mezzio, …). $currentUser finds the logged-in user of a request, or null.
 * Without $pageOrigin the request's own scheme, host and port are used: behind a proxy, make sure they are the
 * browser's.
 */
class HandoffHandler implements RequestHandlerInterface
{
    /**
     * @param Closure(ServerRequestInterface): ?object $currentUser
     */
    public function __construct(
        private HostSignIn $hostSignIn,
        private Closure $currentUser,
        private ResponseFactoryInterface $responses,
        private StreamFactoryInterface $streams,
        private ?string $pageOrigin = null,
    ) {
    }

    public function handle(ServerRequestInterface $request): ResponseInterface
    {
        $uri = $request->getUri();
        $origin = $this->pageOrigin ?? $uri->getScheme() . '://' . $uri->getHost() . ($uri->getPort() !== null ? ':' . $uri->getPort() : '');
        $result = $this->hostSignIn->handoff()->handle(
            new HandoffInput($request->getMethod(), $request->getHeaders(), (string) $request->getBody(), $origin),
            ($this->currentUser)($request),
        );
        $response = $this->responses->createResponse($result->status)->withBody($this->streams->createStream($result->body));

        foreach ($result->headers as $name => $value) {
            $response = $response->withHeader($name, $value);
        }

        return $response;
    }
}
