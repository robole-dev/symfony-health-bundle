<?php

declare(strict_types=1);

namespace Robole\SymfonyHealthBundle\Health\Registry;

use Robole\SymfonyHealthBundle\Health\HealthCheckInterface;
use Robole\SymfonyHealthBundle\Health\Result\HealthCheckResult;

final class HealthCheckRegistry
{
    /** @var array<string, HealthCheckInterface>|null */
    private ?array $registered = null;

    /** @var list<HealthCheckInterface|\Closure(): HealthCheckInterface>|null */
    private ?array $entries = null;

    /** @param iterable<HealthCheckInterface|\Closure(): HealthCheckInterface> $checks */
    public function __construct(private readonly iterable $checks)
    {
    }

    /** @return iterable<\Closure(): HealthCheckInterface> */
    public function resolvers(): iterable
    {
        if (null === $this->entries) {
            $this->entries = [];
            foreach ($this->checks as $check) {
                $this->entries[] = $check;
            }
        }
        foreach ($this->entries as $entry) {
            yield $entry instanceof \Closure ? $entry : static fn (): HealthCheckInterface => $entry;
        }
    }

    /** @return array<string, HealthCheckInterface> */
    public function all(): array
    {
        if (null !== $this->registered) {
            return $this->registered;
        }
        $registered = [];
        foreach ($this->resolvers() as $resolve) {
            $check = $resolve();
            $name = $check->getName();
            HealthCheckResult::validateName($name);
            if (isset($registered[$name])) {
                throw new \LogicException('Health check names must be unique.');
            }
            $registered[$name] = $check;
        }

        return $this->registered = $registered;
    }
}
