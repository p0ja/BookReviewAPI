<?php

$finder = (new PhpCsFixer\Finder())
    ->in(__DIR__)
    ->exclude(['var', 'config/secrets'])
;

return (new PhpCsFixer\Config())
    ->setRiskyAllowed(true)
    ->setRules([
        '@Symfony' => true,
        // PHPUnit assertions are static; once() is an instance method since PHPUnit 12.
        'php_unit_test_case_static_method_calls' => ['call_type' => 'self', 'methods' => ['once' => 'this']],
    ])
    ->setFinder($finder)
;
