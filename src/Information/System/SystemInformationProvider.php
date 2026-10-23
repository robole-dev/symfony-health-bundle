<?php

declare(strict_types=1);

namespace Robole\SymfonyHealthBundle\Information\System;

use Symfony\Component\HttpKernel\Kernel;

final readonly class SystemInformationProvider
{
    public function __construct(
        private string $environment,
        private bool $debug,
        private bool $includeExtensions = false,
    ) {
    }

    /** @return array<string, mixed> */
    public function getInformation(): array
    {
        $memoryLimit = ini_get('memory_limit');
        $information = [
            'php_version' => PHP_VERSION,
            'php_sapi' => PHP_SAPI,
            'memory_limit' => $memoryLimit,
            'symfony_version' => Kernel::VERSION,
            'environment' => $this->environment,
            'debug' => $this->debug,
            'timezone' => date_default_timezone_get(),
            'utc_time' => gmdate('Y-m-d\TH:i:s\Z'),
        ];
        if ($this->includeExtensions) {
            $extensions = get_loaded_extensions();
            sort($extensions, SORT_STRING);
            $information['extensions'] = $extensions;
        }

        return $information;
    }
}
