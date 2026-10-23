<?php

declare(strict_types=1);

namespace Robole\SymfonyHealthBundle\DependencyInjection\Compiler;

use Robole\SymfonyHealthBundle\DependencyInjection\RoboleHealthExtension;
use Robole\SymfonyHealthBundle\Health\Registry\HealthCheckRegistry;
use Symfony\Component\DependencyInjection\Argument\ServiceClosureArgument;
use Symfony\Component\DependencyInjection\Compiler\CompilerPassInterface;
use Symfony\Component\DependencyInjection\Compiler\PriorityTaggedServiceTrait;
use Symfony\Component\DependencyInjection\ContainerBuilder;

final class HealthCheckPass implements CompilerPassInterface
{
    use PriorityTaggedServiceTrait;

    public function process(ContainerBuilder $container): void
    {
        if (!$container->hasDefinition(HealthCheckRegistry::class)) {
            return;
        }
        $resolvers = [];
        foreach ($this->findAndSortTaggedServices(RoboleHealthExtension::CHECK_TAG, $container) as $reference) {
            $resolvers[] = new ServiceClosureArgument($reference);
        }
        $container->getDefinition(HealthCheckRegistry::class)->setArgument(0, $resolvers);
    }
}
