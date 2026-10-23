<?php

declare(strict_types=1);

namespace Robole\SymfonyHealthBundle\DependencyInjection;

use Doctrine\DBAL\Connection;
use Doctrine\Migrations\DependencyFactory;
use Robole\SymfonyHealthBundle\Controller\HealthController;
use Robole\SymfonyHealthBundle\Health\Check\CacheHealthCheck;
use Robole\SymfonyHealthBundle\Health\Check\DatabaseHealthCheck;
use Robole\SymfonyHealthBundle\Health\Check\DoctrineMigrationsHealthCheck;
use Robole\SymfonyHealthBundle\Health\Check\MessengerHealthCheck;
use Robole\SymfonyHealthBundle\Health\Check\OpcacheHealthCheck;
use Robole\SymfonyHealthBundle\Health\Check\StorageHealthCheck;
use Robole\SymfonyHealthBundle\Health\Enum\HealthStatus;
use Robole\SymfonyHealthBundle\Health\HealthCheckInterface;
use Robole\SymfonyHealthBundle\Health\HealthReportFactory;
use Robole\SymfonyHealthBundle\Health\Registry\HealthCheckRegistry;
use Robole\SymfonyHealthBundle\Health\Runner\HealthCheckRunner;
use Robole\SymfonyHealthBundle\Information\Application\ApplicationInformationProvider;
use Robole\SymfonyHealthBundle\Information\Composer\ComposerDependencyProvider;
use Robole\SymfonyHealthBundle\Information\System\SystemInformationProvider;
use Robole\SymfonyHealthBundle\Routing\HealthRouteLoader;
use Robole\SymfonyHealthBundle\Security\ApiAccessGuard;
use Symfony\Component\DependencyInjection\Argument\ServiceLocatorArgument;
use Symfony\Component\DependencyInjection\Argument\TaggedIteratorArgument;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\DependencyInjection\Extension\Extension;
use Symfony\Component\DependencyInjection\Reference;
use Symfony\Component\Messenger\Transport\Receiver\MessageCountAwareInterface;

/** @phpstan-import-type HealthConfig from Configuration */
final class RoboleHealthExtension extends Extension
{
    public const string CHECK_TAG = 'robole_health.check';

    public function getAlias(): string
    {
        return 'robole_health';
    }

    public function load(array $configs, ContainerBuilder $container): void
    {
        /** @var HealthConfig $config */
        $config = $this->processConfiguration(new Configuration(), $configs);
        $api = $config['api'];
        $active = $config['enabled'] && $api['enabled'];
        $container->register(HealthRouteLoader::class)
            ->setArguments([$api['prefix'], $active && $api['health_enabled'], $active && $api['dependencies_enabled'] && $config['dependencies']['enabled']])
            ->addTag('routing.loader');
        $container->registerForAutoconfiguration(HealthCheckInterface::class)->addTag(self::CHECK_TAG);
        if (!$config['enabled']) {
            return;
        }

        $container->register(HealthCheckRegistry::class)->setArguments([new TaggedIteratorArgument(self::CHECK_TAG)]);
        $container->register(HealthCheckRunner::class)->setArguments([new Reference(HealthCheckRegistry::class), $config['performance']['warning_thresholds_ms']]);
        $container->register(ApiAccessGuard::class)->setArguments([$api['tokens'], $api['allowed_ips'], $api['require_https']]);
        $application = $config['application'];
        $container->register(ApplicationInformationProvider::class)->setArguments([
            $application['name'], '%kernel.environment%', $application['version'], $application['git_commit'], $application['git_branch'], $application['deployed_at'], $application['include_sulu_version'],
        ]);
        $container->register(SystemInformationProvider::class)->setArguments(['%kernel.environment%', '%kernel.debug%', $config['system']['include_extensions']]);
        $dependencies = $config['dependencies'];
        $container->register(ComposerDependencyProvider::class)->setArguments([
            $dependencies['project_dir'], $dependencies['include_dev'], $dependencies['enabled'], $dependencies['max_file_size_bytes'],
            null === $dependencies['cache_pool'] ? null : self::optional($dependencies['cache_pool']), $dependencies['cache_ttl'],
        ]);
        $container->register(HealthReportFactory::class)->setArguments([
            new Reference(HealthCheckRunner::class), new Reference(ApplicationInformationProvider::class), new Reference(SystemInformationProvider::class), new Reference(ComposerDependencyProvider::class), $dependencies['enabled'],
        ]);
        $container->register(HealthController::class)->setPublic(true)->setArguments([
            new Reference(ApiAccessGuard::class), new Reference(HealthReportFactory::class), new Reference(ComposerDependencyProvider::class), $api['critical_http_status'],
        ])->addTag('controller.service_arguments');

        if ($config['checks']['database'] && class_exists(Connection::class)) {
            $container->register(DatabaseHealthCheck::class)->setArguments([self::optional($config['database']['connection'])])->addTag(self::CHECK_TAG);
        }
        if ($config['checks']['storage']) {
            $container->register(StorageHealthCheck::class)->setArguments(['%kernel.cache_dir%', '%kernel.logs_dir%', $config['storage']['include_space']])->addTag(self::CHECK_TAG);
        }
        if ($config['checks']['cache']) {
            $container->register(CacheHealthCheck::class)->setArguments([self::optional($config['cache']['pool'])])->addTag(self::CHECK_TAG);
        }
        if ($config['checks']['opcache']) {
            $container->register(OpcacheHealthCheck::class)->addTag(self::CHECK_TAG);
        }
        if ($config['checks']['messenger'] && interface_exists(MessageCountAwareInterface::class)) {
            $transports = [];
            foreach ($config['messenger']['transports'] as $name => $transport) {
                $transports[$name] = self::optional($transport['service']);
            }
            $container->register(MessengerHealthCheck::class)->setArguments([
                new ServiceLocatorArgument($transports), array_keys($transports), $config['messenger']['failure_transports'], $config['messenger']['failed_warning_threshold'],
            ])->addTag(self::CHECK_TAG);
        }
        if ($config['checks']['doctrine_migrations'] && class_exists(DependencyFactory::class)) {
            $container->register(DoctrineMigrationsHealthCheck::class)->setArguments([
                self::optional($config['migrations']['dependency_factory']), HealthStatus::from($config['migrations']['pending_status']),
            ])->addTag(self::CHECK_TAG);
        }
    }

    private static function optional(string $id): Reference
    {
        return new Reference($id, ContainerInterface::NULL_ON_INVALID_REFERENCE);
    }
}
