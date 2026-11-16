<?php

declare(strict_types=1);

namespace Predoza\Laravel\Console;

use Illuminate\Console\Command;
use Predoza\Doctor\Doctor;
use Predoza\HostSignIn\HostSignIn;

class DoctorCommand extends Command
{
    protected $signature = 'predoza:doctor {--handoff-url= : Your handoff route\'s full URL, to call it as a browser would}';

    protected $description = 'Checks the Predoza host sign-in setup';

    public function handle(HostSignIn $hostSignIn): int
    {
        $appUrl = config('app.url');
        $parts = is_string($appUrl) ? parse_url($appUrl) : false;
        $pageOrigin = is_array($parts) && isset($parts['scheme'], $parts['host']) ? $parts['scheme'] . '://' . $parts['host'] . (isset($parts['port']) ? ':' . $parts['port'] : '') : null;
        $handoffUrl = $this->option('handoff-url');
        $report = (new Doctor($hostSignIn))->run($pageOrigin, is_string($handoffUrl) ? $handoffUrl : null);

        $this->line(implode("\n", ['Predoza host sign-in · predoza/predoza-laravel', '', ...$report->lines(), '', $report->summary()]));

        return $report->passed() ? self::SUCCESS : self::FAILURE;
    }
}
