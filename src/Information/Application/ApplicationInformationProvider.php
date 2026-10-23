<?php

declare(strict_types=1);

namespace Robole\SymfonyHealthBundle\Information\Application;

use Composer\InstalledVersions;
use Symfony\Component\HttpKernel\Kernel;

final readonly class ApplicationInformationProvider
{
    public function __construct(
        private string $name,
        private string $environment,
        private ?string $version = null,
        private ?string $gitCommit = null,
        private ?string $gitBranch = null,
        private ?string $deployedAt = null,
        private bool $includeSuluVersion = true,
    ) {
    }

    /** @return array<string, string|null> */
    public function getInformation(): array
    {
        $suluVersion = null;
        if ($this->includeSuluVersion && class_exists(InstalledVersions::class) && InstalledVersions::isInstalled('sulu/sulu')) {
            $suluVersion = self::text(InstalledVersions::getPrettyVersion('sulu/sulu'));
        }

        return [
            'name' => self::text($this->name),
            'environment' => self::text($this->environment),
            'version' => self::text($this->version),
            'symfony_version' => Kernel::VERSION,
            'sulu_version' => $suluVersion,
            'git_commit' => null !== $this->gitCommit && 1 === preg_match('/\A[a-fA-F0-9]{7,64}\z/D', $this->gitCommit) ? strtolower($this->gitCommit) : null,
            'git_branch' => self::branch($this->gitBranch),
            'deployed_at' => self::deploymentTime($this->deployedAt),
        ];
    }

    private static function text(?string $value): ?string
    {
        if (null === $value || '' === trim($value) || strlen($value) > 256 || 1 !== preg_match('//u', $value) || 1 === preg_match('/[\x00-\x1f\x7f]/', $value)) {
            return null;
        }

        return trim($value);
    }

    private static function branch(?string $value): ?string
    {
        return null !== $value && strlen($value) <= 128 && 1 === preg_match('/\A[a-zA-Z0-9][a-zA-Z0-9._\/-]*\z/D', $value) ? $value : null;
    }

    private static function deploymentTime(?string $value): ?string
    {
        if (null === $value || 1 !== preg_match('/\A\d{4}-\d{2}-\d{2}T\d{2}:\d{2}:\d{2}(?:Z|[+-]\d{2}:\d{2})\z/D', $value)) {
            return null;
        }
        try {
            $date = new \DateTimeImmutable($value);
            $errors = \DateTimeImmutable::getLastErrors();
            if (false !== $errors && ($errors['warning_count'] > 0 || $errors['error_count'] > 0)) {
                return null;
            }

            return $date->setTimezone(new \DateTimeZone('UTC'))->format('Y-m-d\TH:i:s\Z');
        } catch (\Throwable) {
            return null;
        }
    }
}
