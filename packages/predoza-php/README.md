# predoza/predoza-php

Sign your site's logged-in members in to embedded Predoza games.

[![Latest version](https://img.shields.io/packagist/v/predoza/predoza-php)](https://packagist.org/packages/predoza/predoza-php)
[![Downloads](https://img.shields.io/packagist/dt/predoza/predoza-php)](https://packagist.org/packages/predoza/predoza-php)
[![PHP](https://img.shields.io/packagist/php-v/predoza/predoza-php)](https://packagist.org/packages/predoza/predoza-php)
[![License](https://img.shields.io/packagist/l/predoza/predoza-php)](LICENSE)

> This repository is a read-only copy of [predoza/sdk-php](https://github.com/predoza/sdk-php). Open issues and
> pull requests there.

## Contents

- [Install](#install)
- [Site key](#site-key)
- [Members](#members)
- [Handoff route](#handoff-route)
- [Embed the game](#embed-the-game)
- [Check your setup](#check-your-setup)
- [Testing](#testing)
- [Requirements](#requirements)

## Install

```bash
composer require predoza/predoza-php
```

## Site key

On the **Host sign-in** page of the Predoza client panel, turn host sign-in on and press **Create site key**. Copy the
value into your server's environment as `PREDOZA_SITE_KEY`; it is shown only once.

Creating a new key replaces the old one immediately.

## Members

Implement `Predoza\HostSignIn\MemberDirectory`. It receives your logged-in user and returns a `Member`, or `null` when
the user should play as a guest.

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
        if (!$user instanceof User) {
            return null;
        }

        return new Member(
            id: (string) $user->getId(),
            nickname: $user->getUsername(),
            locale: $user->getLocale(),
            confirmedEmail: $user->isEmailConfirmed() ? $user->getEmail() : null,
        );
    }
}
```

| Parameter | Description |
|---|---|
| `id` | Required. The member's id on your site: 1–64 letters, digits, `_` or `-`. |
| `nickname` | The name shown in the game. |
| `locale` | The member's language, e.g. `pl` or `pt_BR`. |
| `confirmedEmail` | The member's email address, only once your site has confirmed it. |

## Handoff route

The game asks your site who is logged in by calling a route on your own domain, `POST /predoza/handoff` by default.
The route must not require a CSRF token or a login.

### Plain PHP

```php
<?php

declare(strict_types=1);

use App\Predoza\Members;
use Predoza\Bridge\Native\Native;
use Predoza\HostSignIn\HostSignIn;

require __DIR__ . '/../vendor/autoload.php';

$hostSignIn = HostSignIn::create((string) getenv('PREDOZA_SITE_KEY'), new Members());

Native::handoff($hostSignIn, current_user(), 'https://www.example.com');
```

`current_user()` stands for your own lookup of the logged-in user (`null` for a guest). The last argument is your
site's origin as the browser sees it.

### Any framework

Pass the request's method, headers and raw body, with your site's origin, and send back what the endpoint returns:

```php
<?php

declare(strict_types=1);

use App\Predoza\Members;
use Predoza\HostSignIn\HandoffInput;
use Predoza\HostSignIn\HostSignIn;

$hostSignIn = HostSignIn::create((string) getenv('PREDOZA_SITE_KEY'), new Members());

$result = $hostSignIn->handoff()->handle(
    new HandoffInput($method, $headers, $rawBody, 'https://www.example.com'),
    $currentUser,
);

// send $result->status, $result->headers and $result->body as they are
```

### PSR-15

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
    $factory,
    $factory,
);
```

Route `POST /predoza/handoff` to `$handoff`. The closure returns the logged-in user of the request, or `null`.

## Embed the game

```php
<?php

declare(strict_types=1);

use Predoza\Embed\EmbedSnippet;

$snippet = new EmbedSnippet(handoffUrl: '/predoza/handoff', loginUrl: '/login', registerUrl: '/register');

echo $snippet->render('sf_CDSofyqv', lang: 'pl');
```

Use one `EmbedSnippet` per page: it adds the game's script once, however many games the page shows.

| `render()` argument | Description |
|---|---|
| `feature` | Required. The game's id from the Predoza client panel. |
| `lang` | Your page's language. The game uses it and hides its own language switcher. |
| `title` | The game frame's title, for screen readers. |
| `theme` | `light`, `dark` or `auto`. |
| `class` | Classes for the game's element. |

`loginUrl` and `registerUrl` are where the game's "Log in" and "Create an account" buttons send the member.

## Check your setup

Run the doctor on your server:

```bash
PREDOZA_SITE_KEY=… vendor/bin/predoza-doctor --origin=https://www.example.com --handoff-url=https://www.example.com/predoza/handoff
```

```
Predoza host sign-in · predoza/predoza-php

 ✓ Site key       Xk3v9QmP2aLr7tYw · Predoza key p1
 ✓ Page origin    https://www.example.com
 ✓ Predoza check  ticket opened · client example
 ✓ Clock          in step with Predoza
 ✓ Handoff route  POST https://www.example.com/predoza/handoff → 204 for a guest
 ✓ Handoff guard  a call without X-Predoza-Handoff → 403
 ✓ CORS           no CORS headers for another site

7 checks · 7 passed · 0 failed · 0 warnings
```

It checks your key with Predoza without signing anyone in, and exits with `1` when a check fails. `--origin` and
`--handoff-url` are optional.

## Testing

`Predoza\Testing` lets you test your handoff route without Predoza:

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
    public function testAMemberIsSignedInAndAGuestIsNot(): void
    {
        $keys = TestKeys::create();
        $frame = new FakeFrame();
        $handoff = HostSignIn::create($keys->envValue, new Members())->handoff();
        $input = new HandoffInput('POST', $frame->headers('https://www.example.com'), $frame->body(), 'https://www.example.com');

        $result = $handoff->handle($input, new User(48213, 'catenaccio'));

        $ticket = json_decode($result->body, true)['ticket'];
        $member = $keys->opener()->open($ticket, $frame->feature, $frame->verifier, time())->claims;
        self::assertSame('48213', $member['sub']);
        self::assertSame(204, $handoff->handle($input, null)->status);
    }
}
```

## Requirements

- PHP 8.2 or later.
- `ext-sodium` is recommended; without it, the package falls back to `paragonie/sodium_compat`.
- For PSR-15: `psr/http-server-handler`, `psr/http-factory` and a PSR-7 implementation such as `nyholm/psr7`.

## License

MIT. See [LICENSE](LICENSE). Report security problems to [dev@predoza.com](mailto:dev@predoza.com).
