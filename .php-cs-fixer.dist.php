<?php

declare(strict_types=1);

$finder = PhpCsFixer\Finder::create()->in(__DIR__.'/src')->in(__DIR__.'/tests');

return (new PhpCsFixer\Config())
    ->setRiskyAllowed(true)
    ->setRules([
        '@Symfony' => true,
        'declare_strict_types' => true,
        'phpdoc_to_comment' => false,
        'php_unit_test_class_requires_covers' => false,
    ])
    ->setFinder($finder);
