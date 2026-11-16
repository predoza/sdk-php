<?php

declare(strict_types=1);

namespace Predoza\Symfony\Tests\App;

use Predoza\Symfony\PredozaBundle;
use Psr\Log\NullLogger;
use Symfony\Bundle\FrameworkBundle\FrameworkBundle;
use Symfony\Bundle\FrameworkBundle\Kernel\MicroKernelTrait;
use Symfony\Bundle\SecurityBundle\SecurityBundle;
use Symfony\Bundle\TwigBundle\TwigBundle;
use Symfony\Component\DependencyInjection\Loader\Configurator\ContainerConfigurator;
use Symfony\Component\HttpKernel\Kernel;
use Symfony\Component\Routing\Loader\Configurator\RoutingConfigurator;

final class TestKernel extends Kernel
{
    use MicroKernelTrait;

    public static string $siteKey = '';

    public function registerBundles(): iterable
    {
        return [new FrameworkBundle(), new SecurityBundle(), new TwigBundle(), new PredozaBundle()];
    }

    public function getProjectDir(): string
    {
        return __DIR__;
    }

    public function getCacheDir(): string
    {
        return sys_get_temp_dir() . '/predoza-symfony-tests/' . md5(self::$siteKey) . '/cache';
    }

    public function getLogDir(): string
    {
        return sys_get_temp_dir() . '/predoza-symfony-tests/logs';
    }

    protected function configureContainer(ContainerConfigurator $container): void
    {
        $container->extension('framework', ['test' => true, 'secret' => 'test', 'session' => ['storage_factory_id' => 'session.storage.factory.mock_file'], 'router' => ['utf8' => true]]);
        $container->extension('security', [
            'providers' => ['users' => ['memory' => ['users' => ['catenaccio' => ['password' => 'x', 'roles' => ['ROLE_USER']]]]]],
            'firewalls' => ['main' => ['lazy' => true, 'provider' => 'users', 'switch_user' => true]],
        ]);
        $container->extension('twig', ['default_path' => __DIR__ . '/templates']);
        $container->extension('predoza', ['site_key' => self::$siteKey, 'member_directory' => Members::class, 'login_url' => 'login', 'register_url' => '/register']);
        $container->services()->set(Members::class);
        $container->services()->set('logger', NullLogger::class);
        $container->services()->set(GameController::class)->autowire()->tag('controller.service_arguments')->public();
    }

    protected function configureRoutes(RoutingConfigurator $routes): void
    {
        $routes->import('@PredozaBundle/config/routes.php');
        $routes->add('game', '/game')->controller(GameController::class);
        $routes->add('login', '/login')->controller(GameController::class);
    }
}
