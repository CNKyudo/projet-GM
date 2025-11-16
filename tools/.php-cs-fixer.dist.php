<?php

$finder = PhpCsFixer\Finder::create()
    ->in(__DIR__ . '/../src')
    ->in(__DIR__ . '/../tests')
    ->exclude(__DIR__ . '/../var')
    ->append([__FILE__]);

return (new PhpCsFixer\Config())
    ->setRules([
        '@Symfony' => true,
        '@PSR12' => true,
        'array_syntax' => ['syntax' => 'short'],
        // add more rules if desired
    ])
    ->setFinder($finder);
