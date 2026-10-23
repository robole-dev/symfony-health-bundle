<?php

declare(strict_types=1);

namespace Robole\SymfonyHealthBundle\Tests\Unit\Check;

use Doctrine\DBAL\DriverManager;
use Doctrine\Migrations\Configuration\Configuration;
use Doctrine\Migrations\Configuration\Connection\ExistingConnection;
use Doctrine\Migrations\Configuration\Migration\ExistingConfiguration;
use Doctrine\Migrations\DependencyFactory;
use Doctrine\Migrations\Version\MigrationStatusCalculator;
use PHPUnit\Framework\TestCase;
use Robole\SymfonyHealthBundle\Health\Check\DoctrineMigrationsHealthCheck;
use Robole\SymfonyHealthBundle\Health\Enum\HealthStatus;
use Robole\SymfonyHealthBundle\Tests\Fixtures\PendingMigration;

final class DoctrineMigrationsHealthCheckTest extends TestCase
{
    public function testPendingMigrationsAreCountedWithoutInitializingMetadataOrExecutingSql(): void
    {
        $connection = DriverManager::getConnection(['driver' => 'pdo_sqlite', 'memory' => true]);
        $config = new Configuration();
        $config->addMigrationClass(PendingMigration::class);
        $factory = DependencyFactory::fromConnection(new ExistingConfiguration($config), new ExistingConnection($connection));
        $result = (new DoctrineMigrationsHealthCheck($factory))->check();
        self::assertSame(HealthStatus::Warning, $result->status);
        self::assertSame(1, $result->metrics['pending_migrations']);
        self::assertSame([], $connection->createSchemaManager()->listTableNames());
        self::assertSame(HealthStatus::Critical, (new DoctrineMigrationsHealthCheck($factory, HealthStatus::Critical))->check()->status);
        $connection->close();
    }

    public function testUnavailableMetadataDoesNotExposeDetails(): void
    {
        $connection = DriverManager::getConnection(['driver' => 'pdo_sqlite', 'memory' => true]);
        $factory = DependencyFactory::fromConnection(new ExistingConfiguration(new Configuration()), new ExistingConnection($connection));
        $calculator = $this->createStub(MigrationStatusCalculator::class);
        $calculator->method('getNewMigrations')->willThrowException(new \RuntimeException('secret table /private/path'));
        $factory->setService(MigrationStatusCalculator::class, $calculator);
        $result = (new DoctrineMigrationsHealthCheck($factory))->check();
        self::assertSame(HealthStatus::Unknown, $result->status);
        self::assertStringNotContainsString('secret', json_encode($result->toArray(), JSON_THROW_ON_ERROR));
        $connection->close();
    }

    public function testNoFactoryIsUnsupported(): void
    {
        self::assertSame(HealthStatus::Unsupported, (new DoctrineMigrationsHealthCheck())->check()->status);
    }
}
