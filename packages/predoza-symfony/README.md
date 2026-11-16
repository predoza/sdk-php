# predoza/predoza-symfony

Sign your Symfony site's logged-in members in to embedded Predoza games.

[![Latest version](https://img.shields.io/packagist/v/predoza/predoza-symfony)](https://packagist.org/packages/predoza/predoza-symfony)
[![Downloads](https://img.shields.io/packagist/dt/predoza/predoza-symfony)](https://packagist.org/packages/predoza/predoza-symfony)
[![PHP](https://img.shields.io/packagist/php-v/predoza/predoza-symfony)](https://packagist.org/packages/predoza/predoza-symfony)
[![License](https://img.shields.io/packagist/l/predoza/predoza-symfony)](LICENSE)

> This repository is a read-only copy of [predoza/sdk-php](https://github.com/predoza/sdk-php). Open issues and
> pull requests there.

## Contents

- [Install](#install)
- [Site key](#site-key)
- [Members](#members)
- [Configure](#configure)
- [Route](#route)
- [Embed the game](#embed-the-game)
- [Check your setup](#check-your-setup)
- [Requirements](#requirements)

## Install

```bash
composer require predoza/predoza-symfony
```

Register the bundle in `config/bundles.php`:

```php
return [
    // ...
    Predoza\Symfony\PredozaBundle::class => ['all' => true],
];
```

## Site key

On the **Host sign-in** page of the Predoza client panel, turn host sign-in on and press **Create site key**. Copy the
value into your server's environment as `PREDOZA_SITE_KEY`; it is shown only once.

Creating a new key replaces the old one immediately.

## Members

Implement `Predoza\HostSignIn\MemberDirectory`. It receives the user of the current security token and returns a
`Member`, or `null` when the user should play as a guest.

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
            confirmedEmail: $user->isVerified() ? $user->getEmail() : null,
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

Users switched to with `switch_user` always play as guests.

## Configure

```yaml
# config/packages/predoza.yaml
predoza:
    site_key: '%env(PREDOZA_SITE_KEY)%'
    member_directory: App\Predoza\Members
    login_url: app_login
    register_url: app_register
```

| Option | Description |
|---|---|
| `site_key` | Required. Your site key. |
| `member_directory` | Required. The service id of your `MemberDirectory`. |
| `login_url` | Where the game's "Log in" button sends the member: a route name, a path or a URL. |
| `register_url` | Where the game's "Create an account" button sends the member: a route name, a path or a URL. |

## Route

```yaml
# config/routes/predoza.yaml
predoza:
    resource: '@PredozaBundle/config/routes.php'
```

This adds `POST /predoza/handoff` (route `predoza_handoff`), which the game calls to find out who is logged in. Keep
it inside the firewall that holds your users' session, and don't put it behind a login in `access_control`.

If you use NelmioCorsBundle, make sure no rule allows other origins on this path:

```yaml
# config/packages/nelmio_cors.yaml
nelmio_cors:
    paths:
        '^/predoza/handoff$':
            origin_regex: true
            allow_origin: ['^$']
```

Behind a load balancer or proxy, configure `framework.trusted_proxies` so the request has your site's real `https`
origin.

## Embed the game

```twig
{{ predoza_game('sf_CDSofyqv') }}
```

| Option | Description |
|---|---|
| `lang` | The game's language. Defaults to the request's locale; the game then hides its own language switcher. |
| `title` | The game frame's title, for screen readers. |
| `theme` | `light`, `dark` or `auto`. |
| `class` | Classes for the game's element. |

```twig
{{ predoza_game('sf_CDSofyqv', {title: 'Weekend predictor', theme: 'dark', class: 'my-6'}) }}
```

The game's script is added once per page, however many games it shows.

## Check your setup

```bash
bin/console predoza:doctor --handoff-url=https://www.example.com/predoza/handoff
```

```
Predoza host sign-in · predoza/predoza-symfony

 ✓ Site key       Xk3v9QmP2aLr7tYw · Predoza key p1
 ✓ Page origin    https://www.example.com
 ✓ Predoza check  ticket opened · client example
 ✓ Clock          in step with Predoza
 ✓ Handoff route  POST https://www.example.com/predoza/handoff → 204 for a guest
 ✓ Handoff guard  a call without X-Predoza-Handoff → 403
 ✓ CORS           no CORS headers for another site

7 checks · 7 passed · 0 failed · 0 warnings
```

It checks your key with Predoza without signing anyone in. The page origin is checked when
`framework.router.default_uri` is set.

## Requirements

- PHP 8.2 or later.
- Symfony 6.4, 7.4 or 8.x, with `symfony/security-bundle` and `symfony/twig-bundle`.

## License

MIT. See [LICENSE](LICENSE). Report security problems to [dev@predoza.com](mailto:dev@predoza.com).
