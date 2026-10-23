<?php

declare(strict_types=1);

namespace Robole\SymfonyHealthBundle\Routing;

use Robole\SymfonyHealthBundle\Controller\HealthController;
use Symfony\Component\Config\Loader\Loader;
use Symfony\Component\Routing\Route;
use Symfony\Component\Routing\RouteCollection;

final class HealthRouteLoader extends Loader
{
    public function __construct(
        private readonly string $prefix = '/api/health',
        private readonly bool $healthEnabled = true,
        private readonly bool $dependenciesEnabled = true,
    ) {
        parent::__construct();
    }

    public function load(mixed $resource, ?string $type = null): RouteCollection
    {
        $routes = new RouteCollection();
        if ($this->healthEnabled) {
            $routes->add('robole_health', new Route($this->prefix, ['_controller' => HealthController::class.'::health', '_format' => 'json'], methods: ['GET']));
        }
        if ($this->dependenciesEnabled) {
            $routes->add('robole_health_dependencies', new Route($this->prefix.'/dependencies', ['_controller' => HealthController::class.'::dependencies', '_format' => 'json'], methods: ['GET']));
        }

        return $routes;
    }

    public function supports(mixed $resource, ?string $type = null): bool
    {
        return 'robole_health' === $type;
    }
}
