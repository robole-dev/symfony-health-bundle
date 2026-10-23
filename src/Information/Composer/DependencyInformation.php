<?php

declare(strict_types=1);

namespace Robole\SymfonyHealthBundle\Information\Composer;

use Robole\SymfonyHealthBundle\Health\Enum\HealthStatus;

/**
 * @phpstan-type Package array{name: string, version: string, type: string, dependency_type: 'production'|'development'}
 * @phpstan-type Constraints array{require: array<string, string|false>, require-dev: array<string, string|false>, conflict: array<string, string|false>, platform: array<string, string|false>}
 */
final readonly class DependencyInformation
{
    /**
     * @param list<Package> $packages
     * @param Constraints   $constraints
     */
    public function __construct(
        public HealthStatus $status,
        public ?string $lockHash = null,
        public ?int $productionPackages = null,
        public ?int $developmentPackages = null,
        public array $packages = [],
        public array $constraints = ['require' => [], 'require-dev' => [], 'conflict' => [], 'platform' => []],
    ) {
    }

    /** @return array<string, mixed> */
    public function summary(): array
    {
        return [
            'status' => $this->status->value,
            'lock_hash' => $this->lockHash,
            'production_packages' => $this->productionPackages,
            'development_packages' => $this->developmentPackages,
        ];
    }

    /** @return array<string, mixed> */
    public function inventory(): array
    {
        return $this->summary() + [
            'packages' => $this->packages,
            'constraints' => [
                'require' => (object) $this->constraints['require'],
                'require-dev' => (object) $this->constraints['require-dev'],
                'conflict' => (object) $this->constraints['conflict'],
                'config' => ['platform' => (object) $this->constraints['platform']],
            ],
        ];
    }
}
