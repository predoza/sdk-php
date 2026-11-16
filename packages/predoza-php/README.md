# predoza/predoza-php

Predoza host sign-in for PHP: sign your site's logged-in members in to embedded Predoza games.

[![Latest version](https://img.shields.io/packagist/v/predoza/predoza-php)](https://packagist.org/packages/predoza/predoza-php)
[![Downloads](https://img.shields.io/packagist/dt/predoza/predoza-php)](https://packagist.org/packages/predoza/predoza-php)
[![PHP](https://img.shields.io/packagist/php-v/predoza/predoza-php)](https://packagist.org/packages/predoza/predoza-php)
[![License](https://img.shields.io/packagist/l/predoza/predoza-php)](LICENSE)

The framework-free core of the Predoza PHP SDK. It answers the game's sign-in request for your logged-in member,
prints the game's embed snippet, checks your setup and helps you test your integration.

On Symfony or Laravel, use [`predoza/predoza-symfony`](https://github.com/predoza/predoza-symfony) or
[`predoza/predoza-laravel`](https://github.com/predoza/predoza-laravel) instead. They build on this package.

> This repository is a read-only split of [predoza/sdk-php](https://github.com/predoza/sdk-php). Open issues and
> pull requests there.

## Contents

- [Install](#install)
- [Get a site key](#get-a-site-key)
- [Tell the SDK who your members are](#tell-the-sdk-who-your-members-are)
- [Answer the handoff request](#answer-the-handoff-request)
- [Plain PHP: Native](#plain-php-native)
- [PSR-15: HandoffHandler](#psr-15-handoffhandler)
- [Embed snippet](#embed-snippet)
- [Doctor](#doctor)
- [Testing your integration](#testing-your-integration)
- [What you must get right](#what-you-must-get-right)
- [Requirements](#requirements)

## Install

```bash
composer require predoza/predoza-php
```

## Get a site key

1. Open the **Host sign-in** page of the Predoza client panel and turn host sign-in on.
2. Press **Create site key** and copy the `PREDOZA_SITE_KEY` value. It is shown once: Predoza keeps only the public
   half.
3. Put it in your server's environment, never in a page or in your repository:

   ```
   PREDOZA_SITE_KEY=pzsk.…
   ```

**New site key** replaces the key. The old key keeps working until the new one signs its first ticket, for 24 hours
at most, so you can deploy the new value without downtime. The same page shows the last sign-in and the reason for
the last refused ticket.

The value has the form `pzsk.{Predoza key id}.{Predoza public key}.{your private seed}`. A damaged value throws
`Predoza\HostSignIn\InvalidSiteKey` with a hint. `SiteKey` never prints its private half and refuses to be
serialized.

## Tell the SDK who your members are

Implement `Predoza\HostSignIn\MemberDirectory`. It gets your logged-in user object and returns a `Member`, or `null`
for anyone who should play as a guest: a disabled or banned account, a shared guest account, or staff impersonating
a member.

```php
<?php

declare(strict_types=1);

namespace App\Predoza;

use App\Entity\User;
use Predoza\HostSignIn\Member;
use Predoza\HostSignIn\MemberDirectory;

class Members implements MemberDirectory
{
    public function memberFor(object $user): ?Member
    {
        if (!$user instanceof User || $user->isBanned() || $user->isSharedAccount()) {
            return null;
        }

        return new Member(
            id: (string) $user->getId(),
            nickname: $user->getDisplayName(),
            locale: $user->getLocale(),
            confirmedEmail: $user->isEmailConfirmed() ? $user->getEmail() : null,
        );
    }
}
```

| `Member` argument | What to pass |
|---|---|
| `id` | Your site's id for the member: 1 to 64 letters, digits, `_` or `-`. Anything else throws `InvalidArgumentException`, so map other ids before passing them. |
| `nickname` | The member's public name on your site, never an email address. Predoza keeps it when it is 1 to 32 letters, marks, digits, spaces or `._-`, starting with a letter or digit; otherwise the game shows a placeholder. |
| `locale` | The member's language, for example `pl` or `pt_BR`. It is matched to the game's languages. |
| `confirmedEmail` | The member's address, only when your site has confirmed it. Otherwise `null`. |

## Answer the handoff request

`embed.js` calls your handoff route (`POST /predoza/handoff` by convention) on your page's own origin. Build a
`HostSignIn` once from the site key and your `MemberDirectory`, describe the request with `HandoffInput`, and send
the `EndpointResult` back as it is.

```php
<?php

declare(strict_types=1);

use App\Predoza\Members;
use Predoza\HostSignIn\HandoffInput;
use Predoza\HostSignIn\HostSignIn;

$hostSignIn = HostSignIn::create((string) getenv('PREDOZA_SITE_KEY'), new Members());

$input = new HandoffInput(
    method: $method,                       // the request method, e.g. "POST"
    headers: $headers,                     // array<string, string|list<string>>, names in any case
    rawBody: $rawBody,                     // the raw request body
    pageOrigin: 'https://www.example.com', // your page's origin as the browser sees it
);

$result = $hostSignIn->handoff()->handle($input, $currentUser); // your logged-in user object, or null

// $result->status, $result->headers and $result->body: send them as they are
```

`HostSignIn::create()` also takes an optional PSR-20 clock (`Psr\Clock\ClockInterface`) as its third argument.

The endpoint checks the request in this order and answers:

| Status | When |
|---|---|
| `405` | The method is not `POST`. Carries `Allow: POST`. |
| `403` | `X-Predoza-Handoff` is not exactly `1`, `Origin` is not `pageOrigin`, or `Sec-Fetch-Site` is present and not `same-origin`. |
| `415` | The media type is not `application/json` (parameters such as `charset` are allowed). |
| `400` | The body is not a JSON object with a valid `challenge` (43 base64url characters) and `feature` (1 to 64 of `A-Za-z0-9_-`). |
| `204` | A guest: `$currentUser` is `null` or your `MemberDirectory` returned `null`. |
| `200` | A member: `{"ticket": "…"}` with `Content-Type: application/json`. |

Every answer carries `Cache-Control: no-store` and no CORS headers.

## Plain PHP: Native

For a plain PHP file as the handoff route, `Predoza\Bridge\Native\Native::handoff()` reads the request from
`$_SERVER` and `php://input` and sends the answer with `http_response_code()`, `header()` and `echo`.

```php
<?php

declare(strict_types=1);

use App\Predoza\Members;
use Predoza\Bridge\Native\Native;
use Predoza\HostSignIn\HostSignIn;

require __DIR__ . '/../vendor/autoload.php';

// open PHP's session only for a visitor who has one, so guests don't get a session
if (isset($_COOKIE[session_name()])) {
    session_start(['read_and_close' => true]);
}

$hostSignIn = HostSignIn::create((string) getenv('PREDOZA_SITE_KEY'), new Members());

Native::handoff($hostSignIn, current_user(), 'https://www.example.com');
```

`current_user()` stands for however your site finds its logged-in user (an object, or `null`). Serve the file at the
URL your embed snippet names as the handoff URL. To build the input and send the answer in separate steps, use
`Native::input($pageOrigin)` and `Native::send($result)`.

## PSR-15: HandoffHandler

`Predoza\Bridge\Psr15\HandoffHandler` is a `RequestHandlerInterface` for PSR-15 apps such as Slim or Mezzio. It
needs the PSR-15 and PSR-17 interfaces and an implementation of them, for example `nyholm/psr7`:

```bash
composer require psr/http-server-handler psr/http-factory nyholm/psr7
```

```php
<?php

declare(strict_types=1);

use App\Predoza\Members;
use Nyholm\Psr7\Factory\Psr17Factory;
use Predoza\Bridge\Psr15\HandoffHandler;
use Predoza\HostSignIn\HostSignIn;
use Psr\Http\Message\ServerRequestInterface;

$factory = new Psr17Factory();

$handoff = new HandoffHandler(
    HostSignIn::create((string) getenv('PREDOZA_SITE_KEY'), new Members()),
    static fn (ServerRequestInterface $request): ?object => $request->getAttribute('user'),
    $factory,                  // ResponseFactoryInterface
    $factory,                  // StreamFactoryInterface
    'https://www.example.com', // page origin
);
```

Route `POST /predoza/handoff` to `$handoff`. The closure returns the request's logged-in user or `null`; here it
reads an attribute your authentication middleware sets. Without the last argument, the page origin is the request
URI's scheme, host and port: behind a proxy, make sure those are the browser's.

## Embed snippet

`Predoza\Embed\EmbedSnippet` prints the game's element and `embed.js`, which must load once per page. Use one
instance per page render: `render()` adds the script after the first game only.

```php
<?php

declare(strict_types=1);

use Predoza\Embed\EmbedSnippet;

$snippet = new EmbedSnippet(handoffUrl: '/predoza/handoff', loginUrl: '/login', registerUrl: '/register');

echo $snippet->render('sf_CDSofyqv', lang: 'pl');
```

```html
<div data-predoza-feature="sf_CDSofyqv" data-lang="pl"></div>
<script async src="https://play.predoza.com/embed/v1.js" data-auth-url="/predoza/handoff" data-login-url="/login" data-register-url="/register"></script>
```

| Constructor argument | Default | Attribute |
|---|---|---|
| `handoffUrl` | `/predoza/handoff` | `data-auth-url`. With `null`, the game never asks for a ticket and everyone plays as a guest. |
| `loginUrl` | `null` | `data-login-url`: where "Log in" in the game sends the member. |
| `registerUrl` | `null` | `data-register-url`: where "Create an account" in the game sends the member. |
| `scriptUrl` | `EmbedSnippet::SCRIPT_URL` (`https://play.predoza.com/embed/v1.js`) | `src` |

`render(string $feature, ?string $lang = null, ?string $title = null, ?string $theme = null)` writes `data-lang`
(your page's language, when your site has its own language switcher), `data-title` and `data-theme` (`light`,
`dark` or `auto`). Empty values are left out and every value is HTML-escaped. To place the parts yourself, use
`element()` and `script()`.

## Doctor

The doctor checks your setup from your own server before any member plays. It reads `PREDOZA_SITE_KEY` from the
environment:

```bash
vendor/bin/predoza-doctor --origin=https://www.example.com --handoff-url=https://www.example.com/predoza/handoff
```

```
Predoza host sign-in · predoza/predoza-php

 ✓ Site key       Xk3v9QmP2aLr7tYw · Predoza key p1
 ✓ Page origin    https://www.example.com
 ✓ Predoza check  ticket opened · client example · site key current
 ✓ Clock          in step with Predoza
 ✓ Handoff route  POST https://www.example.com/predoza/handoff → 204 for a guest
 ✓ Handoff guard  a call without X-Predoza-Handoff → 403
 ✓ CORS           no CORS headers for another site

7 checks · 7 passed · 0 failed · 0 warnings
```

It exits with `0` when no check failed (warnings don't fail) and `1` otherwise.

| Check | Runs | Passes when |
|---|---|---|
| Site key | always | `PREDOZA_SITE_KEY` parses. Shows your site key id and Predoza's key id. |
| Page origin | with `--origin` | The origin is `https` and has no path. |
| Predoza check | always | Predoza opens a check ticket at `https://play.predoza.com/v1/host-sign-in/check`. The check ticket names no member and signs nobody in. A key that has been replaced is a warning; a refusal shows Predoza's reason. |
| Clock | when Predoza's answer has a `Date` header | Your clock is within 15 s of Predoza's. From 15 s it warns; from 30 s it fails, because Predoza refuses such tickets. |
| Handoff route | with `--handoff-url` | A guest's `POST` gets `204`. A `419` means the route checks a CSRF token. |
| Handoff guard | with `--handoff-url` | A call without `X-Predoza-Handoff` gets `403`. |
| CORS | with `--handoff-url` | A preflight from another origin gets no `Access-Control-Allow-Origin`. |

The doctor's own calls carry no cookies, so they test the guest path. It doesn't follow redirects (a redirect is
itself a finding) and gives up after 10 seconds. Predoza accepts 20 checks a minute per IP.

From code, `Predoza\Doctor\Doctor` returns a `DoctorReport`:

```php
<?php

declare(strict_types=1);

use Predoza\Doctor\CheckResult;
use Predoza\Doctor\CheckStatus;
use Predoza\Doctor\Doctor;

$report = (new Doctor($hostSignIn))->run(
    pageOrigin: 'https://www.example.com',
    handoffUrl: 'https://www.example.com/predoza/handoff',
);

$failed = array_filter($report->results, static fn (CheckResult $result): bool => $result->status === CheckStatus::Failed);

echo implode("\n", $report->lines()), "\n\n", $report->summary(), "\n";
```

Each `CheckResult` has a `name`, a `status` (`CheckStatus::Ok`, `Warning` or `Failed`) and a `detail`.
`$report->passed()` is `false` when any check failed. The doctor sends its requests with plain PHP streams; pass your
own `Predoza\Doctor\HttpTransport` as the second constructor argument to replace them.

## Testing your integration

`Predoza\Testing` holds what you need to test your handoff route without Predoza:

- `TestKeys::create()` makes a throwaway Predoza key and site key. Use `$keys->envValue` as `PREDOZA_SITE_KEY` and
  `$keys->opener()` to read the tickets your route answers.
- `FakeFrame` does what the game frame does: a PKCE pair (`verifier`, `challenge`), the `feature` (default
  `sf_testgame`), and the request `embed.js` sends (`headers($pageOrigin)`, `body()`).
- `TicketOpener` opens tickets the way Predoza does. `open($ticket, $feature, $verifier, $now)` and
  `check($ticket, $now)` return an `OpenedTicket` (`claims`, `siteKeyId`) or throw `TicketRefused`, whose `reason`
  is the one Predoza would give (`malformed`, `unknown_predoza_key`, `not_for_predoza`, `unknown_site_key`,
  `bad_signature`, `wrong_feature`, `verifier_mismatch`, `expired`, `not_yet_valid`). It doesn't track single use.

```php
<?php

declare(strict_types=1);

namespace App\Tests;

use App\Entity\User;
use App\Predoza\Members;
use PHPUnit\Framework\TestCase;
use Predoza\HostSignIn\HandoffInput;
use Predoza\HostSignIn\HostSignIn;
use Predoza\Testing\FakeFrame;
use Predoza\Testing\TestKeys;

class PredozaHandoffTest extends TestCase
{
    private const PAGE_ORIGIN = 'https://www.example.com';

    public function testAMemberGetsATicketAndAGuestGetsNothing(): void
    {
        $keys = TestKeys::create();
        $frame = new FakeFrame('sf_CDSofyqv');
        $handoff = HostSignIn::create($keys->envValue, new Members())->handoff();
        $input = new HandoffInput('POST', $frame->headers(self::PAGE_ORIGIN), $frame->body(), self::PAGE_ORIGIN);

        $result = $handoff->handle($input, new User(48213, 'catenaccio'));

        self::assertSame(200, $result->status);
        $ticket = json_decode($result->body, true)['ticket'];
        $claims = $keys->opener()->open($ticket, $frame->feature, $frame->verifier, time())->claims;
        self::assertSame('48213', $claims['sub']);
        self::assertSame('catenaccio', $claims['nickname']);

        self::assertSame(204, $handoff->handle($input, null)->status);
    }
}
```

`TicketOpener` needs Predoza's secret key, which in production only Predoza has. It is for tests only.

## What you must get right

The Symfony bundle and the Laravel package take care of these. When you integrate the core directly, you do:

- **The real page origin.** `pageOrigin` is your page's origin as the browser sees it: scheme, host and port, for
  example `https://www.example.com`. It is compared with the `Origin` header exactly. Behind a TLS-terminating proxy
  or load balancer your app may see `http` or an internal host, and every member gets `403`. Take the origin from
  your framework's trusted-proxy aware request, or pass a fixed value.
- **No CSRF check on the route.** `embed.js` sends no CSRF token. The `X-Predoza-Handoff` header and the `Origin` and
  `Sec-Fetch-Site` checks replace it. Exempt the route from your CSRF middleware; the doctor reports a `419`. Don't
  start a session for guests on this route.
- **No CORS headers on the route.** Keep the handoff path out of your CORS configuration. The doctor checks it.
- **No login wall.** A guest must reach the route and get `204`. A redirect to your login page is a failure.
- **Refuse impersonated users.** While staff impersonate a member, pass `null` as the user or return `null` from
  your `MemberDirectory`. The same goes for disabled or banned accounts and shared guest accounts.
- **Pass only confirmed emails.** Predoza keys accounts by email address, so an unconfirmed address could claim
  someone else's account.
- **Member id format.** `[A-Za-z0-9_-]{1,64}`. Map other ids (an email address, an id with a colon) to one that
  fits, and keep it stable for the member.
- **Clock.** Keep your server's clock in sync. Predoza refuses a ticket issued more than 90 s ago or more than 30 s
  ahead of its own clock.
- **Keep the site key secret.** It signs tickets for your members. Keep it in the server environment only.

## Requirements

- PHP 8.2 or later, with `ext-json`.
- `ext-sodium` is optional. Without it, [`paragonie/sodium_compat`](https://github.com/paragonie/sodium_compat), a
  dependency of this package, does the cryptography in plain PHP. `ext-sodium` is faster.
- `psr/clock`.
- For `HandoffHandler`: `psr/http-server-handler`, `psr/http-factory` and a PSR-7/PSR-17 implementation.

## Contributing and security

Issues and pull requests go to [predoza/sdk-php](https://github.com/predoza/sdk-php). Report security problems to
[dev@predoza.com](mailto:dev@predoza.com), not in a public issue.

## License

MIT. See [LICENSE](LICENSE).
