<?php

declare(strict_types=1);

namespace Robole\SymfonyHealthBundle\Information\Composer;

use Composer\Semver\VersionParser;
use Psr\Cache\CacheItemPoolInterface;
use Robole\SymfonyHealthBundle\Health\Enum\HealthStatus;

/**
 * @phpstan-import-type Package from DependencyInformation
 * @phpstan-import-type Constraints from DependencyInformation
 */
final readonly class ComposerDependencyProvider
{
    public function __construct(
        private string $projectDirectory,
        private bool $includeDev = false,
        private bool $enabled = true,
        private int $maxFileSizeBytes = 10485760,
        private ?CacheItemPoolInterface $cache = null,
        private int $cacheTtl = 60,
    ) {
        if (str_contains($projectDirectory, '://') || str_contains($projectDirectory, "\0") || $maxFileSizeBytes < 1 || $cacheTtl < 0) {
            throw new \InvalidArgumentException('Invalid local Composer information configuration.');
        }
    }

    public function getInformation(): DependencyInformation
    {
        if (!$this->enabled) {
            return new DependencyInformation(HealthStatus::Unsupported);
        }
        $key = 'robole_health_dependencies_v1_'.hash('sha256', $this->projectDirectory.'|'.(int) $this->includeDev.'|'.$this->maxFileSizeBytes);
        if (null !== $this->cache && $this->cacheTtl > 0) {
            try {
                $cached = $this->cache->getItem($key);
                $value = $cached->get();
                if ($cached->isHit() && $value instanceof DependencyInformation) {
                    return $value;
                }
            } catch (\Throwable) {
                // An unavailable information cache must not break live information gathering.
            }
        }
        try {
            $lockContents = $this->read('composer.lock');
            $lock = $this->decode($lockContents);
            $composer = $this->decode($this->read('composer.json'));
            $production = $this->packages($lock['packages'] ?? null, 'production');
            $development = $this->packages($lock['packages-dev'] ?? [], 'development');
            $all = [...$production, ...$development];
            if (count(array_unique(array_column($all, 'name'))) !== count($all)) {
                throw new \UnexpectedValueException('Duplicate package.');
            }
            $packages = $this->includeDev ? $all : $production;
            usort($packages, static fn (array $left, array $right): int => strcmp($left['name'], $right['name']));
            $information = new DependencyInformation(
                HealthStatus::Healthy,
                'sha256:'.hash('sha256', $lockContents),
                count($production),
                count($development),
                $packages,
                $this->constraints($composer),
            );
        } catch (\Throwable) {
            // No filenames, malformed content, credentials or parser exceptions leave this provider.
            return new DependencyInformation(HealthStatus::Unknown);
        }
        if (null !== $this->cache && $this->cacheTtl > 0) {
            try {
                $item = $this->cache->getItem($key);
                $this->cache->save($item->set($information)->expiresAfter($this->cacheTtl));
            } catch (\Throwable) {
            }
        }

        return $information;
    }

    private function read(string $filename): string
    {
        $path = rtrim($this->projectDirectory, '/\\').DIRECTORY_SEPARATOR.$filename;
        if (!@is_file($path)) {
            throw new \RuntimeException('Composer information unavailable.');
        }
        $limit = $this->maxFileSizeBytes;
        if ($limit < 1 || $limit > 52428800) {
            throw new \RuntimeException('Invalid file size limit.');
        }
        $contents = @file_get_contents($path, false, null, 0, $limit + 1);
        if (false === $contents || strlen($contents) > $limit) {
            throw new \RuntimeException('Composer information unavailable.');
        }

        return $contents;
    }

    /** @return array<string, mixed> */
    private function decode(string $contents): array
    {
        $decoded = json_decode($contents, true, 64, JSON_THROW_ON_ERROR);
        if (!is_array($decoded) || !str_starts_with(ltrim($contents), '{')) {
            throw new \UnexpectedValueException('Expected a Composer object.');
        }

        /** @var array<string, mixed> $decoded */
        return $decoded;
    }

    /**
     * @param 'production'|'development' $dependencyType
     *
     * @return list<Package>
     */
    private function packages(mixed $input, string $dependencyType): array
    {
        if (!is_array($input) || !array_is_list($input)) {
            throw new \UnexpectedValueException('Invalid package list.');
        }
        $packages = [];
        $parser = new VersionParser();
        foreach ($input as $package) {
            if (!is_array($package) || !self::packageName($package['name'] ?? null)) {
                throw new \UnexpectedValueException('Invalid package.');
            }
            $version = $package['version'] ?? null;
            $type = $package['type'] ?? 'library';
            if (!is_string($version) || strlen($version) > 256 || 1 !== preg_match('/\A[a-zA-Z0-9][a-zA-Z0-9._+\/-]*\z/D', $version)) {
                throw new \UnexpectedValueException('Invalid version.');
            }
            $parser->normalize($version);
            if (!is_string($type) || 1 !== preg_match('/\A[a-z0-9][a-z0-9_-]{0,63}\z/D', $type)) {
                throw new \UnexpectedValueException('Invalid package type.');
            }
            $packages[] = ['name' => $package['name'], 'version' => $version, 'type' => $type, 'dependency_type' => $dependencyType];
        }

        return $packages;
    }

    /**
     * @param array<string, mixed> $composer
     *
     * @return Constraints
     */
    private function constraints(array $composer): array
    {
        $config = $composer['config'] ?? [];
        if (!is_array($config)) {
            throw new \UnexpectedValueException('Invalid Composer config.');
        }

        return [
            'require' => $this->constraintMap($composer['require'] ?? []),
            'require-dev' => $this->includeDev ? $this->constraintMap($composer['require-dev'] ?? []) : [],
            'conflict' => $this->constraintMap($composer['conflict'] ?? []),
            'platform' => $this->constraintMap($config['platform'] ?? [], true),
        ];
    }

    /** @return array<string, string|false> */
    private function constraintMap(mixed $input, bool $platform = false): array
    {
        if (!is_array($input)) {
            throw new \UnexpectedValueException('Invalid constraints.');
        }
        $result = [];
        $parser = new VersionParser();
        foreach ($input as $name => $constraint) {
            if (!is_string($name) || !(self::packageName($name) || 1 === preg_match('/\A(?:php(?:-64bit|-ipv6|-zts|-debug)?|hhvm|composer(?:-plugin-api|-runtime-api)?|(?:ext|lib)-[a-z0-9][a-z0-9_.-]*)\z/D', $name))) {
                throw new \UnexpectedValueException('Invalid constraint name.');
            }
            if ($platform && false === $constraint) {
                $result[$name] = false;
                continue;
            }
            if (!is_string($constraint) || '' === trim($constraint) || strlen($constraint) > 512 || 1 !== preg_match('/\A[a-zA-Z0-9<>=!~^*|.,+@#()_ \/-]+\z/D', $constraint) || str_contains($constraint, '//') || str_starts_with($constraint, '/')) {
                throw new \UnexpectedValueException('Invalid constraint value.');
            }
            $parser->parseConstraints($constraint);
            $result[$name] = trim($constraint);
        }
        ksort($result, SORT_STRING);

        return $result;
    }

    /** @phpstan-assert-if-true non-empty-string $value */
    private static function packageName(mixed $value): bool
    {
        return is_string($value) && strlen($value) <= 200 && 1 === preg_match('/\A[a-z0-9][a-z0-9_.-]*\/[a-z0-9][a-z0-9_.-]*\z/D', $value);
    }
}
