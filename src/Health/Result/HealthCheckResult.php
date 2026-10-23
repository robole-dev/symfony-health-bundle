<?php

declare(strict_types=1);

namespace Robole\SymfonyHealthBundle\Health\Result;

use Robole\SymfonyHealthBundle\Health\Enum\HealthStatus;

/** No free-text error fields: only machine codes, numeric metrics and nested results cross the API boundary. */
final readonly class HealthCheckResult
{
    /**
     * @param array<string, int|float|bool|null> $metrics
     * @param array<string, self>                $components
     */
    public function __construct(
        public HealthStatus $status,
        public ?float $durationMs = null,
        public ?string $code = null,
        public array $metrics = [],
        public array $components = [],
    ) {
        if (null !== $durationMs && (!is_finite($durationMs) || $durationMs < 0)) {
            throw new \InvalidArgumentException('Duration must be finite and non-negative.');
        }
        if (null !== $code) {
            self::validateName($code);
        }
        foreach ($metrics as $name => $value) {
            self::validateName($name);
            self::validateMetric($value);
        }
        foreach ($components as $name => $component) {
            self::validateName($name);
            self::validateComponent($component);
        }
    }

    public static function validateName(string $name): void
    {
        if (1 !== preg_match('/\A[a-z][a-z0-9_]{0,63}\z/D', $name)) {
            throw new \InvalidArgumentException('Use a lowercase identifier of at most 64 characters.');
        }
    }

    private static function validateMetric(mixed $value): void
    {
        if (!is_int($value) && !is_float($value) && !is_bool($value) && null !== $value) {
            throw new \InvalidArgumentException('Metrics must be numeric, boolean or null.');
        }
        if (is_float($value) && !is_finite($value)) {
            throw new \InvalidArgumentException('Metrics must be finite.');
        }
    }

    private static function validateComponent(mixed $value): void
    {
        if (!$value instanceof self) {
            throw new \InvalidArgumentException('Components must be health results.');
        }
    }

    public function withDuration(float $durationMs): self
    {
        return new self($this->status, $durationMs, $this->code, $this->metrics, $this->components);
    }

    /** @return array<string, mixed> */
    public function toArray(): array
    {
        $result = ['status' => $this->status->value];
        if (null !== $this->durationMs) {
            $result['duration_ms'] = round($this->durationMs, 3);
        }
        if (null !== $this->code) {
            $result['code'] = $this->code;
        }
        if ([] !== $this->metrics) {
            $result['metrics'] = $this->metrics;
        }
        if ([] !== $this->components) {
            $result['components'] = array_map(static fn (self $component): array => $component->toArray(), $this->components);
        }

        return $result;
    }
}
