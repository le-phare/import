<?php

$finder = PhpCsFixer\Finder::create()
    ->in(__DIR__)
    ->exclude(__DIR__.'/vendor')
;

return (new PhpCsFixer\Config())
    ->setRules([
        // '@PhpCsFixer' => true,
        '@Symfony' => true,
        'trailing_comma_in_multiline' => [
            'after_heredoc' => true,
            'elements' => [
                'array_destructuring',
                'arrays',
                // 'match', // PHP 8.0+
                // 'parameters' // PHP 8.0+
            ]
        ]
    ])
    ->setFinder($finder)
    ->setCacheFile('.php-cs-fixer.cache')
;
