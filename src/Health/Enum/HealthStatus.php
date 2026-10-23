<?php

declare(strict_types=1);

namespace Robole\SymfonyHealthBundle\Health\Enum;

enum HealthStatus: string
{
    case Healthy = 'healthy';
    case Warning = 'warning';
    case Critical = 'critical';
    case Unknown = 'unknown';
    case Unsupported = 'unsupported';

    /** @param iterable<self> $statuses */
    public static function aggregate(iterable $statuses): self
    {
        $overall = self::Unsupported;
        $found = false;
        foreach ($statuses as $status) {
            $found = true;
            if ($status->severity() > $overall->severity()) {
                $overall = $status;
            }
        }

        return $found ? $overall : self::Unknown;
    }

    private function severity(): int
    {
        return match ($this) {
            self::Unsupported => 0,
            self::Healthy => 1,
            self::Unknown => 2,
            self::Warning => 3,
            self::Critical => 4,
        };
    }
}
