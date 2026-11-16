# predoza/predoza-laravel

Laravel package for Predoza host sign-in: the handoff route, a Blade component for embedded games and a doctor command.

[![Latest version](https://img.shields.io/packagist/v/predoza/predoza-laravel)](https://packagist.org/packages/predoza/predoza-laravel)
[![Downloads](https://img.shields.io/packagist/dt/predoza/predoza-laravel)](https://packagist.org/packages/predoza/predoza-laravel)
[![PHP](https://img.shields.io/packagist/php-v/predoza/predoza-laravel)](https://packagist.org/packages/predoza/predoza-laravel)
[![License](https://img.shields.io/packagist/l/predoza/predoza-laravel)](LICENSE)

Host sign-in lets your site sign its logged-in members in to embedded Predoza games, so they never log in twice.
This package answers the game's request with a ticket for the user of your auth guard. It builds on
[`predoza/predoza-php`](https://github.com/predoza/predoza-php), whose README covers the member
rules and the test helpers.

> This repository is a read-only split of [predoza/sdk-php](https://github.com/predoza/sdk-php). Open issues and
> pull requests there.

## Contents

- [Install](#install)
- [Configure](#configure)
- [The handoff route](#the-handoff-route)
- [Impersonation](#impersonation)
- [Blade component](#blade-component)
- [Proxies and CORS](#proxies-and-cors)
- [Doctor](#doctor)
- [Requirements](#requirements)
- [Testing](#testing)

## Install

```bash
composer require predoza/predoza-laravel
```

Laravel discovers `Predoza\Laravel\PredozaServiceProvider` on its own. Publish the config file:

```bash
php artisan vendor:publish --tag=predoza-config
```

## Configure

Create a site key on the **Host sign-in** page of the Predoza client panel (turn host sign-in on, press **Create
site key**) and put the value in your `.env`. It is shown once.

```dotenv
PREDOZA_SITE_KEY=pzsk.…
```

Then point `members` in `config/predoza.php` at your member directory:

```php
<?php

declare(strict_types=1);

use App\Predoza\Members;

return [
    // ...
    'members' => Members::class,
    // ...
];
```

| Key | Default | |
|---|---|---|
| `site_key` | `env('PREDOZA_SITE_KEY')` | The `PREDOZA_SITE_KEY` value. |
| `members` | `null` | Your class implementing `Predoza\HostSignIn\MemberDirectory`. It is resolved from the container. |
| `guard` | `null` | The auth guard your members sign in with. `null` uses the default guard. |
| `handoff_path` | `/predoza/handoff` | The handoff route's path. |
| `routes` | `true` | `false` to register the route yourself. |
| `login_url` | `/login` | Where "Log in" in the game sends the member. |
| `register_url` | `/register` | Where "Create an account" in the game sends the member. |

Without `site_key` or `members`, the handoff route and the doctor throw an exception that says what to set.

Your `MemberDirectory` gets the guard's user and returns a `Member`, or `null` for anyone who should play as a guest
(a disabled or banned account, a shared guest account):

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
        if (!$user instanceof User || $user->is_banned) {
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

Pass the email only when your site has confirmed it, and use a member id of 1 to 64 letters, digits, `_` or `-`.
See [the core README](https://github.com/predoza/predoza-php#tell-the-sdk-who-your-members-are) for every field.

## The handoff route

Unless `routes` is `false`, the package registers:

- `POST /predoza/handoff` (the value of `handoff_path`), named `predoza.handoff`;
- in the `web` middleware group, so it sees the member's session;
- without the CSRF check (`ValidateCsrfToken` and `VerifyCsrfToken`), which `embed.js` can't pass. The handoff header
  and the `Origin` and `Sec-Fetch-Site` checks replace it.

Don't put the route behind the `auth` middleware: a guest must get `204`, not a redirect to the login page.

### Registering the route yourself

With `'routes' => false`, no route is registered. Add one in `routes/web.php`, without the CSRF check, and name it
`predoza.handoff`: the Blade component points `data-auth-url` at the route of that name.

```php
<?php

declare(strict_types=1);

use Illuminate\Foundation\Http\Middleware\ValidateCsrfToken;
use Illuminate\Support\Facades\Route;
use Predoza\Laravel\Http\HandoffController;

Route::post('/games/handoff', HandoffController::class)
    ->middleware('web')
    ->withoutMiddleware([ValidateCsrfToken::class])
    ->name('predoza.handoff');
```

## Impersonation

Your `MemberDirectory` only sees the user, not who is behind the session. Register a rule with
`Predoza\Laravel\Predoza::refuseWhen()` so that staff impersonating a member never hand that member's identity to
Predoza. With [lab404/laravel-impersonate](https://github.com/404labfr/laravel-impersonate), in your
`AppServiceProvider::boot()`:

```php
<?php

declare(strict_types=1);

namespace App\Providers;

use Illuminate\Http\Request;
use Illuminate\Support\ServiceProvider;
use Predoza\Laravel\Predoza;

class AppServiceProvider extends ServiceProvider
{
    public function boot(): void
    {
        Predoza::refuseWhen(fn (Request $request): bool => app('impersonate')->isImpersonating());
    }
}
```

When any rule returns `true`, the handoff answers `204` and the user plays as a guest. Rules are kept in a static
list; `Predoza::forgetRules()` clears it, for example in a test's `tearDown()`.

## Blade component

```blade
<x-predoza-game feature="sf_CDSofyqv" />

<x-predoza-game feature="sf_CDSofyqv" lang="pl" title="Weekend predictor" theme="dark" />
```

```html
<div data-predoza-feature="sf_CDSofyqv" data-lang="en"></div>
<script async src="https://play.predoza.com/embed/v1.js" data-auth-url="/predoza/handoff" data-login-url="/login" data-register-url="/register"></script>
```

| Attribute | Prints | Default |
|---|---|---|
| `feature` | `data-predoza-feature` | required |
| `lang` | `data-lang` | The app's locale (`App::getLocale()`). |
| `title` | `data-title` | none |
| `theme` | `data-theme`: `light`, `dark` or `auto` | none |
| `class` | `class` on the game's element | none |

The script carries the `predoza.handoff` route's URL, `login_url` and `register_url`. It is printed once per request,
after the first game, however many games the page shows.

## Proxies and CORS

The handoff compares the `Origin` header with the request's scheme and host. Behind a TLS-terminating proxy or load
balancer, trust the proxy so that this is the origin the browser sees, or every member gets `403`. In
`bootstrap/app.php`:

```php
<?php

declare(strict_types=1);

use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Middleware;

return Application::configure(basePath: dirname(__DIR__))
    // ...
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->trustProxies(at: ['10.0.0.0/8']);
    })
    // ...
    ->create();
```

The handoff route must not answer other origins with CORS headers. Keep its path out of `paths` in
`config/cors.php`: Laravel's default (`['api/*', 'sanctum/csrf-cookie']`) is fine, `['*']` is not.

## Doctor

```bash
php artisan predoza:doctor
php artisan predoza:doctor --handoff-url=https://www.example.com/predoza/handoff
```

The command checks your site key, the page origin from `APP_URL` (it must be `https`), asks Predoza to open a check
ticket (it signs nobody in) and compares your clock with Predoza's. With `--handoff-url` it also calls your handoff
route as a browser would: a guest must get `204` (a `419` means a CSRF check is in the way), a call without the
handoff header `403`, and a preflight from another origin no CORS headers. It exits with a non-zero code when a
check fails.

The checks are described in detail in the [core README](https://github.com/predoza/predoza-php#doctor).

## Requirements

- PHP 8.2 or later.
- Laravel 12 or 13.

## Testing

`predoza/predoza-php` ships `TestKeys`, `FakeFrame` and `TicketOpener` for testing your handoff route without
Predoza. See [Testing your integration](https://github.com/predoza/predoza-php#testing-your-integration).

## License

MIT. See [LICENSE](LICENSE).
