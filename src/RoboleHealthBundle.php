<?php

declare(strict_types=1);

namespace Robole\SymfonyHealthBundle;

use Robole\SymfonyHealthBundle\DependencyInjection\Compiler\HealthCheckPass;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\HttpKernel\Bundle\Bundle;

final class RoboleHealthBundle extends Bundle
{
    public function build(ContainerBuilder $container): void
    {
        parent::build($container);
        $container->addCompilerPass(new HealthCheckPass(), priority: -1000);
    }
}
