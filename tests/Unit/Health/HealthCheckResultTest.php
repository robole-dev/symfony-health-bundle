<?php

declare(strict_types=1);

namespace Robole\SymfonyHealthBundle\Tests\Unit\Health;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Robole\SymfonyHealthBundle\Health\Enum\HealthStatus;
use Robole\SymfonyHealthBundle\Health\Result\HealthCheckResult;

final class HealthCheckResultTest extends TestCase
{
    public function testResultIsImmutableAndSerializesSafeFields(): void
    {
        $result = new HealthCheckResult(HealthStatus::Healthy, code: 'ready', metrics: ['count' => 12]);
        $timed = $result->withDuration(1.23456);
        self::assertNull($result->durationMs);
        self::assertSame(['status' => 'healthy', 'duration_ms' => 1.235, 'code' => 'ready', 'metrics' => ['count' => 12]], $timed->toArray());
    }

    #[DataProvider('invalidDurations')]
    public function testInvalidDurationsAreRejected(float $duration): void
    {
        $this->expectException(\InvalidArgumentException::class);
        new HealthCheckResult(HealthStatus::Healthy, $duration);
    }

    /** @return iterable<string, array{float}> */
    public static function invalidDurations(): iterable
    {
        yield 'negative' => [-1.0];
        yield 'infinite' => [INF];
        yield 'not a number' => [NAN];
    }

    public function testRawExceptionMessagesCannotBeUsedAsCodes(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        new HealthCheckResult(HealthStatus::Critical, code: 'Connection failed: password=secret');
    }

    public function testMetricsCannotContainText(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        // Deliberately cross the public PHP boundary with data PHPDoc cannot protect at runtime.
        (new \ReflectionClass(HealthCheckResult::class))->newInstanceArgs(['status' => HealthStatus::Critical, 'metrics' => ['error' => 'password=secret']]);
    }

    public function testNestedResultsSerialize(): void
    {
        $result = new HealthCheckResult(HealthStatus::Unsupported, components: ['async' => new HealthCheckResult(HealthStatus::Unsupported)]);
        self::assertSame(['status' => 'unsupported', 'components' => ['async' => ['status' => 'unsupported']]], $result->toArray());
    }
}
