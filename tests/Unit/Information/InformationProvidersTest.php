<?php

declare(strict_types=1);

namespace Robole\SymfonyHealthBundle\Tests\Unit\Information;

use PHPUnit\Framework\TestCase;
use Robole\SymfonyHealthBundle\Information\Application\ApplicationInformationProvider;
use Robole\SymfonyHealthBundle\Information\System\SystemInformationProvider;

final class InformationProvidersTest extends TestCase
{
    public function testApplicationMetadataWorksWithoutGitOrSulu(): void
    {
        $data = (new ApplicationInformationProvider('Example', 'prod', '1.0', 'ABC1234', 'release/1.0', '2026-10-22T20:30:00+02:00'))->getInformation();
        self::assertSame('abc1234', $data['git_commit']);
        self::assertSame('2026-10-22T18:30:00Z', $data['deployed_at']);
        self::assertNull($data['sulu_version']);
    }

    public function testInvalidDeploymentMetadataIsNormalizedToNull(): void
    {
        $data = (new ApplicationInformationProvider("Bad\nName", 'prod', gitCommit: 'not a hash', gitBranch: 'https://user:secret@host', deployedAt: '2026-02-30T00:00:00Z'))->getInformation();
        self::assertNull($data['name']);
        self::assertNull($data['git_commit']);
        self::assertNull($data['git_branch']);
        self::assertNull($data['deployed_at']);
    }

    public function testSystemInformationIsWhitelistedAndExtensionsAreOptIn(): void
    {
        $data = (new SystemInformationProvider('prod', false))->getInformation();
        self::assertSame(PHP_VERSION, $data['php_version']);
        self::assertSame('prod', $data['environment']);
        self::assertFalse($data['debug']);
        self::assertArrayNotHasKey('extensions', $data);
        self::assertArrayNotHasKey('server', $data);
        self::assertArrayNotHasKey('env', $data);
        self::assertIsString($data['utc_time']);
        self::assertMatchesRegularExpression('/Z$/', $data['utc_time']);
        self::assertArrayHasKey('extensions', (new SystemInformationProvider('prod', false, true))->getInformation());
    }
}
