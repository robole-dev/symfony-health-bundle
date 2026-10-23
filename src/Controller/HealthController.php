<?php

declare(strict_types=1);

namespace Robole\SymfonyHealthBundle\Controller;

use Robole\SymfonyHealthBundle\Health\Enum\HealthStatus;
use Robole\SymfonyHealthBundle\Health\HealthReport;
use Robole\SymfonyHealthBundle\Health\HealthReportFactory;
use Robole\SymfonyHealthBundle\Information\Composer\ComposerDependencyProvider;
use Robole\SymfonyHealthBundle\Security\ApiAccessGuard;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;

final readonly class HealthController
{
    public function __construct(
        private ApiAccessGuard $guard,
        private HealthReportFactory $reports,
        private ComposerDependencyProvider $dependencies,
        private int $criticalHttpStatus = 200,
    ) {
    }

    public function health(Request $request): JsonResponse
    {
        if (null !== $denied = $this->guard->denyUnlessAuthorized($request)) {
            return self::secure($denied);
        }
        $report = $this->reports->create();

        return self::secure(new JsonResponse($report->toArray(), HealthStatus::Critical === $report->getStatus() ? $this->criticalHttpStatus : 200));
    }

    public function dependencies(Request $request): JsonResponse
    {
        if (null !== $denied = $this->guard->denyUnlessAuthorized($request)) {
            return self::secure($denied);
        }

        return self::secure(new JsonResponse([
            'schema_version' => HealthReport::SCHEMA_VERSION,
            'timestamp' => gmdate('Y-m-d\TH:i:s\Z'),
            ...$this->dependencies->getInformation()->inventory(),
        ]));
    }

    private static function secure(JsonResponse $response): JsonResponse
    {
        $response->headers->set('Cache-Control', 'private, no-store, max-age=0');
        $response->headers->set('Pragma', 'no-cache');
        $response->headers->set('X-Content-Type-Options', 'nosniff');

        return $response;
    }
}
