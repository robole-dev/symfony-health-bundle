<?php

declare(strict_types=1);

namespace Robole\SymfonyHealthBundle\Tests\Unit\Health;

use PHPUnit\Framework\TestCase;
use Robole\SymfonyHealthBundle\Health\Enum\HealthStatus;
use Robole\SymfonyHealthBundle\Health\HealthCheckInterface;
use Robole\SymfonyHealthBundle\Health\Registry\HealthCheckRegistry;
use Robole\SymfonyHealthBundle\Health\Result\HealthCheckResult;
use Robole\SymfonyHealthBundle\Health\Runner\HealthCheckRunner;
use Robole\SymfonyHealthBundle\Tests\Fixtures\StubHealthCheck;

final class HealthCheckRunnerTest extends TestCase
{
    public function testFailureIsSanitizedAndOtherChecksStillRun(): void
    {
        $good = new StubHealthCheck('good');
        $bad = new StubHealthCheck('bad', throws: true);
        $results = (new HealthCheckRunner(new HealthCheckRegistry([$bad, $good])))->run();
        self::assertSame(HealthStatus::Critical, $results['bad']->status);
        self::assertSame('check_failed', $results['bad']->code);
        self::assertSame(HealthStatus::Healthy, $results['good']->status);
        self::assertSame(1, $good->calls);
        self::assertGreaterThanOrEqual(0, $results['bad']->durationMs);
        self::assertStringNotContainsString('password', json_encode(array_map(static fn (HealthCheckResult $r): array => $r->toArray(), $results), JSON_THROW_ON_ERROR));
    }

    public function testExcessDurationIsReportedAfterTheCheckReturns(): void
    {
        $check = new class implements HealthCheckInterface {
            public function getName(): string
            {
                return 'slow';
            }

            public function check(): HealthCheckResult
            {
                usleep(5000);

                return new HealthCheckResult(HealthStatus::Healthy);
            }
        };
        $result = (new HealthCheckRunner(new HealthCheckRegistry([$check]), ['slow' => 1]))->run()['slow'];
        self::assertSame(HealthStatus::Warning, $result->status);
        self::assertSame('duration_threshold_exceeded', $result->code);
    }

    public function testCriticalStatusIsNotDowngradedByDurationThreshold(): void
    {
        $check = new StubHealthCheck('critical', HealthStatus::Critical);
        self::assertSame(HealthStatus::Critical, (new HealthCheckRunner(new HealthCheckRegistry([$check]), ['critical' => 1]))->run()['critical']->status);
    }

    public function testServiceConstructionFailureDoesNotBlockOtherChecks(): void
    {
        $good = new StubHealthCheck('good');
        $registry = new HealthCheckRegistry([
            static fn (): never => throw new \RuntimeException('password=secret'),
            static fn (): StubHealthCheck => $good,
        ]);
        $results = (new HealthCheckRunner($registry))->run();
        self::assertSame(HealthStatus::Critical, $results['registration_failure_0']->status);
        self::assertSame(HealthStatus::Healthy, $results['good']->status);
        self::assertSame(1, $good->calls);
    }
}
