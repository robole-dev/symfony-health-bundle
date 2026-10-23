<?php

declare(strict_types=1);

namespace Robole\SymfonyHealthBundle\Tests\Unit\Check;

use PHPUnit\Framework\TestCase;
use Robole\SymfonyHealthBundle\Health\Check\StorageHealthCheck;
use Robole\SymfonyHealthBundle\Health\Enum\HealthStatus;
use Robole\SymfonyHealthBundle\Tests\Fixtures\TemporaryDirectory;

final class StorageHealthCheckTest extends TestCase
{
    use TemporaryDirectory;

    protected function setUp(): void
    {
        $this->createDirectory();
    }

    protected function tearDown(): void
    {
        $this->removeDirectory();
    }

    public function testWritableDirectoriesWithoutDiskInformation(): void
    {
        $result = (new StorageHealthCheck($this->directory, $this->directory))->check();
        self::assertSame(HealthStatus::Healthy, $result->status);
        self::assertSame(['exists' => true, 'writable' => true], $result->components['cache']->metrics);
        self::assertStringNotContainsString($this->directory, json_encode($result->toArray(), JSON_THROW_ON_ERROR));
        self::assertSame([], array_diff(scandir($this->directory) ?: [], ['.', '..']));
    }

    public function testMissingDirectoryIsCritical(): void
    {
        $result = (new StorageHealthCheck($this->directory.'/missing', $this->directory))->check();
        self::assertSame(HealthStatus::Critical, $result->status);
        self::assertFalse($result->components['cache']->metrics['exists']);
        self::assertSame(HealthStatus::Healthy, $result->components['logs']->status);
    }

    public function testDiskInformationIsOptionalAndNeverFaked(): void
    {
        $result = (new StorageHealthCheck($this->directory, $this->directory, true))->check();
        $metrics = $result->components['cache']->metrics;
        self::assertArrayHasKey('free_bytes', $metrics);
        self::assertArrayHasKey('total_bytes', $metrics);
        if (null === $metrics['free_bytes'] || null === $metrics['total_bytes']) {
            self::assertSame(HealthStatus::Unknown, $result->status);
        } else {
            self::assertGreaterThan(0, $metrics['total_bytes']);
        }
    }
}
