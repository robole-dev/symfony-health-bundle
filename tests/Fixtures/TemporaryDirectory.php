<?php

declare(strict_types=1);

namespace Robole\SymfonyHealthBundle\Tests\Fixtures;

use Symfony\Component\Filesystem\Filesystem;

trait TemporaryDirectory
{
    private string $directory;

    private function createDirectory(): void
    {
        $this->directory = sys_get_temp_dir().'/robole-health-test-'.bin2hex(random_bytes(8));
        mkdir($this->directory, 0700, true);
    }

    private function removeDirectory(): void
    {
        (new Filesystem())->remove($this->directory);
    }

    /** @param array<string, mixed> $contents */
    private function writeJson(string $name, array $contents): void
    {
        file_put_contents($this->directory.'/'.$name, json_encode($contents, JSON_THROW_ON_ERROR));
    }
}
