<?php

declare(strict_types=1);

use PhpCsFixer\Fixer\ArrayNotation\ArraySyntaxFixer;
use PhpCsFixer\Fixer\ClassNotation\ProtectedToPrivateFixer;
use PhpCsFixer\Fixer\FunctionNotation\VoidReturnFixer;
use PhpCsFixer\Fixer\Import\NoUnusedImportsFixer;
use PhpCsFixer\Fixer\StringNotation\SingleQuoteFixer;
use Symplify\EasyCodingStandard\Config\ECSConfig;
use Symplify\EasyCodingStandard\ValueObject\Set\SetList;

return ECSConfig::configure()
    ->withRules([
        NoUnusedImportsFixer::class,
        ArraySyntaxFixer::class,
        SingleQuoteFixer::class,
        VoidReturnFixer::class
    ])
    ->withSets([
        SetList::PSR_12,
        SetList::DOCBLOCK,
        SetList::COMMENTS,
        SetList::CLEAN_CODE,
    ])
    ->withSkip([
        ProtectedToPrivateFixer::class,
    ])
    ->withPaths([
        __DIR__ . '/Classes/',
        __DIR__ . '/Configuration/',
        __DIR__ . '/Tests/',
    ])
    ->withRootFiles()
;
