<?php

declare(strict_types=1);

namespace Robole\SymfonyHealthBundle\Health\Check;

use Robole\SymfonyHealthBundle\Health\Enum\HealthStatus;
use Robole\SymfonyHealthBundle\Health\HealthCheckInterface;
use Robole\SymfonyHealthBundle\Health\Result\HealthCheckResult;

final readonly class StorageHealthCheck implements HealthCheckInterface
{
    public function __construct(
        private string $cacheDirectory,
        private string $logDirectory,
        private bool $includeSpace = false,
    ) {
    }

    public function getName(): string
    {
        return 'storage';
    }

    public function check(): HealthCheckResult
    {
        $components = [
            'cache' => $this->directory($this->cacheDirectory),
            'logs' => $this->directory($this->logDirectory),
        ];

        return new HealthCheckResult(HealthStatus::aggregate(array_map(static fn (HealthCheckResult $result): HealthStatus => $result->status, $components)), components: $components);
    }

    private function directory(string $directory): HealthCheckResult
    {
        clearstatcache(true, $directory);
        $exists = @is_dir($directory);
        $writable = $exists && @is_writable($directory);
        $metrics = ['exists' => $exists, 'writable' => $writable];
        if (!$exists || !$writable) {
            return new HealthCheckResult(HealthStatus::Critical, code: 'directory_not_writable', metrics: $metrics);
        }
        if ($this->includeSpace) {
            $free = function_exists('disk_free_space') ? @disk_free_space($directory) : false;
            $total = function_exists('disk_total_space') ? @disk_total_space($directory) : false;
            $metrics['free_bytes'] = false !== $free && is_finite($free) && $free >= 0 ? $free : null;
            $metrics['total_bytes'] = false !== $total && is_finite($total) && $total > 0 ? $total : null;
            if (null === $metrics['free_bytes'] || null === $metrics['total_bytes']) {
                return new HealthCheckResult(HealthStatus::Unknown, code: 'space_information_unavailable', metrics: $metrics);
            }
        }

        return new HealthCheckResult(HealthStatus::Healthy, code: 'directory_writable', metrics: $metrics);
    }
}
