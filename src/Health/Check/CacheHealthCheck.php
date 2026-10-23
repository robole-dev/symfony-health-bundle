<?php

declare(strict_types=1);

namespace Robole\SymfonyHealthBundle\Health\Check;

use Psr\Cache\CacheItemPoolInterface;
use Robole\SymfonyHealthBundle\Health\Enum\HealthStatus;
use Robole\SymfonyHealthBundle\Health\HealthCheckInterface;
use Robole\SymfonyHealthBundle\Health\Result\HealthCheckResult;

final readonly class CacheHealthCheck implements HealthCheckInterface
{
    public function __construct(private ?CacheItemPoolInterface $pool = null)
    {
    }

    public function getName(): string
    {
        return 'cache';
    }

    public function check(): HealthCheckResult
    {
        if (null === $this->pool) {
            return new HealthCheckResult(HealthStatus::Unsupported, code: 'cache_pool_unavailable');
        }
        $key = 'robole_health_probe_'.bin2hex(random_bytes(16));
        $value = bin2hex(random_bytes(16));
        $ownsKey = false;
        try {
            $item = $this->pool->getItem($key);
            if ($item->isHit()) {
                // Never delete or replace an item that existed before this probe.
                return new HealthCheckResult(HealthStatus::Unknown, code: 'cache_key_collision');
            }
            $ownsKey = true;
            if (!$this->pool->save($item->set($value)->expiresAfter(30))) {
                return new HealthCheckResult(HealthStatus::Critical, code: 'cache_write_failed');
            }
            $read = $this->pool->getItem($key);
            if (!$read->isHit() || $value !== $read->get()) {
                return new HealthCheckResult(HealthStatus::Critical, code: 'cache_read_failed');
            }
            if (!$this->pool->deleteItem($key) || $this->pool->hasItem($key)) {
                return new HealthCheckResult(HealthStatus::Critical, code: 'cache_delete_failed');
            }
            $ownsKey = false;

            return new HealthCheckResult(HealthStatus::Healthy, code: 'cache_operational');
        } catch (\Throwable) {
            return new HealthCheckResult(HealthStatus::Critical, code: 'cache_probe_failed');
        } finally {
            if ($ownsKey) {
                try {
                    $this->pool->deleteItem($key);
                } catch (\Throwable) {
                    // The 30-second TTL is the final cleanup safeguard.
                }
            }
        }
    }
}
