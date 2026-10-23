<?php

declare(strict_types=1);

namespace Robole\SymfonyHealthBundle\Tests\Unit\Health;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Robole\SymfonyHealthBundle\Health\Enum\HealthStatus;

final class HealthStatusTest extends TestCase
{
    /** @param list<HealthStatus> $statuses */
    #[DataProvider('aggregations')]
    public function testAggregation(array $statuses, HealthStatus $expected): void
    {
        self::assertSame($expected, HealthStatus::aggregate($statuses));
        self::assertSame($expected, HealthStatus::aggregate(array_reverse($statuses)));
    }

    /** @return iterable<string, array{list<HealthStatus>, HealthStatus}> */
    public static function aggregations(): iterable
    {
        yield 'no checks' => [[], HealthStatus::Unknown];
        yield 'all unsupported' => [[HealthStatus::Unsupported], HealthStatus::Unsupported];
        yield 'unsupported does not downgrade health' => [[HealthStatus::Unsupported, HealthStatus::Healthy], HealthStatus::Healthy];
        yield 'unknown is not healthy' => [[HealthStatus::Healthy, HealthStatus::Unknown], HealthStatus::Unknown];
        yield 'warning' => [[HealthStatus::Warning, HealthStatus::Unknown], HealthStatus::Warning];
        yield 'critical' => [[HealthStatus::Critical, HealthStatus::Warning, HealthStatus::Unknown], HealthStatus::Critical];
    }
}
