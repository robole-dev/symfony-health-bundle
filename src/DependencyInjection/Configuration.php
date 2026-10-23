<?php

declare(strict_types=1);

namespace Robole\SymfonyHealthBundle\DependencyInjection;

use Symfony\Component\Config\Definition\Builder\TreeBuilder;
use Symfony\Component\Config\Definition\ConfigurationInterface;

/**
 * @phpstan-type HealthConfig array{
 *   enabled: bool,
 *   api: array{enabled: bool, health_enabled: bool, dependencies_enabled: bool, prefix: string, tokens: list<string>, allowed_ips: list<string>, require_https: bool, critical_http_status: int},
 *   application: array{name: string, version: ?string, git_commit: ?string, git_branch: ?string, deployed_at: ?string, include_sulu_version: bool},
 *   system: array{include_extensions: bool},
 *   checks: array{database: bool, storage: bool, cache: bool, messenger: bool, opcache: bool, doctrine_migrations: bool},
 *   database: array{connection: string},
 *   storage: array{include_space: bool},
 *   cache: array{pool: string},
 *   messenger: array{transports: array<string, array{service: string, read_only: bool}>, failure_transports: list<string>, failed_warning_threshold: int},
 *   migrations: array{dependency_factory: string, pending_status: string},
 *   performance: array{warning_thresholds_ms: array<string, int>},
 *   dependencies: array{enabled: bool, project_dir: string, include_dev: bool, max_file_size_bytes: int, cache_pool: ?string, cache_ttl: int}
 * }
 */
final class Configuration implements ConfigurationInterface
{
    public function getConfigTreeBuilder(): TreeBuilder
    {
        $tree = new TreeBuilder('robole_health');
        $tree->getRootNode()
            ->children()
                ->booleanNode('enabled')->defaultTrue()->end()
                ->arrayNode('api')->addDefaultsIfNotSet()
                    ->children()
                        ->booleanNode('enabled')->defaultTrue()->end()
                        ->booleanNode('health_enabled')->defaultTrue()->end()
                        ->booleanNode('dependencies_enabled')->defaultTrue()->end()
                        ->stringNode('prefix')->defaultValue('/api/health')
                            ->validate()->ifTrue(static fn (string $value): bool => 1 !== preg_match('#\A/(?:[a-zA-Z0-9_-]+/)*[a-zA-Z0-9_-]+\z#D', $value))->thenInvalid('Use an absolute route prefix without placeholders, a trailing slash, query or fragment.')->end()
                        ->end()
                        ->arrayNode('tokens')->stringPrototype()->cannotBeEmpty()->end()->defaultValue([])->end()
                        ->arrayNode('allowed_ips')->stringPrototype()->cannotBeEmpty()
                            ->validate()->ifTrue(static fn (mixed $value): bool => !is_string($value) || !self::validIpRange($value))->thenInvalid('Use valid IPv4/IPv6 addresses or CIDR ranges.')->end()
                        ->end()->defaultValue([])->end()
                        ->booleanNode('require_https')->defaultTrue()->end()
                        ->enumNode('critical_http_status')->values([200, 503])->defaultValue(200)->end()
                    ->end()
                ->end()
                ->arrayNode('application')->addDefaultsIfNotSet()
                    ->children()
                        ->stringNode('name')->cannotBeEmpty()->defaultValue('Application')->end()
                        ->stringNode('version')->defaultNull()->end()
                        ->stringNode('git_commit')->defaultNull()->end()
                        ->stringNode('git_branch')->defaultNull()->end()
                        ->stringNode('deployed_at')->defaultNull()->end()
                        ->booleanNode('include_sulu_version')->defaultTrue()->end()
                    ->end()
                ->end()
                ->arrayNode('system')->addDefaultsIfNotSet()
                    ->children()->booleanNode('include_extensions')->defaultFalse()->end()->end()
                ->end()
                ->arrayNode('checks')->addDefaultsIfNotSet()
                    ->children()
                        ->booleanNode('database')->defaultTrue()->end()
                        ->booleanNode('storage')->defaultTrue()->end()
                        ->booleanNode('cache')->defaultFalse()->end()
                        ->booleanNode('messenger')->defaultTrue()->end()
                        ->booleanNode('opcache')->defaultTrue()->end()
                        ->booleanNode('doctrine_migrations')->defaultTrue()->end()
                    ->end()
                ->end()
                ->arrayNode('database')->addDefaultsIfNotSet()
                    ->children()->stringNode('connection')->cannotBeEmpty()->defaultValue('doctrine.dbal.default_connection')->end()->end()
                ->end()
                ->arrayNode('storage')->addDefaultsIfNotSet()
                    ->children()->booleanNode('include_space')->defaultFalse()->end()->end()
                ->end()
                ->arrayNode('cache')->addDefaultsIfNotSet()
                    ->children()->stringNode('pool')->cannotBeEmpty()->defaultValue('cache.app')->end()->end()
                ->end()
                ->arrayNode('messenger')->addDefaultsIfNotSet()
                    ->children()
                        ->arrayNode('transports')->useAttributeAsKey('name')->defaultValue([])
                            ->arrayPrototype()
                                ->children()
                                    ->stringNode('service')->isRequired()->cannotBeEmpty()->end()
                                    ->booleanNode('read_only')->defaultFalse()->end()
                                ->end()
                                ->validate()->ifTrue(static fn (array $transport): bool => true !== $transport['read_only'])->thenInvalid('Explicitly set read_only: true only after verifying that getMessageCount() cannot initialize or modify this transport.')->end()
                            ->end()
                            ->validate()->ifTrue(static fn (array $transports): bool => count(array_filter(array_keys($transports), static fn (int|string $key): bool => !is_string($key) || 1 !== preg_match('/\A[a-z][a-z0-9_]{0,63}\z/D', $key))) > 0)->thenInvalid('Transport aliases must be lowercase identifiers of at most 64 characters.')->end()
                        ->end()
                        ->arrayNode('failure_transports')->stringPrototype()->cannotBeEmpty()->end()->defaultValue([])->end()
                        ->integerNode('failed_warning_threshold')->min(1)->defaultValue(1)->end()
                    ->end()
                    ->validate()->ifTrue(self::hasUnknownFailureTransport(...))->thenInvalid('Every failure transport must also be listed in transports.')->end()
                ->end()
                ->arrayNode('migrations')->addDefaultsIfNotSet()
                    ->children()
                        ->stringNode('dependency_factory')->cannotBeEmpty()->defaultValue('doctrine.migrations.dependency_factory')->end()
                        ->enumNode('pending_status')->values(['warning', 'critical'])->defaultValue('warning')->end()
                    ->end()
                ->end()
                ->arrayNode('performance')->addDefaultsIfNotSet()
                    ->children()
                        ->arrayNode('warning_thresholds_ms')->useAttributeAsKey('check')->integerPrototype()->min(1)->end()->defaultValue([])->end()
                    ->end()
                ->end()
                ->arrayNode('dependencies')->addDefaultsIfNotSet()
                    ->children()
                        ->booleanNode('enabled')->defaultTrue()->end()
                        ->stringNode('project_dir')->cannotBeEmpty()->defaultValue('%kernel.project_dir%')
                            ->validate()->ifTrue(static fn (string $value): bool => str_contains($value, '://') || str_contains($value, "\0"))->thenInvalid('Use a local project directory.')->end()
                        ->end()
                        ->booleanNode('include_dev')->defaultFalse()->end()
                        ->integerNode('max_file_size_bytes')->min(1024)->max(52428800)->defaultValue(10485760)->end()
                        ->scalarNode('cache_pool')->defaultValue('cache.system')
                            ->validate()->ifTrue(static fn (mixed $value): bool => null !== $value && (!is_string($value) || '' === trim($value)))->thenInvalid('Use a non-empty cache service ID or null.')->end()
                        ->end()
                        ->integerNode('cache_ttl')->min(0)->max(86400)->defaultValue(60)->end()
                    ->end()
                ->end()
            ->end();

        return $tree;
    }

    private static function validIpRange(string $range): bool
    {
        $parts = explode('/', $range);
        if (count($parts) > 2 || false === filter_var($parts[0], FILTER_VALIDATE_IP)) {
            return false;
        }
        if (1 === count($parts)) {
            return true;
        }

        return ctype_digit($parts[1]) && (int) $parts[1] <= (str_contains($parts[0], ':') ? 128 : 32);
    }

    /** @param array{failure_transports: list<string>, transports: array<string, mixed>} $messenger */
    private static function hasUnknownFailureTransport(array $messenger): bool
    {
        return [] !== array_diff($messenger['failure_transports'], array_keys($messenger['transports']));
    }
}
