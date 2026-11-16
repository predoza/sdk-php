<?php

declare(strict_types=1);

namespace Predoza\Laravel\Tests;

use Orchestra\Testbench\TestCase as Orchestra;
use Predoza\Laravel\Predoza;
use Predoza\Laravel\PredozaServiceProvider;
use Predoza\Testing\TestKeys;

abstract class TestCase extends Orchestra
{
    protected TestKeys $keys;

    protected function setUp(): void
    {
        $this->keys = TestKeys::create();

        parent::setUp();
    }

    protected function tearDown(): void
    {
        Predoza::forgetRules();

        parent::tearDown();
    }

    protected function getPackageProviders($app): array
    {
        return [PredozaServiceProvider::class];
    }

    protected function defineEnvironment($app): void
    {
        $app['config']->set('predoza.site_key', $this->keys->envValue);
        $app['config']->set('predoza.members', Members::class);
        $app['config']->set('app.url', 'https://www.example.com');
        $app['config']->set('app.key', 'base64:'.base64_encode(str_repeat('k', 32)));
    }
}
