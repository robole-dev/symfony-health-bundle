<?php

declare(strict_types=1);

namespace Robole\SymfonyHealthBundle\Tests\Unit\Check;

use PHPUnit\Framework\TestCase;
use Psr\Cache\CacheItemPoolInterface;
use Robole\SymfonyHealthBundle\Health\Check\CacheHealthCheck;
use Robole\SymfonyHealthBundle\Health\Enum\HealthStatus;
use Symfony\Component\Cache\Adapter\ArrayAdapter;

final class CacheHealthCheckTest extends TestCase
{
    public function testProbeLeavesApplicationDataIntactAndCleansItsOwnKey(): void
    {
        $pool = new ArrayAdapter();
        $pool->save($pool->getItem('application_key')->set('keep'));
        self::assertSame(HealthStatus::Healthy, (new CacheHealthCheck($pool))->check()->status);
        self::assertSame('keep', $pool->getItem('application_key')->get());
        self::assertSame(['application_key'], array_keys($pool->getValues()));
    }

    public function testMissingPoolIsUnsupported(): void
    {
        self::assertSame(HealthStatus::Unsupported, (new CacheHealthCheck())->check()->status);
    }

    public function testExceptionsAreSanitized(): void
    {
        $pool = $this->createStub(CacheItemPoolInterface::class);
        $pool->method('getItem')->willThrowException(new \RuntimeException('redis://secret@host'));
        $result = (new CacheHealthCheck($pool))->check();
        self::assertSame(HealthStatus::Critical, $result->status);
        self::assertStringNotContainsString('secret', json_encode($result->toArray(), JSON_THROW_ON_ERROR));
    }
}
