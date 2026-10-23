<?php

declare(strict_types=1);

namespace Robole\SymfonyHealthBundle\Tests\Unit\Check;

use PHPUnit\Framework\TestCase;
use Robole\SymfonyHealthBundle\Health\Check\MessengerHealthCheck;
use Robole\SymfonyHealthBundle\Health\Enum\HealthStatus;
use Symfony\Component\DependencyInjection\ServiceLocator;
use Symfony\Component\Messenger\Transport\Receiver\MessageCountAwareInterface;

final class MessengerHealthCheckTest extends TestCase
{
    public function testSupportedAndUnsupportedTransportsAndFailedMessages(): void
    {
        $transport = $this->createMock(MessageCountAwareInterface::class);
        $transport->expects(self::once())->method('getMessageCount')->willReturn(2);
        $locator = new ServiceLocator(['failed' => static fn (): MessageCountAwareInterface => $transport, 'sync' => static fn (): \stdClass => new \stdClass()]);
        $result = (new MessengerHealthCheck($locator, ['failed', 'sync'], ['failed']))->check();
        self::assertSame(HealthStatus::Warning, $result->status);
        self::assertSame(2, $result->components['failed']->metrics['message_count']);
        self::assertSame(HealthStatus::Unsupported, $result->components['sync']->status);
    }

    public function testOneFailedTransportDoesNotPreventOtherTransportChecks(): void
    {
        $bad = $this->createStub(MessageCountAwareInterface::class);
        $bad->method('getMessageCount')->willThrowException(new \RuntimeException('password=secret'));
        $good = $this->createMock(MessageCountAwareInterface::class);
        $good->expects(self::once())->method('getMessageCount')->willReturn(0);
        $locator = new ServiceLocator(['bad' => static fn (): MessageCountAwareInterface => $bad, 'good' => static fn (): MessageCountAwareInterface => $good]);
        $result = (new MessengerHealthCheck($locator, ['bad', 'good']))->check();
        self::assertSame(HealthStatus::Critical, $result->status);
        self::assertSame(HealthStatus::Healthy, $result->components['good']->status);
        self::assertStringNotContainsString('secret', json_encode($result->toArray(), JSON_THROW_ON_ERROR));
    }

    public function testNoImplicitTransportProbing(): void
    {
        self::assertSame(HealthStatus::Unsupported, (new MessengerHealthCheck(new ServiceLocator([])))->check()->status);
    }
}
