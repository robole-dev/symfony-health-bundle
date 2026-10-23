<?php

declare(strict_types=1);

namespace Robole\SymfonyHealthBundle;

/**
 * Flex 2.x derives this name from the PSR-4 namespace; it does not read bundle-class.
 * Alias the canonical Symfony\Component\HttpKernel\Bundle\Bundle implementation so
 * both entry points have the exact same bundle identity, extension and resource path.
 * The explicit autoload file also supports Composer's authoritative class maps.
 *
 * @internal use RoboleHealthBundle when registering the bundle manually
 */
class_alias(RoboleHealthBundle::class, SymfonyHealthBundle::class);
