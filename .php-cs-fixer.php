<?php

$finder = PhpCsFixer\Finder::create()
    ->in(__DIR__)
    ->exclude('vendor')
    ->exclude('tools')
    ->exclude('locale')
    ->exclude('ihtml')
    ->name('*.php')
    ->name('*.inc');

return (new PhpCsFixer\Config())
    ->setRiskyAllowed(true)
    ->setRules([
        '@PSR12' => true,
        '@PHP81Migration' => true,
        'array_syntax' => ['syntax' => 'short'],
        'binary_operator_spaces' => ['default' => 'single_space'],
        'blank_line_after_opening_tag' => true,
        'blank_line_between_import_groups' => true,
        'cast_spaces' => ['space' => 'none'],
        'concat_space' => ['spacing' => 'one'],
        'fully_qualified_global_functions' => true,
        'fully_qualified_global_constants' => true,
        'no_unused_imports' => true,
        'ordered_imports' => ['sort_algorithm' => 'alpha'],
        'single_line_empty_body' => true,
        'trailing_comma_in_multiline' => ['elements' => ['arrays', 'arguments', 'parameters']],
        'visibility_required' => ['elements' => ['property', 'method', 'const']],
        'whitespace_after_comma_in_array' => true,
    ])
    ->setIndent('    ')
    ->setLineEnding("\n")
    ->setFinder($finder);
