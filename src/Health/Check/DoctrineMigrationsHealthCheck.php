<?php

declare(strict_types=1);

namespace Robole\SymfonyHealthBundle\Health\Check;

use Doctrine\Migrations\DependencyFactory;
use Robole\SymfonyHealthBundle\Health\Enum\HealthStatus;
use Robole\SymfonyHealthBundle\Health\HealthCheckInterface;
use Robole\SymfonyHealthBundle\Health\Result\HealthCheckResult;

final readonly class DoctrineMigrationsHealthCheck implements HealthCheckInterface
{
    public function __construct(
        private ?DependencyFactory $dependencyFactory = null,
        private HealthStatus $pendingStatus = HealthStatus::Warning,
    ) {
    }

    public function getName(): string
    {
        return 'doctrine_migrations';
    }

    public function check(): HealthCheckResult
    {
        if (null === $this->dependencyFactory) {
            return new HealthCheckResult(HealthStatus::Unsupported, code: 'migrations_not_configured');
        }
        try {
            // Only read metadata. Never initialize the metadata table, build an execution plan or migrate.
            $count = count($this->dependencyFactory->getMigrationStatusCalculator()->getNewMigrations());

            return new HealthCheckResult(0 === $count ? HealthStatus::Healthy : $this->pendingStatus, metrics: ['pending_migrations' => $count]);
        } catch (\Throwable) {
            return new HealthCheckResult(HealthStatus::Unknown, code: 'migration_status_unavailable');
        }
    }
}
