<?php

declare(strict_types=1);

namespace Robole\SymfonyHealthBundle\Security;

use Symfony\Component\HttpFoundation\IpUtils;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;

final readonly class ApiAccessGuard
{
    /**
     * @param list<string> $tokens
     * @param list<string> $allowedIps
     */
    public function __construct(
        private array $tokens = [],
        private array $allowedIps = [],
        private bool $requireHttps = true,
    ) {
    }

    public function denyUnlessAuthorized(Request $request): ?JsonResponse
    {
        $authorization = $request->headers->get('Authorization') ?? '';
        $matched = false;
        if (strlen($authorization) <= 8192 && 1 === preg_match('/\ABearer ([a-zA-Z0-9._~+\/-]+=*)\z/iD', $authorization, $matches)) {
            $provided = hash('sha256', $matches[1], true);
            // Compare all configured tokens at a fixed digest length; do not reveal rotation-slot timing.
            foreach ($this->tokens as $token) {
                $matched = self::matches($token, $provided) || $matched;
            }
        }
        if (!$matched) {
            return new JsonResponse(['error' => 'unauthorized'], 401, ['WWW-Authenticate' => 'Bearer realm="robole-health"']);
        }
        if (($this->requireHttps && !$request->isSecure()) || ([] !== $this->allowedIps && !IpUtils::checkIp($request->getClientIp() ?? '', $this->allowedIps))) {
            return new JsonResponse(['error' => 'access_denied'], 403);
        }

        return null;
    }

    private static function matches(mixed $configuredToken, string $providedDigest): bool
    {
        // Nullable/default environment processors may resolve to null after configuration validation.
        $token = is_string($configuredToken) ? $configuredToken : '';
        $valid = '' !== $token && 1 === preg_match('/\A[a-zA-Z0-9._~+\/-]+=*\z/D', $token);
        $equal = hash_equals(hash('sha256', $token, true), $providedDigest);

        return $valid && $equal;
    }
}
