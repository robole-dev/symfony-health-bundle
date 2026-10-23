<?php

declare(strict_types=1);

namespace Robole\SymfonyHealthBundle\Health;

use Robole\SymfonyHealthBundle\Health\Result\HealthCheckResult;

interface HealthCheckInterface
{
    public function getName(): string;

    public function check(): HealthCheckResult;
}
