<?php

declare(strict_types=1);

namespace Robole\SymfonyHealthBundle\Tests\Fixtures;

use Psr\Log\NullLogger;
use Robole\SymfonyHealthBundle\Health\Enum\HealthStatus;
use Robole\SymfonyHealthBundle\RoboleHealthBundle;
use Robole\SymfonyHealthBundle\SymfonyHealthBundle;
use Symfony\Bundle\FrameworkBundle\FrameworkBundle;
use Symfony\Bundle\FrameworkBundle\Kernel\MicroKernelTrait;
use Symfony\Component\DependencyInjection\Loader\Configurator\ContainerConfigurator;
use Symfony\Component\HttpKernel\Kernel;
use Symfony\Component\Routing\Loader\Configurator\RoutingConfigurator;

final class TestKernel extends Kernel
{
    use MicroKernelTrait;

    /** @param array<string, mixed> $healthConfiguration */
    public function __construct(
        private readonly string $runtimeDirectory,
        private readonly array $healthConfiguration = [],
        private readonly bool $throwingCheck = false,
        private readonly bool $useFlexAlias = false,
        private readonly bool $brokenConstruction = false,
    ) {
        parent::__construct('test', false);
    }

    public function registerBundles(): iterable
    {
        yield new FrameworkBundle();
        yield $this->useFlexAlias ? new SymfonyHealthBundle() : new RoboleHealthBundle();
    }

    public function getProjectDir(): string
    {
        return $this->runtimeDirectory;
    }

    public function getCacheDir(): string
    {
        return $this->runtimeDirectory.'/cache';
    }

    public function getLogDir(): string
    {
        return $this->runtimeDirectory.'/logs';
    }

    protected function configureContainer(ContainerConfigurator $container): void
    {
        $container->extension('framework', ['secret' => 'fixture-only', 'router' => ['utf8' => true], 'http_method_override' => false, 'php_errors' => ['log' => false]]);
        $container->extension('robole_health', $this->healthConfiguration);
        $container->services()->set('logger', NullLogger::class);
        $container->services()->set(StubHealthCheck::class)->autoconfigure()->args(['custom', HealthStatus::Healthy, $this->throwingCheck]);
        if ($this->brokenConstruction) {
            $container->services()->set(UnconstructableHealthCheck::class)->autoconfigure();
        }
    }

    protected function configureRoutes(RoutingConfigurator $routes): void
    {
        $routes->import('@RoboleHealthBundle/Resources/config/routes.php');
    }
}
