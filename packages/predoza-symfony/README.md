# predoza/predoza-symfony

Symfony bundle for Predoza host sign-in: the handoff route, a Twig function for embedded games and a doctor command.

[![Latest version](https://img.shields.io/packagist/v/predoza/predoza-symfony)](https://packagist.org/packages/predoza/predoza-symfony)
[![Downloads](https://img.shields.io/packagist/dt/predoza/predoza-symfony)](https://packagist.org/packages/predoza/predoza-symfony)
[![PHP](https://img.shields.io/packagist/php-v/predoza/predoza-symfony)](https://packagist.org/packages/predoza/predoza-symfony)
[![License](https://img.shields.io/packagist/l/predoza/predoza-symfony)](LICENSE)

Host sign-in lets your site sign its logged-in members in to embedded Predoza games, so they never log in twice.
This bundle answers the game's request with a ticket for the user of the current security token. It builds on
[`predoza/predoza-php`](https://github.com/predoza/predoza-php), whose README covers the member
rules and the test helpers.

> This repository is a read-only split of [predoza/sdk-php](https://github.com/predoza/sdk-php). Open issues and
> pull requests there.

## Contents

- [Install](#install)
- [Configure](#configure)
- [Import the route](#import-the-route)
- [Security](#security)
- [CORS](#cors)
- [Twig](#twig)
- [Doctor](#doctor)
- [Requirements](#requirements)
- [Testing](#testing)

## Install

```bash
composer require predoza/predoza-symfony
```

Register the bundle in `config/bundles.php`:

```php
<?php

declare(strict_types=1);

use Predoza\Symfony\PredozaBundle;

return [
    // ... your other bundles
    PredozaBundle::class => ['all' => true],
];
```

## Configure

Create a site key on the **Host sign-in** page of the Predoza client panel (turn host sign-in on, press **Create
site key**) and put the `PREDOZA_SITE_KEY` value in your server's environment, or in the secrets vault with
`bin/console secrets:set PREDOZA_SITE_KEY`. The value is shown once.

```yaml
# config/packages/predoza.yaml
predoza:
    site_key: '%env(PREDOZA_SITE_KEY)%'
    member_directory: App\Predoza\Members
    login_url: /login
    register_url: /register
```

| Option | Default | |
|---|---|---|
| `site_key` | required | The `PREDOZA_SITE_KEY` value. |
| `member_directory` | required | Service id of your `Predoza\HostSignIn\MemberDirectory`. |
| `login_url` | `null` | Where "Log in" in the game sends the member: a path, a URL or a route name (localized routes keep the locale). |
| `register_url` | `null` | Where "Create an account" in the game sends the member: a path, a URL or a route name. |
| `script_url` | `https://play.predoza.com/embed/v1.js` | The `embed.js` URL the Twig function prints. |
| `check_url` | `https://play.predoza.com/v1/host-sign-in/check` | Predoza's setup check, used by the doctor. |

Your `MemberDirectory` gets the user of the security token and returns a `Member`, or `null` for anyone who should
play as a guest (a disabled or banned account, a shared guest account):

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
        if (!$user instanceof User || $user->isBanned()) {
            return null;
        }

        return new Member(
            id: (string) $user->getId(),
            nickname: $user->getDisplayName(),
            locale: $user->getLocale(),
            confirmedEmail: $user->isVerified() ? $user->getEmail() : null,
        );
    }
}
```

With the default `config/services.yaml`, this class is a service named after itself, so
`member_directory: App\Predoza\Members` works as is. Pass the email only when your site has confirmed it, and use a
member id of 1 to 64 letters, digits, `_` or `-`. See
[the core README](https://github.com/predoza/predoza-php#tell-the-sdk-who-your-members-are) for every field.

## Import the route

```yaml
# config/routes/predoza.yaml
predoza:
    resource: '@PredozaBundle/config/routes.php'
```

This adds the route `predoza_handoff`: `POST /predoza/handoff`, handled by
`Predoza\Symfony\Controller\HandoffController`. The Twig function generates the handoff URL from the route name, so
a `prefix` on the import is picked up.

## Security

### Firewall

The controller signs in the user of the current security token. The route must therefore sit in the firewall that
holds the member's session:

- With a single `main` firewall and no `pattern`, there is nothing to do.
- If a firewall listed before it has a `pattern` that matches `/predoza/handoff` (a stateless API firewall, for
  example), the controller sees no user there and every member plays as a guest.
- Don't require a login for the path in `access_control`. A guest must get `204`, not a redirect to the login page.

Without `symfony/security-bundle`, there is no token and every request is answered as a guest.

### Impersonation

While staff are switched to another user with Symfony's `switch_user` (a `SwitchUserToken`), the handoff answers
`204`: staff never hand that member's identity to Predoza. If your app impersonates users some other way, return
`null` from your `MemberDirectory` while it does.

### Trusted proxies

The controller compares the `Origin` header with the request's scheme and host
(`Request::getSchemeAndHttpHost()`). Behind a TLS-terminating proxy or load balancer, configure trusted proxies so
that this is the origin the browser sees, or every member gets `403`:

```yaml
# config/packages/framework.yaml
framework:
    trusted_proxies: '%env(TRUSTED_PROXIES)%'
    trusted_headers: ['x-forwarded-for', 'x-forwarded-host', 'x-forwarded-proto', 'x-forwarded-port']
```

### CSRF

The route checks no CSRF token, and must not: `embed.js` sends none. The handoff header and the `Origin` and
`Sec-Fetch-Site` checks replace it. If your app checks CSRF tokens on every `POST`, exclude `predoza_handoff`.

## CORS

The handoff route must not answer other origins with CORS headers. With NelmioCorsBundle, add a path rule for it
whose `allow_origin` matches nothing, before any broader rule:

```yaml
# config/packages/nelmio_cors.yaml
nelmio_cors:
    paths:
        '^/predoza/handoff$':
            origin_regex: true
            allow_origin: ['^$']
        '^/api/':
            allow_origin: ['%env(CORS_ALLOW_ORIGIN)%']
            allow_methods: ['GET', 'POST']
```

NelmioCorsBundle uses the first rule whose path matches and fills the options a rule leaves out from `defaults`. An
empty `allow_origin: []` counts as left out, so it would fall back to `defaults`. If neither `defaults` nor any rule
covering the path allows an origin, there is nothing to do. `bin/console predoza:doctor --handoff-url=…` checks this.

## Twig

`predoza_game()` prints the game's element, and `embed.js` after the first game of the page:

```twig
{{ predoza_game('sf_CDSofyqv') }}

{{ predoza_game('sf_CDSofyqv', {lang: 'pl', title: 'Weekend predictor', theme: 'dark'}) }}
```

```html
<div data-predoza-feature="sf_CDSofyqv" data-lang="en"></div>
<script async src="https://play.predoza.com/embed/v1.js" data-auth-url="/predoza/handoff" data-login-url="/login" data-register-url="/register"></script>
```

| Option | Attribute | Default |
|---|---|---|
| `lang` | `data-lang` | The request's locale. |
| `title` | `data-title` | none |
| `theme` | `data-theme`: `light`, `dark` or `auto` | none |
| `class` | `class` on the game's element | none |

The script carries the handoff URL, `login_url`, `register_url` and `script_url` from your configuration. It is
printed once per request, however many games the page shows. The function needs `symfony/twig-bundle`.

## Doctor

```bash
bin/console predoza:doctor
bin/console predoza:doctor --handoff-url=https://www.example.com/predoza/handoff
```

The command checks your site key, asks Predoza to open a check ticket (it signs nobody in) and compares your clock
with Predoza's. With `--handoff-url` it also calls your handoff route as a browser would: a guest must get `204`, a
call without the handoff header `403`, and a preflight from another origin no CORS headers. It exits with a non-zero
code when a check fails.

The page origin check uses the router's request context. Set `framework.router.default_uri` (for example
`https://www.example.com`) to have it checked; with the default `localhost` it is skipped.

The checks are described in detail in the [core README](https://github.com/predoza/predoza-php#doctor).

## Requirements

- PHP 8.2 or later.
- Symfony 6.4, 7.4 or 8.x.
- `symfony/security-bundle` to sign members in. Without it, everyone plays as a guest.
- `symfony/twig-bundle` for `predoza_game()`.

## Testing

`predoza/predoza-php` ships `TestKeys`, `FakeFrame` and `TicketOpener` for testing your handoff route without
Predoza. See [Testing your integration](https://github.com/predoza/predoza-php#testing-your-integration).

## License

MIT. See [LICENSE](LICENSE).
