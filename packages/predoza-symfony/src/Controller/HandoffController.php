<?php

declare(strict_types=1);

namespace Predoza\Symfony\Controller;

use Predoza\HostSignIn\HandoffInput;
use Predoza\HostSignIn\HostSignIn;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Security\Core\Authentication\Token\Storage\TokenStorageInterface;
use Symfony\Component\Security\Core\Authentication\Token\SwitchUserToken;

/**
 * The handoff route embed.js calls on your own pages. The member is the user of the current security token; staff
 * switched to another user never hand that user's identity to Predoza.
 */
class HandoffController
{
    public function __construct(
        private HostSignIn $hostSignIn,
        private ?TokenStorageInterface $tokenStorage = null,
    ) {
    }

    public function __invoke(Request $request): Response
    {
        $result = $this->hostSignIn->handoff()->handle(
            new HandoffInput($request->getMethod(), $request->headers->all(), $request->getContent(), $request->getSchemeAndHttpHost()),
            $this->currentUser(),
        );

        return new Response($result->body, $result->status, $result->headers);
    }

    private function currentUser(): ?object
    {
        $token = $this->tokenStorage?->getToken();

        if ($token === null || $token instanceof SwitchUserToken) {
            return null;
        }

        return $token->getUser();
    }
}
