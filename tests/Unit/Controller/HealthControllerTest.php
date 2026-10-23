<?php

declare(strict_types=1);

namespace Robole\SymfonyHealthBundle\Tests\Unit\Controller;

use PHPUnit\Framework\TestCase;
use Psr\Cache\CacheItemPoolInterface;
use Robole\SymfonyHealthBundle\Controller\HealthController;
use Robole\SymfonyHealthBundle\Health\HealthReportFactory;
use Robole\SymfonyHealthBundle\Health\Registry\HealthCheckRegistry;
use Robole\SymfonyHealthBundle\Health\Runner\HealthCheckRunner;
use Robole\SymfonyHealthBundle\Information\Application\ApplicationInformationProvider;
use Robole\SymfonyHealthBundle\Information\Composer\ComposerDependencyProvider;
use Robole\SymfonyHealthBundle\Information\System\SystemInformationProvider;
use Robole\SymfonyHealthBundle\Security\ApiAccessGuard;
use Robole\SymfonyHealthBundle\Tests\Fixtures\StubHealthCheck;
use Symfony\Component\HttpFoundation\Request;

final class HealthControllerTest extends TestCase
{
    public function testUnauthorizedRequestsDoNotInstantiateChecksOrGatherInformation(): void
    {
        $state = new \ArrayObject(['constructed' => false]);
        $cache = $this->createMock(CacheItemPoolInterface::class);
        $cache->expects(self::never())->method('getItem');
        $dependencies = new ComposerDependencyProvider('/not/read', cache: $cache);
        $registry = new HealthCheckRegistry([static function () use ($state): StubHealthCheck {
            $state['constructed'] = true;

            return new StubHealthCheck();
        }]);
        $reports = new HealthReportFactory(new HealthCheckRunner($registry), new ApplicationInformationProvider('Example', 'prod'), new SystemInformationProvider('prod', false), $dependencies);
        $controller = new HealthController(new ApiAccessGuard(['valid']), $reports, $dependencies);
        $request = Request::create('https://example.test');
        self::assertSame(401, $controller->health($request)->getStatusCode());
        self::assertSame(401, $controller->dependencies($request)->getStatusCode());
        self::assertFalse($state['constructed']);
    }
}
