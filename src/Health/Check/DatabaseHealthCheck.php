<?php

declare(strict_types=1);

namespace Robole\SymfonyHealthBundle\Health\Check;

use Doctrine\DBAL\Connection;
use Robole\SymfonyHealthBundle\Health\Enum\HealthStatus;
use Robole\SymfonyHealthBundle\Health\HealthCheckInterface;
use Robole\SymfonyHealthBundle\Health\Result\HealthCheckResult;

final readonly class DatabaseHealthCheck implements HealthCheckInterface
{
    public function __construct(private ?Connection $connection = null)
    {
    }

    public function getName(): string
    {
        return 'database';
    }

    public function check(): HealthCheckResult
    {
        if (null === $this->connection) {
            return new HealthCheckResult(HealthStatus::Unsupported, code: 'database_not_configured');
        }
        $started = hrtime(true);
        try {
            // The platform supplies e.g. SELECT 1 FROM DUAL on Oracle. No application tables are read.
            $value = $this->connection->fetchOne($this->connection->getDatabasePlatform()->getDummySelectSQL());
            $reachable = 1 === $value || '1' === $value;

            return new HealthCheckResult(
                $reachable ? HealthStatus::Healthy : HealthStatus::Critical,
                (hrtime(true) - $started) / 1_000_000,
                $reachable ? 'database_reachable' : 'database_unexpected_result',
            );
        } catch (\Throwable) {
            return new HealthCheckResult(HealthStatus::Critical, (hrtime(true) - $started) / 1_000_000, 'database_unreachable');
        }
    }
}
