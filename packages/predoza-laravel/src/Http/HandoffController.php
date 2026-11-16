<?php

declare(strict_types=1);

namespace Predoza\Laravel\Http;

use Illuminate\Contracts\Auth\Factory as AuthFactory;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Predoza\HostSignIn\HandoffInput;
use Predoza\HostSignIn\HostSignIn;
use Predoza\Laravel\Predoza;

class HandoffController
{
    public function __construct(private HostSignIn $hostSignIn, private AuthFactory $auth)
    {
    }

    public function __invoke(Request $request): Response
    {
        $guard = config('predoza.guard');
        $user = $this->auth->guard(is_string($guard) ? $guard : null)->user();
        $result = $this->hostSignIn->handoff()->handle(
            new HandoffInput($request->getMethod(), $request->headers->all(), $request->getContent(), $request->getSchemeAndHttpHost()),
            $user === null || Predoza::refuses($request) ? null : $user,
        );

        return new Response($result->body, $result->status, $result->headers);
    }
}
