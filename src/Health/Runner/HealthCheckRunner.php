<?php

declare(strict_types=1);

namespace Robole\SymfonyHealthBundle\Health\Runner;

use Robole\SymfonyHealthBundle\Health\Enum\HealthStatus;
use Robole\SymfonyHealthBundle\Health\Registry\HealthCheckRegistry;
use Robole\SymfonyHealthBundle\Health\Result\HealthCheckResult;

final readonly class HealthCheckRunner
{
    /** @param array<string, int> $warningThresholdsMs */
    public function __construct(
        private HealthCheckRegistry $registry,
        private array $warningThresholdsMs = [],
    ) {
    }

    /** @return array<string, HealthCheckResult> */
    public function run(): array
    {
        $results = [];
        $failureIndex = 0;
        foreach ($this->registry->resolvers() as $resolve) {
            $started = hrtime(true);
            try {
                $check = $resolve();
                $name = $check->getName();
                HealthCheckResult::validateName($name);
                if (isset($results[$name])) {
                    throw new \LogicException('Duplicate health check name.');
                }
            } catch (\Throwable) {
                do {
                    $name = 'registration_failure_'.$failureIndex++;
                } while (isset($results[$name]));
                $results[$name] = new HealthCheckResult(HealthStatus::Critical, (hrtime(true) - $started) / 1_000_000, 'check_initialization_failed');
                continue;
            }
            try {
                $result = $check->check();
            } catch (\Throwable) {
                $result = new HealthCheckResult(HealthStatus::Critical, code: 'check_failed');
            }
            $duration = (hrtime(true) - $started) / 1_000_000;
            $threshold = $this->warningThresholdsMs[$name] ?? 0;
            if ($threshold > 0 && $duration > $threshold && HealthStatus::Healthy === $result->status) {
                $result = new HealthCheckResult(HealthStatus::Warning, code: 'duration_threshold_exceeded', metrics: $result->metrics, components: $result->components);
            }
            // A check-specific duration (e.g. the database query) is more precise than the total runtime.
            $results[$name] = null === $result->durationMs ? $result->withDuration($duration) : $result;
        }

        return $results;
    }
}
