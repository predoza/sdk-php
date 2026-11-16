# Predoza PHP SDK

Sign your site's logged-in members in to embedded Predoza games from PHP, Symfony or Laravel.

[![CI](https://img.shields.io/github/actions/workflow/status/predoza/sdk-php/ci.yml?branch=main&label=CI)](https://github.com/predoza/sdk-php/actions/workflows/ci.yml)
[![PHP](https://img.shields.io/badge/php-%5E8.2-777bb4)](https://www.php.net/supported-versions.php)
[![License](https://img.shields.io/github/license/predoza/sdk-php)](LICENSE)

Members logged in to your site play embedded [Predoza](https://play.predoza.com) games signed in, without logging in
twice. Install the package for your framework; this repository is where all three are developed.

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

## Getting started

1. On the **Host sign-in** page of the Predoza client panel, turn host sign-in on and press **Create site key**.
   Copy the value into your server's environment as `PREDOZA_SITE_KEY`; it is shown only once.
2. Install the package for your framework and follow its README.
3. Run the package's doctor on your server to check the setup.

## Development

You need PHP 8.2 or later and Composer.

```bash
composer install
composer test       # PHPUnit for all three packages
composer analyse    # PHPStan at level max
```

Issues and pull requests are welcome here. The package repositories are read-only copies of `packages/*`.

## Security

Don't open a public issue for a security problem. Email [dev@predoza.com](mailto:dev@predoza.com) with a description
and the steps to reproduce it.

## License

MIT. See [LICENSE](LICENSE).
