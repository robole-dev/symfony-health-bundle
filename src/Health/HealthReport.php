<?php

declare(strict_types=1);

namespace Robole\SymfonyHealthBundle\Health;

use Robole\SymfonyHealthBundle\Health\Enum\HealthStatus;
use Robole\SymfonyHealthBundle\Health\Result\HealthCheckResult;

final readonly class HealthReport
{
    public const string SCHEMA_VERSION = '1.0';

    /**
     * @param array<string, mixed>             $application
     * @param array<string, mixed>             $system
     * @param array<string, mixed>|null        $dependencies
     * @param array<string, HealthCheckResult> $checks
     */
    public function __construct(
        public string $timestamp,
        public array $application,
        public array $system,
        public ?array $dependencies,
        public array $checks,
    ) {
    }

    public function getStatus(): HealthStatus
    {
        return HealthStatus::aggregate(array_map(static fn (HealthCheckResult $result): HealthStatus => $result->status, $this->checks));
    }

    /** @return array<string, mixed> */
    public function toArray(): array
    {
        return [
            'schema_version' => self::SCHEMA_VERSION,
            'timestamp' => $this->timestamp,
            'status' => $this->getStatus()->value,
            'application' => $this->application,
            'system' => $this->system,
            'dependencies' => $this->dependencies,
            'checks' => (object) array_map(static fn (HealthCheckResult $result): array => $result->toArray(), $this->checks),
        ];
    }
}
