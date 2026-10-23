<?php

declare(strict_types=1);

namespace Robole\SymfonyHealthBundle\Health;

use Robole\SymfonyHealthBundle\Health\Runner\HealthCheckRunner;
use Robole\SymfonyHealthBundle\Information\Application\ApplicationInformationProvider;
use Robole\SymfonyHealthBundle\Information\Composer\ComposerDependencyProvider;
use Robole\SymfonyHealthBundle\Information\System\SystemInformationProvider;

final readonly class HealthReportFactory
{
    public function __construct(
        private HealthCheckRunner $runner,
        private ApplicationInformationProvider $application,
        private SystemInformationProvider $system,
        private ComposerDependencyProvider $dependencies,
        private bool $dependenciesEnabled = true,
    ) {
    }

    public function create(): HealthReport
    {
        return new HealthReport(
            gmdate('Y-m-d\TH:i:s\Z'),
            $this->application->getInformation(),
            $this->system->getInformation(),
            $this->dependenciesEnabled ? $this->dependencies->getInformation()->summary() : null,
            $this->runner->run(),
        );
    }
}
