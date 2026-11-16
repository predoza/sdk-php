<?php

declare(strict_types=1);

namespace Predoza\Symfony;

use Predoza\Embed\EmbedSnippet;
use Predoza\HostSignIn\HostSignIn;
use Predoza\Symfony\Command\DoctorCommand;
use Predoza\Symfony\Controller\HandoffController;
use Predoza\Symfony\Twig\PredozaExtension;
use Symfony\Component\Config\Definition\Configurator\DefinitionConfigurator;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Loader\Configurator\ContainerConfigurator;
use Symfony\Component\HttpKernel\Bundle\AbstractBundle;
use Twig\Extension\AbstractExtension;

use function Symfony\Component\DependencyInjection\Loader\Configurator\service;

/**
 * predoza:
 *     site_key: '%env(PREDOZA_SITE_KEY)%'
 *     member_directory: App\Predoza\Members      # your Predoza\HostSignIn\MemberDirectory service
 *     login_url: /login
 *     register_url: /register
 */
class PredozaBundle extends AbstractBundle
{
    public function configure(DefinitionConfigurator $definition): void
    {
        $definition->rootNode()
            ->children()
                ->scalarNode('site_key')->isRequired()->cannotBeEmpty()->info('PREDOZA_SITE_KEY from your Host sign-in page')->end()
                ->scalarNode('member_directory')->isRequired()->cannotBeEmpty()->info('Service id of your MemberDirectory')->end()
                ->scalarNode('login_url')->defaultNull()->info('Where "Log in" in the game sends the member')->end()
                ->scalarNode('register_url')->defaultNull()->info('Where "Create an account" in the game sends the member')->end()
                ->scalarNode('script_url')->defaultValue(EmbedSnippet::SCRIPT_URL)->end()
                ->scalarNode('check_url')->defaultValue(HostSignIn::CHECK_URL)->end()
            ->end();
    }

    /**
     * @param array{site_key: string, member_directory: string, login_url: ?string, register_url: ?string, script_url: string, check_url: string} $config
     */
    public function loadExtension(array $config, ContainerConfigurator $container, ContainerBuilder $builder): void
    {
        $services = $container->services();

        $services->set(HostSignIn::class)
            ->factory([HostSignIn::class, 'create'])
            ->args([$config['site_key'], service($config['member_directory']), null, $config['check_url']]);

        $services->set(HandoffController::class)
            ->args([service(HostSignIn::class), service('security.token_storage')->nullOnInvalid()])
            ->tag('controller.service_arguments')
            ->public();

        $services->set(DoctorCommand::class)
            ->args([service(HostSignIn::class), service('router.request_context')])
            ->tag('console.command');

        if (class_exists(AbstractExtension::class)) {
            $services->set(PredozaExtension::class)
                ->args([service('request_stack'), service('router'), $config['script_url'], $config['login_url'], $config['register_url']])
                ->tag('twig.extension');
        }
    }

    public function getPath(): string
    {
        return dirname(__DIR__);
    }
}
