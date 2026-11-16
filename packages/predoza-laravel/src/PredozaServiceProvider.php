<?php

declare(strict_types=1);

namespace Predoza\Laravel;

use Illuminate\Contracts\Foundation\Application;
use Illuminate\Support\Facades\Blade;
use Illuminate\Support\ServiceProvider;
use Predoza\Embed\EmbedSnippet;
use Predoza\HostSignIn\HostSignIn;
use Predoza\HostSignIn\MemberDirectory;
use Predoza\Laravel\Console\DoctorCommand;
use Predoza\Laravel\View\Game;
use RuntimeException;

class PredozaServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->mergeConfigFrom(dirname(__DIR__) . '/config/predoza.php', 'predoza');

        $this->app->singleton(HostSignIn::class, function (Application $app): HostSignIn {
            $siteKey = config('predoza.site_key');
            $members = config('predoza.members');

            if (!is_string($siteKey) || $siteKey === '') {
                throw new RuntimeException('Set PREDOZA_SITE_KEY: create a site key on the Host sign-in page of the Predoza client panel');
            }
            if (!is_string($members) || !is_a($members, MemberDirectory::class, true)) {
                throw new RuntimeException('Set predoza.members to your class implementing ' . MemberDirectory::class);
            }

            return HostSignIn::create($siteKey, $app->make($members));
        });

        // one per request: embed.js is printed with the first game of a page only
        $this->app->scoped(EmbedSnippet::class, function (): EmbedSnippet {
            $handoffPath = config('predoza.handoff_path');
            $loginUrl = config('predoza.login_url');
            $registerUrl = config('predoza.register_url');

            return new EmbedSnippet(
                is_string($handoffPath) ? $handoffPath : null,
                is_string($loginUrl) ? $loginUrl : null,
                is_string($registerUrl) ? $registerUrl : null,
            );
        });
    }

    public function boot(): void
    {
        $this->publishes([dirname(__DIR__) . '/config/predoza.php' => config_path('predoza.php')], 'predoza-config');

        if (is_string(config('predoza.handoff_path'))) {
            $this->loadRoutesFrom(dirname(__DIR__) . '/routes/predoza.php');
        }

        Blade::component('predoza-game', Game::class);

        if ($this->app->runningInConsole()) {
            $this->commands([DoctorCommand::class]);
        }
    }
}
