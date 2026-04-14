<?php

use PhpCsFixer\Config;
use PhpCsFixer\Finder;

$header = <<<'EOF'
Micro\Container

@copyright   Copyright (c) 2018-2019 gyselroth GmbH (https://gyselroth.com)
@license     MIT https://opensource.org/licenses/MIT
EOF;

$config = Config::create()
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
        'declare_strict_types' => true,

        // --- cleanup rules (safe modern replacements) ---
        'no_useless_else' => true,
        'no_useless_return' => true,
        'no_superfluous_elseif' => true,
        'no_unneeded_curly_braces' => true,
        'no_extra_blank_lines' => true,

        // --- null / structure cleanup ---
        'no_null_property_initialization' => true,

        // --- comments style ---
        'single_line_comment_style' => [
            'comment_types' => ['hash'],
        ],
    ])
    ->setFinder(
        Finder::create()
            ->exclude(['build', 'vendor'])
            ->in(__DIR__)
    );

return $config;
