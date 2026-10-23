<?php

declare(strict_types=1);

namespace Robole\SymfonyHealthBundle\Tests\Unit\DependencyInjection;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Robole\SymfonyHealthBundle\DependencyInjection\Configuration;
use Symfony\Component\Config\Definition\Exception\InvalidConfigurationException;
use Symfony\Component\Config\Definition\Processor;

final class ConfigurationTest extends TestCase
{
    public function testDefaultsAreSecureAndOptionalFeaturesAreConservative(): void
    {
        $config = (new Processor())->processConfiguration(new Configuration(), []);
        self::assertIsArray($config['api']);
        self::assertIsArray($config['checks']);
        self::assertIsArray($config['dependencies']);
        self::assertIsArray($config['messenger']);
        self::assertSame([], $config['api']['tokens']);
        self::assertTrue($config['api']['require_https']);
        self::assertFalse($config['checks']['cache']);
        self::assertFalse($config['dependencies']['include_dev']);
        self::assertSame([], $config['messenger']['transports']);
    }

    public function testInformationCacheCanBeDisabledWithNull(): void
    {
        $config = (new Processor())->processConfiguration(new Configuration(), [['dependencies' => ['cache_pool' => null]]]);
        self::assertIsArray($config['dependencies']);
        self::assertNull($config['dependencies']['cache_pool']);
    }

    /** @param array<string, mixed> $input */
    #[DataProvider('invalidConfigurations')]
    public function testInvalidConfigurationIsRejected(array $input): void
    {
        $this->expectException(InvalidConfigurationException::class);
        (new Processor())->processConfiguration(new Configuration(), [$input]);
    }

    /** @return iterable<string, array{array<string, mixed>}> */
    public static function invalidConfigurations(): iterable
    {
        yield 'invalid prefix' => [['api' => ['prefix' => '/api/{token}']]];
        yield 'relative prefix' => [['api' => ['prefix' => 'api/health']]];
        yield 'trailing slash' => [['api' => ['prefix' => '/api/health/']]];
        yield 'invalid status code' => [['api' => ['critical_http_status' => 500]]];
        yield 'invalid IP' => [['api' => ['allowed_ips' => ['example.com']]]];
        yield 'invalid CIDR' => [['api' => ['allowed_ips' => ['192.0.2.1/99']]]];
        yield 'remote file path' => [['dependencies' => ['project_dir' => 'https://private.example']]];
        yield 'negative TTL' => [['dependencies' => ['cache_ttl' => -1]]];
        yield 'invalid migration status' => [['migrations' => ['pending_status' => 'healthy']]];
        yield 'unverified count operation' => [['messenger' => ['transports' => ['async' => ['service' => 'messenger.transport.async']]]]];
        yield 'unregistered failure transport' => [['messenger' => ['failure_transports' => ['failed']]]];
        yield 'invalid threshold' => [['performance' => ['warning_thresholds_ms' => ['database' => 0]]]];
        yield 'typo' => [['unknown_option' => true]];
    }
}
