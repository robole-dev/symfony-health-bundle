<?php

declare(strict_types=1);

namespace Robole\SymfonyHealthBundle\Tests\Unit\Security;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Robole\SymfonyHealthBundle\Security\ApiAccessGuard;
use Symfony\Component\HttpFoundation\Request;

final class ApiAccessGuardTest extends TestCase
{
    #[DataProvider('invalidAuthorization')]
    public function testInvalidAuthorizationIsRejected(?string $header): void
    {
        $request = Request::create('https://example.test/api/health?token=valid-token');
        if (null !== $header) {
            $request->headers->set('Authorization', $header);
        }
        $response = (new ApiAccessGuard(['valid-token']))->denyUnlessAuthorized($request);
        self::assertNotNull($response);
        self::assertSame(401, $response->getStatusCode());
        self::assertSame('{"error":"unauthorized"}', $response->getContent());
        self::assertStringStartsWith('Bearer', $response->headers->get('WWW-Authenticate') ?? '');
    }

    /** @return iterable<string, array{?string}> */
    public static function invalidAuthorization(): iterable
    {
        yield 'missing' => [null];
        yield 'wrong' => ['Bearer wrong-token'];
        yield 'empty' => ['Bearer '];
        yield 'basic' => ['Basic valid-token'];
        yield 'multiple values' => ['Bearer valid-token, Bearer wrong-token'];
        yield 'whitespace' => ['Bearer  valid-token'];
        yield 'oversized' => ['Bearer '.str_repeat('a', 8193)];
    }

    public function testMissingAndEmptyTokenConfigurationFailClosed(): void
    {
        $request = Request::create('https://example.test', server: ['HTTP_AUTHORIZATION' => 'Bearer any']);
        self::assertSame(401, (new ApiAccessGuard())->denyUnlessAuthorized($request)?->getStatusCode());
        self::assertSame(401, (new ApiAccessGuard(['']))->denyUnlessAuthorized($request)?->getStatusCode());
    }

    public function testEitherRotationTokenAuthenticatesAndSchemeIsCaseInsensitive(): void
    {
        foreach (['old-token', 'new-token'] as $token) {
            $request = Request::create('https://example.test', server: ['HTTP_AUTHORIZATION' => 'bearer '.$token]);
            self::assertNull((new ApiAccessGuard(['old-token', 'new-token']))->denyUnlessAuthorized($request));
        }
    }

    public function testHttpsAndIpRestrictionsAreEnforcedAfterAuthentication(): void
    {
        $guard = new ApiAccessGuard(['valid'], ['192.0.2.0/24', '2001:db8::/32']);
        $request = Request::create('http://example.test', server: ['HTTP_AUTHORIZATION' => 'Bearer valid', 'REMOTE_ADDR' => '192.0.2.42']);
        self::assertSame(403, $guard->denyUnlessAuthorized($request)?->getStatusCode());
        $request = Request::create('https://example.test', server: ['HTTP_AUTHORIZATION' => 'Bearer valid', 'REMOTE_ADDR' => '192.0.2.42']);
        self::assertNull($guard->denyUnlessAuthorized($request));
        $deniedRequest = Request::create('https://example.test', server: ['HTTP_AUTHORIZATION' => 'Bearer valid', 'REMOTE_ADDR' => '198.51.100.1']);
        self::assertSame(403, $guard->denyUnlessAuthorized($deniedRequest)?->getStatusCode());
        $ipv6Request = Request::create('https://example.test', server: ['HTTP_AUTHORIZATION' => 'Bearer valid', 'REMOTE_ADDR' => '2001:db8::1']);
        self::assertNull($guard->denyUnlessAuthorized($ipv6Request));
    }

    public function testForwardedIpIsIgnoredWithoutTrustedProxyConfiguration(): void
    {
        $request = Request::create('https://example.test', server: ['HTTP_AUTHORIZATION' => 'Bearer valid', 'REMOTE_ADDR' => '198.51.100.1', 'HTTP_X_FORWARDED_FOR' => '192.0.2.42']);
        self::assertSame(403, (new ApiAccessGuard(['valid'], ['192.0.2.0/24']))->denyUnlessAuthorized($request)?->getStatusCode());
    }
}
