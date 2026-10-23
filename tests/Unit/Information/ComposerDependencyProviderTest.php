<?php

declare(strict_types=1);

namespace Robole\SymfonyHealthBundle\Tests\Unit\Information;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Psr\Cache\CacheItemPoolInterface;
use Robole\SymfonyHealthBundle\Health\Enum\HealthStatus;
use Robole\SymfonyHealthBundle\Information\Composer\ComposerDependencyProvider;
use Robole\SymfonyHealthBundle\Tests\Fixtures\TemporaryDirectory;
use Symfony\Component\Cache\Adapter\ArrayAdapter;

final class ComposerDependencyProviderTest extends TestCase
{
    use TemporaryDirectory;

    protected function setUp(): void
    {
        $this->createDirectory();
        $this->writeJson('composer.lock', [
            'packages' => [['name' => 'vendor/library', 'version' => 'v1.2.3', 'type' => 'library', 'source' => ['url' => 'https://user:secret@private.example/repo.git']]],
            'packages-dev' => [['name' => 'vendor/test', 'version' => 'dev-main', 'type' => 'project']],
            'repositories' => [['url' => 'https://user:secret@private.example']],
        ]);
        $this->writeJson('composer.json', [
            'require' => ['php' => '^8.4', 'vendor/library' => '^1.2'],
            'require-dev' => ['vendor/test' => 'dev-main'],
            'conflict' => ['vendor/old' => '<1.0'],
            'config' => ['platform' => ['php' => '8.4.0', 'ext-redis' => false], 'http-basic' => ['private.example' => ['password' => 'secret']]],
            'repositories' => [['url' => 'https://user:secret@private.example']],
            'scripts' => ['post-install-cmd' => 'echo secret'],
        ]);
    }

    protected function tearDown(): void
    {
        $this->removeDirectory();
    }

    public function testSummaryAndInventoryUseOneValidatedLockSnapshot(): void
    {
        $information = (new ComposerDependencyProvider($this->directory))->getInformation();
        self::assertSame(HealthStatus::Healthy, $information->status);
        self::assertSame('sha256:'.hash_file('sha256', $this->directory.'/composer.lock'), $information->lockHash);
        self::assertSame(1, $information->productionPackages);
        self::assertSame(1, $information->developmentPackages);
        self::assertCount(1, $information->packages);
        self::assertSame('production', $information->packages[0]['dependency_type']);
        self::assertSame([], $information->constraints['require-dev']);
        $json = json_encode($information->inventory(), JSON_THROW_ON_ERROR);
        self::assertStringNotContainsString('secret', $json);
        self::assertStringNotContainsString('private.example', $json);
        self::assertStringNotContainsString('repositories', $json);
        self::assertStringNotContainsString('scripts', $json);
        self::assertStringNotContainsString($this->directory, $json);
        self::assertSame(false, $information->constraints['platform']['ext-redis']);
    }

    public function testDevelopmentInventoryIsAnExplicitOption(): void
    {
        $information = (new ComposerDependencyProvider($this->directory, true))->getInformation();
        self::assertCount(2, $information->packages);
        self::assertSame('development', $information->packages[1]['dependency_type']);
        self::assertSame(['vendor/test' => 'dev-main'], $information->constraints['require-dev']);
    }

    public function testMissingOrOversizedFilesReturnUnknown(): void
    {
        self::assertSame(HealthStatus::Unknown, (new ComposerDependencyProvider($this->directory, maxFileSizeBytes: 10))->getInformation()->status);
        unlink($this->directory.'/composer.lock');
        $information = (new ComposerDependencyProvider($this->directory))->getInformation();
        self::assertSame(HealthStatus::Unknown, $information->status);
        self::assertNull($information->productionPackages);
        self::assertSame([], $information->packages);
    }

    #[DataProvider('malformedLockFiles')]
    public function testMalformedLockDataIsNeverEchoed(string $contents): void
    {
        file_put_contents($this->directory.'/composer.lock', $contents);
        $information = (new ComposerDependencyProvider($this->directory))->getInformation();
        self::assertSame(HealthStatus::Unknown, $information->status);
        self::assertNull($information->lockHash);
        self::assertStringNotContainsString('secret', json_encode($information->inventory(), JSON_THROW_ON_ERROR));
    }

    /** @return iterable<string, array{string}> */
    public static function malformedLockFiles(): iterable
    {
        yield 'invalid JSON' => ['{"secret":'];
        yield 'wrong root' => ['[]'];
        yield 'wrong packages type' => ['{"packages":"secret"}'];
        yield 'bad package name' => ['{"packages":[{"name":"https://user:secret@host","version":"1.0.0"}]}'];
        yield 'bad version' => ['{"packages":[{"name":"vendor/pkg","version":"https://user:secret@host"}]}'];
        yield 'invalid semver' => ['{"packages":[{"name":"vendor/pkg","version":"secret"}]}'];
        yield 'duplicate package' => ['{"packages":[{"name":"vendor/pkg","version":"1.0.0"},{"name":"vendor/pkg","version":"2.0.0"}]}'];
        yield 'invalid type' => ['{"packages":[{"name":"vendor/pkg","version":"1.0.0","type":"/srv/secret"}]}'];
    }

    public function testCredentialsInConstraintValuesAreRejected(): void
    {
        $this->writeJson('composer.json', ['require' => ['vendor/pkg' => 'https://user:secret@private.example']]);
        $information = (new ComposerDependencyProvider($this->directory))->getInformation();
        self::assertSame(HealthStatus::Unknown, $information->status);
        self::assertSame([], $information->packages);
    }

    public function testInformationCacheIsOptionalAndHasBoundedStaleness(): void
    {
        $cache = new ArrayAdapter();
        $provider = new ComposerDependencyProvider($this->directory, cache: $cache, cacheTtl: 60);
        $first = $provider->getInformation();
        $this->writeJson('composer.lock', ['packages' => [], 'packages-dev' => []]);
        self::assertSame($first->lockHash, $provider->getInformation()->lockHash);
        $cache->clear();
        self::assertNotSame($first->lockHash, $provider->getInformation()->lockHash);
        self::assertSame(0, $provider->getInformation()->productionPackages);
    }

    public function testDisabledProviderDoesNotReadFiles(): void
    {
        self::assertSame(HealthStatus::Unsupported, (new ComposerDependencyProvider($this->directory.'/absent', enabled: false))->getInformation()->status);
    }

    public function testFailedInformationCacheFallsBackToLiveReads(): void
    {
        $cache = $this->createStub(CacheItemPoolInterface::class);
        $cache->method('getItem')->willThrowException(new \RuntimeException('redis://password@private.example'));
        $information = (new ComposerDependencyProvider($this->directory, cache: $cache))->getInformation();
        self::assertSame(HealthStatus::Healthy, $information->status);
        self::assertSame(1, $information->productionPackages);
    }
}
