<?php

declare(strict_types=1);

namespace Robole\SymfonyHealthBundle\Tests\Fixtures;

use Robole\SymfonyHealthBundle\Health\Enum\HealthStatus;
use Robole\SymfonyHealthBundle\Health\HealthCheckInterface;
use Robole\SymfonyHealthBundle\Health\Result\HealthCheckResult;

final class StubHealthCheck implements HealthCheckInterface
{
    public int $calls = 0;

    public function __construct(
        private readonly string $name = 'custom',
        private readonly HealthStatus $status = HealthStatus::Healthy,
        private readonly bool $throws = false,
    ) {
    }

    public function getName(): string
    {
        return $this->name;
    }

    public function check(): HealthCheckResult
    {
        ++$this->calls;
        if ($this->throws) {
            throw new \RuntimeException('mysql://user:password@private.example/private?token=secret');
        }

        return new HealthCheckResult($this->status);
    }
}
