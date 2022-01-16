<?php

$finder = (new PhpCsFixer\Finder())
    ->in('src')
    ->in('generated')
    ->exclude('var')
;

return (new PhpCsFixer\Config())
    ->setRules([
        '@Symfony' => true,
        '@Symfony:risky' => true,
        'protected_to_private' => false,
    ])
    ->setRiskyAllowed(true)
    ->setFinder($finder)
;
