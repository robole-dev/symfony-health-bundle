<?php

declare(strict_types=1);

namespace Robole\SymfonyHealthBundle\Tests\Unit\Check;

use PHPUnit\Framework\TestCase;
use Robole\SymfonyHealthBundle\Health\Check\OpcacheHealthCheck;
use Robole\SymfonyHealthBundle\Health\Enum\HealthStatus;

final class OpcacheHealthCheckTest extends TestCase
{
    public function testOpcacheNeverExposesScriptPathsOrConfiguration(): void
    {
        $result = (new OpcacheHealthCheck())->check();
        self::assertContains($result->status, [HealthStatus::Healthy, HealthStatus::Warning, HealthStatus::Unsupported]);
        self::assertArrayNotHasKey('scripts', $result->metrics);
        self::assertArrayNotHasKey('configuration', $result->metrics);
        self::assertStringNotContainsString(__DIR__, json_encode($result->toArray(), JSON_THROW_ON_ERROR));
    }
}
