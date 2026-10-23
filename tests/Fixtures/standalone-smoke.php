<?php

declare(strict_types=1);

// Runs against a separate, production-only Composer installation, without PHPUnit or other dev packages.
use Robole\SymfonyHealthBundle\Tests\Fixtures\TestKernel;
use Symfony\Component\Filesystem\Filesystem;
use Symfony\Component\HttpFoundation\Request;

$arguments = $argv ?? [];
$autoload = $arguments[1] ?? '';
if ('' === $autoload || !is_file($autoload)) {
    throw new RuntimeException('Pass the production-only vendor/autoload.php as the first argument.');
}
require $autoload;
require __DIR__.'/StubHealthCheck.php';
require __DIR__.'/TestKernel.php';

foreach (['Doctrine\\DBAL\\Connection', 'Doctrine\\Migrations\\DependencyFactory', 'Symfony\\Component\\Messenger\\MessageBus', 'Sulu\\Bundle\\SuluCoreBundle\\SuluCoreBundle'] as $optionalClass) {
    if (class_exists($optionalClass)) {
        throw new RuntimeException('Smoke installation unexpectedly contains optional components.');
    }
}
$runtime = sys_get_temp_dir().'/robole-health-smoke-'.bin2hex(random_bytes(8));
mkdir($runtime.'/logs', 0700, true);
file_put_contents($runtime.'/composer.json', '{}');
file_put_contents($runtime.'/composer.lock', '{"packages":[]}');
$restricted = in_array('--restricted', $arguments, true);
$kernel = new TestKernel($runtime, ['api' => ['tokens' => ['smoke-token']], 'storage' => ['include_space' => $restricted]], useFlexAlias: in_array('--flex', $arguments, true));
try {
    foreach (['/api/health', '/api/health/dependencies'] as $path) {
        $anonymous = $kernel->handle(Request::create('https://example.test'.$path));
        if (401 !== $anonymous->getStatusCode()) {
            throw new RuntimeException('Anonymous request was not rejected.');
        }
        $response = $kernel->handle(Request::create('https://example.test'.$path, server: ['HTTP_AUTHORIZATION' => 'Bearer smoke-token']));
        if (200 !== $response->getStatusCode()) {
            throw new RuntimeException('Authenticated production-only API request failed.');
        }
        $data = json_decode((string) $response->getContent(), true, flags: JSON_THROW_ON_ERROR);
        if (!is_array($data) || '1.0' !== ($data['schema_version'] ?? null)) {
            throw new RuntimeException('Unexpected API schema.');
        }
        if ('/api/health' === $path) {
            $checks = $data['checks'] ?? null;
            if (!is_array($checks) || isset($checks['database']) || isset($checks['messenger']) || isset($checks['doctrine_migrations'])) {
                throw new RuntimeException('Optional checks should be absent.');
            }
            if ($restricted && (!is_array($checks['storage']) || 'unknown' !== ($checks['storage']['status'] ?? null) || !is_array($checks['opcache']) || 'unsupported' !== ($checks['opcache']['status'] ?? null))) {
                throw new RuntimeException('Restricted hosting must report unavailable information honestly.');
            }
        }
    }
    echo "Standalone production installation and authenticated endpoints: OK\n";
} finally {
    $kernel->shutdown();
    (new Filesystem())->remove($runtime);
}
