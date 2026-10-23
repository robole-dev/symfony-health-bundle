<?php

declare(strict_types=1);

namespace Robole\SymfonyHealthBundle\Health\Check;

use Robole\SymfonyHealthBundle\Health\Enum\HealthStatus;
use Robole\SymfonyHealthBundle\Health\HealthCheckInterface;
use Robole\SymfonyHealthBundle\Health\Result\HealthCheckResult;

final readonly class OpcacheHealthCheck implements HealthCheckInterface
{
    public function getName(): string
    {
        return 'opcache';
    }

    public function check(): HealthCheckResult
    {
        if (!function_exists('opcache_get_status')) {
            return new HealthCheckResult(HealthStatus::Unsupported, code: 'opcache_status_unavailable');
        }
        // false excludes the script list, which contains absolute paths and can be large.
        try {
            $status = @opcache_get_status(false);
        } catch (\Throwable) {
            return new HealthCheckResult(HealthStatus::Unsupported, code: 'opcache_status_unavailable');
        }
        if (false === $status || !($status['opcache_enabled'] ?? false)) {
            return new HealthCheckResult(HealthStatus::Unsupported, code: 'opcache_unavailable_for_sapi');
        }
        $full = (bool) ($status['cache_full'] ?? false);
        $metrics = ['enabled' => true, 'cache_full' => $full, 'restart_pending' => (bool) ($status['restart_pending'] ?? false)];
        $memory = $status['memory_usage'] ?? [];
        $statistics = $status['opcache_statistics'] ?? [];
        foreach (['used_memory', 'free_memory', 'wasted_memory'] as $key) {
            $value = is_array($memory) ? ($memory[$key] ?? null) : null;
            if (is_int($value) || is_float($value)) {
                $metrics[$key] = $value;
            }
        }
        foreach (['num_cached_scripts', 'hits', 'misses', 'opcache_hit_rate'] as $key) {
            $value = is_array($statistics) ? ($statistics[$key] ?? null) : null;
            if (is_int($value) || is_float($value)) {
                $metrics[$key] = $value;
            }
        }

        return new HealthCheckResult($full ? HealthStatus::Warning : HealthStatus::Healthy, metrics: $metrics);
    }
}
