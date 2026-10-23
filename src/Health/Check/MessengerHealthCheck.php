<?php

declare(strict_types=1);

namespace Robole\SymfonyHealthBundle\Health\Check;

use Psr\Container\ContainerInterface;
use Robole\SymfonyHealthBundle\Health\Enum\HealthStatus;
use Robole\SymfonyHealthBundle\Health\HealthCheckInterface;
use Robole\SymfonyHealthBundle\Health\Result\HealthCheckResult;
use Symfony\Component\Messenger\Transport\Receiver\MessageCountAwareInterface;

final readonly class MessengerHealthCheck implements HealthCheckInterface
{
    /**
     * @param list<string> $transportNames
     * @param list<string> $failureTransports
     */
    public function __construct(
        private ContainerInterface $transports,
        private array $transportNames = [],
        private array $failureTransports = [],
        private int $failedWarningThreshold = 1,
    ) {
    }

    public function getName(): string
    {
        return 'messenger';
    }

    public function check(): HealthCheckResult
    {
        if ([] === $this->transportNames) {
            return new HealthCheckResult(HealthStatus::Unsupported, code: 'messenger_transports_not_configured');
        }
        $components = [];
        foreach ($this->transportNames as $name) {
            try {
                $transport = $this->transports->has($name) ? $this->transports->get($name) : null;
                if (!$transport instanceof MessageCountAwareInterface) {
                    $components[$name] = new HealthCheckResult(HealthStatus::Unsupported, code: 'message_count_unsupported');
                    continue;
                }
                // Never call get(), all(), ack(), reject(), send() or setup().
                $count = $transport->getMessageCount();
                if ($count < 0) {
                    $components[$name] = new HealthCheckResult(HealthStatus::Unknown, code: 'message_count_unavailable');
                    continue;
                }
                $failed = in_array($name, $this->failureTransports, true);
                $status = $failed && $count >= $this->failedWarningThreshold ? HealthStatus::Warning : HealthStatus::Healthy;
                $components[$name] = new HealthCheckResult($status, metrics: ['message_count' => $count, 'failure_transport' => $failed]);
            } catch (\Throwable) {
                $components[$name] = new HealthCheckResult(HealthStatus::Critical, code: 'transport_unavailable');
            }
        }

        return new HealthCheckResult(HealthStatus::aggregate(array_map(static fn (HealthCheckResult $result): HealthStatus => $result->status, $components)), components: $components);
    }
}
