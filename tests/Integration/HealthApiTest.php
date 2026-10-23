<?php

declare(strict_types=1);

namespace Robole\SymfonyHealthBundle\Tests\Integration;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Robole\SymfonyHealthBundle\Tests\Fixtures\TemporaryDirectory;
use Robole\SymfonyHealthBundle\Tests\Fixtures\TestKernel;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

final class HealthApiTest extends TestCase
{
    use TemporaryDirectory;

    private ?TestKernel $kernel = null;

    /** @var callable(\Throwable): void|null */
    private $previousExceptionHandler;

    protected function setUp(): void
    {
        $this->previousExceptionHandler = set_exception_handler(static function (\Throwable $error): void { throw $error; });
        restore_exception_handler();
        $this->createDirectory();
        mkdir($this->directory.'/logs');
        $this->writeJson('composer.json', ['require' => ['php' => '^8.4']]);
        $this->writeJson('composer.lock', ['packages' => [['name' => 'vendor/example', 'version' => '1.0.0']]]);
    }

    protected function tearDown(): void
    {
        $this->kernel?->shutdown();
        $this->removeDirectory();
        // Symfony 8.0 installs a process-wide exception handler while booting its HTTP kernel.
        $current = set_exception_handler(static function (\Throwable $error): void { throw $error; });
        restore_exception_handler();
        if ($current !== $this->previousExceptionHandler) {
            restore_exception_handler();
        }
    }

    public function testBundleBootsAndAutoconfiguredCheckIsExposed(): void
    {
        $this->kernel = new TestKernel($this->directory, ['api' => ['tokens' => ['test-token']]]);
        $response = $this->kernel->handle(self::request('/api/health', 'test-token'));
        self::assertSame(200, $response->getStatusCode());
        self::assertSame('application/json', $response->headers->get('Content-Type'));
        self::assertStringContainsString('no-store', $response->headers->get('Cache-Control') ?? '');
        $data = self::payload($response);
        self::assertSame(['schema_version', 'timestamp', 'status', 'application', 'system', 'dependencies', 'checks'], array_keys($data));
        self::assertSame('1.0', $data['schema_version']);
        $timestamp = self::field($data, 'timestamp');
        self::assertIsString($timestamp);
        self::assertMatchesRegularExpression('/\A\d{4}-\d{2}-\d{2}T\d{2}:\d{2}:\d{2}Z\z/', $timestamp);
        self::assertSame('healthy', self::field($data, 'checks', 'custom', 'status'));
        self::assertSame('unsupported', self::field($data, 'checks', 'database', 'status'));
        self::assertNull(self::field($data, 'application', 'sulu_version'));
        self::assertSame(1, self::field($data, 'dependencies', 'production_packages'));
        self::assertStringNotContainsString($this->directory, (string) $response->getContent());
        self::assertStringNotContainsString('test-token', (string) $response->getContent());
    }

    #[DataProvider('protectedEndpoints')]
    public function testBothEndpointsRejectUnauthorizedRequests(string $path): void
    {
        $this->kernel = new TestKernel($this->directory, ['api' => ['tokens' => ['test-token']]], true);
        foreach ([null, 'wrong-token'] as $token) {
            $response = $this->kernel->handle(self::request($path, $token));
            self::assertSame(401, $response->getStatusCode());
            self::assertSame('{"error":"unauthorized"}', $response->getContent());
        }
    }

    /** @return iterable<string, array{string}> */
    public static function protectedEndpoints(): iterable
    {
        yield 'health' => ['/api/health'];
        yield 'inventory' => ['/api/health/dependencies'];
    }

    public function testMissingTokenConfigurationNeverMakesApiPublic(): void
    {
        $this->kernel = new TestKernel($this->directory);
        self::assertSame(401, $this->kernel->handle(self::request('/api/health', 'any-token'))->getStatusCode());
    }

    public function testNullResolvedEnvironmentTokenFailsClosed(): void
    {
        $this->kernel = new TestKernel($this->directory, ['api' => ['tokens' => ['%env(default::ROBOLE_HEALTH_TEST_UNSET_TOKEN)%']]]);
        self::assertSame(401, $this->kernel->handle(self::request('/api/health', 'any-token'))->getStatusCode());
    }

    public function testFlexDiscoveryAliasUsesTheCanonicalBundleAndExtension(): void
    {
        $this->kernel = new TestKernel($this->directory, ['api' => ['tokens' => ['test-token']]], useFlexAlias: true);
        $response = $this->kernel->handle(self::request('/api/health', 'test-token'));
        self::assertSame(200, $response->getStatusCode());
        self::assertSame(\Robole\SymfonyHealthBundle\RoboleHealthBundle::class, $this->kernel->getBundle('RoboleHealthBundle')::class);
        self::assertArrayHasKey('RoboleHealthBundle', $this->kernel->getBundles());
    }

    public function testCriticalReportRemainsReadableAndExceptionIsSanitized(): void
    {
        $this->kernel = new TestKernel($this->directory, ['api' => ['tokens' => ['test-token'], 'critical_http_status' => 503]], true);
        $response = $this->kernel->handle(self::request('/api/health', 'test-token'));
        self::assertSame(503, $response->getStatusCode());
        $data = self::payload($response);
        self::assertSame('critical', $data['status']);
        self::assertSame('check_failed', self::field($data, 'checks', 'custom', 'code'));
        self::assertIsArray($data['checks']);
        self::assertArrayHasKey('storage', $data['checks']);
        self::assertStringNotContainsString('password', (string) $response->getContent());
    }

    public function testDefaultCriticalResponseIsHttp200(): void
    {
        $this->kernel = new TestKernel($this->directory, ['api' => ['tokens' => ['test-token']]], true);
        $response = $this->kernel->handle(self::request('/api/health', 'test-token'));
        self::assertSame(200, $response->getStatusCode());
        self::assertStringContainsString('"status":"critical"', (string) $response->getContent());
    }

    public function testBrokenServiceConstructionIsLazyAndIsolatedInTheCompiledContainer(): void
    {
        $this->kernel = new TestKernel($this->directory, ['api' => ['tokens' => ['test-token']]], brokenConstruction: true);
        self::assertSame(401, $this->kernel->handle(self::request('/api/health', null))->getStatusCode());
        $response = $this->kernel->handle(self::request('/api/health', 'test-token'));
        $data = self::payload($response);
        self::assertSame('critical', self::field($data, 'status'));
        self::assertSame('check_initialization_failed', self::field($data, 'checks', 'registration_failure_0', 'code'));
        self::assertSame('healthy', self::field($data, 'checks', 'custom', 'status'));
        self::assertStringNotContainsString('secret', (string) $response->getContent());
    }

    public function testDependencyInformationCanBeDisabledWithoutDisablingHealth(): void
    {
        $this->kernel = new TestKernel($this->directory, ['api' => ['tokens' => ['test-token']], 'dependencies' => ['enabled' => false]]);
        $response = $this->kernel->handle(self::request('/api/health', 'test-token'));
        self::assertSame(200, $response->getStatusCode());
        self::assertNull(self::field(self::payload($response), 'dependencies'));
    }

    public function testAuthenticatedInventoryContainsWhitelistedPackagesAndConstraints(): void
    {
        $this->kernel = new TestKernel($this->directory, ['api' => ['tokens' => ['test-token']]]);
        $response = $this->kernel->handle(self::request('/api/health/dependencies', 'test-token'));
        self::assertSame(200, $response->getStatusCode());
        $data = self::payload($response);
        self::assertSame('vendor/example', self::field($data, 'packages', 0, 'name'));
        self::assertSame('production', self::field($data, 'packages', 0, 'dependency_type'));
        self::assertSame('^8.4', self::field($data, 'constraints', 'require', 'php'));
        self::assertIsString($data['lock_hash']);
        self::assertStringStartsWith('sha256:', $data['lock_hash']);
    }

    /** @param array<string, mixed> $config */
    #[DataProvider('disabledEndpoints')]
    public function testDisabledEndpointsAreNotRegistered(array $config, string $path): void
    {
        $this->kernel = new TestKernel($this->directory, $config);
        self::assertSame(404, $this->kernel->handle(self::request($path, 'test-token'))->getStatusCode());
    }

    /** @return iterable<string, array{array<string, mixed>, string}> */
    public static function disabledEndpoints(): iterable
    {
        yield 'bundle off' => [['enabled' => false], '/api/health'];
        yield 'api off' => [['api' => ['enabled' => false]], '/api/health'];
        yield 'health off' => [['api' => ['health_enabled' => false]], '/api/health'];
        yield 'inventory off' => [['api' => ['dependencies_enabled' => false]], '/api/health/dependencies'];
        yield 'dependencies off' => [['dependencies' => ['enabled' => false]], '/api/health/dependencies'];
    }

    public function testPrefixIsConfigurableAndMethodsAreRestricted(): void
    {
        $this->kernel = new TestKernel($this->directory, ['api' => ['prefix' => '/private/status', 'tokens' => ['test-token']]]);
        self::assertSame(200, $this->kernel->handle(self::request('/private/status', 'test-token'))->getStatusCode());
        self::assertSame(404, $this->kernel->handle(self::request('/api/health', 'test-token'))->getStatusCode());
        $request = self::request('/private/status', 'test-token');
        $request->setMethod('POST');
        self::assertSame(405, $this->kernel->handle($request)->getStatusCode());
    }

    private static function request(string $path, ?string $token): Request
    {
        $request = Request::create('https://example.test'.$path, server: ['REMOTE_ADDR' => '127.0.0.1']);
        if (null !== $token) {
            $request->headers->set('Authorization', 'Bearer '.$token);
        }

        return $request;
    }

    /** @return array<array-key, mixed> */
    private static function payload(Response $response): array
    {
        $content = $response->getContent();
        self::assertIsString($content);
        $data = json_decode($content, true, flags: JSON_THROW_ON_ERROR);
        self::assertIsArray($data);

        return $data;
    }

    /** @param array<array-key, mixed> $data */
    private static function field(array $data, string|int ...$path): mixed
    {
        $value = $data;
        foreach ($path as $part) {
            self::assertIsArray($value);
            self::assertArrayHasKey($part, $value);
            $value = $value[$part];
        }

        return $value;
    }
}
