# predoza/predoza-laravel

Sign your Laravel site's logged-in members in to embedded Predoza games.

[![Latest version](https://img.shields.io/packagist/v/predoza/predoza-laravel)](https://packagist.org/packages/predoza/predoza-laravel)
[![Downloads](https://img.shields.io/packagist/dt/predoza/predoza-laravel)](https://packagist.org/packages/predoza/predoza-laravel)
[![PHP](https://img.shields.io/packagist/php-v/predoza/predoza-laravel)](https://packagist.org/packages/predoza/predoza-laravel)
[![License](https://img.shields.io/packagist/l/predoza/predoza-laravel)](LICENSE)

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
composer require predoza/predoza-laravel
php artisan vendor:publish --tag=predoza-config
```

## Site key

On the **Host sign-in** page of the Predoza client panel, turn host sign-in on and press **Create site key**. Copy the
value into your `.env`; it is shown only once.

```dotenv
PREDOZA_SITE_KEY=…
```

Creating a new key replaces the old one immediately.

## Members

Implement `Predoza\HostSignIn\MemberDirectory`. It receives the logged-in user and returns a `Member`, or `null` when
the user should play as a guest.

```php
<?php

declare(strict_types=1);

namespace App\Predoza;

use App\Models\User;
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
            id: (string) $user->getKey(),
            nickname: $user->name,
            locale: $user->locale,
            confirmedEmail: $user->hasVerifiedEmail() ? $user->email : null,
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

If you let staff impersonate users, make impersonated sessions play as guests, for example with
`lab404/laravel-impersonate` in your `AppServiceProvider::boot()`:

```php
use Illuminate\Http\Request;
use Predoza\Laravel\Predoza;

Predoza::refuseWhen(fn (Request $request): bool => app('impersonate')->isImpersonating());
```

## Configure

In `config/predoza.php`:

```php
'members' => App\Predoza\Members::class,
```

| Key | Default | Description |
|---|---|---|
| `site_key` | `env('PREDOZA_SITE_KEY')` | Your site key. |
| `members` | `null` | Required. Your `MemberDirectory` class. |
| `guard` | default guard | The auth guard your members log in with. |
| `handoff_path` | `/predoza/handoff` | The route the game calls. |
| `routes` | `true` | `false` to register the route yourself, under the name `predoza.handoff`. |
| `login_url` | `/login` | Where the game's "Log in" button sends the member. |
| `register_url` | `/register` | Where the game's "Create an account" button sends the member. |

## Route

The package registers `POST /predoza/handoff` (`predoza.handoff`), which the game calls to find out who is logged
in. It runs in the `web` middleware group and skips the CSRF check, because the game can't send a token. Don't put it
behind `auth`: guests must reach it too.

Behind a load balancer or proxy, configure trusted proxies so the request has your site's real `https` origin. In
`config/cors.php`, don't allow other origins on this path (Laravel's default `paths` are fine; `['*']` is not).

## Embed the game

```blade
<x-predoza-game game="sf_CDSofyqv" />
```

| Attribute | Description |
|---|---|
| `game` | Required. The game's id from the Predoza client panel. |
| `lang` | The game's language. Defaults to the app's locale; the game then hides its own language switcher. |
| `title` | The game frame's title, for screen readers. |
| `theme` | `light`, `dark` or `auto`. |
| `class` | Classes for the game's element. |

The game's script is added once per page, however many games it shows.

## Check your setup

```bash
php artisan predoza:doctor --handoff-url=https://www.example.com/predoza/handoff
```

```
Predoza host sign-in · predoza/predoza-laravel

 ✓ Site key       Xk3v9QmP2aLr7tYw · Predoza key p1
 ✓ Page origin    https://www.example.com
 ✓ Predoza check  ticket opened · client example
 ✓ Clock          in step with Predoza
 ✓ Handoff route  POST https://www.example.com/predoza/handoff → 204 for a guest
 ✓ Handoff guard  a call without X-Predoza-Handoff → 403
 ✓ CORS           no CORS headers for another site

7 checks · 7 passed · 0 failed · 0 warnings
```

It checks your key with Predoza without signing anyone in. The page origin comes from `APP_URL`.

## Requirements

- PHP 8.2 or later.
- Laravel 12 or 13.

## License

MIT. See [LICENSE](LICENSE). Report security problems to [dev@predoza.com](mailto:dev@predoza.com).
