<?php

declare(strict_types=1);

namespace Robole\SymfonyHealthBundle\Tests\Unit\Check;

use Doctrine\DBAL\Connection;
use Doctrine\DBAL\DriverManager;
use Doctrine\DBAL\Platforms\AbstractPlatform;
use PHPUnit\Framework\TestCase;
use Robole\SymfonyHealthBundle\Health\Check\DatabaseHealthCheck;
use Robole\SymfonyHealthBundle\Health\Enum\HealthStatus;

final class DatabaseHealthCheckTest extends TestCase
{
    public function testNoConnectionIsUnsupported(): void
    {
        self::assertSame(HealthStatus::Unsupported, (new DatabaseHealthCheck())->check()->status);
    }

    public function testDatabaseProbeUsesThePlatformAndMeasuresQueryDuration(): void
    {
        $connection = $this->createMock(Connection::class);
        $platform = $this->createStub(AbstractPlatform::class);
        $platform->method('getDummySelectSQL')->willReturn('SELECT 1');
        $connection->method('getDatabasePlatform')->willReturn($platform);
        $connection->expects(self::once())->method('fetchOne')->with('SELECT 1')->willReturn(1);
        $result = (new DatabaseHealthCheck($connection))->check();
        self::assertSame(HealthStatus::Healthy, $result->status);
        self::assertGreaterThanOrEqual(0, $result->durationMs);
    }

    public function testFailureDoesNotExposeCredentials(): void
    {
        $connection = $this->createStub(Connection::class);
        $connection->method('getDatabasePlatform')->willThrowException(new \RuntimeException('password=secret /srv/private'));
        $result = (new DatabaseHealthCheck($connection))->check();
        self::assertSame(HealthStatus::Critical, $result->status);
        self::assertStringNotContainsString('secret', json_encode($result->toArray(), JSON_THROW_ON_ERROR));
    }

    public function testRealInMemoryConnectionRequiresNoApplicationTables(): void
    {
        $connection = DriverManager::getConnection(['driver' => 'pdo_sqlite', 'memory' => true]);
        self::assertSame(HealthStatus::Healthy, (new DatabaseHealthCheck($connection))->check()->status);
        self::assertSame([], $connection->createSchemaManager()->listTableNames());
        $connection->close();
    }
}
