<?php

$header = <<<'EOF'
Micro\Container

@copyright   Copyright (c) 2018-2026 gyselroth GmbH (https://gyselroth.com)
@license     MIT https://opensource.org/licenses/MIT
EOF;

$finder = (new PhpCsFixer\Finder())
    ->in(__DIR__);

$config = (new PhpCsFixer\Config())
    ->setRiskyAllowed(true)
    ->setRules([
        '@PSR12' => true,
        '@Symfony' => true,

        // --- formatting / arrays ---
        'array_syntax' => ['syntax' => 'short'],
        'list_syntax' => ['syntax' => 'short'],

        // --- imports ---
        'ordered_imports' => [
            'sort_algorithm' => 'alpha',
        ],

        // --- comments / docs ---
        'header_comment' => [
            'header' => $header,
            'comment_type' => 'PHPDoc',
            'separate' => 'bottom',
            'location' => 'after_declare_strict',
        ],

        'no_superfluous_phpdoc_tags' => true,
        'phpdoc_order' => true,
        'phpdoc_types_order' => true,

        // --- strictness ---
        'strict_comparison' => true,
        'strict_param' => true,
    ])
    ->setFinder($finder);

return $config;
