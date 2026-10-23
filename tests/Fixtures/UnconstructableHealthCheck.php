<?php

declare(strict_types=1);

namespace Robole\SymfonyHealthBundle\Tests\Fixtures;

use Robole\SymfonyHealthBundle\Health\HealthCheckInterface;
use Robole\SymfonyHealthBundle\Health\Result\HealthCheckResult;

final class UnconstructableHealthCheck implements HealthCheckInterface
{
    public function __construct()
    {
        throw new \RuntimeException('password=secret /private/path');
    }

    public function getName(): string
    {
        return 'unconstructable';
    }

    public function check(): HealthCheckResult
    {
        throw new \LogicException('An unconstructable service cannot run.');
    }
}
