<?php

declare(strict_types=1);

namespace Predoza\Symfony\Command;

use Predoza\Doctor\Doctor;
use Predoza\HostSignIn\HostSignIn;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Routing\RequestContext;

#[AsCommand(name: 'predoza:doctor', description: 'Checks the Predoza host sign-in setup')]
class DoctorCommand extends Command
{
    public function __construct(
        private HostSignIn $hostSignIn,
        private RequestContext $requestContext,
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this->addOption('handoff-url', null, InputOption::VALUE_REQUIRED, 'Your handoff route\'s full URL, to call it as a browser would');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $host = $this->requestContext->getHost();
        $scheme = $this->requestContext->getScheme();
        $port = $scheme === 'https' ? $this->requestContext->getHttpsPort() : $this->requestContext->getHttpPort();
        $defaultPort = $scheme === 'https' ? 443 : 80;
        // without framework.router.default_uri the context is localhost, and the page origin check is skipped
        $pageOrigin = $host === '' || $host === 'localhost' ? null : $scheme . '://' . $host . ($port !== $defaultPort ? ':' . $port : '');
        $handoffUrl = $input->getOption('handoff-url');
        $report = (new Doctor($this->hostSignIn))->run($pageOrigin, is_string($handoffUrl) ? $handoffUrl : null);

        $output->writeln(['Predoza host sign-in · predoza/predoza-symfony', '', ...$report->lines(), '', $report->summary()]);

        return $report->passed() ? Command::SUCCESS : Command::FAILURE;
    }
}
