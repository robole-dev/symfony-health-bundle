<?php

declare(strict_types=1);

namespace Robole\SymfonyHealthBundle\Tests\Unit\Health;

use PHPUnit\Framework\TestCase;
use Robole\SymfonyHealthBundle\Health\Registry\HealthCheckRegistry;
use Robole\SymfonyHealthBundle\Tests\Fixtures\StubHealthCheck;

final class HealthCheckRegistryTest extends TestCase
{
    public function testRegistryIsLazyAndSupportsGeneratorsAcrossMultipleRuns(): void
    {
        $state = new \ArrayObject(['resolved' => false]);
        $checks = (static function () use ($state): iterable {
            $state['resolved'] = true;
            yield new StubHealthCheck('first');
            yield new StubHealthCheck('second');
        })();
        $registry = new HealthCheckRegistry($checks);
        self::assertFalse($state['resolved']);
        self::assertSame(['first', 'second'], array_keys($registry->all()));
        self::assertTrue($state['resolved']);
        self::assertSame($registry->all(), $registry->all());
    }

    public function testDuplicateNamesCannotSilentlyReplaceChecks(): void
    {
        $this->expectException(\LogicException::class);
        (new HealthCheckRegistry([new StubHealthCheck('same'), new StubHealthCheck('same')]))->all();
    }

    public function testNamesCannotExposePaths(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        (new HealthCheckRegistry([new StubHealthCheck('/srv/private')]))->all();
    }
}
