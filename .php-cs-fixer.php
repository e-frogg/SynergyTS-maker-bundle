<?php

use PhpCsFixer\Config;
use PhpCsFixer\Finder;

$finder = Finder::create()
    ->in(__DIR__)
    ->name('*.php');

return (new Config())
    ->setFinder($finder)
    ->setRules([
        '@Symfony' => true,
        '@PHP83Migration' => true,
        '@PHP84Migration' => true,
        'phpdoc_to_comment' => false,
    ])
    ->setRiskyAllowed(true);
