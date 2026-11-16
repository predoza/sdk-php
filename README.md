# Predoza PHP SDK

Sign your site's logged-in members in to embedded Predoza games from PHP, Symfony or Laravel.

[![CI](https://img.shields.io/github/actions/workflow/status/predoza/sdk-php/ci.yml?branch=main&label=CI)](https://github.com/predoza/sdk-php/actions/workflows/ci.yml)
[![PHP](https://img.shields.io/badge/php-%5E8.2-777bb4)](https://www.php.net/supported-versions.php)
[![License](https://img.shields.io/github/license/predoza/sdk-php)](LICENSE)

[Predoza](https://play.predoza.com) games embed on your pages with `embed.js`. **Host sign-in** lets members who are
logged in to your site play signed in, without logging in twice. This SDK gives your site the route the game asks,
the embed snippet, a setup check (the doctor) and helpers for your own tests.

This is the development repository. Install one of the packages below, not this repository.

## Contents

- [Packages](#packages)
- [Getting started](#getting-started)
- [Development](#development)
- [Security](#security)
- [License](#license)

## Packages

| Package | For | Repository | Packagist |
|---|---|---|---|
| [`predoza/predoza-php`](packages/predoza-php) | Plain PHP, PSR-15 apps and any other framework | [predoza/predoza-php](https://github.com/predoza/predoza-php) | [![Packagist](https://img.shields.io/packagist/v/predoza/predoza-php)](https://packagist.org/packages/predoza/predoza-php) |
| [`predoza/predoza-symfony`](packages/predoza-symfony) | Symfony 6.4, 7.4 and 8.x | [predoza/predoza-symfony](https://github.com/predoza/predoza-symfony) | [![Packagist](https://img.shields.io/packagist/v/predoza/predoza-symfony)](https://packagist.org/packages/predoza/predoza-symfony) |
| [`predoza/predoza-laravel`](packages/predoza-laravel) | Laravel 12 and 13 | [predoza/predoza-laravel](https://github.com/predoza/predoza-laravel) | [![Packagist](https://img.shields.io/packagist/v/predoza/predoza-laravel)](https://packagist.org/packages/predoza/predoza-laravel) |

On Symfony or Laravel, install the framework package. Both depend on `predoza/predoza-php`. Each package has its own
README with setup steps.

## Getting started

1. Open the **Host sign-in** page of the Predoza client panel, turn host sign-in on and press **Create site key**.
   Copy the `PREDOZA_SITE_KEY` value into your server's environment: it is shown only once.
2. Install the package for your framework and follow its README: tell it who your members are, register the route
   and add the game to your page.
3. Run the doctor on your server to check the setup.

## Development

You need PHP 8.2 or later and Composer.

```bash
composer install
composer test       # PHPUnit for all three packages
composer analyse    # PHPStan at level max
```

The root `composer.json` autoloads all three packages from `packages/*/src`, so a change to the core is tested
against both framework packages in one run. To run one package's tests, pick its suite (`predoza-php`,
`predoza-symfony` or `predoza-laravel`):

```bash
vendor/bin/phpunit --testsuite predoza-laravel
```

Each package keeps its own `composer.json`, which is the one its split repository publishes. When you add a
dependency to a package, add it there and to the root `composer.json`.

Issues and pull requests are welcome here. The package repositories are read-only copies of `packages/*`, updated
from this repository; don't open pull requests there.

## Security

Don't open a public issue for a security problem. Email [dev@predoza.com](mailto:dev@predoza.com) with a description
and the steps to reproduce it.

## License

MIT. See [LICENSE](LICENSE).
